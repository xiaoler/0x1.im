---
categories:
- codeigniter
date: "2014-09-14T23:37:54Z"
title: 修改CodeIgniter的Pagination类使其支持Ajax分页
---
> 本文中针对CodeIgniter的问题都是基于`2.1.3`的版本。

在使用CodeIgniter做项目的过程中，需要用到ajax来分页，但是CI集成的分页类只支持在URL后面跟分页参数的方式。如果想实现ajax的分页，通过一定的方法也可以实现。

###使用原有实现示例

```php
<?php
    $config['base_url']         = 'javascript:pageAnchor(\'';
    $config['suffix']           = '\');';
    $config['first_url']        = 'javascript:pageAnchor(\'/0\')';

    $config['anchor_class']     = ""; //添加你的样式
    $config['cur_tag_open']     = '<a href="javascript:void(0);" class="">';
    $config['cur_tag_close']    = '</a>';
    $config['prev_link']        = '&lt;';
    $config['next_link']        = '&gt;';
    $config['first_link']       = '&laquo;';
    $config['last_link']        = '&raquo;';
    $config['first_tag_close'] = '';
    $config['last_tag_open']   = '';
    $config['next_tag_open']   = '';
    $config['next_tag_close']  = '';
    $config['prev_tag_open']   = '';
    $config['num_tag_open']    = '';

    $config['total_rows']       = 100; //数据总条数
    $config['per_page']         = 10;

    // 这里原来是要配置$config['uri_segment'] 默认为3
    // 分页类中有一个地方
    // $CI->uri->segment($this->uri_segment) != $base_page
    // 通过这个方法去判断当前页码
    // 我们的uri里第三段全为字母,刚好可以绕过这个判断
    // 所以cur_page 这个参数才能够传入进去
    $config['cur_page']         = 2; //当前页
?>
```

只要你的URL的第三段全为字母，就可以绕过判断，这实际上是一个bug。

通过以上的配置，在js中增加一个`pageAnchor`的方法，就可以实现ajax的分页了。

另外，在CdoeIgniter `2.1.3`之前的版本中，通过`$this->load->library('pagination',$config)`的方式来初始化时添加`anchor_class`参数无法生效，这是因为代码中把这个参数的操作放到了构造函数中，这个BUG在`2.1.4`的版本中已经修复了。

###修改后的实现

通过这种方法来实现毕竟也不太恰当，可以提取出这个类来单独使用，这样在别的框架中也可以使用这个分页类。

主要改动如下：

1. 添加了一个`is_ajax`的参数，并修改了默认的跟tag有关的参数，便于直接写入css
2. 移除了跟CI的其它类有关的取URL参数的部分，这样就是一个纯净的分页类了
3. `num_links`小于1时跳到第一页，而不是报错
4. 移除了`query_string_segment`参数，增加`query_string_key`参数，仍然支持通过参数的形式获取分页。

改动后的使用配置方法：

```php
<?php
    $config['is_ajax']          = true;
    $config['base_url']         = 'pageAnchor';
    $config['first_url']        = 'javascript:pageAnchor(\'/0\')';
    $config['anchor_class']     = "";
    $config['cur_tag_open']     = '<a href="javascript:void(0);" class="">';
    $config['cur_tag_close']    = '</a>';
    $config['total_rows']       = 100; // 数据总条数
    $config['cur_page']         = 2;  // 当前页码
?>
```

###下载
[Page.php](/files/code/Page.php "")
