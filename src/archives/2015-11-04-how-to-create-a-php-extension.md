---
tags:
- php
date: "2015-11-04T23:43:58Z"
title: 如何编写一个独立的 PHP 扩展（译）
---

*本文翻译自 PHP 源码中的 README.SELF-CONTAINED-EXTENSIONS。文中标记了 `注` 的内容均为自己添加。内容有点老，也挺啰嗦，没讲什么深入的内容，但是可以作为入门学习参考。*

独立的 PHP 扩展可以独立于 PHP 源码之外进行分发。要创建一个这样的扩展，需要准备好两样东西：

- 配置文件 (config.m4)
- 你的模块源码

接下来我们来描述一下如果创建这些文件并组合起来。

## 准备好系统工具

想要扩展能够在系统上编译并成功运行，需要准备转以下工具：

- GNU autoconf
- GNU automake
- GNU libtool
- GNU m4

以上这些都可以从 [ftp://ftp.gnu.org/pub/gnu/](ftp://ftp.gnu.org/pub/gnu/) 获取。

*注：以上这些都是类 Unix 环境下才能使用的工具。*

## 改装一个已经存在的扩展

为了显示出创建一个独立的扩展是很容易的事情，我们先将一个已经内嵌到 PHP 的扩展改成独立扩展。安装 PHP 并且执行以下命令：

``` sh
$ mkdir /tmp/newext
$ cd /tmp/newext
```

现在你已经有了一个空目录。我们将 mysql 扩展目录下的文件复制过来：

``` sh
$ cp -rp php-4.0.X/ext/mysql/* .
# 注：看来这篇 README 真的需要更新一下了
# PHP7 中已经移除了 mysql 扩展部分
```

到这里扩展就完成了，执行：

``` sh
$ phpize
```

现在你可以独立存放这个目录下的文件到任何地方，这个扩展可以完全独立存在了。

用户在编译时需要使用以下命令：

``` sh
$ ./configure \
       [--with-php-config=/path/to/php-config] \
       [--with-mysql=MYSQL-DIR]
$ make install
```

这样 MySQL 模块就可以使用内嵌的 MySQL 客户端库或者已安装的位于 MySQL 目录中的 MySQL。

*注：意思是说想要编写 PHP 扩展，你既需要已经安装了 PHP，也需要下载一份 PHP 源码。*

## 定义一个新扩展

我们给示例扩展命名为 “foobar”。

新扩展包含两个资源文件：foo.c 和 bar.c（还有一些头文件，但这些不只重要）。

示例扩展不引用任何外部的库（这点很重要，因为这样用户就不需要特别指定一些编译选项了）。

`LTLIBRARY_SOURCES` 选项用于指定资源文件的名字，你可以有任意数量的资源文件。

*注：上面说的是 Makefile.in 文件中的配置选项，可以参考 [xdebug](https://github.com/xdebug/xdebug/blob/master/Makefile.in)。*

## 修改 m4 后缀的配置文件

m4 配置文件可以指定一些额外的检查。对于一个独立扩展来说，你只需要做一些宏调用即可。

``` sh
PHP_ARG_ENABLE(foobar,whether to enable foobar,
[  --enable-foobar            Enable foobar])

if test "$PHP_FOOBAR" != "no"; then
  PHP_NEW_EXTENSION(foobar, foo.c bar.c, $ext_shared)
fi
```

`PHP_ARG_ENABLE` 会自动设置好正确的变量以保证扩展能够被 `PHP_NEW_EXTENSION` 以共享模式启动。

`PHP_NEW_EXTENSION` 的第一个参数是扩展的名称，第二个参数是资源文件。第三个参数 `$ext_shared` 是由 `PHP_ARG_ENABLE/WITH` 为 `PHP_NEW_EXTENSION` 设定的。

请始终使用 `PHP_ARG_ENABLE` 或 `PHP_ARG_WITH` 进行设置。即使你不打算发布你的 PHP 模块，这些设置也可以保证让你的模块和 PHP 主模块的接口保持一体。

*注：`PHP_ARG_ENABLE` 和 `PHP_ARG_WITH` 应该是用于定义模块是动态扩展还是静态编译进 PHP 中，就跟编译 PHP 时使用的 `--enable-xxx` 和 `--with-xxx` 一样。*

## 创建资源文件

`ext_skel` 可以为你的 PHP 模块创建一些通用的代码，你也可以编写一些基本函数定义和 C 代码来处理函数的参数。具体信息可以查看 [READNE.EXT_SKEL](https://github.com/php/php-src/blob/master/README.EXT_SKEL)。

不要担心没有范例，PHP 中有很多模块供你参考，选择一个简单的点开始，添加你自己的代码。

*注：`ext_skel` 可以生成好基本模块需要的资源文件和配置文件，不需要自己创建。*

## 修改自定义模块

将 config.m4 文件和资源文件放到同一个目录中，然后执行 `phpize` （PHP 4.0 以上的版本编译 PHP 的时候都安装了 phpize）。

如果你的 phpize 不在系统环境变量中，你需要指定绝对路径，例如：

``` sh
$ /php/bin/phpize
```

这个命令会自动复制必需的构建文件到当前目录并根据 config.m4 创建配置文件。

通过以上的步骤，你已经有了一个独立的扩展了。

## 安装扩展

扩展可以通过以下命令编译安装：

``` sh
$ ./configure \
            [--with-php-config=/path/to/php-config]
$ make install
```

## 给模块添加共享支持

有时候独立扩展需要是共享的已供其他模块加载。接下来我会解释如何给已经创建好的 foo 模块添加共享支持。

1. 在 config.m4 文件中，使用 `PHP_ARG_WITH/PHP_ARG_ENABLE` 来设定扩展，这样就可以自动使用 `--with-foo=shared[,..]` 或 `--enable-foo=shared[,..]` 这样的指令作为编译参数了。

2. 在 config.m4 文件中，使用 `PHP_NEW_EXTENSION(foo,.., $ext_shared)` 使扩展可以被构建。

3. 添加以下代码到你的 C 语言资源文件中：

   ``` c
   #ifdef COMPILE_DL_FOO
   ZEND_GET_MODULE(foo)
   #endif
   ```

*这一段讲的上面都提到过了，这里只是又强调了一下。*

## PECL 网站约定

如果你打算发布你的扩展到 PECL 的网站，需要考虑以下几点：

1. 添加 LICENSE 或 COPYING 到 package.xml

2. 需要在扩展头文件中定义好版本信息，这个宏会被 `foo_module_entry` 调用来声明扩展版本：

   ``` c
   #define PHP_FOO_VERSION "1.2.3"
   ```
