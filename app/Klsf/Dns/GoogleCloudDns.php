<?php

namespace App\Klsf\Dns;

class GoogleCloudDns implements DnsInterface
{
    use DnsHttp;

    private $url = 'https://dns.googleapis.com/dns/v1';
    private $serviceAccount = [];
    private $projectId = '';
    private $accessToken = '';
    private $accessTokenExpiresAt = 0;

    public function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        list($record, $error) = $this->getDomainRecordInfo($RecordId, $DomainId, $Domain);
        if (!$record) {
            return [false, $error];
        }

        list($ret, $error) = $this->requestJson('POST', "/projects/{$this->projectId}/managedZones/{$DomainId}/changes", [], [
            'deletions' => [$this->buildRecordPayload($record)],
        ]);

        return $ret ? [true, null] : [false, $error];
    }

    public function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        list($current, $error) = $this->getDomainRecordInfo($RecordId, $DomainId, $Domain);
        if (!$current) {
            return [false, $error];
        }

        $newRecord = [
            'Name' => $Name,
            'Type' => strtoupper(trim((string)$Type)),
            'Value' => trim((string)$Value),
            'Domain' => $Domain,
            'TTL' => 300,
        ];

        list($ret, $error) = $this->requestJson('POST', "/projects/{$this->projectId}/managedZones/{$DomainId}/changes", [], [
            'deletions' => [$this->buildRecordPayload($current)],
            'additions' => [$this->buildRecordPayload($newRecord)],
        ]);

        return $ret ? [true, null] : [false, $error];
    }

    public function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $record = [
            'Name' => $Name,
            'Type' => strtoupper(trim((string)$Type)),
            'Value' => trim((string)$Value),
            'Domain' => $Domain,
            'TTL' => 300,
        ];

        list($ret, $error) = $this->requestJson('POST', "/projects/{$this->projectId}/managedZones/{$DomainId}/changes", [], [
            'additions' => [$this->buildRecordPayload($record)],
        ]);
        if (!$ret) {
            return [false, $error];
        }

        return [[
            'RecordId' => $this->buildRecordId($Name, $Type),
            'Name' => $Name,
            'Domain' => $Domain,
        ], null];
    }

    public function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        list($list, $error) = $this->getDomainRecords($DomainId, $Domain);
        if ($list === false) {
            return [false, $error];
        }

        foreach ($list as $record) {
            if ((string)$record['RecordId'] === (string)$RecordId) {
                return [$record, null];
            }
        }

        return [false, '获取域名记录详情失败'];
    }

    public function getDomainRecords($DomainId = null, $Domain = null)
    {
        $list = [];
        $pageToken = '';

        do {
            $query = ['maxResults' => 100];
            if ($pageToken !== '') {
                $query['pageToken'] = $pageToken;
            }

            list($ret, $error) = $this->requestJson('GET', "/projects/{$this->projectId}/managedZones/{$DomainId}/rrsets", $query);
            if (!$ret) {
                return [false, $error];
            }

            foreach (($ret['rrsets'] ?? []) as $item) {
                if (empty($item['name']) || empty($item['type'])) {
                    continue;
                }

                $name = $this->extractHost($item['name'], $Domain);
                $type = strtoupper((string)$item['type']);
                $values = isset($item['rrdatas']) && is_array($item['rrdatas']) ? $item['rrdatas'] : [];

                $list[] = [
                    'RecordId' => $this->buildRecordId($name, $type),
                    'Name' => $name,
                    'Type' => $type,
                    'Value' => $this->normalizeDisplayValue($type, $values[0] ?? ''),
                    'Domain' => $Domain,
                    'TTL' => intval($item['ttl'] ?? 300),
                    'RawValues' => $values,
                ];
            }

            $pageToken = isset($ret['nextPageToken']) ? (string)$ret['nextPageToken'] : '';
        } while ($pageToken !== '');

        return [$list, null];
    }

    public function getDomainList()
    {
        $list = [];
        $pageToken = '';

        do {
            $query = ['maxResults' => 100];
            if ($pageToken !== '') {
                $query['pageToken'] = $pageToken;
            }

            list($ret, $error) = $this->requestJson('GET', "/projects/{$this->projectId}/managedZones", $query);
            if (!$ret) {
                return [false, $error];
            }

            foreach (($ret['managedZones'] ?? []) as $zone) {
                if (($zone['visibility'] ?? 'public') !== 'public' || empty($zone['name']) || empty($zone['dnsName'])) {
                    continue;
                }

                $list[] = [
                    'Domain' => rtrim((string)$zone['dnsName'], '.'),
                    'DomainId' => $zone['name'],
                ];
            }

            $pageToken = isset($ret['nextPageToken']) ? (string)$ret['nextPageToken'] : '';
        } while ($pageToken !== '');

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
        $json = trim((string)($config['ServiceAccountJson'] ?? ''));
        $data = json_decode($json, true);
        $this->serviceAccount = is_array($data) ? $data : [];
        $this->projectId = trim((string)($config['ProjectId'] ?? ($this->serviceAccount['project_id'] ?? '')));
        $this->accessToken = '';
        $this->accessTokenExpiresAt = 0;
    }

    public function configInfo()
    {
        return [
            [
                'name' => 'ProjectId',
                'placeholder' => '可选，留空时自动读取服务账号 JSON 内的 project_id',
                'tips' => '<a href="https://cloud.google.com/dns/docs/reference/rest/v1/managedZones/list" target="_blank">查看 Google Cloud DNS API 文档</a>',
            ],
            [
                'name' => 'ServiceAccountJson',
                'placeholder' => '请粘贴 Google Cloud 服务账号 JSON 内容',
                'tips' => '<a href="https://developers.google.com/identity/protocols/oauth2/service-account" target="_blank">查看 Google 服务账号授权说明</a>',
                'type' => 'textarea',
                'rows' => 8,
            ],
        ];
    }

    private function requestJson($method, $path, $query = [], $body = null)
    {
        list($token, $error) = $this->getAccessToken();
        if (!$token) {
            return [false, $error];
        }

        $queryString = $this->buildQuery($query);
        $url = $this->url . $path . ($queryString !== '' ? '?' . $queryString : '');
        $payload = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        list($res, $error) = $this->request($method, $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => $payload,
        ]);

        if (!$res) {
            return [false, $error];
        }

        $ret = json_decode((string)$res->getBody(), true);
        if (!is_array($ret)) {
            return [false, '解析结果失败'];
        }

        if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) {
            return [$ret, null];
        }

        return [false, $ret['error']['message'] ?? $ret['message'] ?? '接口调用失败'];
    }

    private function getAccessToken()
    {
        if ($this->accessToken !== '' && $this->accessTokenExpiresAt - 60 > time()) {
            return [$this->accessToken, null];
        }

        if (empty($this->serviceAccount['client_email']) || empty($this->serviceAccount['private_key'])) {
            return [false, 'Google Cloud 服务账号配置不完整'];
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        if (!empty($this->serviceAccount['private_key_id'])) {
            $header['kid'] = $this->serviceAccount['private_key_id'];
        }

        $claims = [
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/ndev.clouddns.readwrite',
            'aud' => $this->serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $jwt = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES))
            . '.'
            . $this->base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES));

        $signature = '';
        $privateKey = openssl_pkey_get_private($this->serviceAccount['private_key']);
        if (!$privateKey || !openssl_sign($jwt, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return [false, 'Google Cloud 私钥签名失败'];
        }

        $assertion = $jwt . '.' . $this->base64UrlEncode($signature);
        list($res, $error) = $this->post($claims['aud'], [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ],
        ]);

        if (!$res) {
            return [false, $error];
        }

        $ret = json_decode((string)$res->getBody(), true);
        if (!is_array($ret) || empty($ret['access_token'])) {
            return [false, $ret['error_description'] ?? $ret['error'] ?? '获取 Google Cloud Access Token 失败'];
        }

        $this->accessToken = $ret['access_token'];
        $this->accessTokenExpiresAt = $now + intval($ret['expires_in'] ?? 3600);

        return [$this->accessToken, null];
    }

    private function buildRecordPayload($record)
    {
        $type = strtoupper((string)$record['Type']);
        $value = (string)$record['Value'];

        return [
            'name' => $this->buildRecordName($record['Name'], $record['Domain']),
            'type' => $type,
            'ttl' => intval($record['TTL'] ?? 300),
            'rrdatas' => $record['RawValues'] ?? [$this->formatRecordValue($type, $value)],
        ];
    }

    private function buildRecordName($name, $domain)
    {
        $name = trim((string)$name);
        $domain = rtrim(trim((string)$domain), '.');

        if ($name === '' || $name === '@') {
            return $domain . '.';
        }

        if ($name === $domain || substr($name, -strlen('.' . $domain)) === '.' . $domain) {
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

    private function formatRecordValue($type, $value)
    {
        $type = strtoupper(trim((string)$type));
        $value = trim((string)$value);

        if ($type === 'TXT') {
            return '"' . addcslashes(trim($value, '"'), '"\\') . '"';
        }

        if ($type === 'MX' && !preg_match('/^\d+\s+/', $value)) {
            return '10 ' . rtrim($value, '.') . '.';
        }

        if ($type === 'SRV' && preg_match('/^(\d+\s+\d+\s+\d+\s+)(.+)$/', $value, $matches)) {
            return $matches[1] . rtrim(trim($matches[2]), '.') . '.';
        }

        if (in_array($type, ['CNAME', 'MX', 'NS'], true)) {
            return rtrim($value, '.') . '.';
        }

        return $value;
    }

    private function normalizeDisplayValue($type, $value)
    {
        $value = trim((string)$value);

        if ($type === 'TXT') {
            return trim($value, '"');
        }

        if ($type === 'MX' && preg_match('/^\d+\s+(.+)$/', $value, $matches)) {
            return rtrim($matches[1], '.');
        }

        return rtrim($value, '.');
    }

    private function buildRecordId($name, $type)
    {
        return substr(sha1(strtolower(trim((string)$name)) . '|' . strtoupper(trim((string)$type))), 0, 40);
    }

    private function buildQuery($query)
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

    private function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
