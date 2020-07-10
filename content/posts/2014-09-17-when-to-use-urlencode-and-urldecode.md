---
categories:
- web
date: "2014-09-17T19:07:27Z"
title: 什么时候需要使用urlencode和urldecode函数
---

本文默认的语言为PHP

今天在使用fscokopen的时候需要在输入中带上get参数，测试的时候发现参数传过去有问题，于是简单的把参数urlencode了一下再传，问题解决。

后来检查了一下，原来是在参数中有空格，被拼接在需要通过fputs往scoket里字符串里再写进去就出现问题了。

于是整理了一下关于urlencode和urldecode的小问题：

1. 除了`-._`三个字符、大小写字母、数字，其它字符串都会被urlencode处理（虽然encode编码之后的字符串都是数字和大写字母，但是小写字母是不会被编码的）
1. 通过浏览器在URL后面带GET参数的时候都是经过encode处理的（所以才叫urlencode的嘛）
1. PHP在后台接收参数的时候无需经过urldecode的处理了：

    > **Warning: 超全局变量 $\_GET 和 $\_REQUEST 已经被解码了。对 $\_GET 或 $\_REQUEST 里的元素使用 urldecode() 将会导致不可预计和危险的结果**

1. POST传递和接受参数都不需要经过encode和decode处理，$\_POST接收的参数也不会进行解码操作
1. 在使用fsockopen等函数，通过凭借header信息字符串的方式添加进去的参数，如果经过eneode，需要自己调用urldecode方法
1. encode之后的字符串还会可以再次被encode，`%`会被编码为`%25`，但是如果在浏览器上带上encode之后的字符串，字符串不会被再次编码
1. PHP的urlencode函数被把空格替换成`+`,rawurlencode函数会空格编码成`20%`
