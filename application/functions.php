<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------
//公共函数类


/**
 * 快捷生成sweetalert
 * @param      $_title
 * @param      $_text
 * @param      $_type
 * @param null $_url
 *
 * @return string
 */
function sweetAlert($_title,$_text,$_type,$_url = null)
{
    if(empty($_url)){
        return 'swal("'.$_title.'", "'.$_text.'", "'.$_type.'");';
    }else{
        if($_url == 'REFERER'){
            $_url='/';
            if(isset($_SERVER['HTTP_REFERER'])){
                $_url=$_SERVER['HTTP_REFERER'];
            }
        }
        return 'swal({ title: "'.$_title.'",   text: "'.$_text.'",   type: "'.$_type.'",   showCancelButton: false,   confirmButtonColor: "#DD6B55",   confirmButtonText: "OK",   closeOnConfirm: false }, function(){   window.location.href="'.$_url.'"; });';
    }
}


/**
 * 用户密码加密
 * @param $_pwd
 *
 * @return mixed
 */
function md5Pwd($_pwd)
{
    return md5(md5($_pwd.'dad4553faf133as1d34fa34'));
}
/**
 * 获取随机字符串
 * @return string
 */
function getSid()
{
    return md5(uniqid(mt_rand(),1).time());
}

/**
 * 
 * @param      $value
 * @param bool $html
 *
 * @return string
 */
function getHtmlCode($value,$html=false)
{
    $value = stripslashes($value);
    if($html){
        $value = htmlspecialchars($value);
    }
    return $value;
}