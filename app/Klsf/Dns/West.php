<?php

namespace App\Klsf\Dns;

class West implements DnsInterface
{
    use DnsHttp;

    private $url = 'https://api.west.cn/api/v2/domain/';
    private $username;
    private $password;

    public function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        list($ret, $error) = $this->getResult('deldnsrecord', [
            'domain' => $Domain,
            'id' => $RecordId,
        ]);

        return $ret ? [true, null] : [false, $error];
    }

    public function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->getResult('moddnsrecord', [
            'domain' => $Domain,
            'id' => $RecordId,
            'host' => $this->normalizeHost($Name),
            'type' => strtoupper(trim((string) $Type)),
            'value' => trim((string) $Value),
            'line' => $this->normalizeLine($LineId),
            'ttl' => 900,
            'level' => $this->normalizeLevel($Type),
        ]);

        return $ret ? [true, null] : [false, $error];
    }

    public function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->getResult('adddnsrecord', [
            'domain' => $Domain,
            'host' => $this->normalizeHost($Name),
            'type' => strtoupper(trim((string) $Type)),
            'value' => trim((string) $Value),
            'line' => $this->normalizeLine($LineId),
            'ttl' => 900,
            'level' => $this->normalizeLevel($Type),
        ]);

        if (!$ret) {
            return [false, $error];
        }

        if (isset($ret['data']['id'])) {
            return [[
                'RecordId' => $ret['data']['id'],
                'Name' => $Name,
                'Domain' => $Domain,
            ], null];
        }

        return [false, '添加域名记录失败'];
    }

    public function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        list($list, $error) = $this->getDomainRecords($DomainId, $Domain);
        if ($list === false) {
            return [false, $error];
        }

        foreach ($list as $record) {
            if ((string) $record['RecordId'] === (string) $RecordId) {
            return [[$record], null];
        }
        }

        return [false, '获取域名记录详情失败'];
    }

    public function getDomainRecords($DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->getResult('getdnsrecord', [
            'domain' => $Domain,
            'limit' => 500,
            'pageno' => 1,
        ]);

        if (!$ret) {
            return [false, $error];
        }

        $items = isset($ret['data']['items']) && is_array($ret['data']['items']) ? $ret['data']['items'] : [];
        $list = [];

        foreach ($items as $record) {
            $list[] = [
                'RecordId' => $record['id'],
                'Name' => $this->denormalizeHost($record['item'] ?? '@'),
                'Type' => strtoupper((string) ($record['type'] ?? 'A')),
                'Value' => (string) ($record['value'] ?? ''),
                'Domain' => $Domain,
            ];
        }

        return [$list, null];
    }

    public function getDomainList()
    {
        list($ret, $error) = $this->getResult('getdomains', [
            'limit' => 500,
            'page' => 1,
        ], 'GET');

        if (!$ret) {
            return [false, $error];
        }

        $items = isset($ret['data']['items']) && is_array($ret['data']['items']) ? $ret['data']['items'] : [];
        $list = [];

        foreach ($items as $domain) {
            if (!empty($domain['domain'])) {
                $list[] = [
                    'Domain' => $domain['domain'],
                    'DomainId' => $domain['domain'],
                ];
            }
        }

        return [$list, null];
    }

    public function getRecordLine($_domainId = null, $_domain = null)
    {
        return [
            ['Name' => '默认', 'Id' => ''],
            ['Name' => '电信', 'Id' => 'LTEL'],
            ['Name' => '联通', 'Id' => 'LCNC'],
            ['Name' => '移动', 'Id' => 'LMOB'],
            ['Name' => '教育网', 'Id' => 'LEDU'],
            ['Name' => '搜索引擎', 'Id' => 'LSEO'],
        ];
    }

    public function check()
    {
        list($ret, $error) = $this->getDomainList();

        return $ret ? [true, null] : [false, $error];
    }

    public function config(array $config)
    {
        $this->username = isset($config['Username']) ? trim((string) $config['Username']) : '';
        $this->password = isset($config['ApiPassword']) ? trim((string) $config['ApiPassword']) : '';
    }

    public function configInfo()
    {
        return [
            [
                'name' => 'Username',
                'placeholder' => '请输入西部数码 API 用户名',
                'tips' => '<a href="https://www.west.cn/CustomerCenter/doc/domain_v2.html" target="_blank">查看西部数码域名 API 文档</a>',
            ],
            [
                'name' => 'ApiPassword',
                'placeholder' => '请输入 API 密码',
                'tips' => '请使用西部数码开放的 API 密码，不是登录后台密码。',
            ],
        ];
    }

    private function getResult($action, $params = [], $method = 'POST')
    {
        $timestamp = (string) round(microtime(true) * 1000);
        $query = array_merge([
            'username' => $this->username,
            'time' => $timestamp,
            'token' => md5($this->username . $this->password . $timestamp),
            'act' => $action,
        ], $params);

        if (strtoupper($method) === 'GET') {
            list($res, $error) = $this->get($this->url . '?' . http_build_query($query));
        } else {
            list($res, $error) = $this->post($this->url, [
                'body' => http_build_query($query),
            ]);
        }

        if (!$res) {
            return [false, $error];
        }

        $body = (string) $res->getBody();
        $body = $this->normalizeBody($body);
        $ret = json_decode($body, true);

        if (!is_array($ret)) {
            return [false, '解析结果失败'];
        }

        if (isset($ret['result']) && intval($ret['result']) === 200) {
            return [$ret, null];
        }

        return [false, $ret['msg'] ?? '接口调用失败'];
    }

    private function normalizeBody($body)
    {
        if (!is_string($body) || $body === '') {
            return '';
        }

        $encoding = mb_detect_encoding($body, ['UTF-8', 'GBK', 'GB2312', 'BIG5'], true);
        if ($encoding && strtoupper($encoding) !== 'UTF-8') {
            return mb_convert_encoding($body, 'UTF-8', $encoding);
        }

        return $body;
    }

    private function normalizeHost($host)
    {
        $host = trim((string) $host);

        return $host === '' ? '@' : $host;
    }

    private function denormalizeHost($host)
    {
        $host = trim((string) $host);

        return $host === '@' ? '' : $host;
    }

    private function normalizeLine($lineId)
    {
        $lineId = trim((string) $lineId);

        return $lineId === '0' ? '' : $lineId;
    }

    private function normalizeLevel($type)
    {
        return strtoupper(trim((string) $type)) === 'MX' ? 10 : 10;
    }
}
