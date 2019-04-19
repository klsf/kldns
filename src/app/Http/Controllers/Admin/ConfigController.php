<?php
/**
 * Created by PhpStorm.
 * User: me
 * Date: 2019/4/14
 * Time: 16:42
 */

namespace App\Http\Controllers\Admin;


use App\Helper;
use App\Models\Config;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function post(Request $request)
    {
        $action = $request->post('action');
        switch ($action) {
            case 'config':
                return $this->config($request);
            default:
                return ['status' => -1, 'message' => '对不起，此操作不存在！'];
        }
    }


    private function config(Request $request)
    {
        $params = $request->post();
        foreach ($params as $k => $v) {
            if ($k != 'action') {
                if (is_array($v)) {
                    if ($k === 'mail') {
                        //邮件配置
                        $check = $this->mailCheck($v);
                        if ($check !== true) {
                            return ['status' => -1, 'message' => $check];
                        }
                    }

                    $k = "array_{$k}";
                    $v = json_encode($v);
                }
                Config::updateOrCreate(['k' => $k], ['v' => $v]);
            }
        }
        return ['status' => 0, 'message' => '保存成功'];
    }

    private function mailCheck($config = [])
    {
        $need = ['host', 'port', 'username', 'password', 'test'];
        foreach ($need as $k) {
            if (!isset($config[$k]) || !$config[$k]) {
                return '请确保各项输入正确';
            }
        }
        config(['sys.mail' => $config]);
        list($ret, $error) = Helper::sendEmail($config['test'], '测试邮箱配置', 'email.test', []);
        return $ret ? true : $error;
    }

}