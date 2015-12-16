<?php

/**
* dnspod api 调用类
*/
namespace Dnsapi;

use Dnsapi\Dns;

class Aliyun implements Dns{

	public $code;      //错误码
	public $errorMsg;  //错误信息
	public static $accessKeyId;

	public static $accessKeySecret;

	public static $serverUrl = "https://dns.aliyuncs.com/";

	protected static $signatureMethod = "HMAC-SHA1";

	protected static $signatureVersion = "1.0";
	
    protected static  $dateTimeFormat = 'Y-m-d\TH:i:s\Z'; // ISO8601规范
	
	protected static $sdkVersion = "1.0";

	public function __construct($accessKeyId=null, $accessKeySecret=null) {
		if(empty($accessKeyId)) $accessKeyId=config('AliyunAccessKeyId');
		if(empty($accessKeySecret)) $accessKeySecret=config('AliyunAccessKeySecret');
		self::$accessKeyId=$accessKeyId;
		self::$accessKeySecret=$accessKeySecret;
	}

	/*
	* 验证Token
	*/
	public function checkToken(){
		$params['Action']='DescribeDomains';
		$json = self::execute($params);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif(isset($arr['Code'])){
			$this->errorMsg = $this->getCode($arr['Code']);
			return false;
		}else{
			return true;
		}
	}

	/**
	* 修改记录
	*/
	public function updateRecord($domain_id,$record_id,$name,$type,$value){
		$params['Action']='UpdateDomainRecord';
		$params['RecordId']=$record_id;
		$params['RR']=$name;
		$params['Type']=$type;
		$params['Value']=$value;
		$json = self::execute($params);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif(isset($arr['Code'])){
			$this->errorMsg = $this->getCode($arr['Code']);
			return false;
		}else{
			return array('record_id'=>$arr['RecordId']);
		}
	}
	/**
	* 删除记录
	*/
	public function delRecord($domain_id,$record_id){
		$params['Action']='DeleteDomainRecord';
		$params['RecordId']=$record_id;
		$json = self::execute($params);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif(isset($arr['Code'])){
			$this->errorMsg = $this->getCode($arr['Code']);
			return false;
		}else{
			return true;
		}
	}
	/**
	* 获取记录信息
	*/
	public function getRecordInfo($domain_id,$record_id){
		$params['Action']='DescribeDomainRecordInfo';
		$params['RecordId']=$record_id;
		$json = self::execute($params);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif(isset($arr['Code'])){
			$this->errorMsg = $this->getCode($arr['Code']);
			return false;
		}else{
			$re=array();
			$re['record_id']=$arr['RecordId'];
			$re['name']=$arr['RR'];
			$re['type']=$arr['Type'];
			$re['value']=$arr['Value'];
			$re['domain']=$arr['DomainName'];
			return $re;
		}
	}
	/**
	* 添加纪录
	*/
	public function addRecord($domain_id,$name,$type,$value,$domain=null){
		$params['Action']='AddDomainRecord';
		$params['RR']=$name;
		$params['Type']=$type;
		$params['Value']=$value;
		$params['DomainName']=$domain;
		$json = self::execute($params);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif(isset($arr['Code'])){
			$this->errorMsg = $this->getCode($arr['Code']);
			return false;
		}else{
			$re=array();
			$re['record_id']=$arr['RecordId'];
			$re['name']=$domain;
			return $re;
		}
	}

	/*
	* 获取域名信息
	*/
	public function getDomainInfo($domain){
		$params['Action']='DescribeDomainInfo';
		$params['DomainName']=$domain;
		$json = self::execute($params);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif(isset($arr['Code'])){
			$this->errorMsg = $this->getCode($arr['Code']);
			return false;
		}else{
			$re=array();
			$re['domain_id']=$arr['DomainId'];
			$re['name']=$arr['DomainName'];
			return $re;
		}
	}
	/*
	* 获取域名列表
	*/
	public function getDomainList(){
		$params['Action']='DescribeDomains';
		$json = self::execute($params);
		$arr = json_decode($json,true);
		if(empty($arr)){
			$this->errorMsg = '解析JSON数据失败！';
			return false;
		}elseif(isset($arr['Code'])){
			$this->errorMsg = $this->getCode($arr['Code']);
			return false;
		}else{
			$re=array();
			foreach ($arr['Domains']['Domain'] as $value) {
				$nre=array();
				$nre['id']=$value['DomainId'];
				$nre['name']=$value['DomainName'];
				$re[]=$nre;
			}
			return $re;
		}
	}
	public function getCode($code){
		$this->$code=$code;
		switch ($code){
			case 'MissingParameter':
				return '缺少参数';
				break;
			case 'InvalidParameter':
				return '参数取值无效';
				break;
			case 'UnsupportedOperation':
				return '无效的接口';
				break;
			case 'NoSuchVersion':
				return '无效的版本';
				break;
			case 'Throttling':
				return '操作被流量控制系统拒绝';
				break;
			case 'InvalidAccessKeyId.NotFound':
				return '无效的Access Key';
				break;
			case 'Forbidden':
				return '操作被禁止';
				break;
			case 'Forbidden.RiskControl':
				return '无效的签名';
				break;
			case 'Forbidden.UserVerification':
				return '无实名验证';
				break;
			case 'InternalError':
				return '服务器无法完成对请求的处理';
				break;
			case 'ServiceUnavailable':
				return '服务器当前无法处理请求';
				break;
			case 'InvalidDomainGroup.NotExist':
				return '域名分组不存在';
				break;
			case 'IncorrectDomainUser':
				return '域名在本账户下不存在';
				break;
			case 'DomainRecordDuplicate':
				return '解析记录已存在';
				break;
			case 'DomainRecordConflict':
				return '解析记录冲突';
				break;
			case 'DomainForbidden':
				return '禁止解析操作的域名';
				break;
			case 'RecordForbidden.DNSChange':
				return 'DNS迁移过程禁止操作';
				break;
			case 'DomainRecordNotBelongToUser':
				return '解析记录在本账户下不存在';
				break;
			case 'DomainForbidden':
				return '禁止解析操作的域名';
				break;
			case 'DomainRecordLocked':
				return '解析记录被锁定';
				break;
			case 'RecordForbidden.BlackHole':
				return '域名解析防护进入黑洞';
				break;
			case 'RecordForbidden.DNSChange':
				return 'DNS迁移过程禁止操作';
				break;

			default:
				return $code;
			  
		}
	}

	public static function execute($apiParams){

		//组装系统参数
		$apiParams["AccessKeyId"] = self::$accessKeyId;
		$apiParams["Format"] = 'json';
		$apiParams["SignatureMethod"] = self::$signatureMethod;
		$apiParams["SignatureVersion"] = self::$signatureVersion;
		$apiParams["SignatureNonce"] = uniqid();
		date_default_timezone_set("GMT");
		$apiParams["TimeStamp"] = date(self::$dateTimeFormat);
		$apiParams["Version"] = '2015-01-09';

		//签名
		$apiParams["Signature"] = self::computeSignature($apiParams, self::$accessKeySecret);
		//系统参数放入GET请求串
		$requestUrl = rtrim(self::$serverUrl,"/") . "/?";
		foreach ($apiParams as $apiParamKey => $apiParamValue)
		{
			$requestUrl .= "$apiParamKey=" . urlencode($apiParamValue) . "&";
		}
		$requestUrl = substr($requestUrl, 0, -1);

		return self::getCurl($requestUrl);
	}
	protected static function percentEncode($str)
	{
	    // 使用urlencode编码后，将"+","*","%7E"做替换即满足 API规定的编码规范
	    $res = urlencode($str);
	    
	    //$res = preg_replace('/\+/', '%20', $res);
	    //$res = preg_replace('/\*/', '%2A', $res);
	    //$res = preg_replace('/%7E/', '~', $res);
	    
	    return $res;
	}
	protected static function computeSignature($parameters, $accessKeySecret)
	{
	    // 将参数Key按字典顺序排序
	    ksort($parameters);
	
	    // 生成规范化请求字符串
	    $canonicalizedQueryString = '';
	    foreach($parameters as $key => $value)
	    {
		$canonicalizedQueryString .= '&' . self::percentEncode($key) 
		    . '=' . self::percentEncode($value);
	    }
	
	    // 生成用于计算签名的字符串 stringToSign
	    $stringToSign = 'GET&%2F&' . self::percentencode(substr($canonicalizedQueryString, 1));
	
	    // 计算签名，注意accessKeySecret后面要加上字符'&'
	    $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));
	    return $signature;
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