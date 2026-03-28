<?php

namespace App\Klsf\Dns;

class HuaweiCloud implements DnsInterface
{
    use DnsHttp;

    private $url = 'https://dns.myhuaweicloud.com';
    private $accessKeyId;
    private $secretAccessKey;

    public function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        list($ret, $error) = $this->requestJson('DELETE', "/v2/zones/{$DomainId}/recordsets/{$RecordId}");

        return $ret ? [true, null] : [false, $error];
    }

    public function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->requestJson('PUT', "/v2/zones/{$DomainId}/recordsets/{$RecordId}", [], [
            'name' => $this->buildRecordName($Name, $Domain),
            'type' => strtoupper(trim((string)$Type)),
            'ttl' => 300,
            'records' => [$this->formatRecordValue($Type, $Value)],
        ]);

        return $ret ? [true, null] : [false, $error];
    }

    public function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->requestJson('POST', "/v2/zones/{$DomainId}/recordsets", [], [
            'name' => $this->buildRecordName($Name, $Domain),
            'type' => strtoupper(trim((string)$Type)),
            'ttl' => 300,
            'records' => [$this->formatRecordValue($Type, $Value)],
        ]);

        if (!$ret) {
            return [false, $error];
        }

        if (!empty($ret['id'])) {
            return [[
                'RecordId' => $ret['id'],
                'Name' => $Name,
                'Domain' => $Domain,
            ], null];
        }

        return [false, '添加域名记录失败'];
    }

    public function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->requestJson('GET', "/v2/zones/{$DomainId}/recordsets/{$RecordId}");
        if (!$ret) {
            return [false, $error];
        }

        if (!empty($ret['id'])) {
            return [[
                'RecordId' => $ret['id'],
                'Name' => $this->extractHost($ret['name'] ?? '', $Domain),
                'Type' => strtoupper((string)($ret['type'] ?? 'A')),
                'Value' => $this->normalizeRecordValue($ret['type'] ?? 'A', $ret['records'][0] ?? ''),
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
            $query = ['limit' => 500];
            if ($marker !== '') {
                $query['marker'] = $marker;
            }

            list($ret, $error) = $this->requestJson('GET', "/v2/zones/{$DomainId}/recordsets", $query);
            if (!$ret) {
                return [false, $error];
            }

            foreach (($ret['recordsets'] ?? []) as $record) {
                $list[] = [
                    'RecordId' => $record['id'],
                    'Name' => $this->extractHost($record['name'] ?? '', $Domain),
                    'Type' => strtoupper((string)($record['type'] ?? 'A')),
                    'Value' => $this->normalizeRecordValue($record['type'] ?? 'A', $record['records'][0] ?? ''),
                    'Domain' => $Domain,
                ];
            }

            $marker = $this->extractNextMarker($ret['links']['next'] ?? '');
        } while ($marker !== '');

        return [$list, null];
    }

    public function getDomainList()
    {
        $list = [];
        $marker = '';

        do {
            $query = ['type' => 'public', 'limit' => 500];
            if ($marker !== '') {
                $query['marker'] = $marker;
            }

            list($ret, $error) = $this->requestJson('GET', '/v2/zones', $query);
            if (!$ret) {
                return [false, $error];
            }

            foreach (($ret['zones'] ?? []) as $zone) {
                if (($zone['zone_type'] ?? 'public') !== 'public' || empty($zone['id']) || empty($zone['name'])) {
                    continue;
                }

                $list[] = [
                    'Domain' => rtrim((string)$zone['name'], '.'),
                    'DomainId' => $zone['id'],
                ];
            }

            $marker = $this->extractNextMarker($ret['links']['next'] ?? '');
        } while ($marker !== '');

        return [$list, null];
    }

    public function getRecordLine($_domainId = null, $_domain = null)
    {
        return [
            ['Name' => '默认', 'Id' => 'default'],
        ];
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
                'placeholder' => '请输入华为云 Access Key ID',
                'tips' => '<a href="https://support.huaweicloud.com/intl/en-us/api-dns/dns_api_62003.html" target="_blank">查看华为云 DNS API 文档</a>',
            ],
            [
                'name' => 'SecretAccessKey',
                'placeholder' => '请输入华为云 Secret Access Key',
                'tips' => '公共解析使用华为云 AK/SK 签名调用。',
            ],
        ];
    }

    private function requestJson($method, $path, $query = [], $body = null)
    {
        $url = $this->url . $path;
        if (!empty($query)) {
            $url .= '?' . $this->buildCanonicalQuery($query);
        }

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
        if ($bodyString === '') {
            $ret = [];
        } else {
            $ret = json_decode($bodyString, true);
        }

        if (!is_array($ret)) {
            return [false, '解析结果失败'];
        }

        if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) {
            return [$ret, null];
        }

        return [false, $ret['message'] ?? $ret['error_msg'] ?? '接口调用失败'];
    }

    private function buildHeaders($method, $path, $query, $payload)
    {
        $host = parse_url($this->url, PHP_URL_HOST);
        $date = gmdate('Ymd\THis\Z');
        $contentType = 'application/json';

        $headers = [
            'content-type' => $contentType,
            'host' => $host,
            'x-sdk-date' => $date,
        ];

        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= $key . ':' . trim((string)$value) . "\n";
        }

        $canonicalRequest = strtoupper($method) . "\n"
            . $this->normalizePath($path) . "\n"
            . $this->buildCanonicalQuery($query) . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . hash('sha256', $payload);

        $stringToSign = "SDK-HMAC-SHA256\n" . $date . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->secretAccessKey);

        return [
            'Content-Type' => $contentType,
            'Host' => $host,
            'X-Sdk-Date' => $date,
            'Authorization' => 'SDK-HMAC-SHA256 Access=' . $this->accessKeyId . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
        ];
    }

    private function normalizePath($path)
    {
        $segments = array_map('rawurlencode', array_map('rawurldecode', explode('/', ltrim($path, '/'))));

        return '/' . implode('/', $segments);
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

    private function buildRecordName($name, $domain)
    {
        $name = trim((string)$name);
        if ($name === '' || $name === '@') {
            return rtrim((string)$domain, '.') . '.';
        }

        if ($domain && substr($name, -strlen('.' . $domain)) === '.' . $domain) {
            return rtrim($name, '.') . '.';
        }

        return rtrim($name . '.' . $domain, '.') . '.';
    }

    private function extractHost($fqdn, $domain)
    {
        $fqdn = rtrim(trim((string)$fqdn), '.');
        $domain = rtrim(trim((string)$domain), '.');

        if ($fqdn === '' || $fqdn === $domain) {
            return '';
        }

        $suffix = '.' . $domain;
        if ($domain !== '' && substr($fqdn, -strlen($suffix)) === $suffix) {
            return substr($fqdn, 0, -strlen($suffix));
        }

        return $fqdn;
    }

    private function normalizeRecordValue($type, $value)
    {
        $type = strtoupper(trim((string)$type));
        $value = trim((string)$value);

        if ($type === 'TXT') {
            return trim($value, '"');
        }

        if ($type === 'MX' && preg_match('/^\d+\s+(.+)$/', $value, $matches)) {
            return trim($matches[1], '.');
        }

        return rtrim($value, '.');
    }

    private function formatRecordValue($type, $value)
    {
        $type = strtoupper(trim((string)$type));
        $value = trim((string)$value);

        if (in_array($type, ['CNAME', 'MX', 'NS'], true)) {
            return rtrim($value, '.') . '.';
        }

        return $value;
    }

    private function extractNextMarker($nextLink)
    {
        if (!$nextLink) {
            return '';
        }

        $query = parse_url($nextLink, PHP_URL_QUERY);
        if (!$query) {
            return '';
        }

        parse_str($query, $params);

        return isset($params['marker']) ? (string)$params['marker'] : '';
    }
}
