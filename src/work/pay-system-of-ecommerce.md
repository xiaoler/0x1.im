# 电商平台支付系统设计概要

_created_at: 2023-01-27_

---

架构设计需要代入对业务的理解进行，需要结合业务特征进行设计。没有一蹴而就的完美架构、也没有适用于所有业务的通用模式，更多的是平衡与选择。

对于一个大型系统，通过外宣的文章很难了解其全貌，甚至就算是这类系统的掌舵人未必都能对系统的坑坑洼洼的问题清清楚楚，我们只能管中窥豹，学习可以学习的，至于其他学习不到的，一遍做、一遍摸索向前。

### 支付与电商支付

微信支付其实经常故障，并且基本上每次故障都会上热搜，影响也很大，从这个角度也能说明保持支付系统的稳定性是一件比较难的事情。不过近两年支付宝的故障听说的倒比较少，不知道是确实比较成熟稳定还是因为主要集中线上场景感知不明显的原因。

对于普通用户而言，一个支付系统最重要的是**资金安全**。虽然说没有支付牌照的业务无法直接管理用户的资产，但是错扣或者错账也会激起用户很大的反应。

电商支付跟独立的通用支付系统既有相似之处，又有一些区别，两者之间还有一些联系。一般电商没有支付牌照的情况下，会接入微信支付、支付宝、云闪付（境内）以及信用卡付款的渠道，如果是境外业务还需接入 Paypal 等渠道，而像抖音京东美团虽然自己持有牌照，但是还在推广阶段，所以自己的渠道和第三方渠道同在。

国内比较常见的订单和支付系统的绑定：

-   淘宝+支付宝
-   拼多多+微信支付
-   抖音（微信+支付宝+抖音支付）
-   京东（微信支付+信用卡+白条），京东早期可以货到付款给快递员，早期也是一大特色
-   美团（微信支付+美团支付，疯狂推荐绑卡）

淘宝和支付宝的关系无需多言，拼多多本身起步在微信生态内，和微信支付以及腾讯云都深度绑定着，不过拼多多现在也取得了自己的支付牌照在做绑卡推广阶段。

除了淘宝和支付宝的关系特殊，其他几大平台都要自建支付渠道，主要是费率问题，另外还有不想被别人卡脖子的原因。

### 架构设计点

支付系统的特性与要求：

1. 高稳定性需求
2. 数据安全和准确性十分重要

这两点要求同样适用于电商支付系统。这里会从以下几点阐述支付系统架构设计要点。

1. 订单前置处理
2. 聚合支付网关
3. 订单存储（OLTP）
4. 数据平台（OLAP）
5. 部署架构（异地多活、SET 化）

#### 1. 订单前置处理

不同于支付系统本身，电商订单会存在包括计算交易优惠、优惠券等前置处理。这一块儿整体上会配合上营销系统使用，在业务开发上会存在多做还是少做，谁来做的问题。

此外，交易订单除了商品本身的价格以外，可能还需要包含保险、运费等额外部分。

这一部分既可以由商品交易系统来处理，也可以由支付系统包圆处理，本质处理上还是商品订单号关联优惠券信息、支付订单信息、保险订单信息等。

#### 2. 聚合支付网关

这一层主要是跟代码层面的实现相关。对于没有自己的结算逻辑的业务，大部分业务实现也都在这一层。

这一层首先要解决的支付订单号的规则设计与转换，与交易单号的绑定。如果看过微信支付和支付宝的接口文档都知道，两者的下单接口中都有一个必传参数是 `out_trade_no`，即商户订单号，支付订单生成成功后会返回 `trade_no`，即支付订单号。前者会要求商户号下唯一，后者是微信支付或支付宝系统内唯一的。

这两个订单号在整个支付系统内十分重要，它们保证了下单请求和支付请求的幂等性，使得同一笔交易不会生成多个支付订单、同一笔支付订单不会多次发起扣款。

而对于一个内部的聚合支付网关，以上两个单号都并不是由自己的系统生成，这里就需要添加一层转换，用自己的单号替换对接的系统的 `trade_no`，保持自身系统对外提供的单号规则一致且唯一，不会因为不同的交易订单使用的不同支付渠道导致订单号长短不一或者产生重复单号。

这一层转换既然能解决问题，肯定也会带来新的问题，包括使得整个链路的单号核对逻辑变得复杂、理解成本提高。但这些都是必要的成本。

#### 3. 订单存储

存储可以分为 OLTP、OLAP 两部分，OLAP 这一块儿开源或商业产品层出不穷，整体上已经比较成熟，OLTP 主要还是 MySQL 或者其衍生品为主，虽然历史悠久，但是维护成本也相当高，主要还是因为 OLTP 作为交易原始数据十分重要不能有错漏。

按照拼多多 2021 年财报公布的数据，全年订单数量 610 亿，即每天将近 2 亿笔订单。而按照我之前做的团餐业务网关的数据看，每天也会产生 300 万笔以上的订单，这对存储本身其实是有相当大的压力的，原始订单数据一定需要分片存储。

订单数据分片有多种方式：

1. 按商户号分表
2. 按订单号取模分表
3. 按地区或者商户号分 SET，然后按照单号分表
4. 分库分表+历史数据转存（也可以是单号+时间**双维度分库分表**）

每一种方式都有其优缺点，选取哪种方式，要视业务量和业务特性来决定，数据量越多，需要设计的逻辑就越多。

除了要解决订单数量的问题，还需要解决数据备份的问题。要解决这个问题十分复杂。简单来说，微信支付采用了跨园区强一致的 [phxsql](https://github.com/Tencent/phxsql) 和的[PaxosStore](https://github.com/Tencent/paxosstore)，而支付宝是[对数据进行分类分区](https://help.aliyun.com/document_detail/417028.html)。此外腾讯云上有提供基于 [TDSQL](https://cloud.tencent.com/document/product/557/10521) 的分布式数据库，它的实现是基于 shardkey 自动水平拆分。

#### 4. 数据平台

OLAP 数据库会用于数据的聚合运算，包括结算、分析、初步对账等场景。

需要注意的是，OLAP 中的数据一般是从 OLTP 中异步抽取或者通过消息队列写入，并不能完全保证和原始数据完全一致。如果不一致，应该以原始数据为主。

同样，OLAP 内的数据一般不会用于数据的高精度计算。但也有例外，在对账单出具的过程中，可以通过先在 OLAP 中计算，如果数据不准确，再从 OLTP 的备库中计算的方式来减小 OLTP 的压力，提高运算效率。

#### 5. 部署架构

除了上文提到的数据存储的问题，电商支付系统的部署上和其他业务系统的并没有太大的分别。

传统的部署方式主要就是从同城双活演化到两地三中心的结构，同时按照上文提到的解决数据一致性的问题。

微信支付提到的 SET 化和支付宝说的单元化本质上是一个东西，不过在公开的资料里微信支付并没有提到如何解决无法按 SET 分区的逻辑和数据的处理问题，而支付宝提到的 RZone/GZone/CZone 给了这个问题一个答案。

另外一个比较重要的问题就是使用云服务还是传统的机房部署的方式。使用云服务意味着需要和云服务商深度协同，把半条命交给云服务商，带来的好处是可以把更多的精力放在解决 DevOps 层面的问题上。如果采用自有机房部署或者私有云部署的方式，维护成本增加，但是可控性增强。

微信支付还提供了[主备域名](https://pay.weixin.qq.com/wiki/doc/apiv3/Practices/chapter1_1_7.shtml)的让商户有一定的办法解决短时接入层的故障问题。

##### 两地三中心

为什么至少需要两地三中心？两个案例可以参考一下，第一个是 8·12 天津滨海新区爆炸事故的时候，据内部的说法是腾讯北方的数据中心距离此不到 10km，十分危险，第二个是 2015 年支付宝杭州、2019 年微信支付上海机房均出现过光缆被施工挖断导致大面积故障的情况。

所以我们可以理解为同城双活是一种乐观的备份，而异地是一种必要的悲观备份。

##### SET 化（单元化）

SET 化是一种需要后端服务开发和运维配合实施的结构。可以理解为将上文提到的数据分片的问题提到了业务入口处。

业务逻辑的部署本身横向复制问题并不大，主要要解决的问题还是会回到数据同步上，需要解决包括消息队列的日志同步、业务数据的最终一致性问题。

另外，如果每个 SET 都不做备份服务，那么单个 SET 故障的转移可能就需要手动处理，对整体业务的影响会小，但是受影响的单个用户影响不变。

##### CAP 问题

分布式系统的 [CAP](https://zh.m.wikipedia.org/zh-cn/CAP定理) 问题在以上说的异地多活的部署架构中是一定存在的，可以认为微信支付的方案是 CP（数据强一致、但数据故障导致的风险高），而支付宝是 AP（需要忍受同步的时间差）。这只是一个从结果反推的定义，并不一定全部准确。

### 业务逻辑问题点

除了架构上的设计点，在业务实现上，还有以下问题需要注意。

-   **商户订单号**（单个商户号下唯一）：商户订单号本身由商户生成，上文也有提到。需要单个商户号下唯一是硬性要求；
-   **支付订单号**（支付系统内全局唯一）：支付订单号同理；
-   **拆单合单**：一个商品可以拆分成多个方式组合支付或者多个订单可以同时支付是电商支付的特征和基本要求，在业务上需要处理 1:N 和 N:1 的逻辑；
-   **折扣溢价**（优惠、运费、保险）：上文同样有提到，即使无需处理商户优惠券，也可能需要处理支付系统本身为了推广而提供的优惠措施；
-   **支付结果查询与通知**：支付结果确认包含上下游漫长的链路逻辑，也和支付结果相关性很大；
-   **分账**（二清问题）：对于购物车内不同商户下的商品合单支付的情况，或者有存在子商户的情况，就需要考虑分账的问题，分账涉及二清问题，需要通过有支付牌照的支付服务商接口发起；
-   **退款处理**：退款时限、时限，退款带来的优惠券退回等问题，可能涉及到多个子系统内的通知；
-   **对账与平账**：这是最容易产生商户客服问题的环节之一，账单不平可能需要手动处理；
-   **单边账与复式记账**：复式记账表示用户侧记录一笔支付订单的同时，商户的账户产生一笔相应金额的收单记录，关于这个问题也可以参考[支付宝对单边账的解释](https://opendocs.alipay.com/support/01rfnu)；
-   **国密支持问题**：微信支付和支付宝有收到此要求。

### 参考资料：

-   [新一代金融核心突破之全分布式单元化技术架构](https://mp.weixin.qq.com/s/-pwgwEB97tG6eKsDje8pYQ)
-   [蚂蚁金服异地多活单元化架构下的微服务体系](https://developer.aliyun.com/article/665155)
-   [阿里云 - 金融分布式架构 SOFAStack](https://help.aliyun.com/document_detail/417028.html)
-   [微信 PaxosStore 简介](https://pic.huodongjia.com/ganhuodocs/2017-06-29/1498718050.42.pdf)
-   [PaxosStore 在微信支付业务的实践](https://www.infoq.cn/article/u897dqtxkttpaxwfprik)
-   [微信支付 - 跨城冗灾升级指引](https://pay.weixin.qq.com/wiki/doc/apiv3/Practices/chapter1_1_7.shtml)
-   [百亿级微信红包的高并发资金交易系统设计方案](https://www.infoq.cn/article/2017hongbao-weixin)
-   [微信支付数据库管理实践](https://pic.huodongjia.com/ganhuodocs/2017-07-15/1500104397.69.pdf)
