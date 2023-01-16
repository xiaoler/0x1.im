---
tags:
- mobile
date: "2014-08-04T18:00:00Z"
title: MQTT(使用mosquitto做broker)做Android推送总结
---

> “读万卷书，行万里路”。我觉得这句话用在程序员的工作中就是：在网络中找一万篇资料，在实践中做一万种尝试。

<hr>
**2014-09-17:**
<br>
**在本文中，由于作者事先不了解，设计不合理，使每个设备采用prefix+CLIENT_ID的方式作为topic，导致需要给每个设备的topic单独推送，才产生了一些问题，特别是推送的时间上的问题，是PHP循环往每个topic写入消息的时间。希望读者不要被我误导。**
<br>
**给每个设备一个topic，实际上只在做点对点的推送的时候需要，如果没有这个需求，比如全局的推送，或者是几个大类的推送，完全可以设计一个更合理的topic规则，把主要的精力放在client和broker的维护上。**
<hr>

由于Android的开放性，Android的推送解决方案有很多。这其中最便于使用的，应该是google提供的GCM的方案，但是GCM是基于GMS服务的。由于国内的ROM大多干掉了GMS，或者是由于某些众所周知的原因，我们无法使用这个方案获得稳定的推送服务（这个Apple的APNS不同，APNS是IOS的设备上唯一可用的推送解决方案，也是稳定的方案）。基于这些原因，我们选择了自建推送服务的方式。

###1. 基础建设：
`纸上得来终觉浅 绝知此事要躬行`

1. **理论支撑** ：使用MQTT作为Android实现方案的原因源于一篇四年前的文章：[How to Implement Push Notifications for Android](http://tokudu.com/post/50024574938/how-to-implement-push-notifications-for-android "")；
2. **与Web管理的对接** ： 文章的作者同时也提供了PHP端的Client方案：[PhpMQTTClient](https://github.com/tokudu/PhpMQTTClient "")。
3. **服务端** ： 当然，这只是Web端的实现方案，至于后端需要使用的Broker，我们找到了[mosquitto](http://mosquitto.org/ "")。
4. **客户端** ： 在客户端中，我们使用的是来自IBM的wmqtt.jar的包：[wmqtt](http://mqtt.org/wiki/doku.php/ia92#wmqtt_ia92_java_utility "")

以上四个基本条件是我们具备了部署基于MQTT协议的Android的推送服务的基本条件。在最初的测试中，也没有遇到过太大的问题，测试顺利，于是我们在我们的应用和服务器之间部署了这套解决方案。

###2. 从0到1的变化：
`千里之行，始于足下`

由于事先并没有做推送的经验，在实际实施的过程中我们明白的几个基础的概念：

1. MQTT协议是一个即时通讯协议，推送实际上用到的只是它可以publish内容给topic的功能。topic是一个广播，所有订阅了这个topic的客户端都可以收到消息，为了实现针对设备的点对点推送，我们使用一个`prefix+Client ID`的方式给每个设备一个topic（如果没有这个需求，可以采取其它灵活的方式）。
2. 为了保证客户端能够实时的收到推送消息，即使是程序退出，客户端用于接收消息的service也需要处于保持状态。
3. 客户端与Broker、Broker和Web端的都是socket通信，推送的过程是用于Web端的client发布一个消息到Broker，Broker再将消息发送给当前其它连接到Broker的Client。所以能及时收到消息的只是现在和Broker保持连接状态的设备。
4. 服务端需要维护一个设备id列表，这个列表中的id必须都是唯一的（在前期，我们选择使用Android ID，这也带来了很多麻烦）。

基于以上几点，我们也可以发现以下的问题：

1. 不是所有的设备都能够及时的获取到推送的内容。
2. 客户端的service随时会有被各种安全软件干掉的风险。

通过前期的调研我们也发现，这些问题也是其它的第三方推送服务也都会遇到的问题。只要迈出第一步，让服务先work起来，其它的问题后续来优化。

###3. 从1到1万：
`不积硅步，无以致千里`

这个阶段主要是丰富推送的功能，解决一些前表面上的问题，我们做了以下的调整：

1. 在设备量到10000的时候，遇到了一个问题：推送10000个设备时间过长。这个问题很快得到了解决：这是由于没发送一个设备，都新建了一个从Web端到Broker的socket连接，这实际上是没有必要的，只要socket不断开所有Publish的工作都可以通过一个socket进行（这和APNS有些不一样的地方，在苹果的推送服务中，如果有一个设备id是无效的，整个推送都会断开），在前文提到过的Web端的库中，是有指定重连的操作的。

2. 丰富推送的内容。虽然推送的内容都是文本，但是文本的解析却是客户端维持的service来进行的，所以通过推送json的方式，实现了分别推送新闻、天气等富文本信息，并可以通过点击跳转到不同的页面。

3. 分地区推送的需求，这个实现方式经过一些迭代，最早是通过用户注册地来实现的，后来改为了用户安装应用时上报的地区的方式。

###4. 从1万到10万，必须做出的改变：
`行百里者半于九十`

数据量到达10万的时候，一些问题也逐渐凸显。

1. **Android ID重复的问题** ：
从网上查询来的资料，大部分都是使用Android的系统参数`ANDROID_ID`来做推送的。然而实践表明，这个参数并不是可靠的。生产环境中使用这个参数有极大的几率重复。由于一个相同的设备id连接到Broker的时候，之前的连接就会断开，这就会导致相同设备ID的设备只有一个会收到推送的消息。
在续的改造过程中，我们将设备ID换成了自己生成的一套唯一随机的ID。

2. **错误的id字符** ：
在查看MQTT的文档中，我们只注意到了设备ID需要在1~23位之间，却并没有注意到字符的限制。最初生成的id是base64的编码。在后面的测试中 ，总是发现推送到某些设备之后推送就断开了。经过检查发现，这是由于一些设备id中存在`+`符号导致的。
在Topic中，`+`和`#`会被当作通配符处理，导致出现 *Socket error* 的错误。
经过咨询，得到了以下的答案：
> Roger Light (roger.light) said :
Are you saying that clients that have a client id with '+' in are rejected? This shouldn't happen. If you mean that clients are publishing to a topic with '+' in, then you are correct that this is not allowed.

3. **从Broker中获取有用的信息** ：
生产环境中需要通过从Broker中获取一些有用的信息用于监控推送的状态。在Mosquitto的配置中，可以把`log_type`设定为`all`来记录全部的log。
通过订阅Mosquitto的一个特定的Topic，可以获取到一些推送的统计信息：
`mosquitto_sub -h 192.168.0.1 -p 1883 -t $SYS/broker/# -v`

4. **对于不在线的设备的处理（消息持久化）** ：
IOS和Windows Phone的设备的推送服务由于是系统提供的服务，只要设备网络在线，都是可以及时收到消息的，对于Android的自建推送服务来说，显然无法保证这一点。然而通过消息持久化的配置，也可以实现以下策略：
    - 应用处于打开状态，设备在线的时候，可以及时的收到消息
    - 应用退出、推送的Service在线的时候，可以收到推送消息
    - 应用和Service都被关闭，下次应用启动的时候，可以收到一天内的推送消息

    基于以上的策略，可以在客户端和Broker之间配置消息持久化和订阅的持久化。配置过程中需要在以下几个地方注意：
    1. Web端发送消息的时候，QoS设定为1
    2. Mosquitto的配置文件中，设定`persistence`为`true`
    3. 客户端`MQTT_CLEAN_START`(Clean session)为`false`，即不在服务启动时清理session，`MQTT_QUALITIES_OF_SERVICE`(QoS)与Web端保持一致;

5. **安全策略的控制** ：
在Mosquitto的后端配置中，可以使用限定客户端前缀，使用ACL权限控制，配置SSL连接的方式进行安全控制。

###5. 从10万到more，更多要做的事情...
`路遥知马力`

1. **推送时间的优化调整** ：
实际环境中，一台4G内存，4核CPU的服务器，发送20万台设备的消息大概需要4分钟左右，推送服务器并没有什么压力，这个时间取决于Web端将所有的消息Publish到Broker服务器的时间。可以通过多线程的方式进行优化。

3. **及时清理失效的设备id** ：
由于技术上的改造和迭代，一些设备ID在更新之后就不会再使用，服务端设定一定的策略来清理无效的设备ID可以减轻推送的压力。比如通过记录设备最后一次连接到Broker的时间，如果这个时间超出某个限制（一个月），就清理掉这个设备id。下次设备重新连入的时候还会再发送设备ID，这样即不会给服务器造成压力，也不会漏掉某些设备的推送。

4. **集群部署** ：
Mosquitto支持集群部署的配置（Bridges），其原理也是将一个消息Puhlish到集群中的其它服务器，然后由其它服务器来发送。
> A bridge is a way of connecting multiple MQTT brokers together.

5. **如何让客户端的service始终在线** ：
参考：
> 在android中，service被杀死后在没有被系统/安全软件禁止的条件下是能够自启动的，具体可自行网上搜索“android service onstartcommand START_STICKY”

###6. Mosquitto的配置优化
我们的部分配置：

``` sh
allow_zero_length_clientid false
persistent_client_expiration 1d
max_connections -1
persistence true
log_type all
connection_messages false
allow_anonymous false
```

###7. 资源/资料收集
1. Apache的`open source messaging and Integration Patterns server`，ActiceMQ，使用java编写，使用与管理很方便，目前发现的问题是内存使用量较大：[Apache ActiveMQ](http://activemq.apache.org/ "")
2. Eclipse的客户端库：[Eclipse Paho](http://git.eclipse.org/c/paho/org.eclipse.paho.mqtt.c.git/ "")
3. MQTT v3.1协议规范：[MQTT V3.1 Protocol Specification](http://public.dhe.ibm.com/software/dw/webservices/ws-mqtt/mqtt-v3r1.html "")
4. Mosquitto文档：[Mosquitto Documentation](http://mosquitto.org/documentation/ "")
