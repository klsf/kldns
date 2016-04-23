<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------

namespace app\util\dnsApi;

/**
 * 获取对应域名解析平台API实例
 * Class klsfDns
 *
 * @package app\util\dnsApi
 */
class KlsfDns
{
    private static $dnsApi = array();
    public static function getApi($_dns)
    {
        if(isset(self::$dnsApi[$_dns])){
            return self::$dnsApi[$_dns];
        }
        switch ($_dns){
            case 'aliyun':
                self::$dnsApi['aliyun'] = new Aliyun(C('AliyunAccessKeyId'),C('AliyunAccessKeySecret'));
                break;
            case 'cloudxns':
                self::$dnsApi['cloudxns'] = new CloudXNS(C('CloudXnsApiKey'),C('CloudXnsSecretKey'));
                break;
            default:
                $_dns = 'dnspod';
                self::$dnsApi['dnspod'] = new Dnspod(C('DnspodTokenID'),C('DnspodToken'));
        }
        return self::$dnsApi[$_dns];

    }

}