---
categories:
- web
date: "2014-09-16T21:01:00Z"
title: PHP的session与cookie & CodeIgniter的session修改
---
### 设定cookie的读取为httponly

最近网站被XSS了，还被报到乌云上去了。感谢仁慈的好事者没有做什么破坏，也给我提了醒。郁闷之与，做好防范才是关键。
做好防范，除了做好设置过滤以外，同事提到一个环节是设定cookie的读取模式为`httponly`。

于是我找了一下什么是httponly。在php.ini中有一个设置参数：

    session.cookie_httponly =

试试用`ini_set`把这个参数置为1，清理cookie刷新一下，结果显示不行。从php.ini文件中改也不行。

于是放弃了这种方法，在CodeIgniter中的源文件中改了几个地方，通过setcookie的参数设定httponly。

但是还有点不死心，想看一下这个参数到底是做什么的。在网上找了一下，也没有专门去讲这个事情的，甚至有些提到这个参数和setcookie混用的。

在PHP的源码中搜了一下，，在`ext/session/session.c`找到了以下的地方：

```c
if (PS(cookie_httponly)) {
    smart_str_appends(&ncookie, COOKIE_HTTPONLY);
}

smart_str_0(&ncookie);

php_session_remove_cookie(TSRMLS_C); /* remove already sent session ID cookie */
/*  'replace' must be 0 here, else a previous Set-Cookie
    header, probably sent with setcookie() will be replaced! */
sapi_add_header_ex(estrndup(ncookie.s->val, ncookie.s->len), ncookie.s->len, 0, 0 TSRMLS_CC);
```

从这段代码中可以看出httponly这个参数是被写在客户端存储session的cookie头上的，所以作用的范围仅限于session，而且只有使用了PHP自己的session的时候才会起作用（这个参数对其它的cookie更是没有任何作用的），在使用`session_start()`之后，客户端会保存一个cookie记录当前会话的session数据（默认名称是`PHPSESSID`，通过sesssion.name修改），这里配置的所有关于cookie的参数，都是针对这一条数据的。CI的session是自己实现的。所以使用这个参数当然是没有用的。

于是我梳理了一下cookie和session的知识并记录下来。很多信息从网络上可以获取到。

### cookie与session

1.**session与session机制**

session与session机制是两个的概念。

**session，顾名思义，指的是会话的过程，而session机制指的是创建会话过程并维护的方式。**
我们说的PHP的session指的是PHP实现并维护的session机制（可以使用`session_start()`函数开启并使用`$_SESSION`保存和获取参数），但是我们也可以自己实现或者使用框架里的session实现方式。

2.**session机制**

一个完善的session机制如下：

1. 服务器生成一个id作为会话的id，同时可以已这个id为基础存储一些数据，整个session的id和数据可以存在文件里，也可以存在数据库里（PHP的session机制默认是存储文件）。

2. 服务器把session的id和数据经过整理、加密等一系类过程作为一个数据（字符串）发送给客户端（浏览器），客户端将这个session存储下来。存储的方式可能有多种，最常用的是cookie（也可以是别的方式，只要保证在自己的web程序中可以取到，比如存在一个form标签里也是可以的）。

3. 客户端（浏览器）发送请求时，带上session数据（大多时候是cookie）一起送给服务器，服务器通过解析这段数据来判断请求来自哪里，已经在这次会话的过程中存储的一些数据（后端）。

3.**session和cookie的关系**

cookie是一种存储机制，指的是web服务可以在客户端存储一小段数据。当某个web服务在客户端存储有cookie数据的时候，客户端可以在之后的每次请求中都带上这段数据（浏览器是会自动带上这段数据的）。当然客户端也完全可以选择不带上这段数据，浏览器也可以禁用cookie。

session与cookie的关系在于：在绝大多数的情况下，我们会默认使用cookie来存储session会话的数据，而且现在浏览器都实现了带cookie请求的方式，再加上PHP的session机制，我们不需要考虑怎么去设计并维护一个简单的session会话。

一些网站也会在cookie被禁用的情况下通过其它方式维护session。

### 给CI的cookie类加上httponly

**说明：在2.1.3之后版本，CodeIgniter 已经修改了 Input 类的 cookie 方法和 cookie helper 的set\_cookie 函数。并且扩展了 session 类，在 session\_cookie 中，以下提到的改动已经加入，并且添加了 session\_native 类，封装了 PHP 提供的 session 机制。**

CI也是通过cookie来存session的，这点没有什么特别之处。不过CI的session机制并不是通过PHP原生的方法来调用的，是自己实现的。

使用CI的session类的好处在于可以很方便的使用数据库来存储和维护session数据。这是你可以在服务端里保存一些session相关的关键数据，比如用户的id。

**如果你不使用数据库存储session数据，那就不要使用CI的session类，因为它会直接把你的数据写到cookie里去，虽然数据是加密的：**

```php
<?php
    // Are we saving custom data to the DB?  If not, all we do is update the cookie
    if ($this->sess_use_database === FALSE)
    {
        $this->_set_cookie();
        return;
    }
?>
```

修改CI的cookie使其可以支持可以设定为httponly:

- 修改systerm/libraries/Session.php：添加一个属性 $cookie_httponly ，在构造函数的循环获取变量设置的数组里添加上这个属性；在 \_set\_cookie 方法末尾setcookie的地方添加上 $this->cookie\_httponly;
- 修改systerm/core/Input.php： set\_cookie 方法中添加参数 $http\_only ,并在末尾setcookie函数里添加这个参数;
- 修改systerm/helper/cookie\_helper.php： set\_cookie 方法方法中也添加这个参数，并在末尾 $CI->input->set\_cookie 的调用里加上。

如果在项目中不会使用到 $this->input->cookie() 的方法，可以不修改Input类，如果不使用cookie helper中的 set_cookie 方法，也可以不修改这个方法。

如果在项目用有使用登录的类库并调用了以上这些方法时，需要带上 http_only 的参数。

config文件中可以通过`$config['cookie_httponly'] = TRUE`来设置，或者在调用 session 类时传入。

### 小工具
Chrome有个插件[EditThisCookie](http://www.editthiscookie.com/ "")可以很方便的修改和删除 cookie。
