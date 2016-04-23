<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------


namespace app\index\controller;


use app\util\dnsApi\Aliyun;
use app\util\dnsApi\CloudXNS;
use app\util\dnsApi\Dnspod;
use app\util\dnsApi\KlsfDns;
use app\util\Page;
use app\util\PdoHelper;
use think\Controller;
use think\Cookie;

class Admin extends Controller
{
    private $pdo;


    public function userInfo($uid)
    {
        if (!$user = $this->pdo->find("select * from pre_users where uid=:uid limit 1",array(":uid"=>$uid))){
            $this->assign("alert",sweetAlert("温馨提示","用户不存在！","warning",U("userList")));
            return $this->fetch("common/sweetAlert");
        }
        if (I("post.action") == "update"){
            $update = array();
            $update[':uid'] = $uid;
            $update[':email'] = I("post.email");
            $update[':level'] = I("post.level/d");
            $update[':max'] = I("post.max/d");
            if(I("post.pwd")){
                $update[':pwd'] = md5Pwd(I("post.pwd"));
            }else{
                $update[':pwd'] = $user['pwd'];
            }
            if($this->pdo->execute("update pre_users set email=:email,level=:level,max=:max,pwd=:pwd where uid=:uid limit 1",$update)){
                $this->assign("alert",sweetAlert("温馨提示","修改成功!","success",U("userInfo",array("uid"=>$uid))));
            }else{
                $this->assign("alert",sweetAlert("温馨提示","修改失败!","warning"));
            }

        }

        $this->assign("user",$user);
        $this->assign("webTitle","用户详情");
        return $this->fetch("userInfo");
    }

    public function userList()
    {
        $pageList = new Page($this->pdo->getCount("select uid from pre_users"),10);
        $users = $this->pdo->selectAll("select a.*,(select count(record_id) from pre_records where uid=a.uid) as count from pre_users as a order by a.uid desc ".$pageList->limit);

        $this->assign("users",$users);
        $this->assign("pageList",$pageList);
        $this->assign("webTitle","用户列表");
        return $this->fetch("userList");
    }
    public function recordList($id = null,$uid = null)
    {
        if (!empty($uid)) {//指定用户的解析记录
            $this->assign("uid",$uid);
            if (!empty($id)) {
                $this->assign("domainId", $id);
                $pageList = new Page($this->pdo->getCount("select record_id from pre_records where domain_id=:id and uid=:uid", array(":id" => $id,":uid"=>$uid)), 10);
                $records = $this->pdo->selectAll("select a.*,b.`user`,c.`name` as domain_name from `pre_records` as a left join `pre_users` as b on b.uid = a.uid left join `pre_domains` as c on c.domain_id=a.domain_id where a.domain_id=:id and a.uid=:uid order by updatetime desc " . $pageList->limit, array(":id" => $id,":uid"=>$uid));
            } else {
                $pageList = new Page($this->pdo->getCount("select record_id from pre_records where uid=:uid",array(":uid"=>$uid)), 10);
                $records = $this->pdo->selectAll("select a.*,b.`user`,c.`name` as domain_name from `pre_records` as a left join `pre_users` as b on b.uid = a.uid left join `pre_domains` as c on c.domain_id=a.domain_id where a.uid=:uid order by updatetime desc " . $pageList->limit,array(":uid"=>$uid));
            }
        }else{
            if (!empty($id)) {
                $this->assign("domainId", $id);
                $pageList = new Page($this->pdo->getCount("select record_id from pre_records where domain_id=:id", array(":id" => $id)), 10);
                $records = $this->pdo->selectAll("select a.*,b.`user`,c.`name` as domain_name from `pre_records` as a left join `pre_users` as b on b.uid = a.uid left join `pre_domains` as c on c.domain_id=a.domain_id where a.domain_id=:id order by updatetime desc " . $pageList->limit, array(":id" => $id));
            } else {
                $pageList = new Page($this->pdo->getCount("select record_id from pre_records"), 10);
                $records = $this->pdo->selectAll("select a.*,b.`user`,c.`name` as domain_name from `pre_records` as a left join `pre_users` as b on b.uid = a.uid left join `pre_domains` as c on c.domain_id=a.domain_id order by updatetime desc " . $pageList->limit);
            }
        }
        //获取域名列表
        $domains = $this->pdo->selectAll("select * from pre_domains ");
        $this->assign("domains",$domains);

        $this->assign("pageList",$pageList);
        $this->assign("records",$records);
        $this->assign("webTitle","记录列表");
        return $this->fetch("recordList");
    }

    public function addDomain()
    {
        $domain = I("post.domain");
        if (I("post.action") == "add" && $domain){
            $level = I("post.level/d");
            $dns = I("post.dns");
            $klsfDns = KlsfDns::getApi($dns);
            if(!$info = $klsfDns->getDomainInfo($domain)){
                $error = $klsfDns->getErrorInfo();
                $this->assign("alert",sweetAlert("温馨提示",$error['msg'],"warning"));
            }else{
                $insert = array(':domain_id'=>$info['domain_id'],':dns'=>$dns,':name'=>trim($info['name'],'.'),':level'=>$level);
                if($this->pdo->execute("INSERT INTO `pre_domains` (`domain_id`, `dns`, `name`, `level`) VALUES (:domain_id, :dns, :name, :level)",$insert)){
                    $this->assign("alert",sweetAlert("添加成功","添加域名".$info['name']."成功！","success",U("domainList")));
                    return $this->fetch("common/sweetAlert");
                }else{
                    $this->assign("alert",sweetAlert("温馨提示","保存数据库失败！","warning"));
                }
            }
        }
        $this->assign("webTitle","添加域名");
        return $this->fetch("addDomain");
    }

    public function domainList()
    {
        //获取域名列表
        $domains = $this->pdo->selectAll("select a.*,(select count(record_id) from pre_records where domain_id = a.domain_id) as count from pre_domains as a ");
        $this->assign("domains",$domains);

        $this->assign("webTitle","域名列表");
        return $this->fetch("domainList");
    }
    public function apiSet()
    {
        if (I("post.action") == "dnspod"){
            $key1 = I("post.DnspodTokenID");
            $key2 = I("post.DnspodToken");
            if( !is_numeric($key1) || $key1<10000){
                $this->assign("alert",sweetAlert("温馨提示","TokenID格式错误！","warning"));
            }elseif (strlen($key2) != 32) {
                $this->assign("alert",sweetAlert("温馨提示","Token格式错误！","warning"));
            }else{
                $klsfDns = new Dnspod($key1,$key2);
                if (!$klsfDns->checkToken()){
                    $this->assign("alert",sweetAlert("温馨提示","Token验证失败，请重新填写！","warning"));
                }else{
                    $sql = "insert into pre_configs set `vkey`=:k,`value`=:v on duplicate key update `value`=:v";
                    $this->pdo->execute($sql,array(":k"=>"DnspodTokenID",":v"=>$key1));
                    $this->pdo->execute($sql,array(":k"=>"DnspodToken",":v"=>$key2));
                    $this->assign("alert",sweetAlert("验证成功","Dnspod Token验证成功并已成功保存！","success",U("apiSet")));
                }
            }
        }elseif (I("post.action") == "aliyun"){
            $key1 = I("post.AliyunAccessKeyId");
            $key2 = I("post.AliyunAccessKeySecret");
            if (strlen($key1) != 16){
                $this->assign("alert",sweetAlert("温馨提示","AccessKeyId格式错误！","warning"));
            }elseif (strlen($key2) != 30) {
                $this->assign("alert",sweetAlert("温馨提示","AccessKeySecret格式错误！","warning"));
            }else{
                $klsfDns = new Aliyun($key1,$key2);
                if (!$klsfDns->checkToken()){
                    $this->assign("alert",sweetAlert("温馨提示","Token验证失败，请重新填写！","warning"));
                }else{
                    $sql = "insert into pre_configs set `vkey`=:k,`value`=:v on duplicate key update `value`=:v";
                    $this->pdo->execute($sql,array(":k"=>"AliyunAccessKeyId",":v"=>$key1));
                    $this->pdo->execute($sql,array(":k"=>"AliyunAccessKeySecret",":v"=>$key2));
                    $this->assign("alert",sweetAlert("验证成功","Aliyun Token验证成功并已成功保存！","success",U("apiSet")));
                }
            }
        }elseif (I("post.action") == "cloudxns"){
            $key1 = I("post.CloudXnsApiKey");
            $key2 = I("post.CloudXnsSecretKey");
            if (strlen($key1) != 32){
                $this->assign("alert",sweetAlert("温馨提示","API KEY格式错误！","warning"));
            }elseif (strlen($key2) != 16) {
                $this->assign("alert",sweetAlert("温馨提示","SECRET KEY格式错误！","warning"));
            }else{
                $klsfDns = new CloudXNS($key1,$key2);
                if (!$klsfDns->checkToken()){
                    $this->assign("alert",sweetAlert("温馨提示","Token验证失败，请重新填写！","warning"));
                }else{
                    $sql = "insert into pre_configs set `vkey`=:k,`value`=:v on duplicate key update `value`=:v";
                    $this->pdo->execute($sql,array(":k"=>"CloudXnsApiKey",":v"=>$key1));
                    $this->pdo->execute($sql,array(":k"=>"CloudXnsSecretKey",":v"=>$key2));
                    $this->assign("alert",sweetAlert("验证成功","CloudXNS Token验证成功并已成功保存！","success",U("apiSet")));
                }
            }
        }

        $this->assign("webTitle","API配置");
        return $this->fetch("apiSet");
    }

    public function webSet()
    {
        if (I("post.action" == "set")){
            unset($_POST['action']);
            if($_POST['webAdmin']){
                $_POST['webAdmin'] = md5Pwd($_POST['webAdmin']);
            }else{
                unset($_POST['webAdmin']);
            }
            $sql = "insert into pre_configs set `vkey`=:k,`value`=:v on duplicate key update `value`=:v";
            foreach ($_POST as $k=>$v){
                $this->pdo->execute($sql,array(":k"=>$k,":v"=>$v));
            }
            $this->assign("alert",sweetAlert("保存成功","网站配置保存成功！","warning",U("webSet")));
        }
        $this->assign("webTitle","网站配置");
        return $this->fetch("webSet");
    }
    public function index()
    {
        return $this->fetch();
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
     * 判断是否登录
     */
    protected function isAdmin()
    {
        $webAdmin = Cookie::get("webAdmin");
        if(empty($webAdmin) || $webAdmin !== C("webAdmin")){
            header("Location:".U("index/Index/adminLogin"));
            exit();
        }
    }

    function __construct()
    {
        parent::__construct();
        $this->pdo = new PdoHelper();
        $this->loadWebConfig();
        $this->isAdmin();
    }
}