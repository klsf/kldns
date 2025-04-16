<?php
/**
 * Created by PhpStorm.
 * User: AMEN
 * Date: 2025/4/16
 * Time: 9:59
 */

namespace App\Klsf\Dns;


class West implements DnsInterface
{
    use DnsHttp;
    private $method = "POST";
    private $username;
    private $password;
    private $params = [];
    private WestApi $WestApi;

    function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        $data = array(
            'action' => '/domain/',
            'method' => 'POST',
            'body' => array(
                'act' => 'deldnsrecord',
                'domain' => $Domain,
                'id' => $RecordId
            )
        );
        $resp = $this->WestApi->fetch_api($data);
        if ($resp['result'] == 200) {
            return [true, null];
        }
        return [false, $resp['msg']];
    }

    function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $line = $LineId;
        if ($LineId === "0") {
            $line = "";
        }
        $data = array(
            'action' => '/domain/',
            'method' => 'POST',
            'body' => array(
                'act' => 'moddnsrecord',
                'domain' => $Domain,
                'id' => $RecordId,
                'value' => $Value,
                'ttl' => "60",
                'host' => $Name,
                'type' => $Type,
                'line' => $line,
                'level' => "10",
            )
        );
        $resp = $this->WestApi->fetch_api($data);
        if ($resp['result'] == 200) {
            return [true, null];
        }
        return [false, $resp['msg']];
    }

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $line = $LineId;
        if ($LineId === "0") {
            $line = "";
        }
        $data = array(
            'action' => '/domain/',
            'method' => 'POST',
            'body' => array(
                'act' => 'adddnsrecord',
                'domain' => $Domain,
                'host' => $Name,
                'type' => $Type,
                'value' => $Value,
                'line' => $line,
                'ttl' => "60",
                'level' => "10",
            )
        );
        $resp = $this->WestApi->fetch_api($data);
        if ($resp['result'] == 200) {
            return [[
                'RecordId' => $resp['data']['id'],
                'Name' => $Name,
                'Domain' => $Domain
            ], null];
        }
        return [false, $resp['msg']];
    }

    function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
//        $data = array(
//            'action' => '/domain/',
//            'method' => 'POST',
//            'body' => array(
//                'act' => 'getdnsrecord',
//                'domain' => $Domain,
//                'host' => $Name,
//                'type' => $Type,
//                'value' => $Value,
//                'line' => $LineId,
//                'ttl' => "60",
//                'level' => "10",
//            )
//        );
//        $params = [];
//        $params['RecordId'] = $RecordId;
//        list($ret, $error) = $this->getResult("DescribeDomainRecordInfo", $params);
//        if (!$ret) return [false, $error];
//        if (isset($ret['RecordId'])) {
//            return [[
//                'RecordId' => $ret['RecordId'],
//                'Name' => $ret['RR'],
//                'Type' => $ret['Type'],
//                'Value' => $ret['Value'],
//                'Domain' => $ret['DomainName']
//            ], null];
//        }
        return [false, '获取域名记录详情失败'];
    }

    function getDomainRecords($DomainId = null, $Domain = null)
    {
        $data = array(
            'action' => '/domain/',
            'method' => 'POST',
            'body' => array(
                'act' => 'getdnsrecord',
                'domain' => $Domain,
                'pageno' => 1,
                'limit' => 999999,
            )
        );
        $resp = $this->WestApi->fetch_api($data);
        if ($resp['result'] != 200) {
            return [false, '获取域名记录列表失败'];
        }
        $list = [];
        foreach ($resp['data']['items'] as $record) {
            $list[] = [
                'RecordId' => $record['id'],
                'Name' => $record['item'],
                'Type' => $record['type'],
                'Value' => $record['value'],
                'Domain' => $Domain
            ];
        }
        return [$list, null];
    }

    function getDomainList()
    {
        $data = array(
            'action' => '/domain/?act=getdomains&domain=&limit=99999&page=1',
            'method' => 'GET'
        );
        $resp = $this->WestApi->fetch_api($data);
        if ($resp['result'] != 200) {
            return [false, $resp['msg']];
        }

        // 本来就是array 抽风 应为 'array' 类型的形参，提供的是 'bool|string'
        if (!key_exists('data', $resp)) {
            return [false, '获取域名列表失败'];
        }
        $list = [];
        foreach ($resp['data']['items'] as $domain) {
            $list[] = [
                'Domain' => $domain['domain'],
                'DomainId' => $domain['id']
            ];
        }
        return [$list, null];
    }

    function getRecordLine($_domainId = null, $_domain = null)
    {
        $str = '默认:0,电信:LTEL,联通:LCNC,移动:LMOB,教育网:LEDU,搜索引擎:LSEO';
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
        date_default_timezone_set("GMT");//设置时区

        $this->username = isset($config['username']) ? $config['username'] : null;
        $this->password = isset($config['password']) ? $config['password'] : null;
        $this->WestApi = new WestApi($this->username, $this->password);
    }

    function configInfo()
    {
        return [
            [
                'name' => 'username',
                'placeholder' => '请输入 username',
                'tips' => ''
            ],
            [
                'name' => 'password',
                'placeholder' => '请输入 password',
                'tips' => 'api连接密码:<a href="https://www.west.cn/manager/API/APIconfig.asp">点我获取</a>>'
            ]
        ];
    }
}
