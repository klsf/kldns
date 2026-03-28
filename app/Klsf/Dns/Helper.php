<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 21:14
 */

namespace App\Klsf\Dns;


class Helper
{
    private const REMOVED_DRIVERS = [
        'CloudXNS',
    ];

    private const DRIVER_LABELS = [
        'Aliyun' => '阿里云 DNS',
        'BaiduCloud' => '百度智能云 DNS',
        'Cloudflare' => 'Cloudflare',
        'DnsCom' => 'DNS.com',
        'DnsDun' => 'DnsDun',
        'DnsLa' => 'DNSLA',
        'Dnspod' => 'DNSPod',
        'GoogleCloudDns' => 'Google Cloud DNS',
        'HuaweiCloud' => '华为云 DNS',
        'Route53' => 'Amazon Route 53',
        'West' => '西部数码',
    ];

    /**
     * @return array
     */
    public static function getList()
    {
        $list = [];
        $dir = __DIR__ . '/';
        $files = scandir($dir);
        foreach ($files as $file) {
            $file = explode('.', $file);
            if ($file[1] === 'php' && !in_array($file[0], array_merge(['DnsHttp', 'Helper', 'DnsInterface'], self::REMOVED_DRIVERS))) {
                $list[] = $file[0];
            }
        }
        return $list;
    }

    public static function getLabel($dns)
    {
        return self::DRIVER_LABELS[$dns] ?? $dns;
    }

    public static function getLabelMap()
    {
        return self::DRIVER_LABELS;
    }

    /**
     * @param $pt
     * @return DnsInterface|bool
     */
    public static function getModel($dns)
    {
        $dir = __DIR__ . '/';
        if (in_array($dns, self::REMOVED_DRIVERS, true)) {
            return false;
        }

        if (file_exists($dir . "{$dns}.php")) {
            $class = "App\\Klsf\\Dns\\{$dns}";
            $model = new $class;
            return $model;
        }
        return false;
    }
}
