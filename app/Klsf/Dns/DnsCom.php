<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/16
 * Time: 10:28
 */

namespace App\Klsf\Dns;


class DnsCom implements DnsInterface
{
    use DnsHttp;
    private $url = "https://openapi.dns.com/api/";
    private $apiKey;
    private $apiSecret;
    private $params = [];

    function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        $params = [];
        $params['domain'] = $DomainId ?: $Domain;
        $params['record_id'] = $RecordId;
        $params['status'] = 'delete';
        list($ret, $error) = $this->getResult("domain/operate/", $params);
        return $ret ? [true, null] : [false, $error];
    }

    function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domain'] = $DomainId ?: $Domain;
        $params['record_id'] = $RecordId;
        $params['record'] = $Name;
        $params['type'] = $Type;
        $params['value'] = $Value;
        $params['view_id'] = $LineId;
        list($ret, $error) = $this->getResult("record/update/", $params);
        return $ret ? [true, null] : [false, $error];
    }

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domain'] = $DomainId ?: $Domain;
        $params['record'] = $Name;
        $params['type'] = $Type;
        $params['value'] = $Value;
        $params['view_id'] = $LineId;
        list($ret, $error) = $this->getResult("record/create/", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['data']['record_id'])) {
            return [[
                'RecordId' => $ret['data']['record_id'],
                'Name' => $ret['data']['record'],
                'Domain' => $Domain
            ], null];
        }
        return [false, '添加域名记录失败'];
    }

    function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domain'] = $DomainId ?: $Domain;
        $params['record_id'] = $RecordId;
        list($ret, $error) = $this->getResult("record/getOne/", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['data'])) {
            $record = $ret['data'];
            return [[
                'RecordId' => $record['record_id'],
                'Name' => $record['record'],
                'Type' => $record['type'],
                'Value' => $record['value'],
                'Domain' => $Domain
            ], null];
        }
        return [false, '获取域名记录详情失败'];
    }

    function getDomainRecords($DomainId = null, $Domain = null)
    {
        $list = [];
        $page = 1;

        do {
            $params = [];
            $params['domain'] = $DomainId ?: $Domain;
            $params['page'] = $page;
            $params['paginate'] = 100;
            list($ret, $error) = $this->getResult("record/lists/", $params);
            if (!$ret) return [false, $error];

            if (!isset($ret['data']['data'])) {
                return [false, '获取域名记录列表失败'];
            }

            foreach ($ret['data']['data'] as $record) {
                $list[] = [
                    'RecordId' => $record['record_id'],
                    'Name' => $record['record'],
                    'Type' => $record['type'],
                    'Value' => $record['value'],
                    'Domain' => $Domain
                ];
            }

            $total = isset($ret['data']['total']) ? intval($ret['data']['total']) : count($list);
            $page++;
        } while (count($list) < $total);

        if ($list !== []) {
            return [$list, null];
        }
        return [false, '获取域名记录列表失败'];
    }

    function getDomainList()
    {
        $list = [];
        $page = 1;

        do {
            $params = [];
            $params['page'] = $page;
            $params['paginate'] = 100;
            list($ret, $error) = $this->getResult("domain/lists/", $params);
            if (!$ret) return [false, $error];

            if (!isset($ret['data']['data'])) {
                return [false, '获取域名列表失败'];
            }

            foreach ($ret['data']['data'] as $domain) {
                $list[] = [
                    'Domain' => $domain['domain'],
                    'DomainId' => $domain['domain_id']
                ];
            }

            $total = isset($ret['data']['total']) ? intval($ret['data']['total']) : count($list);
            $page++;
        } while (count($list) < $total);

        if ($list !== []) {
            return [$list, null];
        }
        return [false, '获取域名列表失败'];
    }

    function getRecordLine($_domainId = null, $_domain = null)
    {
        if (!$_domainId && !$_domain) {
            return [
                ['Name' => '默认', 'Id' => 0]
            ];
        }

        list($ret, $error) = $this->getResult('domain/getView/', [
            'domain' => $_domainId ?: $_domain
        ]);
        if (!$ret || !isset($ret['data'])) {
            return [
                ['Name' => '默认', 'Id' => 0]
            ];
        }

        $list = [];
        foreach ($ret['data'] as $line) {
            $list[] = [
                'Name' => $line['view_name'],
                'Id' => $line['id'],
            ];
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
        $this->apiKey = isset($config['ApiKey']) ? $config['ApiKey'] : null;
        $this->apiSecret = isset($config['ApiSecret']) ? $config['ApiSecret'] : null;

        $this->params['apiKey'] = $this->apiKey;
    }

    function configInfo()
    {
        return [
            [
                'name' => 'ApiKey',
                'placeholder' => '请输入ApiKey',
                'tips' => '<a href="https://www.dns.com/zh/docs/2/3" target="_blank">查看 DNS.COM OpenAPI 文档</a>'
            ],
            [
                'name' => 'ApiSecret',
                'placeholder' => '请输入ApiSecret',
                'tips' => ''
            ]
        ];
    }

    private function getResult($action, $params = [])
    {
        $params['timestamp'] = time();
        $params = array_merge($this->params, $params);
        $params['hash'] = $this->getHash($params);
        list($res, $error) = $this->post($this->url . $action, [
            'body' => $this->buildParameter($params)
        ]);

        if (!$res) return [false, $error];

        $body = (string)$res->getBody();
        if ($ret = json_decode($body, true)) {
            if (isset($ret['code'])) {
                if ($ret['code'] == 0) {
                    return [$ret, null];
                } elseif (isset($ret['message'])) {
                    return [false, $ret['message']];
                }
            }
        }
        return [false, '解析结果失败'];
    }

    private function getHash($params = [])
    {
        ksort($params);
        $str = $this->buildParameter($params);
        return md5($str . $this->apiSecret);
    }

    private function buildParameter($params = [])
    {
        $array = array();
        foreach ($params as $k => $v) {
            $array[] = "$k=" . $v;
        }
        return implode('&', $array);
    }
}
