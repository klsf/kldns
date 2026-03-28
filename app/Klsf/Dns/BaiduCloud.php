<?php

namespace App\Klsf\Dns;

class BaiduCloud implements DnsInterface
{
    use DnsHttp;

    private $url = 'https://dns.baidubce.com';
    private $accessKeyId;
    private $secretAccessKey;

    public function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        list($ret, $error) = $this->requestJson('DELETE', "/v1/dns/zone/{$Domain}/record/{$RecordId}");

        return $ret ? [true, null] : [false, $error];
    }

    public function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $body = [
            'rr' => $this->normalizeHost($Name),
            'type' => strtoupper(trim((string)$Type)),
            'value' => trim((string)$Value),
            'ttl' => 300,
        ];

        $line = $this->normalizeLine($LineId);
        if ($line !== 'default') {
            $body['line'] = $line;
        }

        if (strtoupper(trim((string)$Type)) === 'MX') {
            $body['priority'] = 10;
        }

        list($ret, $error) = $this->requestJson('PUT', "/v1/dns/zone/{$Domain}/record/{$RecordId}", [], $body);

        return $ret ? [true, null] : [false, $error];
    }

    public function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $body = [
            'rr' => $this->normalizeHost($Name),
            'type' => strtoupper(trim((string)$Type)),
            'value' => trim((string)$Value),
            'ttl' => 300,
        ];

        $line = $this->normalizeLine($LineId);
        if ($line !== 'default') {
            $body['line'] = $line;
        }

        if (strtoupper(trim((string)$Type)) === 'MX') {
            $body['priority'] = 10;
        }

        list($ret, $error) = $this->requestJson('POST', "/v1/dns/zone/{$Domain}/record", ['clientToken' => $this->createClientToken()], $body);
        if (!$ret) {
            return [false, $error];
        }

        list($record, $lookupError) = $this->findLatestRecord($Domain, $Name, $Type, $Value);
        if ($record === false) {
            return [false, $lookupError ?: '添加域名记录成功，但获取记录信息失败'];
        }

        return [[
            'RecordId' => $record['RecordId'],
            'Name' => $record['Name'],
            'Domain' => $Domain,
        ], null];
    }

    public function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->requestJson('GET', "/v1/dns/zone/{$Domain}/record", ['id' => $RecordId]);
        if (!$ret) {
            return [false, $error];
        }

        foreach (($ret['records'] ?? []) as $record) {
            if ((string)($record['id'] ?? '') !== (string)$RecordId) {
                continue;
            }

            return [[
                'RecordId' => $record['id'],
                'Name' => $this->denormalizeHost($record['rr'] ?? ''),
                'Type' => strtoupper((string)($record['type'] ?? 'A')),
                'Value' => trim((string)($record['value'] ?? '')),
                'Domain' => $Domain,
            ], null];
        }

        return [false, '获取域名记录详情失败'];
    }

    public function getDomainRecords($DomainId = null, $Domain = null)
    {
        $list = [];
        $marker = '';

        do {
            $query = ['maxKeys' => 1000];
            if ($marker !== '') {
                $query['marker'] = $marker;
            }

            list($ret, $error) = $this->requestJson('GET', "/v1/dns/zone/{$Domain}/record", $query);
            if (!$ret) {
                return [false, $error];
            }

            foreach (($ret['records'] ?? []) as $record) {
                $list[] = [
                    'RecordId' => $record['id'],
                    'Name' => $this->denormalizeHost($record['rr'] ?? ''),
                    'Type' => strtoupper((string)($record['type'] ?? 'A')),
                    'Value' => trim((string)($record['value'] ?? '')),
                    'Domain' => $Domain,
                ];
            }

            $marker = !empty($ret['isTruncated']) ? (string)($ret['nextMarker'] ?? '') : '';
        } while ($marker !== '');

        return [$list, null];
    }

    public function getDomainList()
    {
        $list = [];
        $marker = '';

        do {
            $query = ['maxKeys' => 1000];
            if ($marker !== '') {
                $query['marker'] = $marker;
            }

            list($ret, $error) = $this->requestJson('GET', '/v1/dns/zone', $query);
            if (!$ret) {
                return [false, $error];
            }

            foreach (($ret['zones'] ?? []) as $zone) {
                if (empty($zone['name'])) {
                    continue;
                }

                $list[] = [
                    'Domain' => $zone['name'],
                    'DomainId' => $zone['id'] ?? $zone['name'],
                ];
            }

            $marker = !empty($ret['isTruncated']) ? (string)($ret['nextMarker'] ?? '') : '';
        } while ($marker !== '');

        return [$list, null];
    }

    public function getRecordLine($_domainId = null, $_domain = null)
    {
        $list = [
            ['Name' => '默认', 'Id' => 'default'],
            ['Name' => '电信', 'Id' => 'ct'],
            ['Name' => '移动', 'Id' => 'cmnet'],
            ['Name' => '联通', 'Id' => 'cnc'],
            ['Name' => '教育网', 'Id' => 'edu'],
            ['Name' => '搜索引擎', 'Id' => 'search'],
        ];

        list($ret, $error) = $this->requestJson('GET', '/v1/dns/customline', ['maxKeys' => 1000]);
        if (!$ret || empty($ret['lineList']) || !is_array($ret['lineList'])) {
            return $list;
        }

        foreach ($ret['lineList'] as $line) {
            if (empty($line['name'])) {
                continue;
            }
            $list[] = [
                'Name' => $line['name'],
                'Id' => $line['name'],
            ];
        }

        return $list;
    }

    public function check()
    {
        list($ret, $error) = $this->getDomainList();

        return $ret ? [true, null] : [false, $error];
    }

    public function config(array $config)
    {
        $this->accessKeyId = trim((string)($config['AccessKeyId'] ?? ''));
        $this->secretAccessKey = trim((string)($config['SecretAccessKey'] ?? ''));
    }

    public function configInfo()
    {
        return [
            [
                'name' => 'AccessKeyId',
                'placeholder' => '请输入百度智能云 AccessKey',
                'tips' => '<a href="https://intl.cloud.baidu.com/en/doc/DNS/s/kl4s7g11z-intl-en" target="_blank">查看百度智能云 DNS API 文档</a>',
            ],
            [
                'name' => 'SecretAccessKey',
                'placeholder' => '请输入百度智能云 SecretKey',
                'tips' => '使用 BCE Auth V1 对请求进行签名。',
            ],
        ];
    }

    private function requestJson($method, $path, $query = [], $body = null)
    {
        $queryString = $this->buildCanonicalQuery($query);
        $url = $this->url . $path . ($queryString !== '' ? '?' . $queryString : '');
        $payload = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = $this->buildHeaders($method, $path, $query, $payload);

        list($res, $error) = $this->request($method, $url, [
            'headers' => $headers,
            'body' => $payload,
        ]);

        if (!$res) {
            return [false, $error];
        }

        $bodyString = (string)$res->getBody();
        $ret = $bodyString === '' ? [] : json_decode($bodyString, true);
        if (!is_array($ret)) {
            return [false, '解析结果失败'];
        }

        if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) {
            return [$ret, null];
        }

        return [false, $ret['message'] ?? $ret['messageZh'] ?? '接口调用失败'];
    }

    private function buildHeaders($method, $path, $query, $payload)
    {
        $host = parse_url($this->url, PHP_URL_HOST);
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $headers = [
            'host' => $host,
            'x-bce-date' => $timestamp,
        ];

        if ($payload !== '') {
            $headers['content-type'] = 'application/json; charset=utf-8';
        }

        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = $this->canonicalHeaders($headers);
        $canonicalRequest = strtoupper($method) . "\n"
            . $this->normalizePath($path) . "\n"
            . $this->buildCanonicalQuery($query) . "\n"
            . $canonicalHeaders;

        $authStringPrefix = 'bce-auth-v1/' . $this->accessKeyId . '/' . $timestamp . '/1800';
        $signingKey = hash_hmac('sha256', $authStringPrefix, $this->secretAccessKey);
        $signature = hash_hmac('sha256', $canonicalRequest, $signingKey);

        $result = [
            'Host' => $host,
            'x-bce-date' => $timestamp,
            'Authorization' => $authStringPrefix . '/' . $signedHeaders . '/' . $signature,
        ];

        if ($payload !== '') {
            $result['Content-Type'] = 'application/json; charset=utf-8';
        }

        return $result;
    }

    private function canonicalHeaders($headers)
    {
        ksort($headers);
        $items = [];
        foreach ($headers as $key => $value) {
            $items[] = strtolower($key) . ':' . trim((string)$value);
        }

        return implode("\n", $items);
    }

    private function buildCanonicalQuery($query)
    {
        if (!$query) {
            return '';
        }

        ksort($query);
        $items = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $items[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }

        return implode('&', $items);
    }

    private function normalizePath($path)
    {
        $segments = array_map('rawurlencode', array_map('rawurldecode', explode('/', ltrim($path, '/'))));

        return '/' . implode('/', $segments);
    }

    private function normalizeHost($name)
    {
        $name = trim((string)$name);

        return $name === '' || $name === '@' ? '@' : $name;
    }

    private function denormalizeHost($name)
    {
        $name = trim((string)$name);

        return $name === '@' ? '' : $name;
    }

    private function normalizeLine($lineId)
    {
        $lineId = trim((string)$lineId);

        return $lineId === '' || $lineId === '0' ? 'default' : $lineId;
    }

    private function findLatestRecord($domain, $name, $type, $value)
    {
        list($list, $error) = $this->getDomainRecords(null, $domain);
        if ($list === false) {
            return [false, $error];
        }

        $targetName = $this->denormalizeHost($this->normalizeHost($name));
        $targetType = strtoupper(trim((string)$type));
        $targetValue = trim((string)$value);

        foreach (array_reverse($list) as $record) {
            if ($record['Name'] === $targetName && strtoupper((string)$record['Type']) === $targetType && trim((string)$record['Value']) === $targetValue) {
                return [$record, null];
            }
        }

        return [false, '添加域名记录成功，但获取记录信息失败'];
    }

    private function createClientToken()
    {
        return md5(uniqid('', true) . mt_rand(1000, 9999));
    }
}
