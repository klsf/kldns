<?php
/**
* 域名解析api结合类  
*/
include_once('Dns.class.php');
include_once('Dnspod.class.php');
include_once('Aliyun.class.php');
include_once('CloudXNS.class.php');


use Dnsapi\Dnspod;
use Dnsapi\Aliyun;
use Dnsapi\CloudXNS;

class Dnsapi{
	public static $api;

	public static function getApi($dns='dnspod'){
		if(isset(self::$api[$dns])){
			return self::$api[$dns];
		}
		switch ($dns){
		case 'aliyun':
			self::$api['aliyun']=new Aliyun();
			break;
		case 'cloudxns':
			self::$api['cloudxns']=new CloudXNS();
			break;
		default:
			$dns='dnspod';
			self::$api['dnspod']=new Dnspod();
		}
		return self::$api[$dns];
	}
}
