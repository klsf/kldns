<?php

//公共函数库

function config($name=null, $value=null,$default=null) {
    static $_config = array();
    // 无参数时获取所有
    if (empty($name)) {
        return $_config;
    }
    // 优先执行设置获取或赋值
    if (is_string($name)) {
        if (!strpos($name, '.')) {
            $name = strtoupper($name);
            if (is_null($value))
                return isset($_config[$name]) ? $_config[$name] : $default;
            $_config[$name] = $value;
            return null;
        }
        // 二维数组设置和获取支持
        $name = explode('.', $name);
        $name[0]   =  strtoupper($name[0]);
        if (is_null($value))
            return isset($_config[$name[0]][$name[1]]) ? $_config[$name[0]][$name[1]] : $default;
        $_config[$name[0]][$name[1]] = $value;
        return null;
    }
    // 批量设置
    if (is_array($name)){
        $_config = array_merge($_config, array_change_key_case($name,CASE_UPPER));
        return null;
    }
    return null; // 避免非法参数
}


function getRequest($action,$type='request'){
	switch($type){
		case 'get':
			return isset($_GET[$action])?$_GET[$action]:null;
			break;
		case 'post':
			return isset($_POST[$action])?$_POST[$action]:null;
			break;
		default:
			return isset($_REQUEST[$action])?$_REQUEST[$action]:null;
			break;
	}
}

function getSafe($value){
	if (!get_magic_quotes_gpc()){
		$value = addslashes($value);
	}
	return $value;
}

function getPwd($pwd){
	return md5(md5($pwd.'dad4553faf133as1d34fa34'));
}

function getSid(){
	return md5(uniqid(mt_rand(),1).time());
}

function getIP(){ 
	if (getenv('HTTP_CLIENT_IP')) { 
		$ip = getenv('HTTP_CLIENT_IP'); 
	}elseif (getenv('HTTP_X_FORWARDED_FOR')) { 
		$ip = getenv('HTTP_X_FORWARDED_FOR'); 
	}elseif (getenv('HTTP_X_FORWARDED')) { 
		$ip = getenv('HTTP_X_FORWARDED'); 
	}elseif (getenv('HTTP_FORWARDED_FOR')) { 
		$ip = getenv('HTTP_FORWARDED_FOR'); 
	}elseif (getenv('HTTP_FORWARDED')) { 
		$ip = getenv('HTTP_FORWARDED'); 
	}else{ 
		$ip = $_SERVER['REMOTE_ADDR']; 
	} 
	return $ip; 
} 

