<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------

namespace app\util;

use think\Model;

class Page extends Model
{
    private $saveGet;     //是否保留GET参数
    private $total;       //总记录
    private $pageSize;    //每页显示多少条
    private $limit;        //limit
    private $page;        //当前页码
    private $pageNum;        //总页码
    private $url;            //地址
    private $bothNum;        //两边保持数字分页的量

    //构造方法初始化
    public function __construct($_total, $_pageSize, $_saveGet = true)
    {
        $this->saveGet = $_saveGet;
        $this->total = $_total ? $_total : 1;
        $this->pageSize = $_pageSize;
        $this->pageNum = ceil($this->total / $this->pageSize);
        $this->page = $this->setPage();
        $this->limit = "LIMIT " . ($this->page - 1) * $this->pageSize . ",$this->pageSize";
        $this->url = $this->setUrl();
        $this->bothNum = 2;
    }

    //拦截器
    public function __get($_key)
    {
        return $this->$_key;
    }

    //获取当前页码
    private function setPage()
    {
        if (!empty($_GET['page'])) {
            if ($_GET['page'] > 0) {
                if ($_GET['page'] > $this->pageNum) {
                    return $this->pageNum;
                } else {
                    return $_GET['page'];
                }
            } else {
                return 1;
            }
        } else {
            return 1;
        }
    }

    //获取地址
    private function setUrl()
    {
        $_url = $_SERVER["REQUEST_URI"];
        $_par = parse_url($_url);
        if ($this->saveGet && isset($_par['query'])) {
            parse_str($_par['query'], $_query);
            unset($_query['page']);
            $_url = $_par['path'] . '?' . http_build_query($_query) . '&';
        } else {
            $_url = $_par['path'] . '?';
        }
        return $_url;
    }    //数字目录

    private function pageList()
    {
        $_pageList = null;
        for ($i = $this->bothNum; $i >= 1; $i--) {
            $_page = $this->page - $i;
            if ($_page < 1) continue;
            $_pageList .= ' <li><a href="' . $this->url . 'page=' . $_page . '">' . $_page . '</a></li>';
        }
        $_pageList .= ' <li class="active"><a >' . $this->page . '</a></li> ';
        for ($i = 1; $i <= $this->bothNum; $i++) {
            $_page = $this->page + $i;
            if ($_page > $this->pageNum) break;
            $_pageList .= '<li><a href="' . $this->url . 'page=' . $_page . '">' . $_page . '</a></li>';
        }
        return $_pageList;
    }

    //首页
    private function first()
    {
        if ($this->page == $this->bothNum + 2) {
            return '<li><a href="' . $this->url . '">1</a></li>';
        } elseif ($this->page > $this->bothNum + 2) {
            return '<li><a href="' . $this->url . '">1</a></li><li class="disabled"><span>...</span></li>';
        }
    }

    //上一页
    private function prev()
    {
        if ($this->page == 1) {
            return '<li class="disabled"><span>&laquo;</span></li>';
        }
        return '<li><a href="' . $this->url . 'page=' . ($this->page - 1) . '">&laquo;</a></li>';
    }

    //下一页
    private function next()
    {
        if ($this->page == $this->pageNum) {
            return '<li class="disabled"><span>&raquo;</span></li>';
        }
        return '<li><a href="' . $this->url . 'page=' . ($this->page + 1) . '">&raquo;</a></li>';
    }

    //尾页
    private function last()
    {
        if ($this->pageNum - $this->page == $this->bothNum + 1) {
            return '<li><a href="' . $this->url . 'page=' . $this->pageNum . '">' . $this->pageNum . '</a></li>';
        } elseif ($this->pageNum - $this->page > $this->bothNum + 1) {
            return '<li class="disabled"><span>...</span></li><li><a href="' . $this->url . 'page=' . $this->pageNum . '">' . $this->pageNum . '</a></li>';
        }
    }

    //分页信息
    public function showPage()
    {
        $_page = '<ul class="pagination">';
        $_page .= $this->prev();
        $_page .= $this->first();
        $_page .= $this->pageList();
        $_page .= $this->last();
        $_page .= $this->next();
        $_page .= '</ul>';
        return $_page;
    }
}