<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 22:03
 */

namespace App\Klsf\Dns;


interface DnsInterface
{
    function deleteDomainRecord($RecordId, $DomainId, $Domain);

    function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null);

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null);

    function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null);

    function getDomainRecords($DomainId = null, $Domain = null);

    function getDomainList();

    function getRecordLine($_domainId = null, $_domain = null);

    function check();

    function config(array $config);

    function configInfo();
}