---
categories:
- nginx
date: "2015-04-09T15:36:35Z"
title: 使用Nginx 的 image filter 模块裁剪图片
---

## 背景

项目中有个地方需要根据客户端的要求缩放图片。最开始想用PHP来实现这个功能。设想中如果已经存在图片`a.jpg`，则可以通过类似`a_400x400.jpg`的方式来获取图片特定尺寸的缩略图。

要实现此功能可以在图片上传的时候就事先裁好指定尺寸的图片，或者在获取的时候拦截请求来实现。

如果使用第一种方法，则只能实现裁剪好预设尺寸的图片，而且会影响到上传图片的效率，如果裁剪失败，也无法后续处理。

使用第二种方式的问题是图片资源存储在一个静态资源的目录，需要在没有图片的情况下将请求转发给PHP去处理。

于是我设想能否在Nginx这一层去做这件事情，恰好Nginx有一个image filter的模块，只不过在编译的时候默认没有编译进去。

手动添加参数编译此模块，开始修改nginx的配置文件。

## 配置

第一个版本的配置如下：

``` sh
# 我使用16进制数的方式给图片重命名
location ~* /(.*)\/([0-9a-f]+)_(\d+)x(\d+)\.(jpg|png|jpeg|gif)$ {
    # 如果存在文件就终止规则
    if (-f $request_filename) {
        break;
    }
    
    # 设定一些参数
    set $filepath $1;
    set $filename "$2.$5";
    set $thumb    "$2_$3x$4.$5";
    set $width    $3;
    set $height   $4;
    
    # 如果原文件不存在可以直接返回404
    if (!-f $document_root/$filepath/$filename) {
        return 404;
    }

    # 重写URL
    rewrite /(.*)\/([0-9a-f]+)_([0-9x]+)\.(jpg|png|jpeg|gif) /$1/$2.$4 break;
    # 执行图片缩放
    image_filter test;
    image_filter resize $width $height;
    image_filter_jpeg_quality 75;
}
```

但是在这个版本的配置中，如果配置原文件不存在，实际上没法正确返回404，而是返回415。过滤还是执行了。

还有一个问题就是在每次访问缩略图的时候都会重新生成，如果访问量比较大的情况下，效率并不高。

进过一系列的实践后，我又改好了一个版本：

``` sh
location ~* /(.*)\/([0-9a-f]+)_(\d+)x(\d+)\.(jpg|png|jpeg|gif)$ {
    if (-f $request_filename) {
        break;
    }

    set $filepath $1;
    set $filename "$2.$5";
    set $thumb    "$2_$3x$4.$5";
    set $width    $3;
    set $height   $4;

    if (!-f $document_root/$filepath/$filename) {
        return 404;
    }

    rewrite /(.*)\/([0-9a-fx_]+)\.(.*) /imgcache/$2.$3;

    if (!-f $request_filename) {
        proxy_pass http://127.0.0.1:$server_port/image-resize/$filepath/$filename?width=$width&height=$height;
        break;
    }

    proxy_store          $document_root/imgcache/$thumb;
    proxy_store_access   user:rw  group:rw  all:r;
    proxy_set_header     Host $host;
}

location /image-resize {
    rewrite /(image-resize)/(.*) /$2 break;

    image_filter resize $arg_width $arg_height;
    image_filter_jpeg_quality 75;

    allow 127.0.0.0/8;
    deny all;
}
```

通过`proxy_pass`的方式解决415的问题，并通过`proxy_store`的方式将图片存到指定的目录（imgcache），在每次访问之前先进行判断。
