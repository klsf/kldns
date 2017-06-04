<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/4/3
 * Time: 19:36
 */

namespace klsf\klsfdns;


class KlsfDns
{
    public static function getClass($_type, $key)
    {
        $key = explode(',', $key);
        switch ($_type) {
            case "aliyun":
                return AliYun::getInstance($key[0], $key[1]);
            case "cloudxns":
                return CloudXNS::getInstance($key[0], $key[1]);
            case "dnscom":
                return DnsCom::getInstance($key[0], $key[1]);
            case "dnspod":
                return DnsPod::getInstance($key[0], $key[1]);
            default:
                return DnsPod::getInstance($key[0], $key[1]);
        }
    }
}