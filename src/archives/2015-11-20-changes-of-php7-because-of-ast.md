---
tags:
- php
date: "2015-11-20T12:58:25Z"
title: PHP7 的抽象语法树（AST）带来的变化
---

*本文大部分内容参照 AST 的 RFC 文档而成：[https://wiki.php.net/rfc/abstract_syntax_tree](https://wiki.php.net/rfc/abstract_syntax_tree)，为了易于理解从源文档中节选部分进行介绍。*

*本文并不会告诉你抽象语法树是什么，这需要你自己去了解，这里只是描述 AST 给 PHP 带来的一些变化。*

## 新的执行过程

PHP7 的内核中有一个重要的变化是加入了 AST。在 PHP5中，从 php 脚本到 opcodes 的执行的过程是：

1. Lexing：词法扫描分析，将源文件转换成 token 流；
2. Parsing：语法分析，在此阶段生成 op arrays。

PHP7 中在语法分析阶段不再直接生成 op arrays，而是先生成 AST，所以过程多了一步：

1. Lexing：词法扫描分析，将源文件转换成 token 流；
2. Parsing：语法分析，从 token 流生成抽象语法树；
3. Compilation：从抽象语法树生成 op arrays。

## 执行时间和内存消耗

 从以上的步骤来看，这比之前的过程还多了一步，所以按常理来说这反而会增加程序的执行时间和内存的使用。但事实上内存的使用确实增加了，但是执行时间上却有所降低。

以下结果是使用小（代码大约 100 行）、中（大约 700 行）、大（大约 2800 行）三个脚本分别进行测试得到的，测试脚本： [https://gist.github.com/nikic/289b0c7538b46c2220bc](https://gist.github.com/nikic/289b0c7538b46c2220bc).

每个文件编译 100 次的执行时间（注意文章的测试结果时间是 14 年，PHP7 还叫 PHP-NG 的时候）：

|        | php-ng | php-ast | diff   |
| ------ | ------ | ------- | ------ |
| SMALL  | 0.180s | 0.160s  | -12.5% |
| MEDIUM | 1.492s | 1.268s  | -17.7% |
| LARGE  | 6.703s | 5.736s  | -16.9% |

单次编译中的内存峰值：

|        | php-ng | php-ast | diff   |
| ------ | ------ | ------- | ------ |
| SMALL  | 378kB  | 414kB   | +9.5%  |
| MEDIUM | 507kB  | 643kB   | +26.8% |
| LARGE  | 1084kB | 1857kB  | +71.3% |

单次编译的测试结果可能并不能代表实际使用的情况，以下是使用 [PhpParser](https://github.com/nikic/PHP-Parser/tree/master/lib/PhpParser) 进行完整项目测试得到的结果：

|        | php-ng | php-ast | diff   |
| ------ | ------ | ------- | ------ |
| TIME   | 25.5ms | 22.8ms  | -11.8% |
| MEMORY | 2360kB | 2482kB  | +5.1%  |

测试表明，使用 AST 之后程序的执行时间整体上大概有 10% 到 15% 的提升，但是内存消耗也有增加，在大文件单次编译中增加明显，但是在整个项目执行过程中并不是很严重的问题。

还有注意的是以上的结果都是在没有 Opcache 的情况下，生产环境中打开 Opcache 的情况下，内存的消耗增加也不是很大的问题。

## 语义上的改变

如果仅仅是时间上的优化，似乎也不是使用 AST 的充足理由。其实实现 AST 并不是基于时间优化上的考虑，而是为了解决语法上的问题。下面来看一下语义上的一些变化。

#### yield 不需要括号

在 PHP5 的实现中，如果在一个表达式上下文（例如在一个赋值表达式的右侧）中使用 yield，你必须在 yield 申明两边使用括号：

``` php
<?php
$result = yield fn();   // 不合法的
$result = (yield fn()); // 合法的
```

这种行为仅仅是因为 PHP5 的实现方式的限制，在 PHP7 中，括号不再是必须的了。所以下面这些写法也都是合法的：

``` php
<?php
$result = yield;
$result = yield $v;
$result = yield $k => $v;
```

当然了，还得遵循 yield 的应用场景才行。

#### 括号不影响行为

在 PHP5 中，`($foo)['bar'] = 'baz'` 和 `$foo['bar'] = 'baz'` 两个语句的含义不一样。事实上前一种写法是不合法的，你会得到下面这样的错误：

``` php
<?php
($foo)['bar'] = 'baz';
# PHP Parse error: Syntax error, unexpected '[' on line 1
```

但是在 PHP7 中，两种写法表示同样的意思。

同样，如果函数的参数被括号包裹，类型检查存在问题，在 PHP7 中这个问题也得到了解决：

``` php
<?php
function func() {
    return [];
}

function byRef(array &$a) {
}

byRef((func()));
```

以上代码在 PHP5 中不会告警，除非使用 `byRef(func())` 的方式调用，但是在 PHP7 中，不管 `func()` 两边有没有括号都会产生以下错误：

```
PHP Strict standards:  Only variables should be passed by reference ...
```

#### list() 的变化

list 关键字的行为改变了很多。list 给变量赋值的顺序（等号左右同时的顺序）以前是从右至左，现在是从左到右：

``` php
<?php
list($array[], $array[], $array[]) = [1, 2, 3];
var_dump($array);

// PHP5: $array = [3, 2, 1]
// PHP7: $array = [1, 2, 3]

# 注意这里的左右的顺序指的是等号左右同时的顺序，
# list($a, $b) = [1, 2] 这种使用中 $a == 1, $b == 2 是没有疑问的。
```

产生上面变化的原因正是因为在 PHP5 的赋值过程中，`3` 会最先被填入数组，`1` 最后，但是现在顺序改变了。

同样的变化还有：

``` php
<?php
$a = [1, 2];
list($a, $b) = $a;

// PHP5: $a = 1, $b = 2
// PHP7: $a = 1, $b = null + "Undefined index 1"
```

这是因为在以前的赋值过程中 `$b` 先得到 `2`，然后 `$a` 的值才变成 `1`，但是现在 `$a` 先变成了 `1`，不再是数组，所以 `$b` 就成了 `null`。

list 现在只会访问每个偏移量一次：

``` php
<?php
list(list($a, $b)) = $array;

// PHP5:
$b = $array[0][1];
$a = $array[0][0];

// PHP7:
// 会产生一个中间变量，得到 $array[0] 的值
$_tmp = $array[0];
$a = $_tmp[0];
$b = $_tmp[1];
```

空的 list 成员现在是全部禁止的，以前只是在某些情况下：

``` php
<?php
list() = $a;           // 不合法
list($b, list()) = $a; // 不合法
foreach ($a as list()) // 不合法 (PHP5 中也不合法)
```

#### 引用赋值的顺序

引用赋值的顺序在 PHP5 中是从右到左的，现在时从左到右：

``` php
<?php
$obj = new stdClass;
$obj->a = &$obj->b;
$obj->b = 1;
var_dump($obj);

// PHP5:
object(stdClass)#1 (2) {
  ["b"] => &int(1)
  ["a"] => &int(1)
}

// PHP7:
object(stdClass)#1 (2) {
  ["a"] => &int(1)
  ["b"] => &int(1)
}
```

#### \_\_clone 方法可以直接调用

现在可以直接使用 `$obj->__clone()` 的写法去调用 `__clone` 方法。`__clone` 是之前唯一一个被禁止直接调用的魔术方法，之前你会得到一个这样的错误：

``` s
Fatal error: Cannot call __clone() method on objects - use 'clone $obj' instead in ...
```

## 变量语法一致性

AST 也解决了一些语法一致性的问题，这些问题是在另外一个 RFC 中被提出的：[https://wiki.php.net/rfc/uniform\_variable\_syntax](https://wiki.php.net/rfc/uniform_variable_syntax).

在新的实现上，以前的一些语法表达的含义和现在有些不同，具体的可以参照下面的表格：

| Expression              | PHP5                      | PHP7                      |
| ----------------------- | ------------------------- | ------------------------- |
| $$foo\['bar'\]\['baz'\] | ${$foo\['bar'\]\['baz'\]} | ($$foo)\['bar'\]\['baz'\] |
| $foo->$bar['baz']       | $foo->{$bar['baz']}       | ($foo->$bar)['baz']       |
| $foo->$bar\['baz'\]\(\) | $foo->{$bar['baz']}()     | ($foo->$bar)\['baz'\]\(\) |
| Foo::$bar\['baz'\]\(\)  | Foo::{$bar['baz']}()      | (Foo::$bar)\['baz'\]\(\)  |

整体上还是以前的顺序是从右到左，现在从左到右，同时也遵循括号不影响行为的原则。这些复杂的变量写法是在实际开发中需要注意的。
