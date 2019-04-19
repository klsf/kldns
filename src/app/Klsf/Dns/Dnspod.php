<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 20:49
 */

namespace App\Klsf\Dns;
class Dnspod implements DnsInterface
{
    use DnsHttp;
    private $url = "https://dnsapi.cn/";
    private $id;
    private $token;
    private $params = [];


    public function deleteDomainRecord($RecordId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $DomainId && $params['domain_id'] = $DomainId;
        $Domain && $params['domain'] = $Domain;
        $params['record_id'] = $RecordId;
        list($ret, $error) = $this->getResult("Record.Remove", $params);
        return $ret ? [true, null] : [false, $error];
    }

    public function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $DomainId && $params['domain_id'] = $DomainId;
        $Domain && $params['domain'] = $Domain;
        $params['record_id'] = $RecordId;
        $params['sub_domain'] = $Name;
        $params['record_type'] = $Type;
        $params['value'] = $Value;
        $params['mx'] = 20;
        $params['record_line_id'] = $LineId;
        list($ret, $error) = $this->getResult("Record.Modify", $params);
        return $ret ? [true, null] : [false, $error];
    }

    public function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $DomainId && $params['domain_id'] = $DomainId;
        $Domain && $params['domain'] = $Domain;
        $params['sub_domain'] = $Name;
        $params['record_type'] = $Type;
        $params['value'] = $Value;
        $params['mx'] = 20;
        $params['record_line_id'] = $LineId;
        list($ret, $error) = $this->getResult("Record.Create", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['record'])) {
            $record = $ret['record'];
            return [[
                'RecordId' => $record['id'],
                'Name' => $record['name'],
                'Domain' => $Domain
            ], null];
        }
        return [false, '添加域名记录失败'];
    }

    public function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $DomainId && $params['domain_id'] = $DomainId;
        $Domain && $params['domain'] = $Domain;
        $params['record_id'] = $RecordId;
        list($ret, $error) = $this->getResult("Record.Info", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['record'])) {
            $record = $ret['record'];
            return [[
                'RecordId' => $record['id'],
                'Name' => $record['sub_domain'],
                'Type' => $record['record_type'],
                'Value' => $record['value'],
                'Domain' => $record['domain']
            ], null];
        }
        return [false, '获取域名记录详情失败'];
    }

    public function getDomainRecords($DomainId = null, $Domain = null)
    {
        $params = [];
        $DomainId && $params['domain_id'] = $DomainId;
        $Domain && $params['domain'] = $Domain;
        list($ret, $error) = $this->getResult("Record.List", $params);
        if (!$ret) return [false, $error];

        if (isset($ret['records'])) {
            $Domain = $ret['domain']['name'];
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
        list($ret, $error) = $this->getResult("Domain.List");
        if (!$ret) return [false, $error];

        if (isset($ret['domains'])) {
            $list = [];
            foreach ($ret['domains'] as $domain) {
                $list[] = [
                    'Domain' => $domain['name'],
                    'DomainId' => $domain['id']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名列表失败'];
    }

    public function getRecordLine($_domainId = null, $_domain = null)
    {
        //由于大部分域名套餐都是免费版，就不用动态获取，都是固定值
        $str = '默认:0,国内:7=0,国外:3=0,电信:10=0,联通:10=1,教育网:10=2,移动:10=3,百度:90=0,谷歌:90=1,搜搜:90=4,有道:90=2,必应:90=3,搜狗:90=5,奇虎:90=6,搜索引擎:80=0';
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

    public function check()
    {
        list($ret, $error) = $this->getResult("Info.Version");
        return $ret ? [true, null] : [false, $error];
    }

    public function config(array $config)
    {
        $this->id = isset($config['ID']) ? $config['ID'] : 0;
        $this->token = isset($config['Token']) ? $config['Token'] : null;

        $this->params['login_token'] = $this->id . ',' . $this->token;
        $this->params['format'] = "json";
        $this->params['lang'] = "cn";
        $this->params['error_on_empty'] = "yes";
    }

    public function configInfo()
    {
        return [
            [
                'name' => 'ID',
                'placeholder' => '请输入ID',
                'tips' => '<a href="https://support.dnspod.cn/Kb/showarticle/tsid/227/" target="_blank">查看获取教程</a>'
            ],
            [
                'name' => 'Token',
                'placeholder' => '请输入Token',
                'tips' => ''
            ]
        ];
    }

    private function getResult($action, $params = [])
    {
        $parameter = array_merge($this->params, $params);

        list($res, $error) = $this->post($this->url . $action, [
            'body' => $this->buildParameter($parameter)
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