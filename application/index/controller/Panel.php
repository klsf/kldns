<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------


namespace app\index\controller;


use app\util\dnsApi\KlsfDns;

class Panel extends Klsf
{

    public function profile()
    {

        $this->assign("webTitle","个人信息");
        return $this->fetch();
    }

    public function update($id)
    {
        if (!$record = $this->pdo->find("select a.*,b.name as domain,b.dns FROM `pre_records` as a left join `pre_domains` as b on b.domain_id=a.domain_id where a.`uid`=:uid and a.record_id=:id limit 1",array(":uid"=>$this->userInfo['uid'],":id"=>$id))){
            $this->assign("alert",sweetAlert("温馨提示","此记录不存在！","warning",U("index")));
            return $this->fetch("common/sweetAlert");
        }
        $action = I("post.action");
        if ($action == "update"){
            $name = I("post.name");
            $type = I("post.type");
            $value = I("post.value");

            $domain_id = $record['domain_id'];
            $klsfDns = KlsfDns::getApi($record['dns']);

            if($ret = $klsfDns->updateRecord($domain_id,$id,$name,$type,$value)){
                if($this->pdo->execute("update pre_records set `name`=:name,`type`=:type,`value`=:value,`updatetime`=NOW() where record_id=:id limit 1",array(':id'=>$ret['record_id'],':name'=>$name,':type'=>$type,':value'=>$value))){
                    $this->assign("alert",sweetAlert("修改成功","记录修改成功！","success"));
                    $record = $this->pdo->find("select a.*,b.name as domain,b.dns FROM `pre_records` as a left join `pre_domains` as b on b.domain_id=a.domain_id where a.`uid`=:uid and a.record_id=:id limit 1",array(":uid"=>$this->userInfo['uid'],":id"=>$id));
                }else{
                    $this->assign("alert",sweetAlert("修改失败","解析成功，保存数据库失败！","warning"));
                }
            }else{
                $info = $klsfDns->getErrorInfo();
                $this->assign("alert",sweetAlert("修改失败",$info['msg'],"warning"));
            }
        }

        $this->assign("record",$record);
        $this->assign("webTitle","记录修改");
        return $this->fetch();
    }

    public function index()
    {
        $action = I("post.action");
        if ($action == 'addrecord'){
            $this->assign("isAdd",true);
            $domain_id = I("post.domain_id");
            $name = trim(strtolower(I("post.name")));
            $type = I("post.type");
            $value = I("post.value");
            $code = I("post.code");
            if (strlen($code)!=4 || !isset($_COOKIE['verification']) || md5(strtolower($code))!==$_COOKIE['verification']){
                $this->assign("alert",sweetAlert("温馨提示","验证码不正确","warning"));
            }elseif (C("allowNum") == -1) {
                $this->assign("alert", sweetAlert("温馨提示", "站长已经关闭用户自助解析功能！", "warning"));
            }elseif (!$this->checkAllow($name)){
                $this->assign("alert", sweetAlert("温馨提示", "对不起，前缀{$name}不允许用户解析！", "warning"));
            }elseif (C("allowNum") && $this->pdo->getCount("select record_id from pre_records where uid=:uid",array(":uid"=>$this->userInfo['uid'])) >= C("allowNum")){
                $this->assign("alert",sweetAlert("温馨提示","你最大允许解析".C("allowNum")."条记录！","warning"));
            }elseif (!$row = $this->pdo->find("select dns,name from pre_domains where domain_id=:id and level <= :level limit 1",array(":id"=>$domain_id,":level"=>$this->userInfo['level']))) {
                $this->assign("alert", sweetAlert("温馨提示", "所选择域名不存在！", "warning"));
            }else{
                setCookie('verification',null,-1,'/');//销毁验证码
                $klsfDns = KlsfDns::getApi($row['dns']);
                if($ret = $klsfDns->addRecord($domain_id,$name,$type,$value,$row['name'])){
                    $insert = array(':record_id'=>$ret['record_id'],':uid'=>$this->userInfo['uid'],':domain_id'=>$domain_id,':name'=>$ret['name'],':type'=>$type,':value'=>$value);
                    if($this->pdo->execute("INSERT INTO `pre_records` (`record_id`, `uid`, `domain_id`, `name`, `type`, `value`, `updatetime`) VALUES (:record_id, :uid, :domain_id, :name, :type, :value, NOW())",$insert)){
                        $this->assign("isAdd",false);
                        $this->assign("alert",sweetAlert("添加成功","添加记录成功！","success"));
                    }else{
                        $this->assign("alert",sweetAlert("温馨提示","解析成功，保存数据库失败！","warning"));
                    }
                }else{
                    $info = $klsfDns->getErrorInfo();
                    $this->assign("alert",sweetAlert("温馨提示",$info['msg'],"warning"));
                }
            }

        }

        //获取记录列表
        $records = $this->pdo->selectAll("select a.*,b.name as domain,b.dns from pre_records as a left join pre_domains as b on b.domain_id=a.domain_id where a.uid=:uid",array(":uid"=>$this->userInfo['uid']));
        //获取域名列表
        $domains = $this->pdo->selectAll("select * from pre_domains where `level` <= :level",array(":level"=>$this->userInfo['level']));
        //遍历记录检查是否还存在，不存在则从数据库删除
        $newRecords = array();
        foreach ($records as $record){
            $klsfDns = KlsfDns::getApi($record['dns']);
            if ($re = $klsfDns->getRecordInfo($record['domain_id'],$record['record_id'])){
                $re['domain'] = $record['domain'];
                $newRecords[]=$re;
            }else{//不存在则删除记录
                $this->pdo->execute("delete from pre_records where record_id=:id limit 1",array(":id"=>$record['record_id']));
             }
        }
        $this->assign("records",$newRecords);
        $this->assign("domains",$domains);

        $this->assign("webTitle","控制面板");
        return $this->fetch();
    }


    private function checkAllow($_name){
        if(C("forbidRecord")){
            $arr = explode(",",trim(C("forbidRecord")));
            if(in_array($_name,$arr)){
                return false;
            }
        }
        return true;
    }
    private function checkLogin()
    {
        if (empty($this->userInfo)){
            $this->assign("alert",sweetAlert("未登录","请登录后操作！","warning",U("index/Index")));
            exit($this->fetch("common/sweetAlert"));
        }
    }
    function __construct()
    {
        parent::__construct();
        $this->checkLogin();
    }
}