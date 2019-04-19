<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/16
 * Time: 9:42
 */

namespace App\Klsf\Dns;


use GuzzleHttp\Client;

class CloudXNS implements DnsInterface
{
    use DnsHttp;
    private $url = "https://www.cloudxns.net/api2/";
    private $apiKey;
    private $secretKey;
    private $date;

    function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        list($ret, $error) = $this->getResult("record/{$RecordId}/{$DomainId}", null, 'DELETE');
        return $ret ? [true, null] : [false, $error];
    }

    function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domain_id'] = $DomainId;
        $params['host'] = $Name;
        $params['type'] = $Type;
        $params['value'] = $Value;
        $params['line_id'] = $LineId;
        list($ret, $error) = $this->getResult("record/{$RecordId}", json_encode($params), 'PUT');
        return $ret ? [true, null] : [false, $error];
    }

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domain_id'] = $DomainId;
        $params['host'] = $Name;
        $params['type'] = $Type;
        $params['value'] = $Value;
        $params['line_id'] = $LineId;
        list($ret, $error) = $this->getResult("record/", json_encode($params), 'POST');
        if (!$ret) return [false, $error];
        if (isset($ret['record_id'])) {
            return [[
                'RecordId' => $ret['record_id'][0],
                'Name' => $Name,
                'Domain' => $Domain
            ], null];
        }
        return [false, '添加域名记录失败'];
    }

    function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        return [false, '无获取域名详情接口'];
    }

    function getDomainRecords($DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->getResult("record/{$DomainId}?host_id=0&offset=0&row_num=1000", null);
        if (!$ret) return [false, $error];

        if (isset($ret['data'])) {
            $list = [];
            foreach ($ret['data'] as $record) {
                $list[] = [
                    'RecordId' => $record['record_id'],
                    'Name' => $record['host'],
                    'Type' => $record['type'],
                    'Value' => $record['value'],
                    'Domain' => $Domain
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名记录列表失败'];
    }

    function getDomainList()
    {
        list($ret, $error) = $this->getResult("domain");
        if (!$ret) return [false, $error];

        if (isset($ret['data'])) {
            $list = [];
            foreach ($ret['data'] as $domain) {
                $list[] = [
                    'Domain' => trim($domain['domain'], '.'),
                    'DomainId' => $domain['id']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名列表失败'];
    }

    function getRecordLine($_domainId = null, $_domain = null)
    {
        $str = '默认:1,电信:2,联通:3,移动:144,铁通:5,教育网:6,国内其他:8,海外:9,搜索引擎:133,百度搜索:136,谷歌搜索:135,好搜搜索:137,雅虎搜索:138,搜搜搜索:139,必应搜索:142';
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
        list($ret, $error) = $this->getResult("type");
        return $ret ? [true, null] : [false, $error];
    }

    function config(array $config)
    {
        $this->apiKey = isset($config['ApiKey']) ? $config['ApiKey'] : null;
        $this->secretKey = isset($config['SecretKey']) ? $config['SecretKey'] : null;
        $this->date = date("r");

        $this->client = new Client([
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false,
            'headers' => [
                'API-KEY' => $this->apiKey,
                'API-REQUEST-DATE' => $this->date,
                'API-FORMAT' => 'json'
            ]
        ]);
    }

    function configInfo()
    {
        return [
            [
                'name' => 'ApiKey',
                'placeholder' => '请输入ApiKey',
                'tips' => '<a href="https://www.cloudxns.net/AccountManage/apimanage.html" target="_blank">点击查看</a>'
            ],
            [
                'name' => 'SecretKey',
                'placeholder' => '请输入SecretKey',
                'tips' => ''
            ]
        ];
    }

    private function getResult($action, $params = null, $method = 'GET')
    {
        $url = $this->url . $action;

        list($res, $error) = $this->request($method, $url, [
            'headers' => [
                'API-HMAC' => $this->getSign($url, $params, $this->date)
            ],
            'body' => $params
        ]);

        if (!$res) return [false, $error];

        $body = (string)$res->getBody();
        if ($ret = json_decode($body, true)) {
            if (isset($ret['code'])) {
                if ($ret['code'] == 1) {
                    return [$ret, null];
                } elseif (isset($ret['message'])) {
                    return [false, $ret['message']];
                }
            }
        }
        return [false, '解析结果失败'];
    }

    private function getSign($url, $params, $date)
    {
        return md5($this->apiKey . $url . $params . $date . $this->secretKey);
    }
}