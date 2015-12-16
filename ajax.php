<?php
require_once('inc/common.php');
if(!isset($loginuser['uid'])){
	exit("<script>window.location.href='/';</script>");
}

$action = isset($_GET['action'])?$_GET['action']:null;

//删除纪录
if($action == 'delrecord'){
	$record_id = isset($_GET['record_id'])?trim($_GET['record_id']):null;
	if(!$record_id){
		alert('记录ID不能为空！');
	}
	
	$stmt = $db->prepare('SELECT a.domain_id,b.dns FROM `kldns_records`as a left join `kldns_domains` as b on b.domain_id=a.domain_id WHERE a.`uid`=:uid and a.record_id=:id limit 1');
	$stmt->execute(array(':uid'=>$loginuser['uid'],':id'=>$record_id));
	if (!$row=$stmt->fetch(PDO::FETCH_ASSOC)) {
		alert('此记录不存在！');
	}
	$domain_id=$row['domain_id'];
	$dnsApi = Dnsapi::getApi($row['dns']);
	if($dnsApi->delRecord($domain_id,$record_id)){
		$db->exec("DELETE FROM `kldns_records` WHERE (`record_id`='".$record_id."' and `uid`='".$loginuser['uid']."')");
		remove('#Record_'.$record_id);
	}else{
		alert($dnsApi->errorMsg);
	}
}





function alert($msg){
	exit("alert('{$msg}');");
}
function remove($id){
	exit("$('{$id}').remove();");
}
