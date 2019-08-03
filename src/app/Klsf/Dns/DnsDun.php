<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 20:49
 */

namespace App\Klsf\Dns;
class DnsDun implements DnsInterface
{
    use DnsHttp;
    private $url = "https://api.dnsdun.com/";
    private $params = [];


    public function deleteDomainRecord($RecordId, $DomainId = null, $Domain = null)
    {
        $params = [
            'domain' => $Domain,
            'record_id' => $RecordId
        ];
        list($ret, $error) = $this->getResult("c=record&a=del", $params);
        return $ret ? [true, null] : [false, $error];
    }

    public function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domain'] = $Domain;
        $params['record_id'] = $RecordId;
        $params['sub_domain'] = $Name;
        $params['record_type'] = $Type;
        $params['value'] = $Value;
        $params['record_line'] = $LineId;
        list($ret, $error) = $this->getResult("c=record&a=modify", $params);
        return $ret ? [true, null] : [false, $error];
    }

    public function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domain'] = $Domain;
        $params['sub_domain'] = $Name;
        $params['record_type'] = $Type;
        $params['value'] = $Value;
        $params['record_line'] = $LineId;
        list($ret, $error) = $this->getResult("c=record&a=add", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['record'])) {
            $record = $ret['record'];
            return [[
                'RecordId' => $record['id'],
                'Name' => $Name,
                'Domain' => $Domain
            ], null];
        }
        return [false, '添加域名记录失败'];
    }

    public function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        $params = [
            'domain' => $Domain,
            'record_id' => $RecordId
        ];
        list($ret, $error) = $this->getResult("c=record&a=info", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['record'])) {
            $record = $ret['record'];
            return [[
                'RecordId' => $record['id'],
                'Name' => $record['sub_domain'],
                'Type' => $record['record_type'],
                'Value' => $record['value'],
                'Domain' => $Domain
            ], null];
        }
        return [false, '获取域名记录详情失败'];
    }

    public function getDomainRecords($DomainId = null, $Domain = null)
    {
        $params = [
            'domain' => $Domain,
            'length' => 100
        ];
        list($ret, $error) = $this->getResult("c=record&a=list", $params);
        if (!$ret) return [false, $error];

        if (isset($ret['records'])) {
            $list = [];
            foreach ($ret['records'] as $record) {
                $list[] = [
                    'RecordId' => $record['id'],
                    'Name' => $record['name'],
                    'Type' => $record['type'],
                    'Value' => $record['value'],
                    'Domain' => $Domain
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名记录列表失败'];
    }

    public function getDomainList()
    {
        list($ret, $error) = $this->getResult("c=domain&a=getList", ['length' => 100]);
        if (!$ret) return [false, $error];
        if (isset($ret['domains'])) {
            $list = [];
            foreach ($ret['domains'] as $domain) {
                $list[] = [
                    'Domain' => $domain['domain'],
                    'DomainId' => $domain['domain']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名列表失败'];
    }

    public function getRecordLine($_domainId = null, $_domain = null)
    {
        $str = '默认,电信,移动,联通,铁通,教育网';
        $l1 = explode(',', $str);
        $list = array();
        foreach ($l1 as $str) {
            $list[] = array(
                'Name' => $str,
                'Id' => $str,
            );
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
        $this->params['uid'] = isset($config['UID']) ? $config['UID'] : 0;
        $this->params['api_key'] = isset($config['API_KEY']) ? $config['API_KEY'] : null;
        $this->params['format'] = "json";
        $this->params['lang'] = "cn";
    }

    public function configInfo()
    {
        return [
            [
                'name' => 'UID',
                'placeholder' => '请输入UID',
                'tips' => '<a href="https://www.dnsdun.com/user/?c=user&a=index#/user" target="_blank">账户管理</a>点开api设置即可看到自已的uid'
            ],
            [
                'name' => 'API_KEY',
                'placeholder' => '请输入API_KEY',
                'tips' => ''
            ]
        ];
    }

    private function getResult($action, $params = [])
    {
        $parameter = array_merge($this->params, $params);

        list($res, $error) = $this->post($this->url . '?' . $action, [
            'body' => $this->buildParameter($parameter),
            'proxy' => '127.0.0.1:8888'
        ]);

        if (!$res) return [false, $error];

        $body = (string)$res->getBody();
        if ($ret = json_decode($body, true)) {
            if (isset($ret['status']['code'])) {
                if ($ret['status']['code'] == 1) {
                    return [$ret, null];
                } elseif (isset($ret['status']['message'])) {
                    return [false, $ret['status']['message']];
                }
            }
        }
        return [false, '解析结果失败'];
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