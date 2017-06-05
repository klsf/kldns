<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/6/5
 * Time: 10:56
 */

namespace klsf\klsfdns;


class DnsLA implements Dns
{
    const DOMAIN_API_URL = "https://www.dns.la/api/domain.ashx";
    const RECORD_API_URL = "https://www.dns.la/api/record.ashx";

    private static $_instance = null;
    private $commonParams = array();//请求参数
    private $apiID;
    private $apiPass;
    private $errorInfo = array();//错误信息

    /**
     * DnsLA constructor.
     * @param null $_apiID
     * @param null $_apiPass
     */
    function __construct($_apiID = null, $_apiPass = null)
    {
        $this->apiID = $_apiID;
        $this->apiPass = $_apiPass;
        $this->commonParams['apiid'] = $this->apiID;
        $this->commonParams['apipass'] = $this->apiPass;
    }

    /**
     * @param null $_apiID
     * @param null $_apiPass
     * @return DnsLA|null
     */
    static public function getInstance($_apiID = null, $_apiPass = null)
    {
        if (is_null(self::$_instance) || isset (self::$_instance)) {
            self::$_instance = new self ($_apiID, $_apiPass);
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
        $parameter['cmd'] = 'create';
        $parameter['domainid'] = $_domainId;
        $parameter['host'] = $_rr;
        $parameter['recordtype'] = $_type;
        $parameter['recordline'] = 'Def';
        $parameter['recorddata'] = $_value;
        if ($arr = $this->getResult(self::RECORD_API_URL, $parameter)) {
            return array(
                'RecordId' => $arr['resultid'],
                'RR' => $_rr,
                'DomainName' => $_domain
            );
        }
        return false;
    }

    /**
    /**
     * 删除解析记录
     * @param $_recordId
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function deleteDomainRecord($_recordId, $_domainId = null, $_domain = null)
    {
        $parameter['cmd'] = 'remove';
        $parameter['domainid'] = $_domainId;
        $parameter['recordid'] = $_recordId;
        if ($this->getResult(self::RECORD_API_URL, $parameter)) {
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
        $parameter['cmd'] = 'edit';
        $parameter['domainid'] = $_domainId;
        $parameter['recordid'] = $_recordId;
        $parameter['host'] = $_rr;
        $parameter['recordtype'] = $_type;
        $parameter['recordline'] = 'Def';
        $parameter['recorddata'] = $_value;
        if ($this->getResult(self::RECORD_API_URL, $parameter)) {
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
        $parameter['domainid'] = $_domainId;
        $parameter['recordid'] = $_recordId;
        $parameter['cmd'] = 'get';
        if ($record = $this->getResult(self::RECORD_API_URL, $parameter)) {
            $record = $record['data'];
            return array(
                'RecordId' => $record['recordid'],
                'RR' => $record['host'],
                'Type' => $record['record_type'],
                'Value' => $record['record_data'],
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
        $parameter['domainid'] = $_domainId;
        $parameter['cmd'] = 'list';
        if ($arr = $this->getResult(self::RECORD_API_URL, $parameter)) {
            if (isset($arr['datas'])) {
                $list = array();
                foreach ($arr['datas'] as $record) {
                    $list[] = array(
                        'RecordId' => $record['recordid'],
                        'RR' => $record['host'],
                        'Type' => $record['record_type'],
                        'Value' => $record['record_data'],
                        'DomainName' => $_domain
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
        $parameter['cmd'] = 'list';
        $parameter['pagesize'] = 200;
        if ($arr = $this->getResult(self::DOMAIN_API_URL, $parameter)) {
            if (isset($arr['datas'])) {
                $list = array();
                foreach ($arr['datas'] as $domain) {
                    $list[] = array(
                        'DomainName' => $domain['domainname'],
                        'DomainId' => $domain['domainid']
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

    /**
     * @param $_url
     * @param null $_parameter
     * @return bool
     */
    private function getResult($_url, $_parameter = null)
    {
        $this->errorInfo = array();
        $parameter = array_merge($this->commonParams, $_parameter);
        $ret = $this->getCurl($_url, $this->buildParameter($parameter));
        $arr = json_decode($ret, true);
        if (isset($arr['status'])) {
            if ($arr['status']['code'] == 300) {
                return $arr;
            }
            $this->errorInfo = array(
                'code' => $arr['status']['code'],
                'message' => $arr['status']['message'],
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

    /**
     * @param $_url
     * @param null $_postParameter
     * @return mixed
     */
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