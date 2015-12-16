<?php
require_once('common.php');
$action = getRequest('action','get');
//删除用户
if($action == 'deluser'){
	$uid = getRequest('uid','get');
	if(!is_numeric($uid)){
		alert('UID格式错误');
	}
	$stmt = $db->prepare('DELETE FROM `kldns_users` WHERE (`uid`=:uid) limit 1');
	if($stmt->execute(array(':uid'=>$uid))){
		remove('#List_'.$uid);
	}else{
		alert('删除用户失败,可能用户已不存在！');
	}
}elseif($action == 'delrecord'){

	$record_id = getRequest('record_id','get');
	if(!is_numeric($record_id)){
		alert('记录ID格式错误');
	}
	$stmt = $db->prepare('SELECT a.domain_id,b.dns FROM `kldns_records`as a left join `kldns_domains` as b on b.domain_id=a.domain_id WHERE a.`uid`=:uid and a.record_id=:id limit 1');
	$stmt->execute(array(':uid'=>$loginuser['uid'],':id'=>$record_id));
	if (!$row=$stmt->fetch(PDO::FETCH_ASSOC)) {
		alert('此记录不存在！');
	}
	$domain_id=$row['domain_id'];
	$dnsApi = Dnsapi::getApi($row['dns']);
	
	if($dnsApi->delRecord($domain_id,$record_id) || $dnsApi->code == 8){
		$stmt = $db->prepare('DELETE FROM `kldns_records` WHERE (`record_id`=:record_id) limit 1');
		$stmt->execute(array(':record_id'=>$record_id));
		remove('#List_'.$record_id);
	}else{
		alert($dnsApi->errorMsg);
	}
}elseif($action=='domainlist'){
	$dns=getRequest('dns','get');
	$dnsApi = Dnsapi::getApi($dns);
	if($dns=='dnspod'){
		if (empty(config('DnspodTokenID')) || empty(config('DnspodToken'))) {
			skip('apiConfig.php','请先配置DnsPod Token');
		}
	}elseif($dns=='aliyun'){
		if (empty(config('AliyunAccessKeyId')) || empty(config('AliyunAccessKeySecret'))) {
			skip('apiConfig.php','请先配置AliYun AccessKey');
		}
	}elseif($dns=='cloudxns'){
		if (empty(config('CloudXnsSecretKey')) || empty(config('CloudXnsSecretKey'))) {
			skip('apiConfig.php','请先配置CloudXNS API KEY');
		}
	}else{
		alert('暂时没有'.$dns.'平台的API');
	}
	if($row = $dnsApi->getDomainList()){
		$list=array();
		foreach ($row as $value) {
			$list["{$value['id']}"]=$value['name'];
		}
		//过滤已经添加的域名
		$stmt=$db->prepare('SELECT domain_id as id FROM `kldns_domains` WHERE dns=:dns');//获取域名列表
		$stmt->execute(array(':dns'=>$dns));
		while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
			if(isset($list["{$row['id']}"])){
				unset($list["{$row['id']}"]);
			}
		}
		echo'$("#domainSelect").empty();';
		if(empty($list)){
			alert('没有待添加的域名');
		}
		foreach ($list as $key => $value) {
			echo'$("#domainSelect").append("<option value=\''.$value.'\'>'.$value.'</option>");'; 
		}
		exit();
	}else{
		alert($list->errorMsg);
	}
}





function alert($msg){
	exit("alert('{$msg}');");
}
function remove($id){
	exit("$('{$id}').remove();");
}
function skip($url,$msg=null){
	if(!empty($msg)){
		echo "alert('{$msg}');";
	}
	exit("window.location.href='{$url}';");
}
