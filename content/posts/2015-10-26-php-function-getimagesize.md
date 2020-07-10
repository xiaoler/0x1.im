---
categories:
- php
date: "2015-10-26T23:12:53Z"
title: getimagesize 函数不是完全可靠的
---

`getimagesize` 函数并不属于 GD 扩展的部分，标准安装的 PHP 都可以使用这个函数。可以先看看这个函数的文档描述：[http://php.net/manual/zh/function.getimagesize.php](http://php.net/manual/zh/function.getimagesize.php)

如果指定的文件如果不是有效的图像，会返回 false，返回数据中也有表示文档类型的字段。如果不用来获取文件的大小而是使用它来判断上传文件是否是图片文件，看起来似乎是个很不错的方案，当然这需要屏蔽掉可能产生的警告，比如代码这样写：

``` php
<?php
$filesize = @getimagesize('/path/to/image.png');
if ($filesize) {
	do_upload();
}

# 另外需要注意的是，你不可以像下面这样写：
# if ($filesize[2] == 0)
# 因为 $filesize[2] 可能是 1 到 16 之间的整数，但却绝对不对是0。
```

但是如果你仅仅是做了这样的验证，那么很不幸，你成功的在代码里种下了一个 webshell 的隐患。

要分析这个问题，我们先来看一下这个函数的原型：

``` c
static void php_getimagesize_from_stream(php_stream *stream, zval **info, INTERNAL_FUNCTION_PARAMETERS)
{
	...
	itype = php_getimagetype(stream, NULL TSRMLS_CC);
	switch( itype) {
		...
	}
	...
}

static void php_getimagesize_from_any(INTERNAL_FUNCTION_PARAMETERS, int mode) {
	...
	php_getimagesize_from_stream(stream, info, INTERNAL_FUNCTION_PARAM_PASSTHRU);
	php_stream_close(stream);
}

PHP_FUNCTION(getimagesize)
{
	php_getimagesize_from_any(INTERNAL_FUNCTION_PARAM_PASSTHRU, FROM_PATH);
}
```

限于篇幅上面隐藏了一些细节，现在从上面的代码中我们知道两件事情就够了：

1. 最终处理的函数是 `php_getimagesize_from_stream`
2. 负责判断文件类型的函数是 `php_getimagetype`

接下来看一下 `php_getimagetype` 的实现：

``` c
PHPAPI int php_getimagetype(php_stream * stream, char *filetype TSRMLS_DC)
{
	...
	if (!memcmp(filetype, php_sig_gif, 3)) {
		return IMAGE_FILETYPE_GIF;
	} else if (!memcmp(filetype, php_sig_jpg, 3)) {
		return IMAGE_FILETYPE_JPEG;
	} else if (!memcmp(filetype, php_sig_png, 3)) {
		...
	}
}
```

去掉了一些细节，`php_sig_gif` `php_sig_png` 等是在文件头部定义的：

``` c
PHPAPI const char php_sig_gif[3] = {'G', 'I', 'F'};
...
PHPAPI const char php_sig_png[8] = {(char) 0x89, (char) 0x50, (char) 0x4e, (char) 0x47,
                                    (char) 0x0d, (char) 0x0a, (char) 0x1a, (char) 0x0a};
```

可以看出来 image type 是根据文件流的前几个字节（文件头）来判断的。那么既然如此，我们可不可以构造一个特殊的 PHP 文件来绕过这个判断呢？不如来尝试一下。

找一个十六进制编辑器来写一个的 PHP 语句，比如：

``` php
<?php phpinfo(); ?>
```

这几个字符的十六进制编码（UTF-8）是这样的：

```
3C3F 7068 7020 7068 7069 6E66 6F28 293B 203F 3E
```

我们构造一下，把 PNG 文件的头字节加在前面变成这样的：

```
8950 4E47 0D0A 1A0A 3C3F 7068 7020 7068 7069 6E66 6F28 293B 203F 3E
```

最后保存成 `.php` 后缀的文件（注意上面是文件的十六进制值），比如 test.php。执行一下 `php test.php` 你会发现完全可以执行成功。那么能用 `getimagesize` 读取它的文件信息吗？新建一个文件写入代码试一下：

``` php
<?php
print_r(getimagesize('test.php'));
```

执行结果：

```
Array
(
    [0] => 1885957734
    [1] => 1864902971
    [2] => 3
    [3] => width="1885957734" height="1864902971"
    [bits] => 32
    [mime] => image/png
)
```

成功读取出来，并且文件也被正常识别为 PNG 文件，虽然宽和高的值都大的有点离谱。

现在你应该明白为什么上文说这里留下了一个 webshell 的隐患的吧。如果这里只有这样的上传判断，而且上传之后的文件是可以访问的，就可以通过这个入口注入任意代码执行了。

那么为什么上面的文件可以 PHP 是可以正常执行的呢？用 [token\_get\_all](http://php.net/manual/zh/function.token-get-all.php) 函数来看一下这个文件：

``` php
<?php
print_r(token_get_all(file_get_contents('test.php')));
```

如果显示正常的话你能看到输出数组的第一个元素的解析器代号是 312，通过 [token_name](http://php.net/manual/zh/function.token-name.php) 获取到的名称会是 T\_INLINE\_HTML，也就是说文件头部的信息被当成正常的内嵌的 HTML 代码被忽略掉了。

至于为什么会有一个大的离谱的宽和高，看一下 `php_handle_png` 函数的实现就能知道，这些信息也是通过读取特定的文件头的位来获取的。

所以，对于正常的图片文件，getimagesize 完全可以胜任，但是对于一些有心构造的文件结构却不行。

在处理用户上传的文件时，先简单粗暴的判断文件扩展名并对文件名做一下处理，保证在服务器上不是 php 文件都不能直接执行也是一种有效的方式。然后可以使用 getimagesize 做一些辅助处理。
