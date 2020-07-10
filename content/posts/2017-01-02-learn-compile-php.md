---
tags:
- php
date: "2017-01-02T12:00:00Z"
title: 如何学习 PHP 源码 - 从编译开始
---

PHP Mailing Lists 上这两天有个好玩儿的问题：[Introduction to the PHP source code](http://externals.io/thread/581)，大概就是有人想知道如何学习 PHP 源码，可是这种事情不是应该自己去发掘的吗？

上面是玩笑话，现在我也说说如何学习 PHP 解释器的源码。

首选你要知道的是 PHP 解释器源码的 github 地址：[https://github.com/php/php-src](https://github.com/php/php-src) ，话说回来还有人不知道吗？这里有几乎所有 PHP 的代码提交记录、pull requests 和一些 issue 等。

## 创建编译脚本或者发布包

从 Branch 中选择一个版本 tag，和每次 PHP 发布出来的版本就是一致的。也许你会发现你想编译的的时候缺找不到 `configure` 文件，但是有 `configure.in` 文件。这时候需要先执行的是 `buildconf`（如果是在 Windows 下面可以执行 `buildconf.bat`，不过我从来没有尝试过在 Windows 下面编译 PHP，所以具体的步骤我就不清楚了）。buildconf 本身是个简单的 shell 脚本，你可以用记事本打开看看它（最终的执行文件在 `build` 目录里，这个目录里有一些与编译有关的文件）。

这里面涉及到一个系列的编译工具：[Autotools](https://www.gnu.org/software/automake/manual/html_node/Autotools-Introduction.html)。如果你有兴趣，可以简单的了解一下，没有兴趣的话也不用多考虑，因为这些工具绝大多数 Linux 系统上都是已经存在的。

如果你想将 Github 上的 PHP 源码做成一个可发布的源码包，你可以看看 `makedist` 这个文件，它也是一个 shell 脚本（实际上源码里几乎所有跟编译相关的脚本都是 shell 脚本）。但是如果想直接执行者这个脚本，你可能会收到缺少以下组件的提示：`re2c` 和 `Bison`。仔细看 makedist 的文件，里面有调用 `genfiles` 这个脚本的语句，上面两个工具就是在 genfiles 的脚本里被调用的。

re2c 和 Bison 分别是 PHP 用到的词法解析器和语法分析器。在 genfiles 这个文件中可以看到它们的调用其实是在 `Makefile.frag` 中写着，分别通过 `zend_language_scanner.l` 和 `zend_language_parser.y` 生成相应的 C 语言文件（这个应该很多地方都有提到过）。

## 编译解释器并初始化

到了编译环节，编译之前需要先通过 `configure` 文件生成 Makefile 然后执行 `make`，所以 `gcc` 自然是必不可少的。configure 文件本身也是一个 shell 脚本，你也可以简单阅读一下它的内容。不过既然它是由 `autoconf` 从 `configure.in` 中生成的，也许直接查看 configure.in 会更轻松一些。

到这里总结一下就是：抛开一些核心扩展额依赖（比如 xml，ssl 等），编译 PHP 的先决条件是机器上有 Autotools 的工具（automake，autoconf 等），需要安装 re2c 和 Bison，当然还有编译工具（gcc）。

也许大家都知道，使用 `configure`  生成 Makefile 的时候可以通过 `--prefix` 参数指定目录，同时也可以选择编译哪些核心模块。至于哪些模块会被默认集成而哪些不会，这些本身是写在每个扩展的 `config.m4` （也有几个是被命名为 config0.m4 或 config9.m4）文件里的的，全都通过一些  `--enable`、`--disable`、`--with` 和 `--without` 的选项来控制。

编译的也与你采用的 Web 服务器有关，这涉及到你需要使用哪个 `sapi`，如果是 Apache，也许需要指定 `--with-apxs2` 的参数，如果是 Nginx，`php-fpm` 在默认条件下是会被编译的，但可以指定 php-fpm 的执行组和用户，不过这个是可以在编译完成后在配置中修改的。

编译完成之后还有一些事情需要考虑，最基本的问题是 PHP 的配置文件的问题，还有一个是如果使用的是 php-fpm，如何更便捷的控制它的启动、停止以及重启等。

在 PHP 源码根中已经准备了两份配置文件的模板：`php.ini-development` 和 `php.ini-production`。显然是分别用于开发环境和生产环境的，将其中一个复制到配置文件目录并重命名为 `php.ini` 即可（如果你不知道配置文件的目录在哪里，可以使用 `php --ini` 命令查看）。然后也可以根据你的需要修改它。

至于 php-fpm 的控制脚本，源码中本身也是有提供的，在 `sapi/fpm` 目录下。这个目录下的几个文件中有 php-fpm 配置文件的模板，也有稍微修改即可放到服务器 `/etc/init.d` 目录下用于控制 php-fpm 的 `start`、`stop`、`restart` 和 `reload` 动作的脚本，现在的版本中也提供了用于 `systemd` 的 service 文件。

## 扩展编译

如果 PHP 编译完成之后，发现还需要一些没有编译进去的核心扩展或者第三方扩展，你可以单独编译它们。

扩展编译的整个过程一共四句命令：

1. phpize
2. ./configure
3. make
4. make install

`phpize` 命令是用来准备 PHP 扩展库的编译环境的。在执行 `phpize` 的时候，如果有多个版本的 PHP，用哪个就要选哪个。这个命令和编译后的 php 的二进制文件在同一个目录中，也是一个 shell 脚本。

执行 `configure` 的时候，如果当前 `$PATH` 中找不到 `php-config` 或者有多个版本的 PHP 时，也需要通过 `--with-php-config` 的指令来指定 php-config 的目录。php-config 是一个用于获取所安装的 PHP 配置的信息，它也一样是和 php 的二进制文件在同一个目录的 shell 脚本。

phpize 和 php-config 的源码生成文件都是在 scripts 目录下。

所有工作完成之后，就可以愉快的使用你自己定制的 PHP 了。
