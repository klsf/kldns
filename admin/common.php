<?php
require_once('../inc/conn.php');
require_once('../inc/Dnsapi/Dnsapi.class.php');

if(!isset($_COOKIE['kldns_webAdmin']) || $_COOKIE['kldns_webAdmin']!==config('webAdmin')){
	exit("<script>window.location.href='login.php';</script>");
}
