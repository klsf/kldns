<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/6/3
 * Time: 12:55
 */

namespace app\index\controller;


use klsf\klsfdns\KlsfDns;
use think\Cookie;

class AdminAjax extends Common
{
    private $result = ['code' => -1];

    public function admin()
    {
        $user = trim(input('post.user'));
        $pwd = trim(input('post.pwd'));
        if (strlen($user) < 3) {
            $this->result['meesage'] = '管理员用户名太简单';
        } elseif (strlen($pwd) < 5) {
            $this->result['message'] = '管理员密码太简单';
        } else {
            db('configs')->where('vkey', 'admin')->limit(1)->update(['value' => $user]);
            db('configs')->where('vkey', 'password')->limit(1)->update(['value' => passwordEncrypt($pwd)]);
            $this->result['code'] = 0;
            $this->result['message'] = '修改管理员账号成功';
        }
        return $this->result;
    }

    public function api()
    {
        $keyRow = $_POST['key'];
        $key = "";
        foreach ($keyRow as $v) {
            $key .= trim($v) . ',';
        }
        $key = trim($key, ',');
        $dns = input('post.dns');

        $dnsApi = KlsfDns::getClass($dns, $key);
        if (!$dnsApi->checkToken()) {
            $this->result['message'] = '验证接口密匙失败，请重新确认！';
        } elseif (!$lines = $dnsApi->getRecordLine()) {
            $this->result['message'] = '获取解析平台线路失败！';
        } else {
            $data = [
                'dns' => $dns,
                'api_key' => $key,
                'lines' => json_encode($lines),
            ];
            if (db('dns_apis')->where('dns', $dns)->find()) {
                db('dns_apis')->where('dns', $dns)->limit(1)->update($data);
            } else {
                db('dns_apis')->insert($data);
            }
            $this->result['code'] = 0;
            $this->result['message'] = '保存配置成功';
        }
        return $this->result;
    }

    public function config()
    {
        foreach ($_POST as $k => $v) {
            $k = trim($k);
            $v = trim($v);
            $data = [
                'vkey' => $k,
                'value' => $v
            ];
            if (db('configs')->where('vkey', $k)->find()) {
                db('configs')->where('vkey', $k)->limit(1)->update($data);
            } else {
                db('configs')->insert($data);
            }
        }
        $this->result['code'] = 0;
        $this->result['message'] = '保存配置成功';
        return $this->result;
    }

    public function domain()
    {
        $action = input('action');
        if ($action == 'list') {
            $page = input('post.page/d');
            $pageSize = input('post.pageSize/d');
            $pageSize = ($pageSize > 5) ? $pageSize : 10;
            $page = ($page < 1) ? 1 : $page;
            $this->result['code'] = 0;
            $this->result['message'] = '获取成功';

            $dns = input('dns');
            $domain = input('post.domain');
            $query = db("domains")->alias('a');
            if ($dns) {
                $query->where('a.dns', $dns);
            }
            if ($domain) {
                $query->where('a.domain', $domain);
            }
            $query2 = clone $query;

            $total = $query->count('a.domain_id');
            $this->result['page'] = $page;
            $this->result['totalPage'] = ceil(($total + 0.1) / $pageSize);
            $this->result['list'] = $query2->field('a.*,count(b.record_id) as total')->join('records b', 'b.domain_id = a.domain_id', 'left')->group('a.domain_id')->order('add_time desc')->page($page, $pageSize)->select();
        } elseif ($action == 'apiList') {
            $dns = input("post.dns");
            if (!$key = db('dns_apis')->where('dns', $dns)->find()) {
                $this->result['message'] = '此平台接口信息未配置';
            } else {
                $dnsApi = KlsfDns::getClass($dns, $key['api_key']);
                if ($list = $dnsApi->getDomainList()) {
                    $this->result['code'] = 0;
                    $this->result['message'] = '获取成功';
                    $this->result['list'] = $list;
                } else {
                    $this->result['message'] = $dns->errorInfo()['message'];
                }
            }
        } elseif ($action == 'add') {
            $domain = input('post.domain');
            $domain_id = input('post.domain_id');
            $dns = input('post.dns');
            $powerRow = $_POST['power'];
            $power = 0;
            foreach ($powerRow as $v) {
                $power = $power | intval($v);
            }
            $data = [
                'domain' => $domain,
                'domain_id' => $domain_id,
                'dns' => $dns,
                'power' => $power,
                'add_time' => date("Y-m-d H:i:s")
            ];
            if (db('domains')->where('domain_id', $domain_id)->find()) {
                $this->result['message'] = '此域名已经添加过';
            } elseif (db('domains')->insert($data)) {
                $this->result['code'] = 0;
                $this->result['message'] = '添加成功';
            } else {
                $this->result['message'] = '添加失败';
            }
        } elseif ($action == 'del') {
            $domain_id = input('post.domain_id');
            if (db('domains')->where('domain_id', $domain_id)->delete()) {
                $this->result['code'] = 0;
                $this->result['message'] = '删除成功';
            } else {
                $this->result['message'] = '删除是失败';
            }
        } elseif ($action == 'update') {
            $domain_id = input('post.domain_id');
            $powerRow = $_POST['power'];
            $power = 0;
            foreach ($powerRow as $v) {
                $power = $power | intval($v);
            }
            if (db('domains')->where('domain_id', $domain_id)->update(['power' => $power])) {
                $this->result['code'] = 0;
                $this->result['message'] = '修改成功';
            } else {
                $this->result['message'] = '修改失败';
            }
        } else {
            $this->result['message'] = '操作不存在';
        }

        return $this->result;
    }

    public function record()
    {
        $action = input('action');
        $rr = strtolower(input('post.rr'));
        $type = input('post.type');
        $value = strtolower(input('post.value'));
        if ($action == 'update') {
            $record_id = input('post.record_id');
            if (!preg_match('/^[a-z0-9\-\_]{2,15}$/', $rr)) {
                $this->result['message'] = '主机记录格式不正确';
            } elseif (strlen($value) < 5) {
                $this->result['message'] = '记录值不正确';
            } elseif (!in_array($type, array('A', 'CNAME'))) {
                $this->result['message'] = '解析类型不存在';
            } elseif (!$domain = db("records")->alias('a')->field('b.*')->join('domains b', 'b.domain_id = a.domain_id')->where('a.record_id', $record_id)->find()) {
                $this->result['message'] = '记录不存在';
            } elseif (!$domain['dns']) {
                $this->result['message'] = '域名不存在';
            } elseif (!$key = db('dns_apis')->where('dns', $domain['dns'])->find()) {
                $this->result['message'] = '此平台接口信息未配置';
            } else {
                $dns = KlsfDns::getClass($domain['dns'], $key['api_key']);
                if ($ret = $dns->updateDomainRecord($record_id, $rr, $type, $value, $domain['domain_id'], $domain['domain'])) {
                    $data = [
                        'rr' => $rr,
                        'type' => $type,
                        'value' => $value
                    ];
                    if (db('records')->where('record_id', $record_id)->update($data)) {
                        $this->result['code'] = 0;
                        $this->result['message'] = '修改成功';
                    } else {
                        $this->result['message'] = '保存进数据库失败';
                    }
                } else {
                    $this->result['message'] = $dns->errorInfo()['message'];
                }
            }
        } elseif ($action == 'del') {
            $record_id = input('post.record_id');
            if (!$domain = db("records")->alias('a')->field('b.*')->join('domains b', 'b.domain_id = a.domain_id ')->where('a.record_id', $record_id)->find()) {
                $this->result['message'] = '记录不存在';
            } elseif (db('records')->where('record_id', $record_id)->delete()) {
                if ($domain['dns'] && $key = db('dns_apis')->where('dns', $domain['dns'])->find()) {
                    $dns = KlsfDns::getClass($domain['dns'], $key['api_key']);
                    $dns->deleteDomainRecord($record_id, $domain['domain_id'], $domain['domain']);
                }
                $this->result['code'] = 0;
                $this->result['message'] = '删除成功';
            } else {
                $this->result['message'] = '删除失败';
            }
        } elseif ($action == 'list') {
            $page = input('post.page/d');
            $page = ($page < 1) ? 1 : $page;
            $this->result['code'] = 0;
            $this->result['message'] = '获取成功';

            $domain_id = input('domain_id');
            $uid = input('post.uid/d');
            $query = db("records")->alias('a')->join('domains b', 'b.domain_id = a.domain_id');
            if ($domain_id) {
                $query->where('a.domain_id', $domain_id);
            }
            if ($uid > 0) {
                $query->where('a.uid', $uid);
            }
            $query2 = clone $query;
            $total = $query->field('a.*,b.domain')->count('a.record_id');
            $this->result['page'] = $page;
            $this->result['totalPage'] = ceil(($total + 0.1) / 10);
            $this->result['list'] = $query2->field('a.*,b.domain,c.user')->join('users c', 'c.uid = a.uid', 'left')->order('add_time desc')->page($page, 10)->select();
        } else {
            $this->result['message'] = '操作不存在';
        }

        return $this->result;
    }

    public function user()
    {
        $action = input('action');

        if ($action == 'update') {
            $uid = input('post.uid/d');
            $group = input('post.group/d');
            $coin = input('post.coin/d');
            $pwd = input('post.pwd');
            if ($pwd && strlen($pwd) < 5) {
                $this->result['message'] = '新密码太简单';
            } else {
                $data = [
                    'group' => $group,
                    'coin' => $coin,
                ];
                if ($pwd) {
                    $data['pwd'] = passwordEncrypt($pwd);
                    $data['sid'] = createSid();
                }
                if (db('users')->where('uid', $uid)->update($data)) {
                    $this->result['code'] = 0;
                    $this->result['message'] = '修改成功';
                } else {
                    $this->result['message'] = '修改失败';
                }
            }
        } elseif ($action == 'del') {
            $uid = input('post.uid/d');
            if (db('users')->where('uid', $uid)->delete()) {
                $this->result['code'] = 0;
                $this->result['message'] = '删除成功';
            } else {
                $this->result['message'] = '删除失败';
            }
        } elseif ($action == 'list') {
            $page = input('post.page/d');
            $page = ($page < 1) ? 1 : $page;
            $this->result['code'] = 0;
            $this->result['message'] = '获取成功';

            $group = input('post.group/d');
            $uid = input('post.uid/d');
            $user = input('post.user');
            $query = db('users');
            if ($group) {
                $query->where('`group` & ' . $group);
            }
            if ($user) {
                $query->where('`user`', $user);
            }
            if ($uid > 0) {
                $query->where('`uid`', $uid);
            }
            $query2 = clone $query;

            $total = $query->count('uid');
            $this->result['page'] = $page;
            $this->result['totalPage'] = ceil(($total + 0.1) / 10);
            $this->result['list'] = $query2->order('uid desc')->page($page, 10)->select();
        } else {
            $this->result['message'] = '操作不存在';
        }

        return $this->result;
    }

    private function checkLogin()
    {
        if ($sid = Cookie::get("adminSid")) {
            $check = passwordEncrypt(config('web_admin') . ',' . config('web_password'));
            if ($check === $sid) {
                return true;
            }
        }
        $this->result['message'] = '请先登录';
        return $this->result;
    }

    function _initialize()
    {
        parent::_initialize();
        $this->checkLogin();
    }
}