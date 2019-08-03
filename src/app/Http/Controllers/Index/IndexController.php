<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:41
 */

namespace App\Http\Controllers\Index;


use App\Helper;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\DomainRecord;
use App\Models\User;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class IndexController extends Controller
{
    public function autoCheck(Request $request, $key)
    {
        if (strlen($key) != 32 || config('sys.cronKey') !== $key) {
            exit('监控密匙不正确！');
        } else {
            $start = time();
            $keywords = config('sys.keywords');
            $keywords = explode("
", $keywords);
            $_keywords = [];
            foreach ($keywords as $k) {
                $k = trim($k);
                if (strlen($k) > 1) {
                    $_keywords[] = $k;
                }
            }
            if (empty($_keywords)) {
                exit('未配置检测关键词！');
            }

            $client = new Client([
                'timeout' => 10,
                'http_errors' => false,
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36'
                ]
            ]);
            echo "开始检测----" . date("Y-m-d H:i:s") . "<br>\r\n";
            while ($record = DomainRecord::with('domain')->where('checked_at', '<', $start - 300)->orderBy('checked_at', 'asc')->first()) {
                $record->checked_at = time();
                $record->save();
                $del = false;

                if (!$record->domain) {
                    echo "{$record->id}----域名不存在<br>\r\n";
                } else {
                    $domain = $record->name . '.' . $record->domain->domain;
                    try {
                        $res = $client->get('http://' . $domain);
                        $body = (string)$res->getBody();
                        foreach ($_keywords as $k) {
                            if (strpos($body, $k) > -1) {
                                //包含关键词，直接删除
                                Helper::deleteRecord($record);
                                echo "{$record->id}----{$domain}----del:{$k}<br>\r\n";
                                $record->delete();
                                $del = true;
                                break;
                            }
                        }
                        if (!$del) {
                            echo "{$record->id}----{$domain}----ok<br>\r\n";
                        }
                    } catch (\Exception $e) {
                        echo "{$record->id}----{$domain}----{$e->getMessage()}<br>\r\n";
                    }
                }

                if (time() - $start > 25) {
                    break;
                }
            }
        }

    }

    public function password(Request $request)
    {
        if ($request->method() === 'POST') {
            $action = $request->post('action');
            switch ($action) {
                case 'sendPasswordEmail':
                    return $this->sendPasswordEmail($request);
                case 'setPassword':
                    return $this->setPassword($request);
                default:
                    return ['status' => -1, 'message' => '对不起，此操作不存在！'];
            }
        } else {
            $code = $request->get('code');
            if ($sid = Crypt::decrypt($code)) {
                if ($user = User::where('sid', $sid)->first()) {
                    return view('password')->with('user', $user);
                }
            }
            abort(500, '链接已失效@/login');
        }
    }

    private function setPassword(Request $request)
    {
        $result = ['status' => 1];
        $code = $request->get('code');
        $password = $request->post('password');
        $re_password = $request->post('re_password');
        if (!$sid = Crypt::decrypt($code)) {
            $result['message'] = '链接已失效';
        } elseif (!$user = User::where('sid', $sid)->first()) {
            $result['message'] = '链接已失效';
        } elseif (strlen($password) < 5) {
            $result['message'] = '新密码太简单';
        } elseif ($re_password !== $password) {
            $result['message'] = '两次密码不一致';
        } else {
            $user->sid = md5(uniqid() . Str::random());
            $user->password = Hash::make($password);
            if ($user->save()) {
                $result = ['status' => 0, 'message' => "重设密码成功！"];
            } else {
                $result['message'] = '重设密码失败，请稍后再试！';
            }
        }
        return $result;
    }

    private function sendPasswordEmail(Request $request)
    {
        $result = ['status' => 1];
        $username = $request->post('username');
        if (strlen($username) < 3) {
            $result['message'] = '请输入要找回的账号或邮箱地址';
        } elseif (strtolower($request->post('code')) !== Session::get('captcha_code')) {
            $result['message'] = '验证码不正确';
        } elseif (!$user = User::where('gid', '>', 99)->where(function ($query) use ($username) {
            $query->where('username', $username)->orWhere('email', $username);
        })->first()) {
            $result['message'] = '账号或者邮箱地址不存在';
        } else {
            $url = "http://{$_SERVER['HTTP_HOST']}/password?code=" . Crypt::encrypt($user->sid);
            list($ret, $error) = Helper::sendEmail($user->email, '重置用户密码', 'email.password', [
                'username' => $user->username,
                'webName' => config('sys.web.name', 'app.name'),
                'url' => $url
            ]);
            if (!$ret) {
                $result['message'] = '发送邮件失败：' . $error;
            } else {
                $result = ['status' => 0, 'message' => "已将重置密码链接已发送至你的邮箱：{$user->email}，请注意查收！"];
            }
        }
        return $result;
    }

    public function verify(Request $request)
    {
        $code = $request->get('code');
        if ($sid = Crypt::decrypt($code)) {
            if ($user = User::where('sid', $sid)->where('status', 1)->first()) {
                $user->status = 2;
                $user->sid = md5(uniqid() . Str::random());
                $user->save();
                abort(200, '激活认证成功@/home');
            }
        }
        abort(500, '链接已失效@/');

    }

    public function reg(Request $request)
    {
        $result = ['status' => 1];
        $verify = config('sys.user.email', 0);
        $password = $request->post('password');
        $data = [
            'username' => $request->post('username'),
            'password' => Hash::make($password),
            'email' => strtolower($request->post('email')),
            'point' => abs(intval(config('sys.user.point', 0))),
            'sid' => md5(uniqid() . Str::random()),
            'status' => $verify ? 1 : 2
        ];
        if (!config('sys.user.reg', 0)) {
            $result['message'] = '对不起，暂时关闭注册';
        } elseif (strtolower($request->post('code')) !== Session::get('captcha_code')) {
            $result['message'] = '验证码不正确';
        } elseif (!Helper::checkEmail($data['email'])) {
            $result['message'] = '邮箱格式不正确';
        } elseif (strlen($data['username']) < 4) {
            $result['message'] = '用户名太短';
        } elseif (strlen($password) < 5) {
            $result['message'] = '密码太简单';
        } elseif (User::where('username', $data['username'])->first()) {
            $result['message'] = '此用户名已被注册';
        } elseif (User::where('email', $data['email'])->first()) {
            $result['message'] = '此邮箱已经注册过';
        } elseif ($user = User::create($data)) {
            if ($data['status'] === 2) {
                $result = ['status' => 0, 'message' => "恭喜您，注册成功，马上登录！"];
            } else {
                Helper::sendVerifyEmail($user);
                $result = ['status' => 0, 'message' => "恭喜您，注册成功。已发送一封激活邮件到你的邮箱：{$data['email']}，请注意查收！"];
            }
        } else {
            $result['message'] = '注册失败，请稍后再试！';
        }
        return $result;
    }

    public function check(Request $request)
    {
        $result = ['status' => 1];
        list($name, $error) = Helper::checkDomainName($request->post('name'));
        $did = $request->post('did');
        if (!$name) {
            $result['message'] = $error;
        } elseif (!$domain = Domain::available()->where('did', $did)->first()) {
            $result['message'] = '域名不存在或无权使用';
        } else {
            if (DomainRecord::where('did', $did)->where('name', $name)->first()) {
                $result['message'] = '此域名已被使用';
            } else {
                $result = ['status' => 0, 'message' => "{$name}.{$domain->domain} 可用！"];
            }
        }
        return $result;
    }

    public function captcha(Request $request)
    {
        $phrase = new PhraseBuilder();
        // 设置验证码位数
        $code = $phrase->build(5);
        // 生成验证码图片的Builder对象，配置相应属性
        $builder = new CaptchaBuilder($code, $phrase);
        // 设置背景颜色
        $builder->setBackgroundColor(220, 210, 230);
        $builder->setMaxAngle(30);
        $builder->setMaxBehindLines(5);
        $builder->setMaxFrontLines(5);
        // 可以设置图片宽高及字体
        $builder->build($width = 120, $height = 40, $font = null);
        // 获取验证码的内容
        $phrase = $builder->getPhrase();
        // 把内容存入session
        Session::flash('captcha_code', $phrase);
        // 生成图片
        $builder->output();
        $content = ob_get_clean();
        return response($content, 200, ['Content-Type' => 'image/jpeg',]);
    }

}