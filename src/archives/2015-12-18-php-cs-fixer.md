---
tags:
- php
date: "2015-12-18T00:00:00Z"
title: php-cs-fixer (PHP Coding Standards Fixer)
---

[php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) 是个代码格式化工具，格式化的标准是 PSR-1、PSR-2 以及一些 symfony 的标准。这个工具也和 symfony、twig 等优秀的 PHP 库出自同门。

## 安装与更新

需要使用 PHP 5.3.6 以上的版本。

你可以直接下载封装好的 phar 包：[php-cs-fixer.phar](http://get.sensiolabs.org/php-cs-fixer.phar)；

或者通过 wget 下载（下面的都是 OSX 和 Linux 上的用法）：

``` sh
wget http://get.sensiolabs.org/php-cs-fixer.phar -O php-cs-fixer
```

或者通过 curl 下载：

``` sh
curl http://get.sensiolabs.org/php-cs-fixer.phar -o php-cs-fixer
```

下载完成后给可执行的权限，然后移动到 bin 目录下面即可：

```sh
sudo chmod a+x php-cs-fixer
sudo mv php-cs-fixer /usr/local/bin/php-cs-fixer
```

这样就可以在任何地方直接使用 `php-cs-fixer` 命令来调用了。

也可以用过 Composer 来安装：

``` sh
composer global require fabpot/php-cs-fixer
```

如果你是 Mac 用户、homebrew 用户并且已经 tap 过 homebrew/php 的话，也可以直接：

``` sh
brew install php-cs-fixer
```

或者：

``` sh
brew install homebrew/php/php-cs-fixer
```

如果后续需要更新的话：

``` sh
php-cs-fixer self-update
```

如果是通过 homebrew 安装的：

``` sh
brew upgrade php-cs-fixer
```

如果没有将执行文件放到 bin 目录下或者在 Windows需要使用 `php php-cs-fixer.phar` 代替 `php-cs-fixer`。

## 用法

用法也很简单，最基本的命令参数就是 `fix`，直接执行时会尽可能多的根据默认标准格式化代码：

``` sh
# 格式化目录 如果是当前目录的话可以省略目录
php-cs-fixer fix /path/to/dir
# 格式化文件
php-cs-fixer.phar fix /path/to/file
```

`--verbose` 选项用于展示应用了的规则，默认是文本（`txt`）格式。

`--level` 选项用于控制需要使用的规则层级：

``` sh
php-cs-fixer fix /path/to/project --level=psr0
php-cs-fixer fix /path/to/project --level=psr1
php-cs-fixer fix /path/to/project --level=psr2
php-cs-fixer fix /path/to/project --level=symfony
```

默认情况下执行的是 PSR-2 的所有选项以及一些附加选项（主要是 symfony 相关的）。还有一些属于『贡献级别』的选项，你可以通过 `--fixers` 选择性的添加，`--fixers` 的多个条件要用逗号分开：

``` sh
php-cs-fixer fix /path/to/dir --fixers=linefeed,short_tag,indentation
```

如果有需要的话也可以使用 `-name_of_fixer` 采取黑名单的方式设定禁用哪些选项。如果同时设定了 `--fixers` 和 `-name_of_fixer`，前者的优先级更高。

同时使用 `--dry-run` 和 `--diff` 命令可以显示出需要修改的汇总，但是并不实际修改。

通过以下方式也可以查看有哪些内容是会修改的，但是并不实际改动文件：

``` sh
cat foo.php | php-cs-fixer fix --diff -
```

## 自定义配置

`--config` 选项可以用来设置选取目录以及文件进行分析并格式化，但是这个选项只能设置一些常见的已知的项目，比如 symfony：

``` c
# For the Symfony 2.3+ branch
php-cs-fixer fix /path/to/sf23 --config=sf23
```

已有选项：

- default 默认配置
- magento magento 项目
- sf23 symfony 的项目

更多时候，我们可以通过配置文件来自定义格式化选项以及搜索的目录和文件。自定义配置通过在项目根目录添加一个 `.php_cs` 文件的方式实现。

设置本身就是 PHP 代码，最后返回一个 Symfony\CS\ConfigInterface 的实例即可。你可以设置格式化的选项、级别、文件以及目录。

下面是一个简单的例子：

``` php
<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->exclude('somedir') // 忽略 somedir
    ->in(__DIR__) // 当前目录
;

return Symfony\CS\Config\Config::create()
    ->fixers(['strict_param', 'short_array_syntax']) // 添加两个选项
    ->finder($finder)
;
```

如果你想完全自定义格式化选项，就需要将格式化级别清空，并指定好所有需要的选项：

``` php
<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->in(__DIR__)
;

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::NONE_LEVEL)
    ->fixers(['trailing_spaces', 'encoding'])
    ->finder($finder)
;
```

你也可以通过在选项前面添加 `-` 的方式来禁用某些选项，比如下面这个例子不采用 PSR-0：

``` php
<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->exclude('somedir')
    ->in(__DIR__)
;

return Symfony\CS\Config\Config::create()
    ->fixers(['-psr0'])
    ->finder($finder)
;
```

默认条件下的格式化级别是 symfony (最严格)，你可以修改这个级别：

``` php
<?php

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::PSR2_LEVEL)
;
```

通过这些设置选项的组合，可以很轻易的定制出自己想要的效果。

你也可以通过 `--config-file` 选项指定 `.php_cs` 文件的位置。

启用缓存可以在后续的执行中加快速度，通过以下方法设置：

``` php
<?php

return Symfony\CS\Config\Config::create()
    ->setUsingCache(true)
;
```

## 编辑器插件

下面这些编辑器/IDE 的插件可以帮你简化格式化的工作：

- [Atom](https://github.com/Glavin001/atom-beautify)
- [NetBeans](http://plugins.netbeans.org/plugin/49042/php-cs-fixer)
- [PhpStorm](http://tzfrs.de/2015/01/automatically-format-code-to-match-psr-standards-with-phpstorm)
- [Sublime Text](https://github.com/benmatselby/sublime-phpcs)
- [Vim](https://github.com/stephpy/vim-php-cs-fixer)

## 格式化选项

- **psr0 [PSR-0]**

    PSR-0 的路径和命名空间标准

- **encoding [PSR-1]**

    文件必须是不带 BOM 的 UTF-8 编码；

- **short\_tag [PSR-1]**

    只能使用 `<?php ?>` 和 `<?= ?>` 两种 PHP 代码标签；

- **braces [PSR-2]**

    所有语句块都必须包含在花括号内，且位置以及缩进是符合标准的；

- **class\_definition [PSR-2]**

    class、trait、interfaces 关键字和名称之间只能有一个空格；

- **elseif [PSR-2]**

    使用 `elseif` 替代 `else if`；

- **eof\_ending [PSR-2]**

    文件必须以空白行结尾；

- **function\_call\_space [PSR-2]**

    调用函数和方法时，函数名和方法名与参数扩展之间不能有空格；

- **function\_declaration [PSR-2]**

    函数声明时空格的使用需要符合 PSR-2；

- **indentation [PSR-2]**

    代码必须使用四个空格缩进而不是制表符；

- **line\_after\_namespace [PSR-2]**

    命名空间的声明后必须有一个空白行；

- **linefeed [PSR-2]**

    所有 PHP 文件都只能使用 LF(Unix) 结尾；

- **lowercase\_constants [PSR-2]**

    PHP 常量 true、false 和 null 必须使用小写；

- **lowercase\_keywords [PSR-2]**

    PHP 关键字必须都是小写；

- **method\_argument\_space [PSR-2]**

    方法声明及调用时，参数之间的逗号前不能有空格，逗号后必须有一个空格；

- **multiple\_use [PSR-2]**

    每个 use 只能声明一个元素；

- **parenthesis [PSR-2]**

    圆括号内两侧不能有空格；

- **php\_closing\_tag [PSR-2]**

    纯 PHP 文件必须省略 `?>` 标签；

- **single\_line\_after\_imports [PSR-2]**

    每个 use 声明独立一行，且 use 语句块之后要有一个空白行；

- **trailing\_spaces [PSR-2]**

    删除非空行之后多余的空格；

- **visibility [PSR-2]**

    每个属性和方法都必须指定作用域是 `public`、`protected` 还是 `private`，`abstract` 和 `final` 必须位于作用域关键字之前，`static` 必须位于作用域之后；

- **array\_element\_no\_space\_before\_comma [symfony]**

    数组声明中，逗号之前不能有空格；

- **array\_element\_white\_space\_after\_comma [symfony]**

    数组声明中，逗号之后必须有一个人空格；

- **blankline\_after\_open\_tag [symfony]**

    PHP 开始标签的同一行不能有代码，且下面必须有一个空白行；

- **concat\_without\_spaces [symfony]**

    点连接符左右两边不能有多余的空格；

- **double\_arrow\_multiline\_whitespaces [symfony]**

    `=>` 操作符两端不能有多个空白行；

- **duplicate\_semicolon [symfony]**

    删除重复的分号；

- **empty\_return [symfony]**

    return 语句如果没有任何返回的话直接写 return 即可（不用 return null）；

- **extra\_empty\_lines [symfony]**

    删除多余的空白行；

- **function\_typehint\_space [symfony]**

    修正函数参数和类型提示之间的缺失的空格问题；

- **include [symfony]**

    `include` 和文件路径之间需要有一个空格，文件路径不需要用括号括起来；

- **join\_function [symfony]**

    使用 `join` 替换 `implode` 函数；

- **list\_commas [symfony]**

    删除 `list` 语句中多余的逗号；

- **method\_argument\_default\_value [symfony]**

    函数参数中有默认值的参数不能位于无默认值的参数之前；

- **multiline\_array\_trailing\_comma [symfony]**

    多行数组最后一个元素应该也有一个逗号；

- **namespace\_no\_leading\_whitespace [symfony]**

    命名空间前面不应该有空格；

- **new\_with\_braces [symfony]**

    使用 new 新建实例时后面都应该带上括号；

- **no\_blank\_lines\_after\_class\_opening [symfony]**

    类开始标签后不应该有空白行；

- **no\_empty\_lines\_after\_phpdocs [symfony]**

    PHP 文档块开始开始元素下面不应该有空白行；

- **object\_operator [symfony]**

    `T_OBJECT_OPERATOR` (`->`) 两端不应有空格；

- **operators\_spaces [symfony]**

    二进制操作符两端至少有一个空格；

- **phpdoc\_indent [symfony]**

    phpdoc 应该保持缩进；

- **phpdoc\_inline\_tag [symfony]**

    修正 phpdoc 内联标签格式，使标签与后续内容始终位于一行；

- **phpdoc\_no\_access [symfony]**

    `@access` 不应该出现在 phpdoc 中；

- **phpdoc\_no\_empty\_return [symfony]**

    `@return void` 和 `@return null` 不应该出现在 phpdoc 中；

- **phpdoc\_no\_package [symfony]**

    `@package` 和 `@subpackage` 不应该出现在 phpdoc 中；

- **phpdoc\_params [symfony]**

    `@param`, `@throws`, `@return`, `@var`, 和 `@type` 等 phpdoc 标签都要垂直对齐；

- **phpdoc\_scalar [symfony]**

    phpdoc 标量类型声明时应该使用 `int` 而不是 `integer`，`bool` 而不是 `boolean`，`float` 而不是 `real` 或者 `double`；

- **phpdoc\_separation [symfony]**

    phpdoc 中注释相同的属性应该放在一起，不同的属性之间应该有一个空白行分割；

- **phpdoc\_short\_description [symfony]**

    phpdoc 的简要描述应该以 `.`、`!` 或 `?` 结尾；

- **phpdoc\_to\_comment [symfony]**

    文档块应该都是结构化的元素；

- **phpdoc\_trim [symfony]**

    除了文档块最开始的部分和最后的部分，phpdoc 开始和结束都应该是有内容的；

- **phpdoc\_type\_to\_var [symfony]**

    `@type` 需要使用 `@var` 代替；

- **phpdoc\_types [symfony]**

    phpdoc 中应该正确使用大小写；

- **phpdoc\_var\_without\_name [symfony]**

    `@var` 和 `@type` 注释中不应该包含变量名；

- **pre\_increment [symfony]**

    不应该使用 `++i` 或 `--i` 的用法；

- **print\_to\_echo [symfony]**

    如果可能的话，使用 `echo` 代替 `print` 语句；

- **remove\_leading\_slash\_use [symfony]**

    删除 `use` 前的空行；

- **remove\_lines\_between\_uses [symfony]**

    删除 `use` 语句块中的空行；

- **return [symfony]**

    `return` 之前应该有一个空行；

- **self\_accessor [symfony]**

    在当前类中使用 `self` 代替类名；

- **short\_bool\_cast [symfony]**

    `bool` 类型数据前不应该试用两个感叹号；

- **single\_array\_no\_trailing\_comma [symfony]**

    PHP 单行数组最后一个元素后面不应该有空格；

- **single\_blank\_line\_before\_namespace [symfony]**

    命名空间声明前应该有一个空白行；

- **single\_quote [symfony]**

    简单字符串应该使用单引号代替双引号；

- **spaces\_after\_semicolon [symfony]**

    修复分号后面的空格；

- **spaces\_before\_semicolon [symfony]**

    禁止只有单行空格和分号的写法；

- **spaces\_cast [symfony]**

    变量和修饰符之间应该有一个空格；

- **standardize\_not\_equal [symfony]**

    使用 `<>` 代替 `!=`；

- **ternary\_spaces [symfony]**

    三元运算符之间的空格标准化；

- **trim\_array\_spaces [symfony]**

    数组需要格式化成和函数/方法参数类似，上下没有空白行；

- **unalign\_double\_arrow [symfony]**

    不对齐 `=>`；

- **unalign\_equals [symfony]**

    不对齐等号；

- **unary\_operators\_spaces [symfony]**

    一元运算符和运算数需要相邻；

- **unneeded\_control\_parentheses [symfony]**

    删除控制结构语句中多余的括号；

- **unused\_use [symfony]**

    删除没有用到的 use 语句；

- **whitespacy\_lines [symfony]**

    删除空白行中多余的空格；

除了以上这些选项以外，还有一些用户贡献的选项，这里就不再一一介绍了。

对于代码风格是否统一，执行什么样的标准，每个人、每个团队可能都有自己的看法。这里只是介绍一下这个工具，至于如何选用，还在于大家自己。如果是开源项目，你也可以试用一下 [StyleCI](https://styleci.io/)。
