---
tags:
- web
date: "2014-12-14T18:36:44Z"
title: 开始学习和使用Laravel
---

## About Laravel

Laravel是一个最近两年兴起的框架，在去年的PHP框架流行程度统计中居首，占据25.87%的份额。
<br>
Laravel是一个面向对象的PHP框架，大量运用了PHP5的特性。Laravel 4.0的版本需要在PHP 5.3.7 以上的环境中运行，而最新的4.2 版本则需要PHP 5.4以上的环境。
<br>
Laravel是一个重量级的框架，依赖于PHP社区中的现有标准、框架来实现。所以学习Laravel要先从以下几个项目和概念开始。

## PSR
PSR的全称是PHP Standard Recommendation (PHP标准推荐)，是由PHP-FIG (PHP Framework Interop Group) 创导并规定的，到目前一共发不过5个标准 (PSR-0 ~ PSR-4)。

PSR项目的github地址：[https://github.com/php-fig/fig-standards](https://github.com/php-fig/fig-standards)
<br>
关于PSR的具体介绍可以参考：[http://segmentfault.com/a/1190000000380008](http://segmentfault.com/a/1190000000380008)

**PSR:**

- PSR-0 自动加载
- PSR-1 基本代码规范
- PSR-2 代码样式
- PSR-3 日志接口
- PSR-4 autoloader, PSR-4可以替代PSR-0, 也可以和包括PSR-0在内的其他自动加载机制共同使用

Laravel 中并不直接使用到PSR，而是因为Laravel 使用了另外一个项目：Composer。

## Composer
Composer 是 PHP 用来管理依赖（dependency）关系的工具。你可以在自己的项目中声明所依赖的外部工具库（libraries），Composer 会帮你安装这些依赖的库文件。类似于Node.js的npm 和 Ruby的 bundler。

[Packagist](https://packagist.org/) 是Composer 的主要资源库，默认的，Composer 只使用Packagist 仓库。通过指定仓库地址，你可以从任何地方获取包。

Composer支持PSR-0,PSR-4,classmap及files包含以支持文件自动加载。

Laravel 使用Composer 安装。安装完成后vendor 目录下得composer 目录下有autolad 文件，会根据项目需要加载的类生成classmap。在项目中只需要：
> require 'vendor/autoload.php';

即可自动加载所有需要的类。

Composer 中文文档：[http://docs.phpcomposer.com/](http://docs.phpcomposer.com/)
<br>
项目Github 地址：[https://github.com/composer/composer](https://github.com/composer/composer)

## Symfony2
Symfony是一个基于MVC模式的面向对象的PHP5框架。Symfony允许在一个web应用中分离事务控制，服务逻辑和表示层。

Symfony2是一个由20多个独立的开发库组成的工具集，你可以在任何PHP项目里使用这些代码。这些开发库，被称作Symfony2组件，功能涵盖了绝大部分的开发需求。

没错，Symfony是另外一个完整功能的PHP框架。Laravel中使用了部分Symfony2的组件，包括HttpFoundation 和 Routing 等。

## IOC && DI
Laravel使用IoC（Inversion of Control，控制反转，这是一个设计模式）容器管理类依赖。简单说来，就是一个类把自己的的控制权交给另外一个对象，类间的依赖由这个对象去解决。

实现控制反转的方式主要由依赖注入（Dependency Injection，简称DI）和依赖查找（Dependency Lookup），依赖注入属于依赖的显示申明，而依赖查找则是通过查找来解决依赖。前者应用比较广泛。

DI 是不用编写固定代码来处理类之间依赖的方法，相反的，这些依赖是在运行时注入的，这样允许处理依赖时具有更大的灵活性。（控制反转和依赖注入这两个概念最常见的例子大多都是java的）。

Laravel 的每个核心包中都包含一个服务提供器（ServiceProvider）的类，它将依赖聚集一起统一申明和管理，让依赖变得更加容易维护。

## Facades
Facades（一种设计模式，通常翻译为外观模式或者门面模式）提供了一个static 接口去访问注册到IoC 容器中的类 。

在 Laravel 应用程序中， Facade 是提供从容器中访问对象的类。Facade 类只需要实现一个方法： `getFacadeAccesor`。getFacadeAccessor 方法的工作是定义如何从容器中取得对象。Facades 基类调用PHP的魔术方法 `__callStatic()`从Facade 中延迟访问取得对象。

因此，当你使用Facade调用，类似Cache::get，Laravel会从IoC容器取得Cache管理类并调用get方法。在技术层上说，Laravel Facades是一种便捷的语法使用Laravel IoC容器作为服务定位器。

## 附录
- Laravel 中文文档地址：[http://v4.golaravel.com/docs/4.2](http://v4.golaravel.com/docs/4.2)
- Symfony2 中文文档地址：[http://symfony.cn/docs/index.html](http://symfony.cn/docs/index.html)
- PHP-FIG：[http://www.php-fig.org/](http://www.php-fig.org/)
