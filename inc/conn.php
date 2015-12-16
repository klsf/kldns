<?php
@header('Content-Type: text/html; charset=UTF-8');

require_once('functions.php');
if(!$config=include_once('config.php')){
	header("Location:/install");
	exit();
}
$mysql=$config['db'];
try{
	$db=new PDO("mysql:host=".$mysql['host'].";dbname=".$mysql['database'].";port=".$mysql['port'],$mysql['username'],$mysql['password']);
}catch(Exception $e){
	exit('链接数据库失败:'.$e->getMessage());
}
$db->exec("set names utf8");

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