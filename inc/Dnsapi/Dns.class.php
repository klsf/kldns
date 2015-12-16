<?php
//dnsapi 接口
namespace Dnsapi;

interface Dns{
	
	/*
	* 验证Token
	*/
	public function checkToken();

    /*
	* 获取域名列表
	*/
	public function getDomainList();

    /*
	* 获取域名信息
	*/
	public function getDomainInfo($domain);

	/**
	* 添加纪录
	*/
	public function addRecord($domain_id,$name,$type,$value,$domain=null);

	/**
	* 获取记录信息
	*/
	public function getRecordInfo($domain_id,$record_id);

	/**
	* 删除记录
	*/
	public function delRecord($domain_id,$record_id);

	/**
	* 修改记录
	*/
	public function updateRecord($domain_id,$record_id,$name,$type,$value);

}