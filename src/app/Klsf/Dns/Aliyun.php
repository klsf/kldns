<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/15
 * Time: 20:13
 */

namespace App\Klsf\Dns;


class Aliyun implements DnsInterface
{
    use DnsHttp;
    private $url = "http://alidns.aliyuncs.com/";
    private $method = "POST";
    private $accessKeyId;
    private $accessKeySecret;
    private $params = [];

    function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        $params = [];
        $params['RecordId'] = $RecordId;
        list($ret, $error) = $this->getResult("DeleteDomainRecord", $params);
        return $ret ? [true, null] : [false, $error];
    }

    function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['RecordId'] = $RecordId;
        $params['RR'] = $Name;
        $params['Type'] = $Type;
        $params['Value'] = $Value;
        $params['Line'] = $LineId;
        list($ret, $error) = $this->getResult("UpdateDomainRecord", $params);
        return $ret ? [true, null] : [false, $error];
    }

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['DomainName'] = $Domain;
        $params['RR'] = $Name;
        $params['Type'] = $Type;
        $params['Value'] = $Value;
        $params['Line'] = $LineId;
        list($ret, $error) = $this->getResult("AddDomainRecord", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['RecordId'])) {
            return [[
                'RecordId' => $ret['RecordId'],
                'Name' => $Name,
                'Domain' => $Domain
            ], null];
        }
        return [false, '添加域名记录失败'];
    }

    function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['RecordId'] = $RecordId;
        list($ret, $error) = $this->getResult("DescribeDomainRecordInfo", $params);
        if (!$ret) return [false, $error];
        if (isset($ret['RecordId'])) {
            return [[
                'RecordId' => $ret['RecordId'],
                'Name' => $ret['RR'],
                'Type' => $ret['Type'],
                'Value' => $ret['Value'],
                'Domain' => $ret['DomainName']
            ], null];
        }
        return [false, '获取域名记录详情失败'];
    }

    function getDomainRecords($DomainId = null, $Domain = null)
    {
        $params = [];
        $params['DomainName'] = $Domain;
        list($ret, $error) = $this->getResult("DescribeDomainRecords", $params);
        if (!$ret) return [false, $error];

        if (isset($ret['DomainRecords']['Record'])) {
            $list = [];
            foreach ($ret['DomainRecords']['Record'] as $record) {
                $list[] = [
                    'RecordId' => $record['RecordId'],
                    'Name' => $record['RR'],
                    'Type' => $record['Type'],
                    'Value' => $record['Value'],
                    'Domain' => $record['DomainName']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名记录列表失败'];
    }

    function getDomainList()
    {
        list($ret, $error) = $this->getResult("DescribeDomains");
        if (!$ret) return [false, $error];

        if (isset($ret['Domains']['Domain'])) {
            $list = [];
            foreach ($ret['Domains']['Domain'] as $domain) {
                $list[] = [
                    'Domain' => $domain['DomainName'],
                    'DomainId' => $domain['DomainId']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名列表失败'];
    }

    function getRecordLine($_domainId = null, $_domain = null)
    {
        $str = 'default:默认,telecom:电信,unicom:联通,mobile:移动,oversea:海外,edu:教育网,线路值:线路中文说明,search:搜索引擎,google:谷歌,baidu:百度,biying:必应,youdao:有道,yahoo:雅虎';
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
        date_default_timezone_set("GMT");//设置时区

        $this->accessKeyId = isset($config['AccessKeyId']) ? $config['AccessKeyId'] : null;
        $this->accessKeySecret = isset($config['AccessKeySecret']) ? $config['AccessKeySecret'] : null;

        $this->params["Format"] = 'JSON';
        $this->params["Version"] = '2015-01-09';
        $this->params["AccessKeyId"] = $this->accessKeyId;
        $this->params["SignatureMethod"] = 'HMAC-SHA1';
        $this->params["SignatureVersion"] = '1.0';
    }

    function configInfo()
    {
        return [
            [
                'name' => 'AccessKeyId',
                'placeholder' => '请输入AccessKeyId',
                'tips' => '<a href="https://help.aliyun.com/document_detail/53045.html" target="_blank">查看创建AccessKey教程</a>'
            ],
            [
                'name' => 'AccessKeySecret',
                'placeholder' => '请输入AccessKeySecret',
                'tips' => ''
            ]
        ];
    }

    private function getResult($action, $params = [])
    {
        $params['Action'] = $action;
        $params['Timestamp'] = date("Y-m-d\TH:i:s\Z");
        $params['SignatureNonce'] = uniqid();
        $params = array_merge($this->params, $params);
        $params['Signature'] = $this->computeSignature($params);

        list($res, $error) = $this->post($this->url, [
            'body' => $this->buildParameter($params)
        ]);

        if (!$res) return [false, $error];

        $body = (string)$res->getBody();
        if ($ret = json_decode($body, true)) {
            if (!isset($ret['Code'])) {
                return [$ret, null];
            } elseif (isset($ret['Message'])) {
                return [false, $ret['Message']];
            }
        }
        return [false, '解析结果失败'];
    }

    private function buildParameter($params = [], $encode = false)
    {
        $array = array();
        foreach ($params as $k => $v) {
            //这里是个大坑，签名的时候拼接参数一定要percentEncode一次，后面还要percentEncode一次，相当于两次！  request请求时，参数一定要urlencode一次！
            $array[] = "$k=" . ($encode ? $this->percentEncode($v) : urlencode($v));
        }
        return implode('&', $array);
    }

    private function computeSignature($params)
    {
        // 将参数Key按字典顺序排序
        ksort($params);
        // 生成规范化请求字符串
        $queryStr = $this->buildParameter($params, true);
        // 生成用于计算签名的字符串 stringToSign
        $stringToSign = $this->method . '&%2F&' . $this->percentEncode($queryStr);
        // 计算签名，注意accessKeySecret后面要加上字符'&'
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true));
        return $signature;
    }

    private function percentEncode($str)
    {
        // 使用urlencode编码后，将"+","*","%7E"做替换即满足 API规定的编码规范
        $res = urlencode($str);
        $res = str_replace(array('+', '*'), array('%20', '%2A'), $res);
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }
}