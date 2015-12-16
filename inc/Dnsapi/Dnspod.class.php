<?php

/**
* dnspod api 调用类
*/
namespace Dnsapi;

use Dnsapi\Dns;

class Dnspod implements Dns{

	public static $code;      //错误码
	public $errorMsg;  //错误信息
	public $tokenId;
	public $token;
	private $comPost;

	public function __construct($_tokenId=null, $_token=null) {
		if(empty($_tokenId)) $_tokenId=config('DnspodTokenID');
		if(empty($_token)) $_token=config('DnspodToken');
		$this->tokenId=$_tokenId;
		$this->token=$_token;
		$this->comPost='login_token='.$_tokenId.','.$_token.'&format=json';
	}
	
	/*
	* 验证Token
	*/
	public function checkToken(){
		$apiUrl = 'https://dnsapi.cn/Info.Version';
		$post = $this->comPost;
		$json = self::getCurl($apiUrl,$post);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['status']['code'] == 1){
			return true;
		}else{
			return false;
		}
	}

	/*
	* 获取域名信息
	*/
	public function getDomainInfo($domain){
		$apiUrl = 'https://dnsapi.cn/Domain.Info';
		$post = $this->comPost.'&domain='.$domain;
		$json = self::getCurl($apiUrl,$post);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['status']['code'] != 1){
			$this->errorMsg = self::getDomainInfoCode($arr['status']['code']);
			return false;
		}else{
			$re=array();
			$re['domain_id']=$arr['domain']['id'];
			$re['name']=$arr['domain']['name'];
			return $re;
		}
	}
	public static function getDomainInfoCode($code){
		self::$code=$code;
		switch ($code){
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
				return $code;
			  
		}
	}

	/*
	* 获取域名列表
	*/
	public function getDomainList(){
		$apiUrl = 'https://dnsapi.cn/Domain.List';
		$post = $this->comPost;
		$json = self::getCurl($apiUrl,$post);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['status']['code'] != 1){
			$this->errorMsg = self::getDomainListCode($arr['status']['code']);
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
	public static function getDomainListCode($code){
		self::$code=$code;
		switch ($code){
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
				return $code;
			  
		}
	}

	/**
	* 修改记录
	*/
	public function updateRecord($domain_id,$record_id,$name,$type,$value){
		$apiUrl = 'https://dnsapi.cn/Record.Modify';
		$post = $this->comPost.'&domain_id='.$domain_id.'&record_id='.$record_id.'&sub_domain='.$name.'&record_type='.$type.'&record_line='.urlencode('默认').'&value='.$value;
		$json = self::getCurl($apiUrl,$post);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['status']['code'] != 1){
			$this->errorMsg = self::addRecordCode($arr['status']['code']);
			return false;
		}else{
			return array('record_id'=>$arr['record']['id']);
		}
	}


	/**
	* 删除记录
	*/
	public function delRecord($domain_id,$record_id){
		$apiUrl = 'https://dnsapi.cn/Record.Remove';
		$post = $this->comPost.'&domain_id='.$domain_id.'&record_id='.$record_id;
		$json = self::getCurl($apiUrl,$post);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['status']['code'] != 1){
			$this->errorMsg = self::getRecordInfoCode($arr['status']['code']);
			return false;
		}else{
			return true;
		}
	}
		

	/**
	* 获取记录信息
	*/
	public function getRecordInfo($domain_id,$record_id){
		$apiUrl = 'https://dnsapi.cn/Record.Info';
		$post = $this->comPost.'&domain_id='.$domain_id.'&record_id='.$record_id;
		$json = self::getCurl($apiUrl,$post);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['status']['code'] != 1){
			$this->errorMsg = self::getRecordInfoCode($arr['status']['code']);
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
	public static function getRecordInfoCode($code){
		self::$code=$code;
		switch ($code){
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
				return $code;
			  
		}
	}

	
	/**
	* 添加纪录
	*/
	public function addRecord($domain_id,$name,$type,$value,$domain=null){
		$apiUrl = 'https://dnsapi.cn/Record.Create';
		$post = $this->comPost.'&domain_id='.$domain_id.'&sub_domain='.$name.'&record_type='.$type.'&record_line='.urlencode('默认').'&value='.$value;
		$json = self::getCurl($apiUrl,$post);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['status']['code'] != 1){
			$this->errorMsg = self::addRecordCode($arr['status']['code']);
			return false;
		}else{
			$record=$arr['record'];
			$re=array();
			$re['record_id']=$record['id'];
			$re['name']=$record['name'];
			return $re;
		}
	}
	public static function addRecordCode($code){
		self::$code=$code;
		switch ($code){
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
				return $code;
			  
		}
	}

	/**
	* 获取记录列表
	*/
	public function getRecordList($domain_id){
		$apiUrl = 'https://dnsapi.cn/Record.List';
		$post = $this->comPost.'&domain_id='.$domain_id;
		$json = self::getCurl($apiUrl,$post);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['status']['code'] != 1){
			$this->errorMsg = self::getRecordCode($arr['status']['code']);
			return false;
		}else{
			$re=array();
			foreach ($arr['records'] as $value) {
				$nre=array();
				$nre['id']=$value['id'];
				$nre['name']=$value['name'];
				$nre['type']=$value['type'];
				$nre['update']=$value['updated_on'];
				$re[]=$nre;
			}
			return $re;
		}
	}
	public static function getRecordListCode($code){
		self::$code=$code;
		switch ($code){
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
				return '共要获取的记录的数量无效';
				break;
			case 9:
				return '不是域名所有者';
				break;
			case 10:
				return '没有记录';
				break;
			default:
				return $code;
			  
		}
	}


	private static function getCurl($url,$post=null,$arr=array()){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_TIMEOUT,60);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$header[] = "Accept:*";
		$header[] = "Accept-Encoding:gzip,deflate,sdch";
		$header[] = "Accept-Language:zh-CN,zh;q=0.8";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		if(strlen($url) > 5 && strtolower(substr($url,0,5)) == "https" ) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}
		if($post != null){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
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
			curl_setopt($ch, CURLOPT_USERAGENT,$ua);
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