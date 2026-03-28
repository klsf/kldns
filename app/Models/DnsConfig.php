<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:34
 */

namespace App\Models;


class DnsConfig extends Model
{
    protected $primaryKey = 'dns';
    public $incrementing = false;
    protected $guarded = [];
    protected $appends = ['config_masked'];

    public function getConfigAttribute()
    {
        $value = json_decode($this->attributes['config'], true);
        return $value ? $value : [];
    }

    public function getConfigMaskedAttribute()
    {
        return $this->getMaskedConfig();
    }

    public function getMaskedConfig()
    {
        $config = $this->config;
        if (!$config) {
            return '';
        }

        $masked = [];
        foreach ($config as $key => $value) {
            $masked[] = $key . ': ' . $this->maskConfigValue($value);
        }

        return implode("\n", $masked);
    }

    private function maskConfigValue($value)
    {
        $value = trim((string)$value);
        $length = mb_strlen($value);

        if ($length <= 2) {
            return str_repeat('*', $length ?: 1);
        }

        if ($length <= 6) {
            return mb_substr($value, 0, 1) . str_repeat('*', max($length - 2, 1)) . mb_substr($value, -1);
        }

        return mb_substr($value, 0, 2) . str_repeat('*', max($length - 4, 1)) . mb_substr($value, -2);
    }
}
