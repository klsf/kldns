<?php
/**
 * Created by PhpStorm.
 * User: 快乐是福<815856515@qq.com>
 * Date: 2017/6/1
 * Time: 13:03
 */

namespace app\index\controller;


use klsf\klsfdns\KlsfDns;
use think\Cookie;

class Ajax extends Common
{
    private $uid;
    private $userInfo;
    private $result = ['code' => -1];

    public function profile()
    {
        $this->checkLogin();
        $pwd = input('post.pwd');
        if (strlen($pwd) < 5) {
            $this->result['message'] = '密码太简单';
        } elseif (db('users')->where('uid', $this->uid)->update(['pwd' => passwordEncrypt($pwd), 'sid' => createSid()])) {
            $this->result['code'] = 0;
            $this->result['message'] = '修改密码成功';
        } else {
            $this->result['message'] = '修改密码失败';
        }
        return $this->result;
    }

    public function record()
    {
        $this->checkLogin();
        $action = input('action');
        $rr = strtolower(input('post.rr'));
        $type = input('post.type');
        $value = strtolower(input('post.value'));
        if ($action == 'add') {
            $lineIndex = input('post.line/d');
            $domain_id = input('post.domain_id');
            if (!preg_match('/^[a-z0-9\-\_]{2,15}$/', $rr)) {
                $this->result['message'] = '主机记录格式不正确';
            } elseif (strlen($value) < 5) {
                $this->result['message'] = '记录值不正确';
            } elseif (!in_array($type, array('A', 'CNAME'))) {
                $this->result['message'] = '解析类型不存在';
            } elseif ($this->userInfo['coin'] < config('web_record_coin')) {
                $this->result['message'] = '账户金币不足';
            } elseif (db('records')->where('rr', $rr)->where('uid', 'neq', $this->uid)->where('domain_id', $domain_id)->find()) {
                $this->result['message'] = '此记录已被其他用户解析';
            } elseif ($lineIndex && !db('records')->where('rr', $rr)->where('uid', $this->uid)->where('domain_id', $domain_id)->where('line', 0)->find()) {
                $this->result['message'] = '请先添加一条默认线路';
            } elseif (!$domain = db('domains')->where('domain_id', $domain_id)->where('power & ' . $this->userInfo['group'])->find()) {
                $this->result['message'] = '域名不存在';
            } elseif (!$key = db('dns_apis')->where('dns', $domain['dns'])->find()) {
                $this->result['message'] = '此平台接口信息未配置';
            } else {
                $line = getLineInfo($key['lines'], $lineIndex);
                $dns = KlsfDns::getClass($domain['dns'], $key['api_key']);
                if ($ret = $dns->addDomainRecord($rr, $type, $value, $line['Id'], $domain['domain_id'], $domain['domain'])) {
                    if (updateCoin($this->uid, 0 - config('web_record_coin'), "添加记录[{$rr}.{$domain['domain']}]")) {
                        $data = [
                            'uid' => $this->uid,
                            'record_id' => $ret['RecordId'],
                            'domain_id' => $domain_id,
                            'rr' => $rr,
                            'type' => $type,
                            'value' => $value,
                            'line_name' => $line['Name'],
                            'line' => $lineIndex,
                            'add_time' => date("Y-m-d H:i:s")
                        ];
                        if (db('records')->insert($data)) {
                            $this->result['code'] = 0;
                            $this->result['message'] = '添加成功';
                        } else {
                            $this->result['message'] = '保存进数据库失败';
                        }
                    } else {
                        $this->result['message'] = '账户金币不足';
                    }
                } else {
                    $this->result['message'] = $dns->errorInfo()['message'];
                }
            }
        } elseif ($action == 'update') {
            $lineIndex = input('post.line/d');
            $record_id = input('post.record_id');
            if (!preg_match('/^[a-z0-9\-\_]{2,15}$/', $rr)) {
                $this->result['message'] = '主机记录格式不正确';
            } elseif (strlen($value) < 5) {
                $this->result['message'] = '记录值不正确';
            } elseif (!in_array($type, array('A', 'CNAME'))) {
                $this->result['message'] = '解析类型不存在';
            } elseif (!$domain = db("records")->alias('a')->field('b.*')->join('domains b', 'b.domain_id = a.domain_id and b.power&' . $this->userInfo['group'])->where('a.uid', $this->uid)->where('a.record_id', $record_id)->find()) {
                $this->result['message'] = '记录不存在';
            } elseif (db('records')->where('rr', $rr)->where('uid', 'neq', $this->uid)->where('domain_id', $domain['domain_id'])->find()) {
                $this->result['message'] = '此记录已被其他用户解析';
            } elseif ($lineIndex && !db('records')->where('rr', $rr)->where('uid', $this->uid)->where('domain_id', $domain['domain_id'])->where('line', 0)->find()) {
                $this->result['message'] = '请先添加一条默认线路';
            } elseif (!$domain = db('domains')->where('domain_id', $domain['domain_id'])->where('power & ' . $this->userInfo['group'])->find()) {
                $this->result['message'] = '域名不存在';
            } elseif (!$key = db('dns_apis')->where('dns', $domain['dns'])->find()) {
                $this->result['message'] = '此平台接口信息未配置';
            } else {
                $line = getLineInfo($key['lines'], $lineIndex);
                $dns = KlsfDns::getClass($domain['dns'], $key['api_key']);
                if ($ret = $dns->updateDomainRecord($record_id, $rr, $type, $value, $line['Id'], $domain['domain_id'], $domain['domain'])) {
                    $data = [
                        'rr' => $rr,
                        'type' => $type,
                        'value' => $value,
                        'line_name' => $line['Name'],
                        'line' => $lineIndex,
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
            if (!$domain = db("records")->alias('a')->field('b.*')->join('domains b', 'b.domain_id = a.domain_id ')->where('a.uid', $this->uid)->where('a.record_id', $record_id)->find()) {
                $this->result['message'] = '记录不存在';
            } elseif (db('records')->where('record_id', $record_id)->delete()) {
                if ($key = db('dns_apis')->where('dns', $domain['dns'])->find()) {
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
            $rr = input('post.rr');
            $type = input('post.type');
            $value = input('post.value');
            $query = db("records")->alias('a');
            if ($domain_id) {
                $query->where('a.domain_id', $domain_id)->where('a.uid', $this->uid);
            }
            if ($rr) {
                $query->where('a.rr', $rr);
            }
            if ($type) {
                $query->where('a.type', $type);
            }
            if ($value) {
                $query->where('a.value', $value);
            }
            $query2 = clone $query;
            $total = $query->field('a.*,b.domain')->join('domains b', 'b.domain_id = a.domain_id')->count('a.record_id');
            $this->result['page'] = $page;
            $this->result['totalPage'] = ceil(($total + 0.1) / 10);
            $this->result['list'] = $query2->field('a.*,b.domain')->join('domains b', 'b.domain_id = a.domain_id')->order('add_time desc')->page($page, 10)->select();

            $this->result['coin'] = $this->userInfo['coin'];
        } else {
            $this->result['message'] = '操作不存在';
        }

        return $this->result;
    }


    public function domainList()
    {
        $this->checkLogin();
        $list = db("domains")->alias('a')->field('a.domain_id,a.domain,b.lines')->join('dns_apis b ', 'b.dns=a.dns')->where('a.power & ' . $this->userInfo['group'])->order('a.add_time desc')->select();
        if ($list) {
            foreach ($list as $k => $v) {
                $v['lines'] = json_decode($v['lines'], true);
                $list[$k] = $v;
            }
        }
        return $list;
    }

    public function adminLogin()
    {
        $user = input('post.user');
        $pwd = input('post.pwd');
        if ($user === config('web_admin') && passwordEncrypt($pwd) === config('web_password')) {
            $sid = passwordEncrypt($user . ',' . passwordEncrypt($pwd));
            Cookie::set("adminSid", $sid);
            $this->result['code'] = 0;
            $this->result['message'] = '登录成功';
        } else {
            $this->result['message'] = '管理员账号或者密码错误';
        }
        return $this->result;
    }

    public function login()
    {
        if ($userRow = db("users")->field('uid,user')->where('user', input('post.user'))->where('pwd', passwordEncrypt(input('post.pwd')))->find()) {
            $sid = createSid();
            Cookie::set("userSid", $sid);
            db("users")->where('uid', $userRow['uid'])->update(['sid' => $sid]);
            $this->result['code'] = 0;
            $this->result['message'] = '登录成功';
            $this->result['info'] = $userRow;
        } else {
            $this->result['message'] = '账号或者密码错误';
        }
        return $this->result;
    }

    public function reg()
    {
        $user = input("post.user");
        $pwd = input("post.pwd");
        $code = input('post.code');
        @session_start();
        if (!preg_match('/^[a-zA-Z0-9]{5,16}$/', $user)) {
            $this->result['message'] = '用户名只能包含数字和字母，长度5-16';
        } elseif (strlen($pwd) < 6) {
            $this->result['message'] = '密码太简单';
        } elseif (!isset($_SESSION['vc_code']) || $code !== $_SESSION['vc_code']) {
            $this->result['message'] = '验证码不正确';
        } elseif (db('users')->where('user', $user)->find()) {
            $this->result['message'] = '此用户已被注册';
        } elseif (db("users")->insert(['user' => $user, 'pwd' => passwordEncrypt($pwd), 'sid' => createSid(), 'add_time' => date("Y-m-d H:i:s"), 'coin' => intval(config('web_reg_coin'))])) {
            unset($_SESSION['vc_code']);//销毁验证码
            $this->result['code'] = 0;
            $this->result['message'] = '注册成功';
        } else {
            $this->result['message'] = '保存数据库失败';
        }
        return $this->result;
    }

    private function getUserInfo()
    {
        if (!$this->uid) {
            if ($sid = Cookie::get("userSid")) {
                if ($info = db('users')->where('sid', $sid)->find()) {
                    $this->userInfo = $info;
                    $this->uid = $this->userInfo['uid'];
                }
            }
        }
    }

    private function checkLogin()
    {
        $this->getUserInfo();
        if (!$this->userInfo) {
            $this->result['message'] = '请先登录！';
            return $this->result;
        }
    }

}