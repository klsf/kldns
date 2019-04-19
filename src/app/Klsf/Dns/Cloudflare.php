<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/16
 * Time: 11:24
 */

namespace App\Klsf\Dns;


use GuzzleHttp\Client;

class Cloudflare implements DnsInterface
{
    use DnsHttp;
    private $url = "https://api.cloudflare.com/client/v4/";
    private $apiKey;
    private $email;

    function deleteDomainRecord($RecordId, $DomainId, $Domain)
    {
        list($ret, $error) = $this->getResult("zones/{$DomainId}/dns_records/{$RecordId}", [], 'DELETE');
        return $ret ? [true, null] : [false, $error];
    }

    function updateDomainRecord($RecordId, $Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['name'] = $Name;
        $params['type'] = $Type;
        $params['content'] = $Value;
        $params['proxied'] = $LineId ? true : false;
        list($ret, $error) = $this->getResult("zones/{$DomainId}/dns_records/{$RecordId}", $params, 'PUT');
        return $ret ? [true, null] : [false, $error];
    }

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['name'] = $Name;
        $params['type'] = $Type;
        $params['content'] = $Value;
        $params['proxied'] = $LineId ? true : false;
        list($ret, $error) = $this->getResult("zones/{$DomainId}/dns_records", $params, 'POST');
        if (!$ret) return [false, $error];
        if (isset($ret['result']['id'])) {
            $record = $ret['result'];
            return [[
                'RecordId' => $record['id'],
                'Name' => $record['name'],
                'Domain' => $record['zone_name']
            ], null];
        }
        return [false, '添加域名记录失败'];
    }

    function getDomainRecordInfo($RecordId, $DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->getResult("zones/{$DomainId}/dns_records/{$RecordId}");
        if (!$ret) return [false, $error];
        if (isset($ret['result']['id'])) {
            $record = $ret['result'];
            return [[
                'RecordId' => $record['id'],
                'Name' => $record['name'],
                'Type' => $record['type'],
                'Value' => $record['content'],
                'Domain' => $record['zone_name']
            ], null];
        }
        return [false, '获取域名记录详情失败'];
    }

    function getDomainRecords($DomainId = null, $Domain = null)
    {
        list($ret, $error) = $this->getResult("zones/{$DomainId}/dns_records");
        if (!$ret) return [false, $error];

        if (isset($ret['result'])) {
            $list = [];
            foreach ($ret['result'] as $record) {
                $list[] = [
                    'RecordId' => $record['id'],
                    'Name' => $record['name'],
                    'Type' => $record['type'],
                    'Value' => $record['content'],
                    'Domain' => $record['zone_name']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名记录列表失败'];
    }

    function getDomainList()
    {
        list($ret, $error) = $this->getResult("zones?page=1&per_page=50");
        if (!$ret) return [false, $error];

        if (isset($ret['result'])) {
            $list = [];
            foreach ($ret['result'] as $domain) {
                $list[] = [
                    'Domain' => $domain['name'],
                    'DomainId' => $domain['id']
                ];
            }
            return [$list, null];
        }
        return [false, '获取域名列表失败'];
    }

    function getRecordLine($_domainId = null, $_domain = null)
    {
        $list = [];
        $list[] = array(
            'Name' => '默认',
            'Id' => 0,
        );
        $list[] = array(
            'Name' => 'CDN',
            'Id' => 1,
        );
        return $list;
    }

    function check()
    {
        list($ret, $error) = $this->getDomainList();
        return $ret ? [true, null] : [false, $error];
    }

    function config(array $config)
    {
        $this->email = isset($config['Email']) ? $config['Email'] : null;
        $this->apiKey = isset($config['ApiKey']) ? $config['ApiKey'] : null;

        $this->client = new Client([
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Auth-Email' => $this->email,
                'X-Auth-Key' => $this->apiKey
            ]
        ]);
    }

    function configInfo()
    {
        return [
            [
                'name' => 'ApiKey',
                'placeholder' => '请输入API Key',
                'tips' => 'API key generated on the "My Account" page'
            ],
            [
                'name' => 'Email',
                'placeholder' => '请输入Email',
                'tips' => 'Email address associated with your account'
            ]
        ];
    }

    private function getResult($action, $params = [], $method = 'GET')
    {
        list($res, $error) = $this->request($method, $this->url . $action, [
            'body' => json_encode($params)
        ]);

        if (!$res) return [false, $error];

        $body = (string)$res->getBody();
        if ($ret = json_decode($body, true)) {
            if (isset($ret['success'])) {
                if ($ret['success']) {
                    return [$ret, null];
                } elseif (isset($ret['errors']) && count($ret['errors']) > 0) {
                    return [false, $ret['errors'][0]['message']];
                }
            }
        }
        return [false, '解析结果失败'];
    }
}