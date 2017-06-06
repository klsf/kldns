<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/4/2
 * Time: 18:46
 */

namespace klsf\klsfdns;


class DnsPod implements Dns
{
    const API_URL = "https://dnsapi.cn/";

    private static $_instance = null;
    private $commonParams = array();//请求参数
    private $loginToken;
    private $errorInfo = array();//错误信息

    function __construct($_apiId = null, $_apiToken = null)
    {
        $this->loginToken = $_apiId . ',' . $_apiToken;
        $this->commonParams['login_token'] = $this->loginToken;
        $this->commonParams['format'] = "json";
        $this->commonParams['lang'] = "cn";
        $this->commonParams['error_on_empty'] = "yes";
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

    public function addDomainRecord($_rr, $_type, $_value, $_line, $_domainId = null, $_domain = null)
    {
        $_domainId && $parameter['domain_id'] = $_domainId;
        $_domain && $parameter['domain'] = $_domain;
        $parameter['sub_domain'] = $_rr;
        $parameter['record_type'] = $_type;
        $parameter['value'] = $_value;
        $parameter['mx'] = 20;
        $parameter['record_line_id'] = $_line;
        if ($arr = $this->getResult("Record.Create", $parameter)) {
            return array(
                'RecordId' => $arr['record']['id'],
                'RR' => $arr['record']['name'],
                'DomainName' => $_domain
            );
        }
        return false;
    }

    public function deleteDomainRecord($_recordId, $_domainId = null, $_domain = null)
    {
        $_domainId && $parameter['domain_id'] = $_domainId;
        $_domain && $parameter['domain'] = $_domain;
        $parameter['record_id'] = $_recordId;
        if ($this->getResult("Record.Remove", $parameter)) {
            return true;
        }
        return false;
    }

    public function updateDomainRecord($_recordId, $_rr, $_type, $_value, $_line, $_domainId = null, $_domain = null)
    {
        $_domainId && $parameter['domain_id'] = $_domainId;
        $_domain && $parameter['domain'] = $_domain;
        $parameter['record_id'] = $_recordId;
        $parameter['sub_domain'] = $_rr;
        $parameter['record_type'] = $_type;
        $parameter['value'] = $_value;
        $parameter['mx'] = 20;
        $parameter['record_line_id'] = $_line;
        if ($this->getResult("Record.Modify", $parameter)) {
            return true;
        }
        return false;
    }

    public function getDomainRecordInfo($_recordId, $_domainId = null, $_domain = null)
    {
        $_domainId && $parameter['domain_id'] = $_domainId;
        $_domain && $parameter['domain'] = $_domain;
        $parameter['record_id'] = $_recordId;
        if ($record = $this->getResult("Record.Info", $parameter)) {
            return array(
                'RecordId' => $record['record']['id'],
                'RR' => $record['record']['sub_domain'],
                'Type' => $record['record']['record_type'],
                'Value' => $record['record']['value'],
                'DomainName' => $record['domain']['domain']
            );
        }
        return false;
    }

    public function getDomainRecords($_domainId = null, $_domain = null)
    {
        $_domainId && $parameter['domain_id'] = $_domainId;
        $_domain && $parameter['domain'] = $_domain;
        if ($arr = $this->getResult("Record.List", $parameter)) {
            if (isset($arr['records'])) {
                $domain = $arr['domain']['name'];
                $list = array();
                foreach ($arr['records'] as $record) {
                    $list[] = array(
                        'RecordId' => $record['id'],
                        'RR' => $record['name'],
                        'Type' => $record['type'],
                        'Value' => $record['value'],
                        'DomainName' => $domain
                    );
                }
                return $list;
            }
        }
        return false;
    }

    public function getDomainList()
    {
        if ($arr = $this->getResult("Domain.List")) {
            if (isset($arr['domains'])) {
                $list = array();
                foreach ($arr['domains'] as $domain) {
                    $list[] = array(
                        'DomainName' => $domain['name'],
                        'DomainId' => $domain['id']
                    );
                }
                return $list;
            }
        }
        return false;
    }

    /**
     * 获取域名线路列表
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function getRecordLine($_domainId = null, $_domain = null)
    {
        /*
        $_domainId && $parameter['domain_id'] = $_domainId;
        $_domain && $parameter['domain'] = $_domain;
        if ($arr = $this->getResult("Record.Line", $parameter)) {
            if (isset($arr['line_ids'])) {
                $list = array();
                foreach ($arr['line_ids'] as $line => $id) {
                    $list[] = array(
                        'Name' => $line,
                        'Id' => $id,
                    );
                }
                return $list;
            }
        }
        return false;
        */
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

    public function checkToken()
    {
        if ($this->getResult("Info.Version")) {
            return true;
        }
        return false;
    }

    public function errorInfo()
    {
        return $this->errorInfo;
    }

    /**
     * @param $_action
     * @param array $_parameter
     * @return mixed
     */
    private function getResult($_action, $_parameter = array())
    {
        $this->errorInfo = array();
        $parameter = array_merge($this->commonParams, $_parameter);
        $ret = $this->getCurl(self::API_URL . $_action, $this->buildParameter($parameter));
        $arr = json_decode($ret, true);
        if (isset($arr['status']['code'])) {
            if ($arr['status']['code'] == 1) {
                return $arr;
            }
            $this->errorInfo = array(
                'code' => $arr['status']['code'],
                'message' => $arr['status']['message']
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
            $array[] = "$k=" . $v;
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