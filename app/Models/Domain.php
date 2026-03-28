<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:36
 */

namespace App\Models;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Domain extends Model
{
    protected $primaryKey = 'did';
    protected $guarded = ['did'];
    protected $appends = ['record_type_list', 'record_type_text', 'review_mode_text', 'beian_text'];

    public static function getRecordTypeOptions()
    {
        $options = [];
        foreach (static::getRecordTypeDescriptions() as $type => $description) {
            $options[$type] = $type . ' - ' . $description;
        }

        return $options;
    }

    public static function getRecordTypeDescriptions()
    {
        return [
            'A' => '将域名指向 IPv4 地址',
            'AAAA' => '将域名指向 IPv6 地址',
            'CNAME' => '将域名别名指向另一个域名',
            'MX' => '指定接收邮件的服务器',
            'TXT' => '用于验证码、SPF、DKIM 等文本配置',
            'NS' => '指定子域名的权威 DNS 服务器',
            'SRV' => '定义服务协议对应的主机和端口',
            'CAA' => '限制可为该域名签发证书的 CA',
        ];
    }

    public static function getRecordValueTips()
    {
        return [
            'A' => '请输入 IPv4 地址，例如 1.1.1.1',
            'AAAA' => '请输入 IPv6 地址，例如 2400:3200::1',
            'CNAME' => '请输入目标域名，例如 target.example.com',
            'MX' => '请输入邮件服务器域名，例如 mail.example.com',
            'TXT' => '请输入文本内容，例如 v=spf1 include:_spf.google.com ~all',
            'NS' => '请输入权威 DNS 服务器域名，例如 ns1.example.com',
            'SRV' => '请输入 priority weight port target，例如 10 5 443 srv.example.com',
            'CAA' => '请输入 flags tag value，例如 0 issue letsencrypt.org',
        ];
    }

    public static function validateRecordValue($type, $value)
    {
        $type = strtoupper(trim((string) $type));
        $value = trim((string) $value);

        if ($value === '') {
            return [false, '请输入记录值'];
        }

        switch ($type) {
            case 'A':
                if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return [false, static::getRecordValueTips()['A']];
                }
                break;
            case 'AAAA':
                if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    return [false, static::getRecordValueTips()['AAAA']];
                }
                break;
            case 'CNAME':
            case 'MX':
            case 'NS':
                if (!static::isValidDnsTarget($value)) {
                    return [false, static::getRecordValueTips()[$type]];
                }
                break;
            case 'TXT':
                if (mb_strlen($value) > 1024) {
                    return [false, 'TXT 记录值过长，请控制在 1024 个字符内'];
                }
                break;
            case 'SRV':
                if (!preg_match('/^\s*(\d{1,5})\s+(\d{1,5})\s+(\d{1,5})\s+(.+)\s*$/', $value, $matches)) {
                    return [false, static::getRecordValueTips()['SRV']];
                }

                $priority = intval($matches[1]);
                $weight = intval($matches[2]);
                $port = intval($matches[3]);
                $target = trim($matches[4]);

                if ($priority > 65535 || $weight > 65535 || $port < 1 || $port > 65535 || !static::isValidDnsTarget($target)) {
                    return [false, static::getRecordValueTips()['SRV']];
                }
                break;
            case 'CAA':
                if (!preg_match('/^\s*(\d{1,3})\s+([A-Za-z0-9-]+)\s+(.+)\s*$/', $value, $matches)) {
                    return [false, static::getRecordValueTips()['CAA']];
                }

                $flags = intval($matches[1]);
                $tag = strtolower($matches[2]);
                $tagValue = trim($matches[3], " \t\n\r\0\x0B\"'");

                if ($flags < 0 || $flags > 255 || !in_array($tag, ['issue', 'issuewild', 'iodef'], true) || $tagValue === '') {
                    return [false, static::getRecordValueTips()['CAA']];
                }
                break;
        }

        return [true, ''];
    }

    private static function isValidDnsTarget($value)
    {
        $value = strtolower(rtrim(trim((string) $value), '.'));

        if ($value === '' || strlen($value) > 253) {
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return false;
        }

        return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $value) === 1;
    }

    public static function normalizeRecordTypes($types)
    {
        if (!is_array($types)) {
            $types = explode(',', (string) $types);
        }

        $options = static::getRecordTypeOptions();
        $normalized = [];

        foreach ($types as $type) {
            $type = strtoupper(trim((string) $type));
            if ($type !== '' && isset($options[$type])) {
                $normalized[] = $type;
            }
        }

        $normalized = array_values(array_unique($normalized));

        return empty($normalized) ? ['A', 'CNAME'] : $normalized;
    }

    public function scopeAvailable($query, $gid = 0)
    {
        $gid = $gid ? $gid : (Auth::check() ? Auth::user()->gid : 0);
        $query->where(function ($builder) use ($gid) {
            $builder->where('groups', '0');

            if ($gid > 0) {
                $builder->orWhereRaw('FIND_IN_SET(?, `groups`)', [$gid]);
            }
        });
    }

    public function dnsConfig()
    {
        return $this->belongsTo(DnsConfig::class, 'dns', 'dns');
    }

    public function getRecordTypeListAttribute()
    {
        return static::normalizeRecordTypes($this->attributes['record_types'] ?? 'A,CNAME');
    }

    public function getRecordTypeTextAttribute()
    {
        return implode(', ', $this->record_type_list);
    }

    public function getReviewModeTextAttribute()
    {
        return intval($this->attributes['review_mode'] ?? 0) === 1 ? '人工审核' : '自动通过';
    }

    public function getBeianTextAttribute()
    {
        return intval($this->attributes['beian'] ?? 0) === 1 ? '已备案' : '未备案';
    }
}
