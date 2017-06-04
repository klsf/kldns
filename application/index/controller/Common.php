<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/6/1
 * Time: 20:58
 */

namespace app\index\controller;


use think\Controller;
use think\Cookie;

class Common extends Controller
{
    function _initialize()
    {
        parent::_initialize();
        define('DB_PREFIX', config('database')['prefix']);//数据表前缀，后面使用原生SQL可能会用到
        Cookie::init(['prefix' => 'kldns_', 'expire' => 3600 * 24 * 7, 'path' => '/']);
        $this->loadConfig();
        $this->assign('webTitle', '首页');
    }

    private function loadConfig()
    {
        $configs = db('configs')->select();
        foreach ($configs as $v) {
            config("web_{$v['vkey']}", $v['value']);
        }
    }

}