<?php

/**
* dnspod api 调用类
*/
namespace Dnsapi;

use Dnsapi\Dns;

class CloudXNS implements Dns{

	public $code;      //错误码
	public $errorMsg;  //错误信息

	public static $apiurl='https://www.cloudxns.net/api2/';
	public static $recordList;
	public static $date;  //错误信息
	public static $apiKey;
	public static $secretKey;

	public function __construct($apiKey=null, $secretKey=null) {
		if(empty($apiKey)) $apiKey=config('CloudXnsApiKey');
		if(empty($secretKey)) $secretKey=config('CloudXnsSecretKey');
		self::$apiKey=$apiKey;
		self::$secretKey=$secretKey;
		self::$date=date('r', time());
	}

	/*
	* 验证Token
	*/
	public function checkToken(){
		$url=self::$apiurl.'type';
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
			return true;
		}
	}

	/**
	* 修改记录
	*/
	public function updateRecord($domain_id,$record_id,$name,$type,$value){
		$url=self::$apiurl.'record/'.$record_id;
		$params['domain_id']=$domain_id;
		$params['host']=$name;
		$params['type']=$type;
		$params['value']=$value;
		$data=json_encode($params);
		$doHash=self::doHash($url,$data);
		$json = self::getCurl($url,$data,$doHash,array('type'=>'PUT'));
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['code'] != 1){
			$this->errorMsg = $this->getCode($arr['code'],$arr['message']);
			return false;
		}else{
			return array('record_id'=>$arr['data']['id']);
		}
	}
	/**
	* 删除记录
	*/
	public function delRecord($domain_id,$record_id){
		$url=self::$apiurl.'record/'.$record_id.'/'.$domain_id;
		$data=null;
		$doHash=self::doHash($url,$data);
		$json = self::getCurl($url,$data,$doHash,array('type'=>'DELETE'));
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif($arr['code'] != 1){
			$this->errorMsg = $this->getCode($arr['code'],$arr['message']);
			return false;
		}else{
			return true;
		}
	}
	/**
	* 获取记录信息
	*/
	public function getRecordInfo($domain_id,$record_id){
		if(empty(self::$recordList)){
			$list=$this->getRecordList($domain_id);
			self::$recordList=$list;
		}else{
			$list=self::$recordList;
		}
		if(empty($list)){
			return false;
		}else{
			if(isset($list["$record_id"])){
				return $list["$record_id"];
			}else{
				
				$this->errorMsg='记录不存在';
				return false;
			}
		}
	}

	/**
	* 获取记录列表
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

	/**
	* 添加纪录
	*/
	public function addRecord($domain_id,$name,$type,$value,$domain=null){
		$url=self::$apiurl.'record';
		$params['domain_id']=$domain_id;
		$params['host']=$name;
		$params['type']=$type;
		$params['value']=$value;
		$params['line_id']=1;
		$data=json_encode($params);
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
			$re['record_id']=$arr['record_id'][0];
			$re['name']=$name;
			return $re;
		}
	}

	/*
	* 获取域名信息
	*/
	public function getDomainInfo($domain){
		if($list=$this->getDomainList()){
			foreach ($list as $value) {
				if($value['name'] == trim($domain)){
					$arr['domain_id']=$value['id'];
					$arr['name']=$value['name'];
					return $arr;

				}
			}
			$this->errorMsg='域名不存在';
			return false;


		}else{
			return false;
		}
	}
	/*
	* 获取域名列表
	*/
	public function getDomainList(){
		$url=self::$apiurl.'domain';
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
				$nre=array();
				$nre['id']=$value['id'];
				$nre['name']=$value['domain'];
				$re[]=$nre;
			}
			return $re;
		}
	}
	public function getCode($code,$message=null){
		$this->$code=$code;
		switch ($code){
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
				if(empty($message)){
					return $code;
				}else{
					return $message;
				}
	  
		}
	}

	protected static function doHash($url='',$data='') {
        return md5(self::$apiKey . $url . $data . self::$date . self::$secretKey);
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