---
tags:
- server
date: "2014-09-20T14:32:55Z"
title: Linux(Ubuntu) iptables使用小记
---

### 1. 基础介绍

**netfilter/iptables** 是与2.4版本之后Linux内核集成的IP信息包过滤系统。iptables不是防火墙，只是定义过滤规则的工具，读取规则并发挥作用的是netfilter。
netfilter/iptables是内核集成的，不存在start/stop或者禁用的说法。可以用`iptables`命令创建过滤规则。（现在较新的内核中已经默认集成，无需单独安装）

项目主页：[http://www.netfilter.org/projects/iptables/](http://www.netfilter.org/projects/iptables/ "netfilter/iptables")

常用命令：

- 查看帮助：iptables -h
- 查看过滤规则：iptables -L [-n] [-v]
    <br>
    子命令：
    - -n：以数字的方式显示ip，它会将ip直接显示出来，如果不加-n，则会将ip反向解析成主机名
    - -v：显示详细信息

另外，在实际建立规则的过程中，iptables还需要和以下两个命令配合使用：

- 保存创建好的规则到文件：iptables-save
- 从文件中回复规则：iptables-restore


### 2. 规则参数

iptables创建规则的命令和参数相当繁杂，基本的规则形式如下：

``` sh
iptables [-t table] command chain [match] [-j target]
```

以下是各段命令主要参数的解释。

- **-t table，table有四个选项，默认为filter：**
    - filter：一般的过滤功能，默认的table
    - nat：用于NAT功能（端口映射，地址映射等）
    - mangle：用于对特定数据包的修改
    - raw：主要用于配合NOTRACK的响应
    - security：用户强制访问控制(MAC)网络规则

- **command，定义规则写入方式：**
    - -P：定义链的默认规则（所有其它规则都没有匹配到的数据包，将按照默认规则来执行）
    - -A：追加，在当前链的最后新增一个规则
    - -I num：插入，把当前规则插入为第几条
    - -R num：Replays替换/修改第几条规则
    - -D num：删除，明确指定删除第几条规则

- **chain，netfilter可以在五个位置进行过滤：**
    - PREROUTING (路由前)
    - INPUT (数据包流入口)
    - FORWARD (转发管卡)
    - OUTPUT(数据包出口)
    - POSTROUTING（路由后）

- **match：匹配规则，常用的规则有以下几种：**
    - -p：用于匹配协议的（这里的协议通常有3种，TCP/UDP/ICMP，逗号分隔多个协议，ALL是确实设置，`!`表示反向匹配）
    - -s：匹配源地址ip或ip段（IP或IP/MASK，`!`表示反向匹配）
    - -d：匹配信息包的目的地IP地址（`!`表示反向匹配）
    - -i：流入网卡
    - -o：流出网卡
    - --dport：目标端口
    - --sport：源端口
    - --state：连接状态
    - -m：显式扩展以上的规则（即可以匹配多个状态、端口等）

- **target：进行的操作/响应，常见的有以下几种：**
    - DROP（悄悄丢弃）
    - REJECT（明示拒绝）
    - ACCEPT（接受）
    - MASQUERADE（源地址伪装）
    - REDIRECT（重定向）
    - MARK（打防火墙标记的）
    - RETURN（返回）


### 3. 实际运用

在生产环境的配置中，可以先通过`netstat -tunlp`命令查看一下当前服务器上有哪些端口是正在监听或使用的已经使用的是tcp还是udp的协议，然后根据使用情况来进行配置。

1. 允许已经建立的连接接收数据（状态为ESTABLISHED或RELATED的连接）：

``` sh
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
```

1. eth0开放端口22（SSH的默认端口）：

``` sh
iptables -A INPUT -p tcp -i eth0 --dport 22 -j ACCEPT
# 如果有多个网卡，可以选择开放，比如只开放局域网网卡允许ssh登录
```

1. 开放其它需要的服务端口，比如80端口：

``` sh
iptables -A INPUT -p tcp --dport 80 -j ACCEPT

# 或者是开放多个端口：
iptables -A INPUT -p tcp -m multiport --source-port 8081,8082,8083 -j ACCEPT

#或者开放一个端口段：
iptables -A INPUT -p tcp --dport 8084:8090 -j ACCEPT
```

1. 如果需要接受ping

``` sh
# echo-request
iptables -A INPUT -p icmp -m icmp --icmp-type 8 -j ACCEPT

# echo-reply
iptables -A INPUT -p icmp -m icmp --icmp-type 0 -j ACCEPT
```

1. 最后执行全局策略：

``` sh
iptables -P INPUT DROP
iptables -P OUTPUT ACCEPT
iptables -P FORWARD DROP
```

### 4. 保存和恢复规则

通过命令设定的规则只会在当前系统运行的情况下生效，需要通过一定的配置达到在每次开机时自动启动规则。

保存当前iptables的规则到文件中：

``` sh
iptables-save > /etc/iptables.up.rules

# 以下语句添加到/etc/rc.local，在系统重启时恢复规则：
/sbin/iptables-restore < /etc/iptables.up.rules
```

### 5. 如果需要清除所有规则

**当Chain INPUT (policy DROP)时执行/sbin/iptables -F后，你将和服务器断开连接，
所以在清空所有规则前把policy DROP该为INPUT，防止悲剧发生。**

``` sh
iptables -P INPUT ACCEPT

# 清空所有规则
iptables -F
iptables -X

# 计数器置0
iptables -Z
```

### 6. Ubuntu集成的工具：ufw

ufw为了使Ubuntu的netfilter更易于使用和管理而发行的，是由Canonical公司开发的，采用python编写。

ufw的实质也是通过创建iptables规则的方式实现的，只是简化了参数的格式。如果需要对服务器做一些过滤规则，我建议还是直接使用iptables来创建过滤。

ufw使用文档：[http://wiki.ubuntu.org.cn/Ufw使用指南](http://wiki.ubuntu.org.cn/Ufw使用指南)
