<?php
/**
 * Page Class
 *
 * @package     Page
 * @author      Scholer Liu
 * @copyright   Copyright (c) 2014.
 * @license     MIT
 * @since       Version 1.0
 */
class Page {

    var $is_ajax            = FALSE; //是否为ajax分页
    var $base_url           = ''; // 基础链接 如果是ajax请求则为一个基础函数
    var $prefix             = ''; // 前缀 置于数字之前
    var $suffix             = ''; // 后缀 数字之后
    var $first_url          = ''; // 首页的url

    var $total_rows         =  0; // 数据总的条数
    var $per_page           = 10; // 每页数据量
    var $num_links          =  2; // 显示在当前页左右的页码数量
    var $cur_page           =  0; // 当前页码 必须传入
    var $use_page_numbers   = FALSE; // 使用页码数代替偏移量显示

    var $first_link         = '&laquo;'; //首页字符
    var $next_link          = '&gt;'; //下一页字符
    var $prev_link          = '&lt;'; //上一页字符
    var $last_link          = '&raquo;'; //最后一页字符

    var $full_tag_open      = ''; //所有标签的开始
    var $full_tag_close     = ''; //所有标签的闭合
    var $first_tag_open     = ''; //首页标签的开始
    var $first_tag_close    = ''; //首页标签的闭合
    var $last_tag_open      = ''; //最后一页标签的开始
    var $last_tag_close     = ''; //最后一页标签的闭合
    var $cur_tag_open       = '<strong>'; //当前标签的开始
    var $cur_tag_close      = '</strong>'; //当前标签的闭合
    var $next_tag_open      = ''; //下一页标签的开始
    var $next_tag_close     = ''; //下一页标签的闭合
    var $prev_tag_open      = ''; //上一页标签的开始
    var $prev_tag_close     = ''; //上一页标签的闭合
    var $num_tag_open       = ''; //数字标签的开始
    var $num_tag_close      = ''; //数字标签的闭合

    var $page_query_string  = FALSE;
    var $query_string_key   = 'num';
    var $display_pages      = TRUE; //是否显示页码
    var $anchor_class       = ''; //数字标签的css类

    /**
     * Constructor
     *
     * @access  public
     * @param   array   initialization parameters
     */
    public function __construct($params = array())
    {
        if (count($params) > 0)
        {
            $this->initialize($params);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Initialize Preferences
     *
     * @access  public
     * @param   array   initialization parameters
     * @return  void
     */
    function initialize($params = array())
    {
        if (count($params) > 0)
        {
            foreach ($params as $key => $val)
            {
                if (isset($this->$key))
                {
                    $this->$key = $val;
                }
            }
        }
        if ($this->anchor_class != '')
        {
            $this->anchor_class = 'class="'.$this->anchor_class.'" ';
        }
        //如果是ajax分页 base_url传入一个js方法 前后缀为括号和引号
        if ($this->is_ajax)
        {
            $this->base_url = "javascript:".$this->base_url;
            $this->prefix =  '(\'';
            $this->suffix = '\');';
        }
    }

    // --------------------------------------------------------------------

    /**
     * Generate the pagination links
     *
     * @access  public
     * @return  string
     */
    function create_links()
    {
        // If our item count or per-page total is zero there is no need to continue.
        if ($this->total_rows == 0 OR $this->per_page == 0)
        {
            return '';
        }

        // Calculate the total number of pages
        $num_pages = ceil($this->total_rows / $this->per_page);

        // Is there only one page? Hm... nothing more to do here then.
        if ($num_pages == 1)
        {
            return '';
        }

        // Set the base page index for starting page number
        $base_page = $this->use_page_numbers ? 1 : 0;

        // Set current page to 1 if using page numbers instead of offset
        if ($this->use_page_numbers AND $this->cur_page == 0)
        {
            $this->cur_page = $base_page;
        }

        $this->num_links = (int)$this->num_links;

        if ($this->num_links < 1)
        {
            $this->num_links = 1;
        }

        if ( ! is_numeric($this->cur_page))
        {
            $this->cur_page = $base_page;
        }

        // Is the page number beyond the result range?
        // If so we show the last page
        if ($this->use_page_numbers)
        {
            if ($this->cur_page > $num_pages)
            {
                $this->cur_page = $num_pages;
            }
        }
        else
        {
            if ($this->cur_page > $this->total_rows)
            {
                $this->cur_page = ($num_pages - 1) * $this->per_page;
            }
        }

        $uri_page_number = $this->cur_page;

        if ( ! $this->use_page_numbers)
        {
            $this->cur_page = floor(($this->cur_page/$this->per_page) + 1);
        }

        // Calculate the start and end numbers. These determine
        // which number to start and end the digit links with
        $start = (($this->cur_page - $this->num_links) > 0) ? $this->cur_page - ($this->num_links - 1) : 1;
        $end   = (($this->cur_page + $this->num_links) < $num_pages) ? $this->cur_page + $this->num_links : $num_pages;

        // Is pagination being used over GET or POST?  If get, add a per_page query
        // string. If post, add a trailing slash to the base URL if needed
        if ($this->is_ajax === FALSE)
        {
            if ($this->page_query_string === TRUE)
            {
                $this->base_url = rtrim($this->base_url).'&amp;'.$this->query_string_key  .'=';
            }
            else
            {
                $this->base_url = rtrim($this->base_url, '/') .'/';
            }
        }

        // And here we go...
        $output = '';

        // Render the "First" link
        if  ($this->first_link !== FALSE AND $this->cur_page > ($this->num_links + 1))
        {
            $first_url = ($this->first_url == '') ? $this->base_url : $this->first_url;
            $output .= $this->first_tag_open.'<a '.$this->anchor_class.'href="'.$first_url.'">'.$this->first_link.'</a>'.$this->first_tag_close;
        }

        // Render the "previous" link
        if  ($this->prev_link !== FALSE AND $this->cur_page != 1)
        {
            if ($this->use_page_numbers)
            {
                $i = $uri_page_number - 1;
            }
            else
            {
                $i = $uri_page_number - $this->per_page;
            }

            if ($i == 0 && $this->first_url != '')
            {
                $output .= $this->prev_tag_open.'<a '.$this->anchor_class.'href="'.$this->first_url.'">'.$this->prev_link.'</a>'.$this->prev_tag_close;
            }
            else
            {
                $i = ($i == 0) ? '' : $this->prefix.$i.$this->suffix;
                $output .= $this->prev_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$i.'">'.$this->prev_link.'</a>'.$this->prev_tag_close;
            }

        }

        // Render the pages
        if ($this->display_pages !== FALSE)
        {
            // Write the digit links
            for ($loop = $start -1; $loop <= $end; $loop++)
            {
                if ($this->use_page_numbers)
                {
                    $i = $loop;
                }
                else
                {
                    $i = ($loop * $this->per_page) - $this->per_page;
                }

                if ($i >= $base_page)
                {
                    if ($this->cur_page == $loop)
                    {
                        $output .= $this->cur_tag_open.$loop.$this->cur_tag_close; // Current page
                    }
                    else
                    {
                        $n = ($i == $base_page) ? '' : $i;

                        if ($n == '' && $this->first_url != '')
                        {
                            $output .= $this->num_tag_open.'<a '.$this->anchor_class.'href="'.$this->first_url.'">'.$loop.'</a>'.$this->num_tag_close;
                        }
                        else
                        {
                            $n = ($n == '') ? '' : $this->prefix.$n.$this->suffix;

                            $output .= $this->num_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$n.'">'.$loop.'</a>'.$this->num_tag_close;
                        }
                    }
                }
            }
        }

        // Render the "next" link
        if ($this->next_link !== FALSE AND $this->cur_page < $num_pages)
        {
            if ($this->use_page_numbers)
            {
                $i = $this->cur_page + 1;
            }
            else
            {
                $i = ($this->cur_page * $this->per_page);
            }

            $output .= $this->next_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$this->prefix.$i.$this->suffix.'">'.$this->next_link.'</a>'.$this->next_tag_close;
        }

        // Render the "Last" link
        if ($this->last_link !== FALSE AND ($this->cur_page + $this->num_links) < $num_pages)
        {
            if ($this->use_page_numbers)
            {
                $i = $num_pages;
            }
            else
            {
                $i = (($num_pages * $this->per_page) - $this->per_page);
            }
            $output .= $this->last_tag_open.'<a '.$this->anchor_class.'href="'.$this->base_url.$this->prefix.$i.$this->suffix.'">'.$this->last_link.'</a>'.$this->last_tag_close;
        }

        // Kill double slashes.  Note: Sometimes we can end up with a double slash
        // in the penultimate link so we'll kill all double slashes.
        $output = preg_replace("#([^:])//+#", "\\1/", $output);

        // Add the wrapper HTML if exists
        $output = $this->full_tag_open.$output.$this->full_tag_close;

        return $output;
    }
}
// END Page Class

/* End of file Page.php */