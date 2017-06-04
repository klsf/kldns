<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/4/2
 * Time: 20:49
 */

namespace klsf\klsfdns;


class CloudXNS implements Dns
{
    const API_URL = "https://www.cloudxns.net/api2/";

    private static $_instance = null;
    private $apiKey;
    private $secretKey;
    private $errorInfo = array();//错误信息

    function __construct($_apiKey = null, $_secretKey = null)
    {
        $this->apiKey = $_apiKey;
        $this->secretKey = $_secretKey;
    }

    /*
    * 单例模式
    */
    static public function getInstance($_apiKey = null, $_secretKey = null)
    {
        if (is_null(self::$_instance) || isset (self::$_instance)) {
            self::$_instance = new self ($_apiKey, $_secretKey);
        }
        return self::$_instance;
    }


    public function addDomainRecord($_rr, $_type, $_value, $_domainId = null, $_domain = null)
    {
        $parameter['domain_id'] = $_domainId;
        $parameter['host'] = $_rr;
        $parameter['type'] = $_type;
        $parameter['value'] = $_value;
        $parameter['line_id'] = 1;
        if ($arr = $this->getResult("record", json_encode($parameter))) {
            return array(
                'RecordId' => $arr['record_id'][0],
                'RR' => $_rr,
                'DomainName' => $_domain
            );
        }
        return false;
    }


    public function deleteDomainRecord($_recordId, $_domainId = null, $_domain = null)
    {
        if ($this->getResult("record/" . $_recordId . "/" . $_domainId, null, 'DELETE')) {
            return true;
        }
        return false;
    }

    public function updateDomainRecord($_recordId, $_rr, $_type, $_value, $_domainId = null, $_domain = null)
    {
        $parameter['domain_id'] = $_domainId;
        $parameter['host'] = $_rr;
        $parameter['type'] = $_type;
        $parameter['value'] = $_value;
        if ($this->getResult("record/" . $_recordId, json_encode($parameter), 'PUT')) {
            return true;
        }
        return false;
    }

    public function getDomainRecordInfo($_recordId, $_domainId = null, $_domain = null)
    {
        $this->errorInfo = array(
            'code' => '-1',
            'message' => '无此接口'
        );
        return false;
    }

    public function getDomainRecords($_domainId = null, $_domain = null)
    {
        if ($arr = $this->getResult("record/" . $_domainId . "?host_id=0&offset=0&row_num=1000")) {
            if (isset($arr['data'])) {
                $list = array();
                foreach ($arr['data'] as $record) {
                    $list[] = array(
                        'RecordId' => $record['record_id'],
                        'RR' => $record['host'],
                        'Type' => $record['type'],
                        'Value' => $record['value'],
                        'DomainName' => $_domain
                    );
                }
                return $list;
            }
        }
        return false;
    }

    public function getDomainList()
    {
        if ($arr = $this->getResult("domain")) {
            if (isset($arr['data'])) {
                $list = array();
                foreach ($arr['data'] as $domain) {
                    $list[] = array(
                        'DomainName' => trim($domain['domain'], '.'),
                        'DomainId' => $domain['id']
                    );
                }
                return $list;
            }
        }
        return false;
    }

    public function checkToken()
    {
        if ($this->getResult("type")) {
            return true;
        }
        return false;
    }

    public function errorInfo()
    {
        return $this->errorInfo;
    }


    private function getSign($_url, $_postParameter, $_date)
    {
        return md5($this->apiKey . $_url . $_postParameter . $_date . $this->secretKey);
    }

    /**
     * @param $_action
     * @param null $_parameter
     * @param string $_method
     * @return bool
     */
    private function getResult($_action, $_parameter = null, $_method = 'GET')
    {
        $this->errorInfo = array();
        $ret = $this->getCurl(self::API_URL . $_action, $_parameter, $_method);
        $arr = json_decode($ret, true);
        if (isset($arr['code'])) {
            if ($arr['code'] == 1) {
                return $arr;
            }
            $this->errorInfo = array(
                'code' => $arr['code'],
                'message' => $arr['message']
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
     * @param array $_parameter
     * @return string
     */
    private function buildParameter($_parameter = array())
    {
        $array = array();
        foreach ($_parameter as $k => $v) {
            $array[] = "$k=" . ($v);
        }
        return implode('&', $array);
    }

    private function getCurl($_url, $_postParameter = null, $_method = 'GET')
    {
        $date = date("r");
        $sign = $this->getSign($_url, $_postParameter, $date);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $header[] = "Accept:*";
        $header[] = "Accept-Encoding:gzip,deflate,sdch";
        $header[] = "Accept-Language:zh-CN,zh;q=0.8";
        $header[] = "API-KEY:" . $this->apiKey;
        $header[] = "API-REQUEST-DATE:" . $date;
        $header[] = "API-HMAC:" . $sign;
        $header[] = "API-FORMAT:json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        switch ($_method) {
            case "POST":
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case "PUT" :
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                break;
            case "DELETE":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            default:
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
        }

        $_postParameter && curl_setopt($ch, CURLOPT_POSTFIELDS, $_postParameter);

        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}