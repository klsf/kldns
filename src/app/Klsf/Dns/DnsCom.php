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
    private $url = "http://api.dns.com/api/";
    private $apiKey;
    private $apiSecret;
    private $params = [];

    function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        $params = [];
        $params['domainID'] = $DomainId;
        $params['recordID'] = $RecordId;
        list($ret, $error) = $this->getResult("record/remove", $params);
        return $ret ? [true, null] : [false, $error];
    }

    function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domainID'] = $DomainId;
        $params['recordID'] = $RecordId;
        $params['newhost'] = $Name;
        $params['newtype'] = $Type;
        $params['newvalue'] = $Value;
        $params['viewID'] = $LineId;
        list($ret, $error) = $this->getResult("record/modify", $params);
        return $ret ? [true, null] : [false, $error];
    }

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domainID'] = $DomainId;
        $params['host'] = $Name;
        $params['type'] = $Type;
        $params['value'] = $Value;
        $params['viewID'] = $LineId;
        list($ret, $error) = $this->getResult("record/create", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['data']['recordID'])) {
            return [[
                'RecordId' => $ret['data']['recordID'],
                'Name' => $ret['data']['record'],
                'Domain' => $Domain
            ], null];
        }
        return [false, '添加域名记录失败'];
    }

    function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domainID'] = $DomainId;
        $params['recordID'] = $RecordId;
        list($ret, $error) = $this->getResult("record/getsingle", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['data'])) {
            $record = $ret['data'];
            return [[
                'RecordId' => $record['recordID'],
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
        $params = [];
        $params['domainID'] = $DomainId;
        $params['page'] = 1;
        $params['pageSize'] = 1000;
        list($ret, $error) = $this->getResult("record/list", $params);
        if (!$ret) return [false, $error];

        if (isset($ret['data']['data'])) {
            $list = [];
            foreach ($ret['data']['data'] as $record) {
                $list[] = [
                    'RecordId' => $record['recordID'],
                    'Name' => $record['record'],
                    'Type' => $record['type'],
                    'Value' => $record['value'],
                    'Domain' => $record['domains']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名记录列表失败'];
    }

    function getDomainList()
    {
        $params = [];
        $params['page'] = 1;
        $params['pageSize'] = 1000;
        list($ret, $error) = $this->getResult("domain/list", $params);
        if (!$ret) return [false, $error];

        if (isset($ret['data']['data'])) {
            $list = [];
            foreach ($ret['data']['data'] as $domain) {
                $list[] = [
                    'Domain' => $domain['domains'],
                    'DomainId' => $domain['domainsID']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名列表失败'];
    }

    function getRecordLine($_domainId = null, $_domain = null)
    {
        $str = '默认:0,电信:285344768,联通:285345792,移动:285346816';
        $l1 = explode(',', $str);
        $list = array();
        foreach ($l1 as $str) {
            $l2 = explode(':', $str);
            $list[] = array(
                'Name' => $l2[0],
                'Id' => $l2[1],
            );
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
                'tips' => '<a href="https://www.dns.com/member/apiSet" target="_blank">点击查看</a>'
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