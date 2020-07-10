---
tags:
- php
date: "2017-09-23T08:00:00Z"
title: 使用 deployer 部署项目
---

我一直都认为部署是持续集成或者 DevOps 中最重要的一个环节。受限于公司的网络环境，一直在这一块儿能做的事情很少。最近用腾讯云的机器做一些事情，才有机会好好研究一下 [deployer](https://github.com/deployphp/deployer) 这个工具。

### 简介

deployer 主要的功能是创建一系列的工作流来执行部署任务。通过 `task` 函数定义一系列的操作，然后按照顺序执行，完成代码部署前后的工作。你可以自己定义任务，也可以直接使用 deployer 提供的一些已经写好的方法，deployer 称这些封装为 `recipe`。

举个例子 task 定义的例子：

``` php
task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'rsync',
    'deploy:shared',
    'deploy:writable',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success',
]);
```

在名为 `deploy` 的任务中定义了一系列的操作，这样执行 `dep deployer` 的时候，deployer 会按照顺序执行任务，完成部署工作。这一切执行动作本身是基于 ssh 的。

从上面的例子中也可以看出，虽然 deployer 本身主要是针对 git 项目的发布，但也可以通过 rsync 的方式同步代码。而[名为 rsync 的 recipe](https://github.com/deployphp/recipes/blob/master/recipe/rsync.php)具体的内容在github上也可以找到。

使用 deployer 进行代码部署是非常方便编写指令的，还有一个好处就是你可以在任何一次部署结束之后使用 `rollback` 命令进行回滚等操作。

### 起步

创建一个基于 deployer 的项目部署配置很简单，在安装完 deployer 后直接在目录中执行 `dep init` 即可。deployer 本身已经提供了一些知名开源项目的部署配置供选择，如果想高度自定义，选择通用配置（common）即可。

执行完成后会在当前目录中生成一个 `deploy.php` 的文件。 配置文件中最常见的两个函数就是 `set` 和 `task`，`task` 上文已经有过介绍。`set` 函数是用来配置参数用的。它既可以用来设置新的配置项，也可以替换默认的设置。

举例：

``` php
set('allow_anonymous_stats', false);
```

在执行 init 的时候，deployer 会询问你时候允许发送统计信息，如果允许，则会像https://deployer.org/api/stats 这个地址发送你的 php 版本，系统等信息。可以通过上文的设置禁用统计。

通过 `host` 函数可以指定需要部署的机器，如果你已经配置好目标机器的 ssh 访问，则无需重复配置，但也可以在配置中指定密码或者认证 key 文件。然后通过设置 `deploy_path` 配置代码部署目标位置，比如：

``` php
host('my-vm')->set('deploy_path', '/var/www/my-web');
```

deployer 本身提供了一些通用的任务比如 `deploy:prepare`，这项任务会检查目标机器上的代码部署目录是否存在，如果不存在则会创建。

### 配置

deployer 在服务器上创建的目录主要由两个 `releases` 和 `current`，其中 `current` 是软链到 `releases` 下当前版本的目录的，这也是可以随时回滚的原因。

此外，有些文件或者目录本身是需要再多个版本之间共享的，比如用户上传图片的目录，可以通过 `shared_files`、`shared_dirs` 来配置。上传文件的目录也需要是对 php 或者 nginx 允许用户是可写的，可以通过 `writable_dirs` 来设置（这项配置也可以用来首次部署是创建目录）。而 `writable_mode` 则可以指定使用哪种方式来设置目录可写（chown、chmod 或者 chgrp）。

例如针对 laravel/lumen 的 rsync 方式的发布，我会设置同步时忽略 storage 目录，主要设置如下：

``` php
set('rsync', [
    // 不同步的目录
    'exclude' => [
        '.git',
        'storage',
    ],
    'options' => ['delete'],
    'timeout' => 3600,
]);

// 服务器上保存的版本数
set('keep_releases', 5);
// 共享 storage 目录
set('shared_dirs', ['storage']);
// 首次发布时会创建这些目录，并被设置为可写状态
set('writable_dirs', [
    'storage',
    'storage/app',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/views',
    'storage/logs',
]);
```

设置完这些选项，将上文中的 task 放到文件最后保存。最后执行 `dep deploy` 命令，就可以讲代码同步到目标服务器上。

更多的设置选项，比如如何使用 sudo 来执行命令，或者如何设置 js 的构建、在服务器上执行命令等操作，可以直接到[官网文档](https://deployer.org/docs/configuration)中查看。
