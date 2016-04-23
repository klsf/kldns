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
 * Dnspod域名解析API处理类
 * Class dnspod
 *
 * @package app\util\dnsApi
 */
class Dnspod implements DnsApi
{
    const NET_ERROR = '网络繁忙';
    private $tokenId;
    private $token;
    private $comPost;
    private $errorInfo = array();

    function __construct($_tokenId,$_token)
    {
        $this->tokenId=$_tokenId;
        $this->token=$_token;
        $this->comPost='login_token='.$_tokenId.','.$_token.'&format=json';
    }

    public function getErrorInfo()
    {
        return $this->errorInfo;
    }

    public function checkToken()
    {
        $apiUrl = 'https://dnsapi.cn/Info.Version';
        $post = $this->comPost;
        $json = self::getCurl($apiUrl,$post);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['status']['code'] == 1){
            return true;
        }else{
            $this->errorInfo['code'] = $arr['status']['code'];
            return false;
        }
    }

    public function getDomainList()
    {
        $apiUrl = 'https://dnsapi.cn/Domain.List';
        $post = $this->comPost;
        $json = self::getCurl($apiUrl,$post);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorMsg = self::NET_ERROR;
            return false;
        }elseif($arr['status']['code'] != 1){
            $this->errorInfo['code'] = $arr['status']['code'];
            $this->errorInfo['msg'] = self::getDomainListCode($arr['status']['code']);
            return false;
        }else{
            $re=array();
            foreach ($arr['domains'] as $value) {
                $nre=array();
                $nre['id']=$value['id'];
                $nre['name']=$value['name'];
                $re[]=$nre;
            }
            return $re;
        }
    }
    private static function getDomainListCode($_code){
        switch ($_code){
            case 6:
                return '记录开始的偏移无效';
                break;
            case 7:
                return '共要获取的记录的数量无效';
                break;
            case 9:
                return '没有任何域名';
                break;
            default:
                return $_code;

        }
    }

    public function getDomainInfo($_domain)
    {
        $apiUrl = 'https://dnsapi.cn/Domain.Info';
        $post = $this->comPost.'&domain='.$_domain;
        $json = self::getCurl($apiUrl,$post);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['status']['code'] != 1){
            $this->errorInfo['code'] = $arr['status']['code'];
            $this->errorInfo['msg'] = self::getDomainInfoCode($arr['status']['code']);
            return false;
        }else{
            $re=array();
            $re['domain_id']=$arr['domain']['id'];
            $re['name']=$arr['domain']['name'];
            return $re;
        }
    }
    private static function getDomainInfoCode($_code){
        switch ($_code){
            case 6:
                return '域名ID错误';
                break;
            case 8:
                return '非域名所有者';
                break;
            case -7:
                return ' 企业账号的域名需要升级才能设置';
                break;
            case -8:
                return ' 代理名下用户的域名需要升级才能设置';
                break;
            default:
                return $_code;
        }
    }

    public function addRecord($_domain_id, $_name, $_type, $_value, $_domain = null)
    {
        $apiUrl = 'https://dnsapi.cn/Record.Create';
        $post = $this->comPost.'&domain_id='.$_domain_id.'&sub_domain='.$_name.'&record_type='.$_type.'&record_line='.urlencode('默认').'&value='.$_value;
        $json = self::getCurl($apiUrl,$post);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['status']['code'] != 1){
            $this->errorInfo['code'] = $arr['status']['code'];
            $this->errorInfo['msg'] = self::addRecordCode($arr['status']['code']);
            return false;
        }else{
            $record=$arr['record'];
            $re=array();
            $re['record_id']=$record['id'];
            $re['name']=$record['name'];
            return $re;
        }
    }
    private static function addRecordCode($_code)
    {
        switch ($_code){
            case -15:
                return '域名已被封禁';
                break;
            case -7:
                return '企业账号的域名需要升级才能设置';
                break;
            case -8:
                return '代理名下用户的域名需要升级才能设置';
                break;
            case 6:
                return '缺少参数或者参数错误';
                break;
            case 7:
                return '不是域名所有者或者没有权限';
                break;
            case 8:
                return '记录ID错误';
                break;
            case 17:
                return '记录和记录值都不能为空';
                break;
            case 21:
                return '域名被锁定';
                break;
            case 22:
                return '子域名不合法';
                break;
            case 23:
                return '子域名级数超出限制';
                break;
            case 24:
                return '泛解析子域名错误';
                break;
            case 25:
                return '此记录已被使用';
                break;
            case 26:
                return '记录线路错误';
                break;
            case 27:
                return '记录类型错误';
                break;
            case 30:
                return 'MX 值错误，1-20';
                break;
            case 31:
                return '此记录已被使用';
                break;
            case 32:
                return '记录的TTL值超出了限制';
                break;
            case 33:
                return 'AAAA 记录数超出限制';
                break;
            case 34:
                return '记录值非法';
                break;
            case 35:
                return '添加的IP不允许';
                break;
            case 36:
                return '@主机的NS纪录只能添加默认线路';
                break;
            case 82:
                return '不能添加黑名单中的IP';
                break;
            default:
                return $_code;

        }
    }

    public function getRecordInfo($_domain_id, $_record_id)
    {
        $apiUrl = 'https://dnsapi.cn/Record.Info';
        $post = $this->comPost.'&domain_id='.$_domain_id.'&record_id='.$_record_id;
        $json = self::getCurl($apiUrl,$post);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['status']['code'] != 1){
            $this->errorInfo['code'] = $arr['status']['code'];
            $this->errorInfo['msg'] = self::getRecordInfoCode($arr['status']['code']);
            return false;
        }else{
            $record=$arr['record'];
            $re=array();
            $re['record_id']=$record['id'];
            $re['name']=$record['sub_domain'];
            $re['type']=$record['record_type'];
            $re['value']=$record['value'];
            $re['domain']=$arr['domain']['domain'];
            return $re;
        }
    }
    private static function getRecordInfoCode($_code)
    {
        switch ($_code){
            case -7:
                return '企业账号的域名需要升级才能设置';
                break;
            case -8:
                return '代理名下用户的域名需要升级才能设置';
                break;
            case 6:
                return '域名ID错误';
                break;
            case 7:
                return '记录开始的偏移无效';
                break;
            case 8:
                return '记录ID错误';
                break;
            case -15:
                return '域名已被封禁';
                break;
            case 21:
                return '域名被锁定';
                break;
            default:
                return $_code;

        }
    }

    public function delRecord($_domain_id, $_record_id)
    {
        $apiUrl = 'https://dnsapi.cn/Record.Remove';
        $post = $this->comPost.'&domain_id='.$_domain_id.'&record_id='.$_record_id;
        $json = self::getCurl($apiUrl,$post);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['status']['code'] != 1){
            $this->errorInfo['code'] = $arr['status']['code'];
            $this->errorInfo['msg'] = self::getRecordInfoCode($arr['status']['code']);
            return false;
        }else{
            return true;
        }
    }

    public function updateRecord($_domain_id, $_record_id, $_name, $_type, $_value)
    {
        $apiUrl = 'https://dnsapi.cn/Record.Modify';
        $post = $this->comPost.'&domain_id='.$_domain_id.'&record_id='.$_record_id.'&sub_domain='.$_name.'&record_type='.$_type.'&record_line='.urlencode('默认').'&value='.$_value;
        $json = self::getCurl($apiUrl,$post);
        $arr = json_decode($json,true);
        if(empty($arr)){
            $this->errorInfo['msg'] = self::NET_ERROR;
            return false;
        }elseif($arr['status']['code'] != 1){
            $this->errorInfo['code'] = $arr['status']['code'];
            $this->errorInfo['msg'] = self::addRecordCode($arr['status']['code']);
            return false;
        }else{
            return array('record_id'=>$arr['record']['id']);
        }
    }

    private static function getCurl($_url,$_post=null,$_arr=array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$_url);
        curl_setopt($ch, CURLOPT_TIMEOUT,60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $header[] = "Accept:*";
        $header[] = "Accept-Encoding:gzip,deflate,sdch";
        $header[] = "Accept-Language:zh-CN,zh;q=0.8";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        if(strlen($_url) > 5 && strtolower(substr($_url,0,5)) == "https" ) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        if($_post != null){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $_post);
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
        if(isset($_arr['ua']) and $_arr['ua']){
            curl_setopt($ch, CURLOPT_USERAGENT,$_arr['ua']);
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