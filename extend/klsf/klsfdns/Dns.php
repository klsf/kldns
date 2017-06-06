<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/4/1
 * Time: 19:13
 */

namespace klsf\klsfdns;


interface Dns
{
    /**
     * 添加解析记录
     * @param $_rr
     * @param $_type
     * @param $_value
     * @param $_line
     * @param $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function addDomainRecord($_rr, $_type, $_value, $_line, $_domainId, $_domain = null);

    /**
     * 删除解析记录
     * @param $_recordId
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function deleteDomainRecord($_recordId, $_domainId = null, $_domain = null);

    /**
     * 修改解析记录
     * @param $_recordId
     * @param $_rr
     * @param $_type
     * @param $_value
     * @param $_line
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function updateDomainRecord($_recordId, $_rr, $_type, $_value, $_line, $_domainId = null, $_domain = null);

    /**
     * 获取解析记录信息
     * @param $_recordId
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function getDomainRecordInfo($_recordId, $_domainId = null, $_domain = null);

    /**
     * 获取域名记录列表
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function getDomainRecords($_domainId = null, $_domain = null);

    /**
     * 获取域名列表
     * @return mixed
     */
    public function getDomainList();

    /**
     * 获取域名线路列表
     * @param null $_domainId
     * @param null $_domain
     * @return mixed
     */
    public function getRecordLine($_domainId = null, $_domain = null);

    /**
     * 验证配置是否正确
     * @return bool
     */
    public function checkToken();

    /**
     * 获取错误信息
     * @return array
     */
    public function errorInfo();
}