<?php

namespace App\Klsf\Dns;

class Route53 implements DnsInterface
{
    use DnsHttp;

    private $url = 'https://route53.amazonaws.com';
    private $accessKeyId;
    private $secretAccessKey;
    private $sessionToken;

    public function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        list($record, $error) = $this->getDomainRecordInfo($RecordId, $DomainId, $Domain);
        if (!$record) {
            return [false, $error];
        }

        list($ret, $error) = $this->changeRecordSet($DomainId, 'DELETE', $this->buildChangePayload($record));

        return $ret ? [true, null] : [false, $error];
    }

    public function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $record = [
            'Name' => $Name,
            'Type' => strtoupper(trim((string)$Type)),
            'Value' => trim((string)$Value),
            'Domain' => $Domain,
            'TTL' => 300,
        ];

        list($ret, $error) = $this->changeRecordSet($DomainId, 'UPSERT', $this->buildChangePayload($record));

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

        list($ret, $error) = $this->changeRecordSet($DomainId, 'CREATE', $this->buildChangePayload($record));
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
        $name = '';
        $type = '';
        $identifier = '';

        do {
            $query = ['maxitems' => '100'];
            if ($name !== '') {
                $query['name'] = $name;
            }
            if ($type !== '') {
                $query['type'] = $type;
            }
            if ($identifier !== '') {
                $query['identifier'] = $identifier;
            }

            list($ret, $error) = $this->requestXml('GET', "/2013-04-01/hostedzone/{$DomainId}/rrset", $query);
            if (!$ret) {
                return [false, $error];
            }

            foreach ($this->toArray($ret['ResourceRecordSets']['ResourceRecordSet'] ?? []) as $item) {
                $nameValue = $this->valueOf($item['Name'] ?? '');
                $typeValue = strtoupper($this->valueOf($item['Type'] ?? 'A'));
                $record = [
                    'RecordId' => $this->buildRecordId($this->extractHost($nameValue, $Domain), $typeValue, $this->valueOf($item['SetIdentifier'] ?? '')),
                    'Name' => $this->extractHost($nameValue, $Domain),
                    'Type' => $typeValue,
                    'Value' => $this->normalizeDisplayValue($typeValue, $item),
                    'Domain' => $Domain,
                    'TTL' => intval($this->valueOf($item['TTL'] ?? 300)),
                ];

                if (!empty($item['ResourceRecords']['ResourceRecord'])) {
                    $rawValues = [];
                    foreach ($this->toArray($item['ResourceRecords']['ResourceRecord']) as $rr) {
                        $rawValues[] = $this->valueOf($rr['Value'] ?? '');
                    }
                    $record['RawValues'] = $rawValues;
                }

                if (!empty($item['AliasTarget']['DNSName'])) {
                    $record['RawValues'] = [$this->valueOf($item['AliasTarget']['DNSName'])];
                }

                $list[] = $record;
            }

            if ($this->isTrue($ret['IsTruncated'] ?? false)) {
                $name = $this->valueOf($ret['NextRecordName'] ?? '');
                $type = $this->valueOf($ret['NextRecordType'] ?? '');
                $identifier = $this->valueOf($ret['NextRecordIdentifier'] ?? '');
            } else {
                $name = '';
            }
        } while ($name !== '');

        return [$list, null];
    }

    public function getDomainList()
    {
        $list = [];
        $marker = '';

        do {
            $query = ['maxitems' => '100'];
            if ($marker !== '') {
                $query['marker'] = $marker;
            }

            list($ret, $error) = $this->requestXml('GET', '/2013-04-01/hostedzone', $query);
            if (!$ret) {
                return [false, $error];
            }

            foreach ($this->toArray($ret['HostedZones']['HostedZone'] ?? []) as $zone) {
                if ($this->isTrue($zone['Config']['PrivateZone'] ?? false)) {
                    continue;
                }

                $zoneId = trim(str_replace('/hostedzone/', '', $this->valueOf($zone['Id'] ?? '')));
                $domain = rtrim($this->valueOf($zone['Name'] ?? ''), '.');
                if ($zoneId === '' || $domain === '') {
                    continue;
                }

                $list[] = [
                    'Domain' => $domain,
                    'DomainId' => $zoneId,
                ];
            }

            $marker = $this->isTrue($ret['IsTruncated'] ?? false) ? $this->valueOf($ret['NextMarker'] ?? '') : '';
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
        $this->sessionToken = trim((string)($config['SessionToken'] ?? ''));
    }

    public function configInfo()
    {
        return [
            [
                'name' => 'AccessKeyId',
                'placeholder' => '请输入 AWS Access Key ID',
                'tips' => '<a href="https://docs.aws.amazon.com/Route53/latest/APIReference/API_ListHostedZones.html" target="_blank">查看 Amazon Route 53 API 文档</a>',
            ],
            [
                'name' => 'SecretAccessKey',
                'placeholder' => '请输入 AWS Secret Access Key',
                'tips' => '',
            ],
            [
                'name' => 'SessionToken',
                'placeholder' => '如使用临时凭证，请填写 Session Token',
                'tips' => '长期 Access Key 可留空。',
            ],
        ];
    }

    private function changeRecordSet($domainId, $action, $record)
    {
        $body = $this->buildChangeXml($action, $record);

        return $this->requestXml('POST', "/2013-04-01/hostedzone/{$domainId}/rrset", [], $body);
    }

    private function buildChangePayload($record)
    {
        $type = strtoupper((string)$record['Type']);
        $value = (string)$record['Value'];
        $values = $record['RawValues'] ?? [$this->formatRecordValue($type, $value)];

        return [
            'name' => $this->buildRecordName($record['Name'], $record['Domain']),
            'type' => $type,
            'ttl' => intval($record['TTL'] ?? 300),
            'values' => $values,
        ];
    }

    private function buildChangeXml($action, $record)
    {
        $xml = '<ChangeResourceRecordSetsRequest xmlns="https://route53.amazonaws.com/doc/2013-04-01/"><ChangeBatch><Changes><Change>';
        $xml .= '<Action>' . $action . '</Action><ResourceRecordSet>';
        $xml .= '<Name>' . htmlspecialchars($record['name'], ENT_XML1) . '</Name>';
        $xml .= '<Type>' . htmlspecialchars($record['type'], ENT_XML1) . '</Type>';
        $xml .= '<TTL>' . intval($record['ttl']) . '</TTL>';
        $xml .= '<ResourceRecords>';
        foreach ($record['values'] as $value) {
            $xml .= '<ResourceRecord><Value>' . htmlspecialchars($value, ENT_XML1) . '</Value></ResourceRecord>';
        }
        $xml .= '</ResourceRecords></ResourceRecordSet></Change></Changes></ChangeBatch></ChangeResourceRecordSetsRequest>';

        return $xml;
    }

    private function requestXml($method, $path, $query = [], $body = '')
    {
        $queryString = $this->buildCanonicalQuery($query);
        $url = $this->url . $path . ($queryString !== '' ? '?' . $queryString : '');
        $headers = $this->buildHeaders($method, $path, $queryString, $body);

        list($res, $error) = $this->request($method, $url, [
            'headers' => $headers,
            'body' => $body,
        ]);

        if (!$res) {
            return [false, $error];
        }

        $bodyString = (string)$res->getBody();
        if ($bodyString === '') {
            return [[], null];
        }

        $xml = @simplexml_load_string($bodyString);
        if ($xml === false) {
            return [false, trim(strip_tags($bodyString)) ?: '解析结果失败'];
        }

        $ret = json_decode(json_encode($xml), true);
        if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 300) {
            return [$ret, null];
        }

        return [false, $this->extractXmlError($ret)];
    }

    private function buildHeaders($method, $path, $queryString, $payload)
    {
        $host = parse_url($this->url, PHP_URL_HOST);
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', $payload);

        $headers = [
            'content-type' => 'application/xml',
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $timestamp,
        ];

        if ($this->sessionToken !== '') {
            $headers['x-amz-security-token'] = $this->sessionToken;
        }

        ksort($headers);
        $canonicalHeaders = '';
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= $key . ':' . trim((string)$value) . "\n";
        }

        $signedHeaders = implode(';', array_keys($headers));
        $canonicalRequest = strtoupper($method) . "\n"
            . $this->normalizePath($path) . "\n"
            . $queryString . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;

        $credentialScope = $date . '/us-east-1/route53/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        $signingKey = $this->getSigningKey($date);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $result = [
            'Content-Type' => 'application/xml',
            'Host' => $host,
            'X-Amz-Content-Sha256' => $payloadHash,
            'X-Amz-Date' => $timestamp,
            'Authorization' => 'AWS4-HMAC-SHA256 Credential=' . $this->accessKeyId . '/' . $credentialScope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
        ];

        if ($this->sessionToken !== '') {
            $result['X-Amz-Security-Token'] = $this->sessionToken;
        }

        return $result;
    }

    private function getSigningKey($date)
    {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secretAccessKey, true);
        $kRegion = hash_hmac('sha256', 'us-east-1', $kDate, true);
        $kService = hash_hmac('sha256', 'route53', $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
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
        return implode('/', array_map('rawurlencode', array_map('rawurldecode', explode('/', $path))));
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

    private function normalizeDisplayValue($type, $item)
    {
        $value = '';
        if (!empty($item['ResourceRecords']['ResourceRecord'])) {
            $records = $item['ResourceRecords']['ResourceRecord'];
            if (isset($records['Value'])) {
                $records = [$records];
            }
            $first = $records[0]['Value'] ?? '';
            $value = $this->valueOf($first);
        } elseif (!empty($item['AliasTarget']['DNSName'])) {
            $value = $this->valueOf($item['AliasTarget']['DNSName']);
        }

        $value = trim((string)$value);

        if ($type === 'TXT') {
            return trim($value, '"');
        }

        if ($type === 'MX' && preg_match('/^\d+\s+(.+)$/', $value, $matches)) {
            return rtrim($matches[1], '.');
        }

        return rtrim($value, '.');
    }

    private function buildRecordId($name, $type, $identifier = '')
    {
        return substr(sha1(strtolower(trim((string)$name)) . '|' . strtoupper(trim((string)$type)) . '|' . strtolower(trim((string)$identifier))), 0, 40);
    }

    private function valueOf($value)
    {
        return is_array($value) ? '' : (string)$value;
    }

    private function toArray($value)
    {
        if (!is_array($value)) {
            return [];
        }

        return isset($value[0]) ? $value : [$value];
    }

    private function isTrue($value)
    {
        return strtolower($this->valueOf($value)) === 'true';
    }

    private function extractXmlError($ret)
    {
        if (isset($ret['Error']['Message'])) {
            return $this->valueOf($ret['Error']['Message']);
        }

        if (isset($ret['Message'])) {
            return $this->valueOf($ret['Message']);
        }

        return '接口调用失败';
    }
}
