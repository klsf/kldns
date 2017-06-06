<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/6/2
 * Time: 20:36
 */

namespace app\index\controller;


use think\Cookie;
use think\Request;

class Admin extends Common
{
    public function index()
    {
        $this->assign('webTitle', '后台首页');
        return $this->fetch();
    }

    public function api()
    {
        $dnspod = db('dns_apis')->where('dns', 'dnspod')->find();
        $dnspod || $dnspod = ['api_key' => null];
        $this->assign('dnspod', $dnspod);
        $aliyun = db('dns_apis')->where('dns', 'aliyun')->find();
        $aliyun || $aliyun = ['api_key' => null];
        $this->assign('aliyun', $aliyun);
        $cloudxns = db('dns_apis')->where('dns', 'cloudxns')->find();
        $cloudxns || $cloudxns = ['api_key' => null];
        $this->assign('cloudxns', $cloudxns);
        $dnscom = db('dns_apis')->where('dns', 'dnscom')->find();
        $dnscom || $dnscom = ['api_key' => null];
        $this->assign('dnscom', $dnscom);
        $dnsla = db('dns_apis')->where('dns', 'dnsla')->find();
        $dnsla || $dnsla = ['api_key' => null];
        $this->assign('dnsla', $dnsla);
        $this->assign('webTitle', '接口配置');
        return $this->fetch();
    }

    public function config()
    {
        $this->assign('webTitle', '网站配置');
        return $this->fetch();
    }

    public function domain_list()
    {
        $this->assign('dnsList', db('dns_apis')->field('dns')->select());
        $this->assign('webTitle', '域名列表');
        return $this->fetch();
    }

    public function record_list()
    {
        $this->assign('webTitle', '记录列表');
        return $this->fetch();
    }

    public function user_list()
    {
        $this->assign('webTitle', '用户列表');
        return $this->fetch();
    }


    private function checkLogin()
    {
        if ($sid = Cookie::get("adminSid")) {
            $check = passwordEncrypt(config('web_admin') . ',' . config('web_password'));
            if ($check === $sid) {
                return true;
            }
        }
        $this->error('请先登录管理员账号！', url('index/adminLogin'));
    }

    function _initialize()
    {
        parent::_initialize();
        config('default_ajax_return', 'html');//取消AJAX请求自动转化为json数据,pjax会用到
        $this->checkLogin();
        if (Request::instance()->header("X-PJAX")) {
            $this->assign('isPjax', true);
        } else {
            $this->assign('isPjax', false);
        }
    }
}