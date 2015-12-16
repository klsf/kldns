<?php
require_once('conn.php');
require_once('Dnsapi/Dnsapi.class.php');

//加载网站配置
$query=$db->query("SELECT * FROM `kldns_configs`");
while($row = $query->fetch(PDO::FETCH_ASSOC)){
	config($row['vkey'],$row['value']);
}

if(isset($_COOKIE['kldns_sid']) && $usersid = $_COOKIE['kldns_sid']){
	$sql = 'SELECT * FROM `kldns_users` WHERE `sid`=:sid limit 1';
	$stmt = $db->prepare($sql);
	$stmt->execute(array(':sid'=>$usersid));
	$loginuser = $stmt->fetch(PDO::FETCH_ASSOC);
}