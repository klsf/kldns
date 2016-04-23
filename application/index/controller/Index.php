<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------

namespace app\index\controller;

use think\Cookie;

class Index extends Klsf
{
    public function logout()
    {
        Cookie::delete("userSid");
        if (!empty($this->userInfo)) {
            $this->pdo->execute("update pre_users set sid=:sid where uid=:uid limit 1", array(":uid" => $this->userInfo['uid'], ":sid" => getSid()));
        }
        header("Location:/");
    }
    public function adminLogin()
    {
        if (I("post.action" == "login")){
            $pwd = I("post.pwd");
            $pwd = md5Pwd($pwd);
            if(C("webAdmin") === $pwd){
                Cookie::set("webAdmin",$pwd,array("path"=>"/","expire"=>3600*24));
                $this->assign("alert",sweetAlert("登录成功","进入管理后台！","warning",U("index/Admin/index")));
                return $this->fetch("common/sweetAlert");
            }else{
                $this->assign("alert",sweetAlert("温馨提示","密码错误！","warning"));
            }
        }
        $this->assign("webTitle","管理员登录");
        return $this->fetch("adminLogin");
    }

    public function index()
    {
        $action = I("post.action");
        if ($action == "login"){
            $user = I("post.user");
            $pwd = I("post.pwd");
            if (strlen($user)<3 || strlen($pwd)<5) {
                $this->assign("alert", sweetAlert("温馨提示", "用户名或者密码格式不正确！", "warning"));
            }else{
                $pwd = md5Pwd($pwd);
                if($user = $this->pdo->find("select uid from pre_users where (user=:user or email=:user) and pwd=:pwd limit 1",array(":user"=>$user,":pwd"=>$pwd))){
                    $sid = getSid();
                    $this->pdo->execute("update pre_users set sid=:sid where uid=:uid limit 1",array(":uid"=>$user['uid'],":sid"=>$sid));
                    Cookie::set("userSid",$sid,array("path"=>"/","expire"=>3600*24));
                    $this->assign("alert",sweetAlert("登录成功！","欢迎回来，进入控制面板！","success",U("/index/Panel/index")));
                    return $this->fetch("common/sweetAlert");
                }else{
                    $this->assign("alert", sweetAlert("温馨提示", "用户名或者密码不正确！", "warning"));
                }
            }
        }elseif ($action == "reg"){
            $this->assign("isReg",true);
            $user = I("post.user");
            $pwd = I("post.pwd");
            $email = strtolower(I("post.email"));
            $code = I("post.code");
            if (strlen($user)<3){
                $this->assign("alert", sweetAlert("温馨提示", "用户名太短！", "warning"));
            }elseif (strlen($pwd)<5){
                $this->assign("alert", sweetAlert("温馨提示", "密码太简单！", "warning"));
            }elseif (strlen($code) != 4 || !isset($_COOKIE['verification']) || md5(strtolower($code))!==$_COOKIE['verification']) {
                $this->assign("alert", sweetAlert("温馨提示", "验证码错误！", "warning"));
            }elseif (!preg_match('/^[a-zA-Z0-9\-\_]+@[a-zA-Z0-9\-]+\.[a-zA-Z]+$/',$email)){
                $this->assign("alert", sweetAlert("温馨提示", "邮箱格式不正确！", "warning"));
            }elseif ($this->pdo->find("select uid from pre_users where user=:user limit 1",array(":user"=>$user))) {
                $this->assign("alert", sweetAlert("温馨提示", "用户名已存在！", "warning"));
            }elseif ($this->pdo->find("select uid from pre_users where email=:email limit 1",array(":email"=>$email))){
                $this->assign("alert", sweetAlert("温馨提示", "邮箱账号已存在！", "warning"));
            }else{
                setCookie('verification',null,-1,'/');//销毁验证码
                $insert = array();
                $insert[':user'] = $user;
                $insert[':pwd'] = md5Pwd($pwd);
                $insert[':email'] = $email;
                $insert['sid'] = getSid();
                if($this->pdo->execute("INSERT INTO `pre_users` (`user`, `email`, `pwd`, `sid`, `regtime`) VALUES (:user, :email, :pwd, :sid, NOW())",$insert)){
                    $this->assign("alert",sweetAlert("注册成功！","马上登录！","success",U("index")));
                    return $this->fetch("common/sweetAlert");
                }else{
                    $this->assign("alert", sweetAlert("温馨提示", "注册失败，请稍候再试！", "warning"));
                }
            }

        }

        $this->assign("webTitle",C("webTitle")?C("webTitle"):"网站首页");
        return $this->fetch();
    }
}
