---
categories:
- php
date: "2015-11-07T13:30:22Z"
title: max/min 函数（PHP）的一个小 BUG
---

先直接来看一段展示：

``` sh
# Psy Shell v0.3.3 (PHP 5.5.30 — cli) by Justin Hileman
>>> ceil(-0.5)
=> -0.0
>>> max(-0.0, 0)
=> 0.0
>>> max(ceil(-0.5), 0)
=> -0.0
```

上面的演示中，`ceil` 函数返回的是 `-0.0`，`max` 在将 `ceil` 函数调用的结果作为参数传入的时候，返回的也是一个 `-0.0`。

如果给 ceil 的结果赋值给变量，还是能得到 `-0.0` 的结果：

``` sh
>>> $a = ceil(-0.5)
=> -0.0
>>> max($a, 0)
=> -0.0
```

下面就来一一分析是哪些原因导致了这些结果的产生。

## ceil 会返回 -0.0

首先我们来看一下为什么 `ceil` 函数会返回 `-0.0`。

ceil 函数的实现在 $PHP-SRC/ext/stardands/math.c （$PHP-SRC 指的是 PHP 解释器源码根目录）中，为了展示清楚我去掉了一些细节：

``` c
PHP_FUNCTION(ceil)
{
	...
	if (Z_TYPE_PP(value) == IS_DOUBLE) {
		RETURN_DOUBLE(ceil(Z_DVAL_PP(value)));
	} else if (Z_TYPE_PP(value) == IS_LONG) {
		convert_to_double_ex(value);
		RETURN_DOUBLE(Z_DVAL_PP(value));
	}
	...
}
```

从这里可以看出来 ceil 函数做了两个事情：

1. 如果参数类型是 double，则直接调用 C 语言的 ceil 函数并返回执行结果；
2. 如果参数类型是 long，则转换成 double 然后直接返回。

所以 ceil 返回 -0.0 这个本身的原因还在于 C。写个函数测试一下：

``` c
#include <stdio.h>
#include <math.h>

int main(int argc, char const *argv[])
{
    printf("%f\n", ceil(-0.5));
    return 0;
}
```

以上代码在我机器上的执行结果是 `-0.000000`。至于为什么会是这个结果，这是 C 语言的问题，这里也不细说，有兴趣的可以看这里：[http://www.wikiwand.com/zh/-0](http://www.wikiwand.com/zh/-0)。

## 不能直接传入 -0.0

接下来讨论一下为什么执行 `max(-0.0, 0)` 却得不到相同的结果。

用 [vld](https://github.com/derickr/vld) 扩展查看了一下只有以上一行代码的 php 文件看一下结果：

```
line     #* E I O op                    fetch      ext  return  operands
--------------------------------------------------------------------------
   3     0  E >   EXT_STMT
         1        EXT_FCALL_BEGIN
         2        SEND_VAL                                      0
         3        SEND_VAL                                      0
         4        DO_FCALL                           2          'max'
         5        EXT_FCALL_END
   5     6      > RETURN                                        1
```

注意到需要为 2 的 [SEND_VAL](http://php.net/manual/en/internals2.opcodes.send-val.php)  操作，送进去的值是 0。也就说在词法分析阶段之后 `-0.0` 就被转换成 0 了。如何转换的呢？下面我们简单的分析一下的过程。

PHP 的词法分析器由 [re2c](http://re2c.org/) 生成，语法分析器则是由 [Bison](https://www.gnu.org/software/bison/) 生成。在 zend\_language\_scanner.l （$PHP-SRC/Zend 目录下）中我们可以找到以下的语句：

``` c
LNUM	[0-9]+
DNUM	([0-9]*"."[0-9]+)|([0-9]+"."[0-9]*)
EXPONENT_DNUM	(({LNUM}|{DNUM})[eE][+-]?{LNUM})
...
...
<ST_IN_SCRIPTING>{DNUM}|{EXPONENT_DNUM} {
	zendlval->value.dval = zend_strtod(yytext, NULL);
	zendlval->type = IS_DOUBLE;
	return T_DNUMBER;
}
```

`LNUM` 和 `DNUM` 后面都是简单的正则表达式。虽然在词法扫描中 `0.0` 会被标记成 DNUM，并且位于 zend\_strtod.c 的 `zend_strtod` 函数中的也有对于 加减号的处理，但是 `-` 符号并不和 DNUM 匹配（那既然这样为什么 `zend_strtod` 还要处理加减号呢？因为这个函数不只是在这里使用的）。这里最终返回一个 `T_DNUMBER` 的标记。

再看 zend\_language\_parser.y 中：

``` c
common_scalar:
		T_LNUMBER 					{ $$ = $1; }
	|	T_DNUMBER 					{ $$ = $1; }
	...
;

static_scalar: /* compile-time evaluated scalars */
		common_scalar		{ $$ = $1; }
	...
	|	'+' static_scalar { ZVAL_LONG(&$1.u.constant, 0); add_function(&$2.u.constant, &$1.u.constant, &$2.u.constant TSRMLS_CC); $$ = $2; }
	|	'-' static_scalar { ZVAL_LONG(&$1.u.constant, 0); sub_function(&$2.u.constant, &$1.u.constant, &$2.u.constant TSRMLS_CC); $$ = $2; }
    ...
;
```

同样我们去掉了一些细节，简单描述一下上面的语法分析的处理流程：

1. `T_DNUMBER` 是一个 common\_scalar 语句；
2. common_scalar  是一个 static\_scalar 语句；
3. static\_scalar 语句前面存在减号时，将操作数 1 （op1）设定为 值为 0 的 `ZVAL_LONG` ，然后调用 `sub_function`  函数处理两个操作数。

`sub_function` 函数的实现位于 zend_operators.c 中，所做的操作很简单，就是用 op1 的值减去 op2 的值，所以就不会存在传入 `-0.0` 的情况。

## 直接调用或赋值给变量

既然如此，为什么直接使用函数调用做参数或者赋值给变量的方式又可以传入呢？闲来看一下 zend\_language\_parser.y 中对于函数参数的分析语句：

``` c
function_call_parameter_list:
		'(' ')'	{ Z_LVAL($$.u.constant) = 0; }
	|	'(' non_empty_function_call_parameter_list ')'	{ $$ = $2; }
	|	'(' yield_expr ')'	{ Z_LVAL($$.u.constant) = 1; zend_do_pass_param(&$2, ZEND_SEND_VAL, Z_LVAL($$.u.constant) TSRMLS_CC); }
;

non_empty_function_call_parameter_list:
		expr_without_variable	{ Z_LVAL($$.u.constant) = 1;  zend_do_pass_param(&$1, ZEND_SEND_VAL, Z_LVAL($$.u.constant) TSRMLS_CC); }
	|	variable				{ Z_LVAL($$.u.constant) = 1;  zend_do_pass_param(&$1, ZEND_SEND_VAR, Z_LVAL($$.u.constant) TSRMLS_CC); }
	|	'&' w_variable 				{ Z_LVAL($$.u.constant) = 1;  zend_do_pass_param(&$2, ZEND_SEND_REF, Z_LVAL($$.u.constant) TSRMLS_CC); }
...
;
```

为了直观 `non_empty_function_call_parameter_list` 语句块后面我隐去了三行。后面三行的处理逻辑实际上是递归调用，并不影响我们分析。

通过 `function_call_parameter_list` 可以看出函数的参数基本情况包括三种：

1. 没有参数
2. 有参数列表
3. 有 yield 表达式

这里我们只需要关注有参数列表的情况，参数列表中的每个参数也分三种情况：

1. 不包含变量的表达式
2. 变量
3. 引用变量

上文中我们提到的直接传入 `-0.0` 时对应的是第一种情况，传入赋值后的 `$a` 对应的是第二种情况。参数最终都会交给 `zend_do_pass_param` 函数（zend_compile.c）去处理。

那么传入 `ceil(-0.5)` 作为参数呢？实际上也是对应第二种情况，这个问题单独分析起来也比较复杂，省事儿一点我们直接用 vld 看一下执行 `max(ceil(-0.5), 0)`过程：

```
line     #* E I O op                   fetch       ext  return  operands
--------------------------------------------------------------------------
   5     0  E >   EXT_STMT
         1        EXT_FCALL_BEGIN
         2        EXT_FCALL_BEGIN
         3        SEND_VAL                                      -0.5
         4        DO_FCALL                           1  $0      'ceil'
         5        EXT_FCALL_END
         6        SEND_VAR_NO_REF                    6          $0
         7        SEND_VAL                                      0
         8        DO_FCALL                           2          'max'
         9        EXT_FCALL_END
   6    10      > RETURN                                        1
```

序号为 4 的语句中，ceil 的执行结果是赋值给一个 $0 的变量，而在序号为 6 的执行中，执行的是 `SEND_VAR_NO_REF` 的语句，调用的 $0。`SEND_VAR_NO_REF` 的 Opcode 是在何时被指定的呢？也是在 `zend_do_pass_param` 函数中：

``` c
if (op == ZEND_SEND_VAR && zend_is_function_or_method_call(param)) {
    /* Method call */
    op = ZEND_SEND_VAR_NO_REF;
    ...
}
```

函数执行过程中使用 `zend_parse_parameters` 函数（zend_API.c）来获取参数。从参数的存储到获取中间还有很多处理过程，这里不再一一详解。但是需要知道一件事：函数在使用变量作为参数的时候是直接从已经存储的变量列表中读取的，没有经过过滤处理，所以变量 `$a` 或 `ceil(-0.5)` 才可以直接将 `-0.0` 传递给 `max` 函数使用。

## 最后的原因

既然以上都知道了，那还剩一个问题：为什么在 `-0.0` 和 `0` 中 `max` 函数会选择前者？

其实这个问题很简单，看一下 `max` 函数的实现（$PHP-SRC/ext/standard/array.c）就知道真的就是在两值相等时选择了前者：

``` c
max = args[0];

for (i = 1; i < argc; i++) {
    is_smaller_or_equal_function(&result, *args[i], *max TSRMLS_CC);
    if (Z_LVAL(result) == 0) {
        max = args[i];
    }
}
```

同样，`min` 函数也存在这个问题，区别就是 `min` 函数是调用的 `is_smaller_function` 来比较两个数值，两个值相等的时候返回前者。

所以要解决这个问题也很简单，只需要调换一下参数顺序即可：

``` sh
# Psy Shell v0.3.3 (PHP 5.5.30 — cli) by Justin Hileman
>>> max(0, ceil(-0.5))
=> 0
```

## 后话

本文仅仅是管中窥豹，从一个小 “bug” 入口简单的梳理一下各个环节的处理过程，如果想要更深入的理解 PHP 的执行过程，还需要大量的精力和知识储备。

分析 PHP 源码的执行过程不仅是为了对 PHP 有更深刻的理解，也能帮助我们了解一门语言从代码到执行结果中间的各个环节和实现。

关于词法分析器与语法分析器，这里讲的并不多，希望后面有机会的话能够再深入探讨。re2c 的规则比较简单，关于 Bison，则有很多相关的书籍。

文中有粗浅的疏解，也留下有问题，如有错误，欢迎指正。

Stay foolish,stay humble; Keep questioning,keep learning.
