<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use PDO;

class InstallController extends Controller
{


    public function install(Request $request)
    {
        if ($request->method() === 'POST') {
            $action = $request->post('action');
            switch ($action) {
                case 'mysql':
                    return $this->mysql($request);
            }
        } else {
            return view('install')->with('support', $this->checkSupport());
        }
    }

    private function mysql(Request $request)
    {
        $result = ['status' => 1];
        $mysql = [
            'host' => $request->post('host'),
            'port' => $request->post('port'),
            'database' => $request->post('database'),
            'username' => $request->post('username'),
            'password' => $request->post('password'),
            'prefix' => $request->post('prefix'),
        ];
        if (!$mysql['host'] || !$mysql['port'] || !$mysql['database'] || !$mysql['username'] || !$mysql['password'] || !$mysql['prefix']) {
            $result['message'] = '请填写正确MYSQL数据库信息';
        } else {
            try {
                $db = new PDO("mysql:host=" . $mysql['host'] . ";dbname=" . $mysql['database'] . ";port=" . $mysql['port'], $mysql['username'], $mysql['password']);
            } catch (Exception $e) {
                $result['message'] = '连接数据库失败:' . $e->getMessage();
                return $result;
            }
            if (!file_put_contents(__DIR__ . '/../../../config/mysql.php', '<?php' . PHP_EOL . 'return ' . var_export($mysql, true) . ';' . PHP_EOL . PHP_EOL . '?>')) {
                $result['message'] = '写入配置文件失败，请检测 /src/config 是否有写入权限';
            } else {
                $db->exec("set names utf8");
                $sqls = file_get_contents(__DIR__ . '/../../../install/install.sql');
                $sqls = str_replace('`kldns_', '`' . $mysql['prefix'], $sqls);
                $sqls = explode(';', $sqls);
                $success = 0;
                $error = 0;
                $errorList = [];
                foreach ($sqls as $sql) {
                    $sql = trim($sql);
                    if (!empty($sql)) {
                        if ($db->exec($sql) === false) {
                            $error++;
                            $errorList[] = $db->errorInfo();
                        } else {
                            $success++;
                        }
                    }
                }
                $result = ['status' => 0, 'message' => '安装完成', 'data' => [
                    'success' => $success,
                    'error' => $error,
                    'msg' => $errorList
                ]];
            }
        }
        return $result;
    }

    private function checkSupport()
    {
        $list = [
            [
                'name' => 'PHP版本>=7.13',
                'support' => version_compare(PHP_VERSION, '7.1.3', '>=')
            ],
            [
                'name' => 'OpenSSL 扩展',
                'support' => function_exists('openssl_verify')
            ],
            [
                'name' => 'PDO 扩展',
                'support' => class_exists("PDO")
            ],
            [
                'name' => 'Mbstring 扩展',
                'support' => function_exists("mb_convert_encoding")
            ],
            [
                'name' => 'Ctype 扩展',
                'support' => function_exists("ctype_alnum")
            ],
            [
                'name' => '/src/config 目录写入权限',
                'support' => $this->checkPath(__DIR__ . '/../../../config')
            ],
            [
                'name' => '/src/storage 目录写入权限',
                'support' => $this->checkPath(__DIR__ . '/../../../storage')
            ]
        ];
        return $list;
    }

    private function checkPath($path)
    {
        if (is_dir($path) || mkdir($path, 0755, true)) {
            return true;
        }
        return false;
    }
}
