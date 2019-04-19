<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/16
 * Time: 10:58
 */

namespace App\Klsf\Dns;


class DnsLa implements DnsInterface
{
    use DnsHttp;
    private $url = "https://www.dns.la/api/";
    private $apiKey;
    private $apiId;
    private $params = [];

    function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        $params = [];
        $params['cmd'] = 'remove';
        $params['domainid'] = $DomainId;
        $params['recordid'] = $RecordId;
        list($ret, $error) = $this->getResult("record.ashx", $params);
        return $ret ? [true, null] : [false, $error];
    }

    function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['cmd'] = 'edit';
        $params['domainid'] = $DomainId;
        $params['recordid'] = $RecordId;
        $params['host'] = $Name;
        $params['recordtype'] = $Type;
        $params['recordline'] = $LineId;
        $params['recorddata'] = $Value;
        list($ret, $error) = $this->getResult("record.ashx", $params);
        return $ret ? [true, null] : [false, $error];
    }

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['cmd'] = 'create';
        $params['domainid'] = $DomainId;
        $params['host'] = $Name;
        $params['recordtype'] = $Type;
        $params['recordline'] = $LineId;
        $params['recorddata'] = $Value;
        list($ret, $error) = $this->getResult("record.ashx", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['resultid'])) {
            return [[
                'RecordId' => $ret['resultid'],
                'Name' => $Name,
                'Domain' => $Domain
            ], null];
        }
        return [false, '添加域名记录失败'];
    }

    function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domainid'] = $DomainId;
        $params['recordid'] = $RecordId;
        $params['cmd'] = 'get';
        list($ret, $error) = $this->getResult("record.ashx", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['data'])) {
            $record = $ret['data'];
            return [[
                'RecordId' => $record['recordid'],
                'Name' => $record['host'],
                'Type' => $record['record_type'],
                'Value' => $record['record_data'],
                'Domain' => $Domain
            ], null];
        }
        return [false, '获取域名记录详情失败'];
    }

    function getDomainRecords($DomainId = null, $Domain = null)
    {
        $params = [];
        $params['domainid'] = $DomainId;
        $params['cmd'] = 'list';
        list($ret, $error) = $this->getResult("record.ashx", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['datas'])) {
            $list = [];
            foreach ($ret['datas'] as $record) {
                $list[] = [
                    'RecordId' => $record['recordid'],
                    'Name' => $record['host'],
                    'Type' => $record['record_type'],
                    'Value' => $record['record_data'],
                    'Domain' => $Domain
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名记录详情失败'];
    }

    function getDomainList()
    {
        $params = [];
        $params['cmd'] = 'list';
        $params['pagesize'] = 200;
        list($ret, $error) = $this->getResult("domain.ashx", $params);
        if (!$ret) return [false, $error];

        if (isset($ret['datas'])) {
            $list = [];
            foreach ($ret['datas'] as $domain) {
                $list[] = [
                    'Domain' => $domain['domainname'],
                    'DomainId' => $domain['domainid']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名列表失败'];
    }

    function getRecordLine($_domainId = null, $_domain = null)
    {
        $str = 'Def:默认,TelDef:电信,UniDef:联通,EduDef:教育网,MobDef:移动,CTTDef:铁通,ForDef:国外,Spi:搜索引擎,BaiSpi:百度蜘蛛,GooSpi:谷歌蜘蛛,haospi:好搜蜘蛛,SosSpi:搜搜蜘蛛,SogSpi:搜狗蜘蛛,YahSpi:雅虎蜘蛛';
        $l1 = explode(',', $str);
        $list = array();
        foreach ($l1 as $str) {
            $l2 = explode(':', $str);
            $list[] = array(
                'Name' => $l2[1],
                'Id' => $l2[0],
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
        $this->apiId = isset($config['ApiId']) ? $config['ApiId'] : null;
        $this->apiKey = isset($config['ApiKey']) ? $config['ApiKey'] : null;

        $this->params['apiid'] = $this->apiId;
        $this->params['apipass'] = $this->apiKey;
    }

    function configInfo()
    {
        return [
            [
                'name' => 'ApiId',
                'placeholder' => '请输入APIID',
                'tips' => '<a href="https://www.dns.la/manage/APISet.aspx" target="_blank">点击查看</a>'
            ],
            [
                'name' => 'ApiKey',
                'placeholder' => '请输入API密钥',
                'tips' => ''
            ]
        ];
    }

    private function getResult($action, $params = [])
    {
        $params = array_merge($this->params, $params);

        list($res, $error) = $this->post($this->url . $action, [
            'body' => $this->buildParameter($params)
        ]);

        if (!$res) return [false, $error];

        $body = (string)$res->getBody();
        if ($ret = json_decode($body, true)) {
            if (isset($ret['status']['code'])) {
                if ($ret['status']['code'] == 300) {
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