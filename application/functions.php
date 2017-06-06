<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/6/1
 * Time: 13:25
 */

/**
 * 用户密码加密
 * @param $password
 * @return string
 */
function passwordEncrypt($password)
{
    return md5(md5($password . 'kldns') . '815856515');
}

/**
 * 生成唯一用户身份识别码
 * @return string
 */
function createSid()
{
    return md5(md5(uniqid() . rand(10000, 99999)) . md5(time()));
}

/**
 * @param $group
 * @return string
 */
function getUserGroupName($group)
{
    switch ($group) {
        case 1:
            return '普通';
        case 2:
            return '金';
        case 4:
            return '木';
        case 8:
            return '水';
        case 16:
            return '火';
        case 32:
            return '土';
        default:
            return '普通';
    }
}

/**
 * 操作用户金币函数
 * @param $uid
 * @param $coin
 * @param null $remark
 * @return bool
 * @throws \think\Exception
 */
function updateCoin($uid, $coin, $remark = null)
{
    $query = db('users')->where('uid', $uid);
    if ($coin < 0) {
        $query->where('coin', '>=', abs($coin));
    }
    if ($sql = $query->setInc('coin', $coin)) {
        return true;
    }
    return false;
}

/**
 * @param $str
 * @param int $index
 * @param string $separator
 * @return null
 */
function getStringIndex($str, $index = 0, $separator = ',')
{
    $arr = explode($separator, $str);
    return isset($arr[$index]) ? $arr[$index] : null;
}

/**
 * @param $value
 * @param bool $html
 * @return string
 */
function getHtmlCode($value, $html = false)
{
    $value = stripslashes($value);
    if ($html) {
        $value = htmlspecialchars($value);
    }
    return $value;
}

function getLineInfo($lines, $index = 0)
{
    $lines = json_decode($lines, true);
    if (isset($lines[$index])) {
        return $lines[$index];
    }
    return $lines[0];
}