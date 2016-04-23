<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------

namespace app\index\controller;


use app\util\dnsApi\DnsApi;
use app\util\dnsApi\KlsfDns;
use think\Cookie;

class Ajax extends Klsf
{

    /**
     * 删除用户
     * @param $uid
     */
    public function delUser($uid){
        $this->isAdmin();
        if($this->pdo->execute("delete from pre_users where uid=:uid limit 1",array(":uid"=>$uid))){
            $this->remove("#User_".$uid);
        }else{
            $this->alert(sweetAlert("温馨提示","删除用户失败！","warning"));
        }
    }

    /**
     * 从数据库删除域名
     * @param $id
     */
    public function delDomain($id)
    {
        $this->isAdmin();
        if($this->pdo->execute("delete from pre_domains where domain_id=:id limit 1",array(":id"=>$id))){
            $this->alert(sweetAlert("删除成功","域名删除成功！","success",U("index/Admin/domainList")));
        }else{
            $this->alert(sweetAlert("温馨提示","删除域名失败！","warning"));
        }
    }
    /**
     * 获取域名列表
     * @param $dns
     */
    public function domainList($dns){
        $this->isAdmin();
        if (!in_array($dns,array("dnspod","aliyun","cloudxns"))){
            $this->alert(sweetAlert("温馨提示","解析平台不存在！","warning"));
        }
        $klsfDns = KlsfDns::getApi($dns);
        if($domains = $klsfDns->getDomainList()){
            $list=array();
            foreach ($domains as $value) {
                $list["{$value['id']}"]=$value['name'];
            }
            //过滤已经添加的域名
            $stmt = $this->pdo->getStmt("select domain_id as id from `pre_domains` where dns=:dns",array(":dns"=>$dns));
            while($row = $stmt->fetch()){
                if(isset($list["{$row['id']}"])){
                    unset($list["{$row['id']}"]);
                }
            }
            echo'$("#domainSelect").empty();';//清空select选项
            if(empty($list)){
                $this->alert(sweetAlert("温馨提示","没有可添加的域名！","warning"));
            }
            foreach ($list as $key => $value) {
                echo'$("#domainSelect").append("<option value=\''.$value.'\'>'.$value.'</option>");';
            }
            exit();
        }else{
            $this->alert(sweetAlert("温馨提示","获取域名列表失败，请先确定API配置正确！","warning"));
        }
    }

    /**
     * 删除记录
     * @param        $id
     * @param string $type
     */
    public function delRecord($id,$type = "user")
    {
        if($type == "user"){//用户删除
            $this->isLogin();
            if (!$record = $this->pdo->find("select a.domain_id,b.dns from `pre_records`as a left join `pre_domains` as b on b.domain_id=a.domain_id WHERE a.`uid`=:uid and a.record_id=:id limit 1",array(":uid"=>$this->userInfo['uid'],":id"=>$id))){
                $this->alert(sweetAlert("删除失败","此记录不存在！","warning"));
            }
        }else{//管理员删除
            $this->isAdmin();
            if (!$record = $this->pdo->find("select a.domain_id,b.dns from `pre_records`as a left join `pre_domains` as b on b.domain_id=a.domain_id WHERE a.record_id=:id limit 1",array(":id"=>$id))){
                $this->alert(sweetAlert("删除失败","此记录不存在！","warning"));
            }
        }

        $domain_id = $record['domain_id'];
        $klsfDns = KlsfDns::getApi($record['dns']);
        if ($klsfDns->delRecord($domain_id,$id)){
            if($type == "user"){//用户删除
                $this->pdo->execute("delete from pre_records where record_id=:id and uid=:uid limit 1",array(":uid"=>$this->userInfo['uid'],":id"=>$id));
            }else{//管理员删除
                $this->pdo->execute("delete from pre_records where record_id=:id limit 1",array(":id"=>$id));
            }
            $this->remove('#Record_'.$id);
        }else{
            $info = $klsfDns->getErrorInfo();
            $this->alert(sweetAlert("删除失败",$info['msg'],"warning"));
        }

    }

    /**
     * 弹出警告
     * @param $_msg
     */
    private function alert($_msg)
    {
        exit($_msg);
    }

    /**
     * 移除某个元素
     * @param $_id
     */
    private function remove($_id)
    {
        exit("$('{$_id}').remove();");
    }
    /**
     * 判断是否是管理员
     */
    protected function isAdmin()
    {
        $webAdmin = Cookie::get("webAdmin");
        if(empty($webAdmin) || $webAdmin !== C("webAdmin")){
            $this->alert(sweetAlert("无权限","请先登录管理员账号！","warning"));
        }
    }
    protected function isLogin()
    {
        if(empty($this->userInfo)){
            $this->alert(sweetAlert("未登录","请登陆后操作！","warning"));
        }
    }

}