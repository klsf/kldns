<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/4/2
 * Time: 21:50
 */

namespace klsf\klsfdns;


class DnsCom implements Dns
{
    const API_URL = "http://api.dns.com/api/";

    private static $_instance = null;
    private $commonParams = array();//请求参数
    private $apiKey;
    private $apiSecret;
    private $errorInfo = array();//错误信息

    function __construct($_apiKey = null, $_apiSecret = null)
    {
        $this->apiKey = $_apiKey;
        $this->apiSecret = $_apiSecret;
        $this->commonParams['apiKey'] = $this->apiKey;
    }

    static public function getInstance($_apiKey = null, $_apiSecret = null)
    {
        if (is_null(self::$_instance) || isset (self::$_instance)) {
            self::$_instance = new self ($_apiKey, $_apiSecret);
        }
        return self::$_instance;
    }

    /**
     * 添加解析记录
     * @param $_rr
     * @param $_type
     * @param $_value
     * @param $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function addDomainRecord($_rr, $_type, $_value, $_domainId, $_domain = null)
    {
        $parameter['domainID'] = $_domainId;
        $parameter['host'] = $_rr;
        $parameter['type'] = $_type;
        $parameter['value'] = $_value;
        if ($arr = $this->getResult("record/create", $parameter)) {
            return array(
                'RecordId' => $arr['data']['recordID'],
                'RR' => $arr['data']['record'],
                'DomainName' => $_domain
            );
        }
        return false;
    }

    /**
     * 删除解析记录
     * @param $_recordId
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function deleteDomainRecord($_recordId, $_domainId = null, $_domain = null)
    {
        $parameter['domainID'] = $_domainId;
        $parameter['recordID'] = $_recordId;
        if ($this->getResult("record/remove", $parameter)) {
            return true;
        }
        return false;
    }

    /**
     * 修改解析记录
     * @param $_recordId
     * @param $_rr
     * @param $_type
     * @param $_value
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function updateDomainRecord($_recordId, $_rr, $_type, $_value, $_domainId = null, $_domain = null)
    {
        $parameter['domainID'] = $_domainId;
        $parameter['recordID'] = $_recordId;
        $parameter['newhost'] = $_rr;
        $parameter['newtype'] = $_type;
        $parameter['newvalue'] = $_value;
        if ($this->getResult("record/modify", $parameter)) {
            return true;
        }
        return false;
    }

    /**
     * 获取解析记录信息
     * @param $_recordId
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function getDomainRecordInfo($_recordId, $_domainId = null, $_domain = null)
    {
        $parameter['domainID'] = $_domainId;
        $parameter['recordID'] = $_recordId;
        if ($record = $this->getResult("record/getsingle", $parameter)) {
            $record = $record['data'];
            return array(
                'RecordId' => $record['recordID'],
                'RR' => $record['record'],
                'Type' => $record['type'],
                'Value' => $record['value'],
                'DomainName' => $_domain
            );
        }
        return false;
    }

    /**
     * 获取域名记录列表
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function getDomainRecords($_domainId = null, $_domain = null)
    {
        $parameter['domainID'] = $_domainId;
        $parameter['page'] = 1;
        $parameter['pageSize'] = 1000;
        if ($arr = $this->getResult("record/list", $parameter)) {
            if (isset($arr['data']['data'])) {
                $list = array();
                foreach ($arr['data']['data'] as $record) {
                    $list[] = array(
                        'RecordId' => $record['recordID'],
                        'RR' => $record['record'],
                        'Type' => $record['type'],
                        'Value' => $record['value'],
                        'DomainName' => $record['domains']
                    );
                }
                return $list;
            }
        }
        return false;
    }

    /**
     * 获取域名列表
     * @return mixed
     */
    public function getDomainList()
    {
        $parameter['page'] = 1;
        $parameter['pageSize'] = 1000;
        if ($arr = $this->getResult("domain/list/", $parameter)) {
            if (isset($arr['data']['data'])) {
                $list = array();
                foreach ($arr['data']['data'] as $domain) {
                    $list[] = array(
                        'DomainName' => $domain['domains'],
                        'DomainId' => $domain['domainsID']
                    );
                }
                return $list;
            }
        }
        return false;
    }

    /**
     * 验证配置是否正确
     * @return bool
     */
    public function checkToken()
    {
        if ($this->getDomainList()) {
            return true;
        }
        return false;
    }

    /**
     * 获取错误信息
     * @return array
     */
    public function errorInfo()
    {
        return $this->errorInfo;
    }

    private function getHash($_parameter = array())
    {
        ksort($_parameter);
        $str = $this->buildParameter($_parameter);
        return md5($str . $this->apiSecret);
    }

    /**
     * @param $_action
     * @param array $_parameter
     * @return mixed
     */
    private function getResult($_action, $_parameter = array())
    {
        $this->errorInfo = array();
        $parameter['timestamp'] = time();
        $parameter = array_merge($this->commonParams, $_parameter);
        $parameter['hash'] = $this->getHash($parameter);
        $ret = $this->getCurl(self::API_URL . $_action, $this->buildParameter($parameter));
        $arr = json_decode($ret, true);
        if (isset($arr['code'])) {
            if ($arr['code'] == 0) {
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

    private function getCurl($_url, $_postParameter = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $_url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        if ($_postParameter) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $_postParameter);
        }
        curl_setopt($ch, CURLOPT_ENCODING, "gzip");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}