<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/16
 * Time: 10:58
 */

namespace App\Klsf\Dns;


use GuzzleHttp\Client;

class DnsLa implements DnsInterface
{
    use DnsHttp;

    private $url = "https://api.dns.la";
    private $apiId;
    private $apiSecret;

    private const RECORD_TYPES = [
        'A' => 1,
        'NS' => 2,
        'CNAME' => 5,
        'MX' => 15,
        'TXT' => 16,
        'AAAA' => 28,
        'SRV' => 33,
        'URL' => 256,
        'CAA' => 257,
    ];

    private const RECORD_TYPE_NAMES = [
        1 => 'A',
        2 => 'NS',
        5 => 'CNAME',
        15 => 'MX',
        16 => 'TXT',
        28 => 'AAAA',
        33 => 'SRV',
        256 => 'URL',
        257 => 'CAA',
    ];

    function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        list($ret, $error) = $this->getResult('/api/record', ['id' => $RecordId], 'DELETE');
        return $ret ? [true, null] : [false, $error];
    }

    function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [
            'id' => (string)$RecordId,
            'type' => $this->normalizeRecordType($Type),
            'host' => $this->normalizeHost($Name),
            'data' => $Value,
            'ttl' => 600,
        ];

        if ($LineId !== null && $LineId !== '' && $LineId !== 0 && $LineId !== '0') {
            $params['lineId'] = (string)$LineId;
        }

        list($ret, $error) = $this->getResult('/api/record', $params, 'PUT');
        return $ret ? [true, null] : [false, $error];
    }

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [
            'domainId' => (string)$DomainId,
            'type' => $this->normalizeRecordType($Type),
            'host' => $this->normalizeHost($Name),
            'data' => $Value,
            'ttl' => 600,
        ];

        if ($LineId !== null && $LineId !== '' && $LineId !== 0 && $LineId !== '0') {
            $params['lineId'] = (string)$LineId;
        }

        list($ret, $error) = $this->getResult('/api/record', $params, 'POST');
        if (!$ret) {
            return [false, $error];
        }

        if (isset($ret['data']['id'])) {
            return [[
                'RecordId' => $ret['data']['id'],
                'Name' => $Name,
                'Domain' => $Domain
            ], null];
        }

        return [false, '添加域名记录失败'];
    }

    function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
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

    function getDomainRecords($DomainId = null, $Domain = null)
    {
        $list = [];
        $page = 1;

        do {
            list($ret, $error) = $this->getResult('/api/recordList', [
                'pageIndex' => $page,
                'pageSize' => 100,
                'domainId' => (string)$DomainId,
            ], 'GET');
            if (!$ret) {
                return [false, $error];
            }

            if (!isset($ret['data']['results'])) {
                return [false, '获取域名记录列表失败'];
            }

            foreach ($ret['data']['results'] as $record) {
                $list[] = [
                    'RecordId' => $record['id'],
                    'Name' => isset($record['displayHost']) ? $record['displayHost'] : $record['host'],
                    'Type' => $this->resolveRecordTypeName($record['type']),
                    'Value' => isset($record['displayData']) ? $record['displayData'] : $record['data'],
                    'Domain' => $this->normalizeDomainName($Domain),
                ];
            }

            $total = isset($ret['data']['total']) ? intval($ret['data']['total']) : count($list);
            $pageSize = 100;
            $totalPages = max(1, (int)ceil($total / $pageSize));
            $page++;
        } while ($page <= $totalPages);

        return [$list, null];
    }

    function getDomainList()
    {
        $list = [];
        $page = 1;

        do {
            list($ret, $error) = $this->getResult('/api/domainList', [
                'pageIndex' => $page,
                'pageSize' => 100,
            ], 'GET');
            if (!$ret) {
                return [false, $error];
            }

            if (!isset($ret['data']['results'])) {
                return [false, '获取域名列表失败'];
            }

            foreach ($ret['data']['results'] as $domain) {
                $list[] = [
                    'Domain' => $this->normalizeDomainName(isset($domain['displayDomain']) ? $domain['displayDomain'] : $domain['domain']),
                    'DomainId' => $domain['id']
                ];
            }

            $total = isset($ret['data']['total']) ? intval($ret['data']['total']) : count($list);
            $pageSize = 100;
            $totalPages = max(1, (int)ceil($total / $pageSize));
            $page++;
        } while ($page <= $totalPages);

        return [$list, null];
    }

    function getRecordLine($_domainId = null, $_domain = null)
    {
        if ($_domain) {
            list($ret, $error) = $this->getResult('/api/availableLine', [
                'domain' => $this->normalizeDomainName($_domain)
            ], 'GET');
        } else {
            list($ret, $error) = $this->getResult('/api/allLineList', [], 'GET');
        }

        if (!$ret) {
            return [
                ['Name' => '默认', 'Id' => 0]
            ];
        }

        $list = [
            ['Name' => '默认', 'Id' => 0]
        ];

        if (isset($ret['data']) && is_array($ret['data'])) {
            $list = array_merge($list, $this->formatLineList($ret['data'], (bool)$_domain));
        }

        return $list;
    }

    function check()
    {
        list($ret, $error) = $this->getDomainList();
        return $ret ? [true, null] : [false, $error];
    }

    function config(array $config)
    {
        $this->apiId = isset($config['ApiId']) ? $config['ApiId'] : null;
        $this->apiSecret = isset($config['ApiSecret']) ? $config['ApiSecret'] : (isset($config['ApiKey']) ? $config['ApiKey'] : null);

        $this->client = new Client([
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->apiId . ':' . $this->apiSecret),
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept' => 'application/json',
            ]
        ]);
    }

    function configInfo()
    {
        return [
            [
                'name' => 'ApiId',
                'placeholder' => '请输入APIID',
                'tips' => '<a href="https://www.dns.la/docs/ApiDoc" target="_blank">查看 DNS.LA API 文档</a>'
            ],
            [
                'name' => 'ApiSecret',
                'placeholder' => '请输入APISecret',
                'tips' => 'DNS.LA 新版接口使用 Basic Auth：base64(APIID:APISecret)'
            ]
        ];
    }

    private function getResult($action, $params = [], $method = 'GET')
    {
        $options = [];

        if ($method === 'GET' || $method === 'DELETE') {
            if (!empty($params)) {
                $action .= '?' . http_build_query($params);
            }
        } else {
            $options['body'] = json_encode($params, JSON_UNESCAPED_UNICODE);
        }

        list($res, $error) = $this->request($method, $this->url . $action, $options);
        if (!$res) {
            return [false, $error];
        }

        $body = (string)$res->getBody();
        if ($ret = json_decode($body, true)) {
            if (isset($ret['code'])) {
                if (intval($ret['code']) === 200) {
                    return [$ret, null];
                }

                if (isset($ret['msg']) && $ret['msg'] !== '') {
                    return [false, $ret['msg']];
                }
            }
        }

        return [false, '解析结果失败'];
    }

    private function normalizeRecordType($type)
    {
        if (is_numeric($type)) {
            return intval($type);
        }

        $type = strtoupper(trim((string)$type));
        return isset(self::RECORD_TYPES[$type]) ? self::RECORD_TYPES[$type] : 1;
    }

    private function resolveRecordTypeName($type)
    {
        $type = intval($type);
        return isset(self::RECORD_TYPE_NAMES[$type]) ? self::RECORD_TYPE_NAMES[$type] : (string)$type;
    }

    private function normalizeHost($host)
    {
        $host = trim((string)$host);
        return $host === '' ? '@' : $host;
    }

    private function normalizeDomainName($domain)
    {
        return rtrim((string)$domain, '.');
    }

    private function formatLineList(array $lines, $compatible = false)
    {
        $list = [];
        foreach ($lines as $line) {
            $list[] = [
                'Name' => $compatible
                    ? (isset($line['value']) ? $line['value'] : $line['name'])
                    : $line['name'],
                'Id' => $line['id'],
            ];

            if (!$compatible && isset($line['children']) && is_array($line['children']) && $line['children'] !== []) {
                $list = array_merge($list, $this->formatLineList($line['children'], false));
            }
        }

        return $list;
    }
}
