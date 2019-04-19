<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 21:30
 */

namespace App\Klsf\Dns;


use GuzzleHttp\Client;

Trait DnsHttp
{
    public $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8'
            ]
        ]);
    }

    private function request($method = 'POST', $url, $header = [])
    {
        try {
            $res = $this->client->request($method, $url, $header);
        } catch (\Exception $e) {
            return [false, $e->getMessage()];
        }
        return [$res, null];
    }

    protected function get($url, $header = [])
    {
        return $this->request('GET', $url, $header);
    }

    protected function post($url, $header = [])
    {
        return $this->request('POST', $url, $header);
    }
}