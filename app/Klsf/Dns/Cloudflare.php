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
    private $apiToken;
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
        $params['name'] = $this->buildRecordName($Name, $Domain);
        $params['type'] = $Type;
        $params['content'] = $Value;
        $params['proxied'] = $LineId ? true : false;
        list($ret, $error) = $this->getResult("zones/{$DomainId}/dns_records/{$RecordId}", $params, 'PATCH');
        return $ret ? [true, null] : [false, $error];
    }

    function addDomainRecord($Name, $Type, $Value, $LineId, $DomainId = null, $Domain = null)
    {
        $params = [];
        $params['name'] = $this->buildRecordName($Name, $Domain);
        $params['type'] = $Type;
        $params['content'] = $Value;
        $params['proxied'] = $LineId ? true : false;
        list($ret, $error) = $this->getResult("zones/{$DomainId}/dns_records", $params, 'POST');
        if (!$ret) return [false, $error];
        if (isset($ret['result']['id'])) {
            $record = $ret['result'];
            return [[
                'RecordId' => $record['id'],
                'Name' => $this->extractHost($record['name'], $Domain),
                'Domain' => $Domain
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
                'Name' => $this->extractHost($record['name'], $Domain),
                'Type' => $record['type'],
                'Value' => $record['content'],
                'Domain' => $Domain
            ], null];
        }
        return [false, '获取域名记录详情失败'];
    }

    function getDomainRecords($DomainId = null, $Domain = null)
    {
        $list = [];
        $page = 1;

        do {
            list($ret, $error) = $this->getResult("zones/{$DomainId}/dns_records?page={$page}&per_page=100");
            if (!$ret) return [false, $error];

            if (!isset($ret['result'])) {
                return [false, '获取域名记录列表失败'];
            }

            foreach ($ret['result'] as $record) {
                $list[] = [
                    'RecordId' => $record['id'],
                    'Name' => $this->extractHost($record['name'], $Domain),
                    'Type' => $record['type'],
                    'Value' => $record['content'],
                    'Domain' => $Domain
                ];
            }

            $totalPages = isset($ret['result_info']['total_pages']) ? intval($ret['result_info']['total_pages']) : 1;
            $page++;
        } while ($page <= $totalPages);

        if ($list !== []) {
            return [$list, null];
        }
        return [false, '获取域名记录列表失败'];
    }

    function getDomainList()
    {
        $list = [];
        $page = 1;

        do {
            list($ret, $error) = $this->getResult("zones?page={$page}&per_page=100");
            if (!$ret) return [false, $error];

            if (!isset($ret['result'])) {
                return [false, '获取域名列表失败'];
            }

            foreach ($ret['result'] as $domain) {
                $list[] = [
                    'Domain' => $domain['name'],
                    'DomainId' => $domain['id']
                ];
            }

            $totalPages = isset($ret['result_info']['total_pages']) ? intval($ret['result_info']['total_pages']) : 1;
            $page++;
        } while ($page <= $totalPages);

        if ($list !== []) {
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
        $this->apiToken = isset($config['ApiToken']) ? $config['ApiToken'] : null;
        $this->email = isset($config['Email']) ? $config['Email'] : null;
        $this->apiKey = isset($config['ApiKey']) ? $config['ApiKey'] : null;

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->apiToken) {
            $headers['Authorization'] = 'Bearer ' . $this->apiToken;
        } else {
            $headers['X-Auth-Email'] = $this->email;
            $headers['X-Auth-Key'] = $this->apiKey;
        }

        $this->client = new Client([
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false,
            'headers' => $headers
        ]);
    }

    function configInfo()
    {
        return [
            [
                'name' => 'ApiToken',
                'placeholder' => '请输入API Token',
                'tips' => 'Cloudflare 官方当前推荐使用 API Token（Bearer Token）'
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

    private function buildRecordName($name, $domain)
    {
        $name = trim((string)$name);
        $domain = trim((string)$domain);

        if ($name === '' || $name === '@') {
            return $domain;
        }

        if (substr($name, -strlen('.' . $domain)) === '.' . $domain || $name === $domain) {
            return $name;
        }

        return $name . '.' . $domain;
    }

    private function extractHost($fqdn, $domain)
    {
        $fqdn = trim((string)$fqdn, '.');
        $domain = trim((string)$domain, '.');

        if ($fqdn === $domain || $fqdn === '') {
            return '@';
        }

        $suffix = '.' . $domain;
        if ($domain && substr($fqdn, -strlen($suffix)) === $suffix) {
            return substr($fqdn, 0, -strlen($suffix));
        }

        return $fqdn;
    }
}
