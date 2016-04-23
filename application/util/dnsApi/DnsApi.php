<?php
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 快乐是福 <815856515@qq.com>
// +----------------------------------------------------------------------
// | Date: 2016/4/23
// +----------------------------------------------------------------------

namespace app\util\dnsApi;

/**
 * 域名解析API处理接口
 * Interface dnsApi
 *
 * @package app\util\dnsApi
 */
interface DnsApi
{
    /**
     * 验证Token
     * @return mixed
     */
    public function checkToken();

    /**
     * 获取域名列表
     * @return mixed
     */
    public function getDomainList();

    /**
     * 获取域名信息
     * @param $_domain
     *
     * @return mixed
     */
    public function getDomainInfo($_domain);

    /**
     * 添加纪录
     * @param      $_domain_id
     * @param      $_name
     * @param      $_type
     * @param      $_value
     * @param null $_domain
     *
     * @return mixed
     */
    public function addRecord($_domain_id,$_name,$_type,$_value,$_domain=null);

    /**
     * 获取记录信息
     * @param $_domain_id
     * @param $_record_id
     *
     * @return mixed
     */
    public function getRecordInfo($_domain_id,$_record_id);

    /**
     * 删除记录
     * @param $_domain_id
     * @param $_record_id
     *
     * @return mixed
     */
    public function delRecord($_domain_id,$_record_id);

    /**
     * 修改记录
     * @param $_domain_id
     * @param $_record_id
     * @param $_name
     * @param $_type
     * @param $_value
     *
     * @return mixed
     */
    public function updateRecord($_domain_id,$_record_id,$_name,$_type,$_value);

    /**
     * 返回错误信息
     * @return mixed
     */
    public function getErrorInfo();

}