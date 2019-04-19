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
            if ($file[1] === 'php' && !in_array($file[0], ['DnsHttp', 'Helper', 'DnsInterface'])) {
                $list[] = $file[0];
            }
        }
        return $list;
    }

    /**
     * @param $pt
     * @return DnsInterface|bool
     */
    public static function getModel($dns)
    {
        $dir = __DIR__ . '/';
        if (file_exists($dir . "{$dns}.php")) {
            $class = "App\\Klsf\\Dns\\{$dns}";
            $model = new $class;
            return $model;
        }
        return false;
    }
}