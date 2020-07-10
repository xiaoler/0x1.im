---
tags:
- php
date: "2016-07-31T20:59:51Z"
title: PHP 错误与异常的日志记录
---

提到 Nginx + PHP 服务的错误日志，我们通常能想到的有 Nginx 的 access 日志、error 日志以及 PHP 的 error 日志。虽然看起来是个很简单的问题，但里面其实又牵扯到应用配置以及日志记录位置的问题，如果是在 ubuntu 等系统下使用 apt-get 的方式来安装，其自有一套较为合理的的配置文件可用。再者运行的应用程序中的配置也会影响到日志记录的方式及内容。

## 错误与异常的区别

关于错误与异常，我们可以用一个简单的例子来理解：

``` php
<?php
try {
    1 / 0;
} catch (Exception $e) {
    echo "catched", PHP_EOL;
}
```

执行这个小示例会直接得到一个『PHP Warning:  Division by zero …』错误。原因很简单：这是逻辑错误，并不是异常，所以不能被 `try` 捕获。同样，对于变量使用前未定义这种问题，也是同样的会产生 warning 而不是被捕获。

但是这个问题在 PHP7 中却有了一些改动，比如上面的例子中我把 `/` 改成 `%`，在 PHP7 的环境中执行会得到一个不一样的提示：

> PHP Fatal error:  Uncaught DivisionByZeroError ...

根据这个提示，如果我把 catch 中的条件修改一下：

``` php
<?php
try {
    1 / 0;
} catch (DivisionByZeroError $e) {
    echo "catched", PHP_EOL;
}
```

这样就可以正常捕获到错误并输出 `catched` 了。

对于第一个示例，同样如果把 `Excepiton` 修改为 `ErrorException` 也可以正常捕获。

至于为什么求余和除法，在 PHP5 中提示一致而在 PHP7（我的测试环境是 7.0.4） 中除法不属于 `DivisionByZeroError` 的问题，这应该是个 [BUG](https://bugs.php.net/bug.php?id=69957)。

## 日志的记录

PHP 本身可配置的 log 大概有以下几个：

- php-fpm error log（php-fpm.conf 中配置，记录 php-fpm 进程的启动和终止等信息）
- php-fpm slow log（也是在 php-fpm.conf 中配置，记录慢执行）
- php error log（php.ini 中配置，记录应用程序的错误日志）

此外 Nginx 还有两个可配置的log：access 和 error log。这几个日志文件的功能不同，记录的内容也不同。但其中有一个点需要注意：如果配置了 php-fpm 中的 error log 位置，但日志位置不可写（配置时位置得是对的，因为 php-fpm 启动时会做检查），在适当的配置条件下错误日志会被返回到 cgi 中从而写入 nginx 的 error log 中。

所以遇到问题是我们一般的查找思路都是：

1. 到 Nginx access log 中查看请求的状态码
2. 查看 php error log 中的错误记录以及 stack 信息
3. 查看 php-fpm log 中有无异常重启记录（如果核心或者扩展问题，会出现此情况）

但是在以上几种情况下你也会发现，这里面并没有上文提到的程序抛出异常的日志记录。

## 异常记录

异常不同于错误，严格上说它是应用程序逻辑的异常而不是错误，是可以通过合理的程序逻辑来手动触发的。但大多情况下异常也是要进行记录的，比如数据库无法连接或者框架的不当使用触发的异常，我们需要通过日志来定位问题并及时处理。

PHP 提供了两个函数用于自定义处理错误和异常的方法：

- set_error_handler
- set_exception_handler

所以可以通过 `set_exception_handler`  函数注入方法捕获所有的异常并记录 。

[monolog](https://github.com/Seldaek/monolog) 是一个优秀的异常记录的库，也是基于 [PSR-3](http://www.php-fig.org/psr/psr-3/) 标准的实现。Laravel、Symfony 中默认也是使用它来记录异常。如有需要，也可以考虑在自己的项目中引入。
