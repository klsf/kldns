<?php

// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------

namespace app\index\controller;


use app\util\PdoHelper;
use think\Controller;
use think\Cookie;

class Klsf extends Controller
{
    protected $pdo;
    protected $userInfo;//登录用户信息

    function __construct()
    {
        $this->pdo = new PdoHelper();
        parent::__construct();
        $this->loadWebConfig();
        $this->getLoginuUser();
    }

    /**
     * 加载网站配置
     */
    private function loadWebConfig()
    {
        $stmt = $this->pdo->getStmt("select * from pre_configs");
        while ($row = $stmt->fetch()){
            C($row['vkey'],$row['value']);
        }
    }

    /**
     * 获取登录用户信息
     */
    protected function getLoginuUser()
    {
        $userSid = Cookie::get("userSid");
        if(!empty($userSid)){
            $this->userInfo = $this->pdo->find("select * from pre_users where sid=:sid limit 1",array(":sid"=>$userSid));
        }
        $this->assign("userInfo",$this->userInfo);
    }

}