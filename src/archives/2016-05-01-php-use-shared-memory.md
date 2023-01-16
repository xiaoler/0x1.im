---
tags:
- php
date: "2016-05-01T12:00:00Z"
title: PHP 共享内存使用与信号控制
---

## 共享内存

共享内存的使用主要是为了能够在同一台机器不同的进程中共享一些数据，比如在多个 php-fpm 进程中共享当前进程的使用情况。这种通信也称为进程间通信（Inter-Process Communication），简称 IPC。

PHP 内置的 [shmop 扩展](http://php.net/manual/zh/book.shmop.php) (Shared Memory Operations) 提供了一系列共享内存操作的函数（可能是用的人不多吧，这一块儿的文档还没有中文翻译）。在 Linux 上，这些函数直接是通过调用 [shm*](https://beej.us/guide/bgipc/output/html/multipage/shm.html) 系列的函数实现，而 Winodows 上也通过对系统函数的封装实现了同样的调用。

主要函数：

- [shmop_close](http://php.net/manual/zh/function.shmop-close.php) — 关闭共享内存块
- [shmop_delete](http://php.net/manual/zh/function.shmop-delete.php) — 删除共享内存块
- [shmop_open](http://php.net/manual/zh/function.shmop-open.php) — 创建或打开共享内存块
- [shmop_read](http://php.net/manual/zh/function.shmop-read.php) — 从共享内存块中读取数据
- [shmop_size](http://php.net/manual/zh/function.shmop-size.php) — 获取共享内存块的大小
- [shmop_write](http://php.net/manual/zh/function.shmop-write.php) — 向共享内存块中写入数据

与此相关的还有一个很重要的函数：[ftok](http://php.net/manual/zh/function.ftok.php)，通过文件的 inode 信息（*nix 上通过 `stat` 或 `ls -i` 命令查看）创建 IPC 的唯一 key（文件/文件夹的 inode 是唯一的）。这个函数在 Linux 上也是直接调用同名的系统函数实现，Windows 上还是使用一些封装。

一个简单的计数例子：

``` php
<?php
# 创建一块共享内存
$shm_key = ftok(__FILE__, 't');
$shm_id = shmop_open($shm_key, 'c', 0644, 8);
# 读取并写入数据
$count = (int) shmop_read($shm_id, 0, 8) + 1;
shmop_write($shm_id, str_pad($count, 8, '0', STR_PAD_LEFT), 0);
// echo shmop_read($shm_id, 0, 8);
# 关闭内存块，并不会删除共享内存，只是清除 PHP 的资源
shmop_close($shm_id);
```

以上这段代码没执行一次计数加 1，而且数据是在不同进程之间共享的。也就是说除非手动删除这块内存使用，否则这个数据是不会重置的。

有个需要稍微注意的点：`shmop_open` 的第二个参数是个 flag，类似 fopen 的第二个参数，其取值有以前几个：

- "a" 只读访问；
- "c" 如果内存片段不存在，则创建，如果存在，则可读写；
- "w" 读写；
- "n" 创建新的内存片段，如果同样 key 的已存在，则会创建失败，这是为了安全使用共享内存考虑。

此外，由于使用的共享内存片段是固定长度的，在存储和读取的时候要计算好数据的长度，不然可能会写入失败或者读取空值。

## 信号控制

既然上面使用到了共享内存存储数据，就需要考虑是否有多个进程同时写入数据到共享内存的情况，是否需要避免冲突。如果是这样，就需要引入信号量进行控制。

PHP 也提供了类似的内置扩展 [sysvsem](http://php.net/manual/zh/book.sem.php)（这个扩展在 Windows 环境下没有，文档中将 `ftok` 函数也归到这个扩展中，但实际上 `ftok` 是在标准函数库中提供的，所以在 Windows 下也是可用的）。

在说信号量控制之前，先说另外一件有意思的事情：看官方文档你会发现这里同样也有共享内存操作的函数（`shm_*`），因为这其实是同一类别（或者说来自于同一作者）的三个扩展，还有一个是 sysvmsg（队列消息） 。函数的实现上稍有差别，但实际做的事情基本相同。这和上文的 shmop 扩展有什么区别呢？shmop 源码下的 `README` 文件有简单的说明：

> PHP already had a shared memory extension (sysvshm) written by Christian Cartus <cartus@atrior.de>, unfortunately this extension was designed with PHP only in mind and offers high level features which are extremely bothersome for basic SHM we had in mind.

简单说来：sysvshm 扩展提供的方法并不是原封不动的存储用户的数据，而是先使用 PHP 的变量序列化函数对参数进行序列化然后再进行存储。这就导致通过这些方法存储的数据无法和非 PHP 进程共享。不过这样也能存储更丰富的 PHP 数据类型，上文的扩展中 `shmop_write` 只能写入字符串。那么为什么 sysvshm 同样不支持 Windows 呢？因为其并没有引入封装了 `shm*` 系列函数的 `tsrm_win32.h` 的头文件。

引入信号控制之后的示例：

``` php
<?php

$id_key = ftok(__FILE__, 't');
$sem_id = sem_get($id_key);
# 请求信号控制权
if (sem_acquire($sem_id)) {
    $shm_id = shmop_open($id_key, 'c', 0644, 8);
    # 读取并写入数据
    $count = (int) shmop_read($shm_id, 0, 8) + 1;
    shmop_write($shm_id, str_pad($count, 8, '0', STR_PAD_LEFT), 0);
    // echo shmop_read($shm_id, 0, 8);
    # 关闭内存块
    shmop_close($shm_id);
    # 释放信号
    sem_release($sem_id);
}
```

但是本地想模拟实现写入冲突实际上是非常难的（考虑到计算机的执行速度）。在本地测试中，使用 `for` 循环操作时如果不使用 `shmop_close` 关闭资源会出现无法打开共享内存的错误警告。这应该是因为正在共享内存被上一次操作占用中还没有释放导致。
