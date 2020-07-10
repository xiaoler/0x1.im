---
categories:
- php
date: "2018-01-06T14:04:11Z"
title: 基于 Redis 的 Pub/Sub 实现 Websocket 推送
---

### 背景

微信小程序的生态越来越完善，而在技术上，小程序目前只支持两种通信协议：HTTPS 和 WebSocket，所以在需要使用双工通信的时候，除了 WebSocket 也没有别的选择。最近恰好有个这样的需求，所以我也花了点时间研究了一下。

项目上实现的目标就是小程序和服务器建立 WebSocket 建立连接，在服务端收到来自于第三方的事件推送之后，主动推送给客户端而不是靠客户端轮询来获取消息（这里就不介绍 WebSocket 的基础知识了）。因为我们项目组成员大多都是 PHP 开发，所以也是考虑用 PHP 来实现。

### 实现

这里会遇到的问题就是，用 PHP 的库来开一个 WebSocket 服务端口的时候，由于要保持连接，并接收的后续连接，所以服务本身是处于监听端口的状态。而如果程序同时要订阅来自 Redis 的事件，同样也需要监听 Redis 的消息。那么要如何实现呢？这里先直接抛出我所使用的两个库：

- [Ratchet](https://github.com/ratchetphp/Ratchet)：一个 PHP 实现的异步 WebSocket 服务器
- [predis-async](https://github.com/nrk/predis-async): PHP 实现的异步 Redis 客户端

仔细看上面的描述，除了 *PHP 实现* 外，他们还有一个相同的关键词：异步。没错，这里的异步和 node.js 描述的异步差不多是同一回事。实现异步的基础就是：EventLoop。这里我也不具体描述 EventLoop 到底是怎么一回事儿。其实 Ratchet 提供的 examples 里也有一个借用 zeromq 实现 push的例子：

```php
$loop   = React\EventLoop\Factory::create();
// Listen for the web server to make a ZeroMQ push after an ajax request
$context = new React\ZMQ\Context($loop);
$pusher = new MyApp\Pusher;

$pull = $context->getSocket(ZMQ::SOCKET_PULL);
$pull->bind('tcp://127.0.0.1:5555'); // Binding to 127.0.0.1 means the only client that can connect is itself
$pull->on('message', array($pusher, 'onBlogEntry'));

// Set up our WebSocket server for clients wanting real-time updates
$webSock = new React\Socket\Server('0.0.0.0:8080', $loop); // Binding to 0.0.0.0 means remotes can connect
```

上面的例子中可以看出来，实现主动推送的核心点也是在于共享了同一个 loop 实例。

同样，如果要实现基于 Redis Pub/Sub 的推送，也是要利用这一点。上面这两个库使用的 EventLoop 库恰好是同一个：[reactphp/event-loop](https://github.com/reactphp/event-loop)，并且也是上述基于 zeromq 实现推送的 EventLoop 库。

实现上和上面的代码示例类似：

```php
<?php

$loop = LoopFactory::create();
$redis = new Predis\Async\Client('tcp://127.0.0.1:6379', $loop);

// 自己实现一个 WebSocket 的方法实现类
$handler = new Handler();

$redis->connect(function ($client) use ($handler, $handler) {
    echo 'Connected to Redis, now listening for incoming messages...', PHP_EOL;

    $client->pubSubLoop(['psubscribe' => 'pub.*'], function ($event) use ($handler) {
        // 在 Handler 类中 onOpen 方法被调用时，注意存储下当前连接信息。
        // 在 Handler 类中自己实现一个方法用于接收事件消息后的调用，就可以根据连接信息来源主动 push 了
        $handler->onPublishEntry($event);
    });
});

// Run the server application through the WebSocket protocol on port 8090
$app = new RatchetApp('0.0.0.0', 8099, '0.0.0.0', $loop);
// Set a route
$app->route('/handler', $handler, ['*']);
```

通过上面的实现，就可以监听 Pub 到 `pub.*` 的消息并主动推送给通过 WebSocket 连接到后端的客户端了。

### 其他

Ratchet 的 WebSocket hander  一旦被实例化，在所有新进入的连接中都是共享的，所以一定要处理好各个连接之间的身份认证、数据隔离等关系。新连接的认证可以在 `onOpen` 方法被调用时处理。

由于小程序也不支持 Cookie，我推荐使用 [JWT](https://jwt.io/) 做身份认证。虽然 JWT 本身并不完美，但是一个不错的解决问题的方式。

本文只是提供一种 PHP 实现的思路，实际并没有经过大量连接的考验。同样也有很多其他的思路来解决这个问题，比如基于 openresty 的信号量或者 golang 的线程机制来实现。
