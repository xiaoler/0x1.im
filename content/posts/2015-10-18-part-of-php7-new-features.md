---
tags:
- php
date: "2015-10-18T15:29:42Z"
title: PHP 7 的几个新特性
---

### 1. ?? 运算符（NULL 合并运算符）

把这个放在第一个说是因为我觉得它很有用。用法：

``` php
$a = $_GET['a'] ?? 1;
```

它相当于：

``` php
<?php
$a = isset($_GET['a']) ? $_GET['a'] : 1;
```

我们知道三元运算符是可以这样用的：

``` php
$a ?: 1
```

但是这是建立在 $a 已经定义了的前提上。新增的 ?? 运算符可以简化判断。

### 2. 函数返回值类型声明

官方文档提供的例子（注意 `...` 的边长参数语法在 PHP 5.6 以上的版本中才有）：

``` php
<?php
function arraysSum(array ...$arrays): array
{
    return array_map(function(array $array): int {
        return array_sum($array);
    }, $arrays);
}

print_r(arraysSum([1,2,3], [4,5,6], [7,8,9]));
```

从这个例子中可以看出现在函数（包括匿名函数）都可以指定返回值的类型。

这种声明的写法有些类似于 swift：

``` swift
func sayHello(personName: String) -> String {
    let greeting = "Hello, " + personName + "!"
    return greeting
}
```

这个特性可以帮助我们避免一些 PHP 的隐式类型转换带来的问题。在定义一个函数之前就想好预期的结果可以避免一些不必要的错误。

不过这里也有一个特点需要注意。PHP 7 增加了一个 *declare* 指令：`strict_types`，既使用严格模式。

使用返回值类型声明时，如果没有声明为严格模式，如果返回值不是预期的类型，PHP 还是会对其进行强制类型转换。但是如果是严格模式， 则会出发一个 `TypeError` 的 Fatal error。

强制模式：

``` php
<?php
function foo($a) : int
{
    return $a;
}

foo(1.0);
```

以上代码可以正常执行，foo 函数返回 int 1，没有任何错误。

严格模式：

``` php
<?php
declare(strict_types=1);

function foo($a) : int
{
    return $a;
}

foo(1.0);
# PHP Fatal error:  Uncaught TypeError: Return value of foo() must be of the type integer, float returned in test.php:6
```

在声明之后，就会触发致命错误。

是不是有点类似与 js 的 strict mode？

### 3. 标量类型声明

PHP 7 中的函数的形参类型声明可以是标量了。在 PHP 5 中只能是类名、接口、`array` 或者 `callable` (PHP 5.4，即可以是函数，包括匿名函数)，现在也可以使用 `string`、`int`、`float`和 `bool` 了。

官方示例：

``` php
<?php
// Coercive mode
function sumOfInts(int ...$ints)
{
    return array_sum($ints);
}

var_dump(sumOfInts(2, '3', 4.1));
```

需要注意的是上文提到的严格模式的问题在这里同样适用：强制模式（默认，既强制类型转换）下还是会对不符合预期的参数进行强制类型转换，严格模式下则触发 `TypeError` 的致命错误。

### 4. use 批量声明

PHP 7 中 use 可以在一句话中声明多个类或函数或 const 了：

``` php
<?php
use some\namespace\{ClassA, ClassB, ClassC as C};
use function some\namespace\{fn_a, fn_b, fn_c};
use const some\namespace\{ConstA, ConstB, ConstC};
```

但还是要写出每个类或函数或 const 的名称（并没有像 python 一样的 `from some import *` 的方法）。

需要留意的问题是：如果你使用的是基于 composer 和 PSR-4 的框架，这种写法是否能成功的加载类文件？其实是可以的，composer 注册的自动加载方法是在类被调用的时候根据类的命名空间去查找位置，这种写法对其没有影响。

### 5. 其他的特性

其他的一些特性我就不一一介绍了，有兴趣可以查看官方文档：

[http://php.net/manual/en/migration70.new-features.php](http://php.net/manual/en/migration70.new-features.php)

简要说几个：

- PHP 5.3 开始有了匿名函数，现在又有了匿名类了；
- define 现在可以定义常量数组；
- 闭包（ [Closure](http://php.net/manual/en/closure.call.php)）增加了一个 call 方法；
- 生成器（或者叫迭代器更合适）可以有一个最终返回值（return），也可以通过 `yield from` 的新语法进入一个另外一个生成器中（生成器委托）。

生成器的两个新特性（return 和 `yield from`）可以组合。具体的表象大家可以自行测试。PHP 7 现在已经到 RC5 了，最终的版本应该会很快到来。
