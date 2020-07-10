---
categories:
- php
date: "2015-11-08T18:22:26Z"
title: 两行代码给 PHP7 添加一个“非空合并”语法糖
---

我们知道从 PHP 5.3 起三元运算符 `?  :` 有一个写法简洁写法是这样的：

``` php
<?php
$a = 0;
$b = $a ?: 1; # $b === 1
```

这实际上相当于：

``` php
<?php
$a = 0;
$b = $a ? $a : 1; # $b === 1
```

在 PHP5 中，语法分析是这样写的：

``` c
|   expr '?' { zend_do_begin_qm_op(&$1, &$2 TSRMLS_CC); }
    expr ':' { zend_do_qm_true(&$4, &$2, &$5 TSRMLS_CC); }
    expr     { zend_do_qm_false(&$$, &$7, &$2, &$5 TSRMLS_CC); }
|   expr '?' ':' { zend_do_jmp_set(&$1, &$2, &$3 TSRMLS_CC); }
    expr     { zend_do_jmp_set_else(&$$, &$5, &$2, &$3 TSRMLS_CC); }
```

在 PHP7 中，由于 AST（抽象语法树）的引入，语法分析有些简化：

``` c
|   expr '?' expr ':' expr
        { $$ = zend_ast_create(ZEND_AST_CONDITIONAL, $1, $3, $5); }
|   expr '?' ':' expr
        { $$ = zend_ast_create(ZEND_AST_CONDITIONAL, $1, NULL, $4); }
```

PHP7 中语法分析之后都是写到 AST 的节点上。从上面可以看出，简化的 `?:` 和完整的三元表达式的区别就是节点中间的值为 NULL。

PHP7 添加了一个合并操作符（T_COALESCE），用于简化 `isset` 的条件判断：

``` php
<?php
$b = $a ?? 1;
```

它相当于：

``` php
<?php
$b = isset($a) ? $a : 1;
```

仅仅是 `isset` 判断，在 $a 为 0 等值时还是会返回 $b 的值还是为 0 。

这个操作符的语法分析语句是：

``` c
|   expr T_COALESCE expr
        { $$ = zend_ast_create(ZEND_AST_COALESCE, $1, $3); }
```

如果想将 `isset` 换成 `empty` 的效果，也就是说在变量不存在或转换成 boolean  后为 `false` 都赋予其他值，需要这样写：

``` php
<?php
$b = $a ?? 1 ?: 1;
```

显然上面的表达式中中间一部分稍微有些多余，那么做些简化呢？

现在我想添加一个语法 `??:` ，它的作用是对变量做 `empty` 的判断。也就是说达到上面 `$a ?? 1 ?: 1` 的效果：

``` php
<?php
$b = $a ??: 1;
```

改起来很简单，只需要将 `?:` 和 `??` 的分析合并一下（注意这里和上面所有的地方 `$1` `$2` 等符号的数字表示的都是变量或者常量出现的位置顺序）：

``` c
|   expr T_COALESCE ':' expr
        { $$ = zend_ast_create(ZEND_AST_CONDITIONAL,
            zend_ast_create(ZEND_AST_COALESCE, $1, $4), NULL, $4); }
```

仅仅只有两句，因为并没有在词法分析器中添加 Token，所以只能算是个语法糖。

重新编译一下之后就能看到效果啦。测试：

``` sh
$ /usr/local/php/bin/php -r "\$a = 0; echo \$a ?? 1, PHP_EOL;"
0
$ /usr/local/php/bin/php -r "\$a = 0; echo \$a ??: 1, PHP_EOL;"
1
```

Enjoy IT!
