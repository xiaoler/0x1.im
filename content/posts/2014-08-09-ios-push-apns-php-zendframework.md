---
categories:
- mobile
date: "2014-08-09T15:50:00Z"
title: iOS推送（Apple Push Notification Service）部署总结
---
### 1. 基础部署

- 后端：PHP
- 依赖：[zendframework/zendservice-apple-apns](https://github.com/zendframework/ZendService_Apple_Apns)
- 本文中使用的PHP框架：[laravel](http://http://laravel.com/)

### 2. 实现目标

1. 实现中等规模批量设备的推送（1w设备以上），并且有可扩展的余地（十万到百万级）
2. 推送的整体发送时间可控（半小时内，最好**数分钟**内）
3. 保证推送的到达率能够达到**90%**以上
4. 可以从设备列表中及时剔除无效的设备，并能够从APNs的服务器中及时获取反馈
5. 可以获取到已经卸载的设备信息

### 3. 基础知识

**关于APNs，我们首选需要知道：**

1. iOS的推送是通过socket链接将详细发送到苹果的服务器，然后由苹果像设备推送来实现的；
2. 在服务的我们需要自己维护一个token的列表用于记录要向哪些设备发送推送；
3. token是由系统提供的，但是有可能会失效，用户也可能会已经卸载应用；
4. 苹果提供有获取feedback的服务器接口用于获取已经卸载应用的的设备token；

**在实际操作中发现关于推送的部分：**

1. 向一个已经打开的socket连接写入token和推送消息时，如果有一个token是无效的，socket会断开；
2. 已经卸载应用的设备token不算是无效的token（不会导致连接断开），但是像它发送消息是没有意义的且会增加负担；
3. socket断开之前会向连接中写入一个错误信息，可以捕捉错误的方式知道socket是在什么时候断开的，但是这个消息会有延时，无法保证100%接收到；
4. 错误信息不会直接返回是哪个token，而是返回发送时设定的**identifier**；
5. socket也会存在其它异常断开的情况；
6. iOS6以下的设备无法通过feedback的接口获取到已卸载的token（测试结果，没有在文档中验证）；
7. feedback的接口取到的是上次推送的过程中出现的已卸载应用的设备token，而且获取一次之后就会清空；
8. 如果想获得卸载应用的feedback，该应用不能是卸载的设备上的仅有的推送应用（如果是最后一个，设备和苹果的推送服务连接会断开）；

### 4. 实际部署

实际部署中，需要对使用的库做出一些改动。

1.**在ZendService\Apple\Apns\Client\Message中增加一个方法用于每次推送结束之后获取反馈：**

```php
<?php
/**
 * Get Response
 *
 * @return ZendService\Apple\Apns\Response\Message
 */
public function getResponse(){
    if (!$this->isConnected()) {
        throw new Exception\RuntimeException('You must first open the connection by calling open()');
    }
    return new MessageResponse($this->read());
}
?>
```

2.**修改ZendService\Apple\Apns\Client\Feedback中的feedback方法，增加一个判断：**

```php
<?php
if (strlen($token) == 38) {
    $tokens[] = new FeedbackResponse($token);
}
?>
```

这是因为在实际测试中发现，从feedback中读取到的信息除了38位的反馈，末尾还有一个1位的字符会导致产生异常。

3.**具体的实现见附录中的代码**

### 5. 参考文档
1. [Local and Push Notification Programming Guide](https://developer.apple.com/library/mac/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/CommunicatingWIthAPS.html#//apple_ref/doc/uid/TP40008194-CH101-SW1)
[PDF版本](https://developer.apple.com/library/mac/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/RemoteNotificationsPG.pdf)
2. [ZendService\AppleApns](http://framework.zend.com/manual/2.1/en/modules/zendservice.apple.apns.html)
这个示例代码中有两个小错误，在附录的实现中已经修正
3. 推送库安装[ZendService_Apple_Apns](https://github.com/zendframework/ZendService_Apple_Apns)
4. [The Problem With Apples Push Notification Service](http://redth.codes/the-problem-with-apples-push-notification-ser/)

### 附录

推送类实现：

```php
<?php
require_once __DIR__.'/../../vendor/autoload.php';

use ZendService\Apple\Apns\Client\Message as Client;
use ZendService\Apple\Apns\Message;
use ZendService\Apple\Apns\Message\Alert;
use ZendService\Apple\Apns\Response\Message as Response;
use ZendService\Apple\Apns\Client\Feedback as Feedback;
use ZendService\Apple\Apns\Exception\RuntimeException;

class ApnsController extends BaseController {
    //保存设备信息集合
    private $deviceCollection = array();
    //不可用的设备id集合
    private $invalidCollection = array();
    //推送消息
    private $messageAlert = '';
    private $messageBadge = 1;
    private $messageSound = 'default';

    public function push(){
        $this->messageAlert = date('Y-m-d H:i:s');
        $this->deviceCollection = array(
            '4191747e d62960e8 62afd700 bba42d23 cd0203be 6389688d 307ac833 7db66c34',
            '1b0b80f4 beed1d2a e2b3f45a cce243e7 74d95455 17402870 e925edc4 dcedbfbe',
            );
        $this->_sendMessage();
    }

    //获取卸载token
    private function _getFeedback(){
        $client = new Feedback();
        $client->open(Client::PRODUCTION_URI, '/path/to/cer.pem');
        $responses = $client->feedback();
        $client->close();
        foreach ($responses as $response) {
            //处理已经卸载的设备token
        }
    }

    //使用zendservice-apple-apns做推送的接口
    private function _sendMessage(){
        //新建连接
        $client = new Client();
        $client->open(Client::PRODUCTION_URI, '/path/to/cer.pem');

        foreach ($this->deviceCollection as $key => $deviceToken) {
            //实测等待2ms秒左右基本上可以获得稳定的反馈信息 数据量大的时候可以不等待
            usleep(2000);
            $message = $this->_packMessage($key,str_replace(' ','',$deviceToken));
            try {
                $response = $client->send($message);
                if ($response->getCode() != Response::RESULT_OK) {
                    break;
                }
            } catch (RuntimeException $e) {
                $client->close();
                //推送断开之后获取上次推送的feedback
                $this->_getFeedback();
                $this->_invalidCollectionHandle();
                $this->deviceCollection = array_slice($this->deviceCollection,$key);
                return $this->_sendMessage();
            }
        }
        //如果没有捕捉错误,0.5s之后再捕捉一次
        if ($response->getCode() == Response::RESULT_OK) {
            usleep(500000);
            $response = $client->getResponse();
        }
        $client->close();
        //推送断开之后获取上次推送的feedback
        $this->_getFeedback();
        $this->_responseHandle($response);
    }

    //消息和token打包
    private function _packMessage($id,$deviceToken){
        $message = new Message();
        $message->setId($id);
        $message->setExpire(86400);
        $message->setToken($deviceToken);
        $message->setBadge($this->messageBadge);
        $message->setSound($this->messageSound);
        // simple alert:
        $message->setAlert($this->messageAlert);
        return $message;
    }

    //处理异常
    private function _responseHandle($response){
        if ($response->getCode() != Response::RESULT_OK) {
            //先记录完整的错误log再处理
            $this->_errorLog($response);

            switch ($response->getCode()) {
                case Response::RESULT_PROCESSING_ERROR:
                    // you may want to retry
                    break;
                case Response::RESULT_MISSING_TOKEN:
                    // you were missing a token
                    break;
                case Response::RESULT_MISSING_TOPIC:
                    // you are missing a message id
                    break;
                case Response::RESULT_MISSING_PAYLOAD:
                    // you need to send a payload
                    break;
                case Response::RESULT_INVALID_TOKEN_SIZE:
                    // the token provided was not of the proper size
                    break;
                case Response::RESULT_INVALID_TOPIC_SIZE:
                    // the topic was too long
                    break;
                case Response::RESULT_INVALID_PAYLOAD_SIZE:
                    // the payload was too large
                    break;
                case Response::RESULT_INVALID_TOKEN:
                    // the token was invalid; remove it from your system
                    array_push($this->invalidCollection,$this->deviceCollection[$response->getId()]);
                    break;
                case Response::RESULT_UNKNOWN_ERROR:
                    // apple didn't tell us what happened
                    break;
            }
            //截取错误之后的token数组
            $this->deviceCollection = array_slice($this->deviceCollection,$response->getId() + 1);
            //重发 数组为空表示全部设备已经推送完毕
            if (!empty($this->deviceCollection)) {
                $this->_sendMessage();
            }
            else {
                $this->_invalidCollectionHandle();
            }
        }
        else{
            $this->_invalidCollectionHandle();
        }
        return true;
    }

    //推送完成 处理不可用的设备id
    private function _invalidCollectionHandle(){

    }

    //记录推送过程中的错误
    private function _errorLog($response){

    }
}
?>
```
