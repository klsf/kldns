<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/4/1
 * Time: 20:19
 */

namespace klsf\klsfdns;


class AliYun implements Dns
{
    const API_URL = "http://alidns.aliyuncs.com/";
    const API_METHOD = "POST";

    private static $_instance = null;
    private $commonParams = array();//请求参数
    private $accessKeyId;
    private $accessKeySecret;
    private $errorInfo = array();//错误信息


    function __construct($_accessKeyId = null, $_accessKeySecret = null)
    {
        $this->accessKeyId = $_accessKeyId;
        $this->accessKeySecret = $_accessKeySecret;
        $this->init();
    }

    /*
    * 单例模式
    */
    static public function getInstance($_accessKeyId = null, $_accessKeySecret = null)
    {
        if (is_null(self::$_instance) || isset (self::$_instance)) {
            self::$_instance = new self ($_accessKeyId, $_accessKeySecret);
        }
        return self::$_instance;
    }

    /*
     * 初始化
     */
    private function init()
    {
        date_default_timezone_set("GMT");//设置时区
        //组装公共参数
        $this->commonParams["AccessKeyId"] = $this->accessKeyId;
        $this->commonParams["Format"] = 'JSON';
        $this->commonParams["Version"] = '2015-01-09';
        $this->commonParams["AccessKeyId"] = $this->accessKeyId;
        $this->commonParams["SignatureMethod"] = 'HMAC-SHA1';
        $this->commonParams["SignatureVersion"] = '1.0';
    }

    public function addDomainRecord($_rr, $_type, $_value, $_domainId = null, $_domain = null)
    {
        $parameter['DomainName'] = $_domain;
        $parameter['RR'] = $_rr;
        $parameter['Type'] = $_type;
        $parameter['Value'] = $_value;
        if ($arr = $this->getResult("AddDomainRecord", $parameter)) {
            return array(
                'RecordId' => $arr['RecordId'],
                'RR' => $_rr,
                'DomainName' => $_domain
            );
        }
        return false;
    }

    public function deleteDomainRecord($_recordId, $_domainId = null, $_domain = null)
    {
        $parameter['RecordId'] = $_recordId;
        if ($this->getResult("DeleteDomainRecord", $parameter)) {
            return true;
        }
        return false;
    }

    public function updateDomainRecord($_recordId, $_rr, $_type, $_value, $_domainId = null, $_domain = null)
    {
        $parameter['RecordId'] = $_recordId;
        $parameter['RR'] = $_rr;
        $parameter['Type'] = $_type;
        $parameter['Value'] = $_value;
        if ($this->getResult("UpdateDomainRecord", $parameter)) {
            return true;
        }
        return false;
    }

    public function getDomainRecordInfo($_recordId, $_domainId = null, $_domain = null)
    {
        $parameter['RecordId'] = $_recordId;
        if ($record = $this->getResult("DescribeDomainRecordInfo", $parameter)) {
            return array(
                'RecordId' => $record['RecordId'],
                'RR' => $record['RR'],
                'Type' => $record['Type'],
                'Value' => $record['Value'],
                'DomainName' => $record['DomainName']
            );
        }
        return false;
    }

    public function getDomainRecords($_domainId = null, $_domain = null)
    {
        $parameter['DomainName'] = $_domain;
        if ($arr = $this->getResult("DescribeDomainRecords", $parameter)) {
            if (isset($arr['DomainRecords'])) {
                $list = array();
                foreach ($arr['DomainRecords'] as $record) {
                    $list[] = array(
                        'RecordId' => $record['RecordId'],
                        'RR' => $record['RR'],
                        'Type' => $record['Type'],
                        'Value' => $record['Value'],
                        'DomainName' => $record['DomainName']
                    );
                }
                return $list;
            }
        }
        return false;
    }

    public function getDomainList()
    {
        if ($arr = $this->getResult("DescribeDomains")) {
            if (isset($arr['Domains']['Domain'])) {
                $list = array();
                foreach ($arr['Domains']['Domain'] as $domain) {
                    $list[] = array(
                        'DomainName' => $domain['DomainName'],
                        'DomainId' => $domain['DomainId']
                    );
                }
                return $list;
            }
        }
        return false;
    }

    public function checkToken()
    {
        if ($this->getDomainList()) {
            return true;
        }
        return false;
    }

    public function errorInfo()
    {
        return $this->errorInfo;
    }

    private function getErrorMessage($code, $message = null)
    {
        switch ($code) {
            case "MissingParameter":
                return "缺少参数";
            case "InvalidAccessKeyId.NotFound":
                return "无效的Access Key";
            case "SignatureDoesNotMatch":
                return "无效的签名";
            case "Forbidden.UserVerification":
                return "无实名验证";
            case "InternalError":
                return "服务器无法完成对请求的处理";
            case "ServiceUnavailable":
                return "服务器当前无法处理请求";
            case "IncorrectDomainUser":
                return "域名在本账户下不存在";
            case "DomainRecordDuplicate":
                return "解析记录已存在";
            case "DomainRecordConflict":
                return "解析记录冲突";
            case "DomainForbidden":
                return "禁止解析操作的域名";
            case "DomainRecordLocked":
                return "解析记录被锁定";
            case "RecordForbidden.DNSChange":
                return "DNS迁移过程禁止操作";
            case "DomainRecordNotBelongToUser":
                return "解析记录在本账户下不存在";
            case "QuotaExceeded.Record":
                return "超出解析记录类型最大值90";
            case "QuotaExceeded.ARecord":
                return "超出A(AAAA)记录类型最大值";
            case "Forbidden":
                return "禁止操作";
            default:
                return $message;
        }
    }

    /**
     * @param $action
     * @param array $parameter
     * @return bool
     */
    private function getResult($action, $parameter = array())
    {
        $this->errorInfo = array();
        $parameter['Action'] = $action;
        $parameter['Timestamp'] = date("Y-m-d\TH:i:s\Z");
        $parameter['SignatureNonce'] = uniqid();
        $parameter = array_merge($this->commonParams, $parameter);
        $parameter['Signature'] = $this->computeSignature($parameter);
        $ret = $this->getCurl(self::API_URL, $this->buildParameter($parameter));
        $arr = json_decode($ret, true);
        if (!empty($arr)) {
            if (!isset($arr['Code'])) {
                return $arr;
            }
            $this->errorInfo = array(
                'code' => $arr['Code'],
                'message' => $this->getErrorMessage($arr['Code'], $arr['Message'] ? $arr['Message'] : "未知错误")
            );
        } else {
            $this->errorInfo = array(
                'code' => '-666',
                'message' => '解析结果失败'
            );
        }
        return false;
    }

    /**
     * 转化数组为POST字符串
     * @param array $parameter
     * @return string
     */
    private function buildParameter($parameter = array())
    {
        $array = array();
        foreach ($parameter as $k => $v) {
            $array[] = "$k=" . $this->percentEncode($v);
        }
        return implode('&', $array);
    }

    /**
     * 计算签名
     * @param $parameters
     * @return mixed
     */
    private function computeSignature($parameters)
    {
        // 将参数Key按字典顺序排序
        ksort($parameters);
        // 生成规范化请求字符串
        $queryStr = $this->buildParameter($parameters);
        // 生成用于计算签名的字符串 stringToSign
        $stringToSign = self::API_METHOD . '&%2F&' . $this->percentEncode($queryStr);
        // 计算签名，注意accessKeySecret后面要加上字符'&'
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true));
        return $signature;
    }

    private function percentEncode($str)
    {
        // 使用urlencode编码后，将"+","*","%7E"做替换即满足 API规定的编码规范
        $res = urlencode($str);
        $res = preg_replace('/\+/', '%20', $res);
        $res = preg_replace('/\*/', '%2A', $res);
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }

    private function getCurl($url, $postParameter = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        if ($postParameter) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameter);
        }
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}