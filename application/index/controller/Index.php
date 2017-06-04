<?php
namespace app\index\controller;


use klsf\klsfdns\DnsPod;
use think\Cookie;

class Index extends Common
{
    private $uid;
    private $userInfo;

    public function adminLogin()
    {
        $this->assign('webTitle', '管理员登录');
        return $this->fetch();
    }

    public function index()
    {
        config('web_index_title') && $this->assign('webTitle', config('web_index_title'));
        return $this->fetch();
    }

    public function control()
    {
        $this->checkLogin();
        $this->assign('webTitle', '用户控制中心');
        return $this->fetch();
    }

    private function checkLogin()
    {
        if (!$this->userInfo) {
            $this->error('请先登录！');
        }
    }

    private function getUserInfo()
    {
        if ($sid = Cookie::get("userSid")) {
            if ($info = db('users')->where('sid', $sid)->find()) {
                $this->userInfo = $info;
                $this->uid = $this->userInfo['uid'];
            }
        }
        $this->assign('userInfo', $this->userInfo);
    }

    function _initialize()
    {
        parent::_initialize();
        $this->getUserInfo();
    }
}
