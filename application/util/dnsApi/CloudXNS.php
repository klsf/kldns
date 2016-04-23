<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------

namespace app\util\dnsApi;

/**
 * cloudXNS域名解析API处理类
 * Class cloudXNS
 *
 * @package app\util\dnsApi
 */
class CloudXNS implements DnsApi
{
    const NET_ERROR = '网络繁忙';
    private $errorInfo = array();

    private static $apiurl='https://www.cloudxns.net/api2/';
    private static $recordList;
    private static $date;
    private static $apiKey;
    private static $secretKey;

    public function __construct($apiKey=null, $secretKey=null) {
        self::$apiKey = $apiKey;
        self::$secretKey = $secretKey;
        self::$date = date('r', time());
    }

    public function checkToken()
    {
        $url = self::$apiurl.'type';
        $data = null;
        $doHash = self::doHash($url,$data);
        $json = self::getCurl($url,$data,$doHash);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['code'] != 1){
            $this->errorInfo['code'] = $arr['code'];
            $this->errorInfo['msg'] = self::getCode($arr['code'],$arr['message']);
            return false;
        }else{
            return true;
        }
    }

    public function getDomainList()
    {
        $url = self::$apiurl.'domain';
        $data = null;
        $doHash = self::doHash($url,$data);
        $json = self::getCurl($url,$data,$doHash);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['code'] != 1){
            $this->errorInfo['code'] = $arr['code'];
            $this->errorInfo['msg'] = self::getCode($arr['code'],$arr['message']);
            return false;
        }else{
            $re=array();
            foreach ($arr['data'] as $value) {
                $nre=array();
                $nre['id']=$value['id'];
                $nre['name']=$value['domain'];
                $re[]=$nre;
            }
            return $re;
        }
    }

    public function getDomainInfo($_domain)
    {
        if($list=$this->getDomainList()){
            foreach ($list as $value) {
                if($value['name'] == trim($_domain)){
                    $arr['domain_id']=$value['id'];
                    $arr['name']=$value['name'];
                    return $arr;
                }
            }
            $this->errorInfo['msg'] = '域名不存在';
            return false;
        }else{
            return false;
        }
    }

    public function addRecord($_domain_id, $_name, $_type, $_value, $_domain = null)
    {
        $url = self::$apiurl.'record';
        $params['domain_id'] = $_domain_id;
        $params['host'] = $_name;
        $params['type'] = $_type;
        $params['value'] = $_value;
        $params['line_id'] = 1;
        $data=json_encode($params);
        $doHash=self::doHash($url,$data);
        $json = self::getCurl($url,$data,$doHash);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['code'] != 1){
            $this->errorInfo['code'] = $arr['code'];
            $this->errorInfo['msg'] = self::getCode($arr['code'],$arr['message']);
            return false;
        }else{
            $re=array();
            $re['record_id']=$arr['record_id'][0];
            $re['name']=$_name;
            return $re;
        }
    }

    public function getRecordInfo($_domain_id, $_record_id)
    {
        if(empty(self::$recordList)){
            $list = $this->getRecordList($_domain_id);
            self::$recordList = $list;
        }else{
            $list = self::$recordList;
        }
        if(empty($list)){
            return false;
        }else{
            if(isset($list["$_record_id"])){
                return $list["$_record_id"];
            }else{
                $this->errorInfo['msg'] = '记录不存在';
                return false;
            }
        }
    }

    /**
     * 获取记录列表
     * @param $domain_id
     *
     * @return array|bool
     */
    public function getRecordList($domain_id){
        $url=self::$apiurl.'record/'.$domain_id.'?host_id=0&offset=0&row_num=1000';
        $data=null;
        $doHash=self::doHash($url,$data);
        $json = self::getCurl($url,$data,$doHash);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorMsg = '解析JSON数据失败！';
            return false;
        }elseif($arr['code'] != 1){
            $this->errorMsg = $this->getCode($arr['code'],$arr['message']);
            return false;
        }else{
            $re=array();
            foreach ($arr['data'] as $value) {
                $record_id=$value['record_id'];
                $nre=array();
                $nre['record_id']=$record_id;
                $nre['name']=$value['host'];
                $nre['type']=$value['type'];
                $nre['value']=$value['value'];
                $nre['domain']=null;
                $re["$record_id"]=$nre;
            }
            return $re;
        }

    }

    public function delRecord($_domain_id, $_record_id)
    {
        $url = self::$apiurl.'record/'.$_record_id.'/'.$_domain_id;
        $data = null;
        $doHash = self::doHash($url,$data);
        $json = self::getCurl($url,$data,$doHash,array('type'=>'DELETE'));
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['code'] != 1){
            $this->errorInfo['code'] = $arr['code'];
            $this->errorInfo['msg'] = self::getCode($arr['code'],$arr['message']);
            return false;
        }else{
            return true;
        }
    }

    public function updateRecord($_domain_id, $_record_id, $_name, $_type, $_value)
    {
        $url = self::$apiurl.'record/'.$_record_id;
        $params['domain_id'] = $_domain_id;
        $params['host'] = $_name;
        $params['type'] = $_type;
        $params['value'] = $_value;
        $data = json_encode($params);
        $doHash = self::doHash($url,$data);
        $json = self::getCurl($url,$data,$doHash,array('type'=>'PUT'));
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['code'] != 1){
            $this->errorInfo['code'] = $arr['code'];
            $this->errorInfo['msg'] = self::getCode($arr['code'],$arr['message']);
            return false;
        }else{
            return array('record_id'=>$arr['data']['id']);
        }
    }

    public function getErrorInfo()
    {
        return $this->errorInfo;
    }

    private static function getCode($_code){
        switch ($_code){
            case 2:
                return '遇到错误,请重试';
                break;
            case 4:
                return '域名审核尚未通过';
                break;
            case 5:
                return '域名已被管理员禁止';
                break;
            case 6:
                return '域名已被用户停用';
                break;
            case 7:
                return '域名已被用户锁定';
                break;
            case 22:
                return '记录类型错误';
                break;
            case 23:
                return 'IP地址错误';
                break;
            case 25:
                return '必须填写正确格式的域名';
                break;
            case 26:
                return 'AAAA地址错误';
                break;
            case 30:
                return '该主机记录已被锁定';
                break;
            case 34:
                return '该记录已存在';
                break;
            case 35:
                return '存在互斥记录';
                break;
            case 100:
                return '主机名不合法';
                break;
            case 112:
                return '记录值不能为空';
                break;
            case 300:
                return '参数不正确';
                break;
            case 638:
                return '域名不存在';
                break;
            case 302:
                return '请求消息体格式错误';
                break;

            default:
                return $_code;

        }
    }

    protected static function doHash($_url= "",$_data = "") {
        return md5(self::$apiKey . $_url . $_data . self::$date . self::$secretKey);
    }


    private static function getCurl($url,$post=null,$doHash,$arr=array()){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_TIMEOUT,60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $header[] = "Accept:*";
        $header[] = "Accept-Encoding:gzip,deflate,sdch";
        $header[] = "Accept-Language:zh-CN,zh;q=0.8";
        $header[] = "API-KEY:".self::$apiKey;
        $header[] = "API-REQUEST-DATE:".self::$date;
        $header[] = "API-HMAC:".$doHash;
        $header[] = "API-FORMAT:json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        if(isset($arr['type'])){
            switch ($arr['type']){
                case "GET" :
                    curl_setopt($ch, CURLOPT_HTTPGET, true);
                    break;
                case "POST":
                    curl_setopt($ch, CURLOPT_POST,true);
                    break;
                case "PUT" :
                    curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    break;
                case "DELETE":
                    curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                    break;
            }
            if($post != null){
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            }
        }else{
            if($post != null){
                curl_setopt($ch, CURLOPT_POST,true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            }else{
                curl_setopt($ch, CURLOPT_HTTPGET, true);
            }
        }
        if(isset($arr['header']) and $arr['header']){
            curl_setopt($ch, CURLOPT_HEADER, TRUE);
        }
        if(isset($arr['cookie']) and $arr['cookie']){
            curl_setopt($ch, CURLOPT_COOKIE, $arr['cookie']);
        }
        if(isset($arr['refer']) and $arr['refer']){
            curl_setopt($ch, CURLOPT_REFERER, $arr['refer']);
        }
        if(isset($arr['ua']) and $arr['ua']){
            curl_setopt($ch, CURLOPT_USERAGENT,$arr['ua']);
        }else{
            curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Linux; U; Android 4.0.4; es-mx; HTC_One_X Build/IMM76D) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0');
        }
        if(isset($arr['nobody']) and $arr['nobody']){
            curl_setopt($ch, CURLOPT_NOBODY,1);
        }
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

}