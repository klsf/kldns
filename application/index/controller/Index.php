<?php

namespace app\index\controller;


use think\Cookie;

class Index extends Common
{
    private $uid;
    private $userInfo;

    public function resetPassword()
    {
        $sid = input('sid');
        if (strlen($sid) != 32 || !$user = db('users')->where('sid', $sid)->find()) {
            $this->error('链接已失效！', '/');
        } else {
            db('users')->where('uid', $user['uid'])->update(['status' => 1, 'pwd' => passwordEncrypt('123456')]);
            $this->success('重置密码成功，密码重置为：123456。请立即登录后进行密码修改！', '/');
        }
    }

    public function password()
    {
        $this->assign('webTitle', '找回密码');
        return $this->fetch();
    }

    public function sendValidateEmail()
    {
        $email = input('email');
        if (!$email || !$user = db('users')->where('email', $email)->find()) {
            $this->error('用户不存在！', '/');
        } else if (!config('web_email_username')) {
            $this->error('站长未配置发送邮件功能！', '/');
        } else {
            $url = "http://{$_SERVER['HTTP_HOST']}" . url('index/validateEmail', ['sid' => $user['sid']]);
            $body = "尊敬的用户：<br>您好，<br>请点击下面链接进行邮箱验证：<a href='{$url}' target='_blank'>{$url}</a><br>邮箱验证后，你就可以开始使用平台了！";
            $mail = sendEmail($email, config('web_name') . "-邮箱验证", $body);
            if ($mail->send()) {
                $this->success('激活邮件已发送至你邮箱，请查看！', '/');
            } else {
                $this->error('邮件发送失败：' . $mail->ErrorInfo, '/');
            }
        }
    }

    public function validateEmail()
    {
        $sid = input('sid');
        if (strlen($sid) != 32 || !$user = db('users')->where('sid', $sid)->find()) {
            $this->error('链接已失效！', '/');
        } else {
            db('users')->where('uid', $user['uid'])->update(['status' => 1]);
            $this->success('邮箱验证成功！', '/');
        }
    }

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
            if ($info = db('users')->where('sid', $sid)->where('status', 1)->find()) {
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
