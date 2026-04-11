<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use PDO;

class InstallController extends Controller
{
    private string $version = '4.0.2';

    private function installConfigPath(string $file): string
    {
        $directory = storage_path('install');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.'/'.$file;
    }

    public function update()
    {
        $version = config('version', '3.0.1');
        $versionCode = intval(str_replace('.', '', $version));
        $nowVersionCode = intval(str_replace('.', '', $this->version));
        if ($versionCode < $nowVersionCode) {
            $list = $this->getUpdateList();
            if (!empty($list)) {
                try {
                    $db = new PDO(
                        'mysql:host='.config('mysql.host').';dbname='.config('mysql.database').';port='.config('mysql.port'),
                        config('mysql.username'),
                        config('mysql.password')
                    );
                } catch (Exception $e) {
                    abort(500, '连接数据库失败:' . $e->getMessage());
                }
                $db->exec("set names utf8");
                foreach ($list as $code) {
                    $code = $code . '';
                    $code = $code[0] . '.' . $code[1] . '.' . $code[2];
                    $sqls = @file_get_contents(base_path('install/update.' . $code . '.sql'));
                    $sqls = str_replace('`kldns_', '`' . config('mysql.prefix'), $sqls);
                    $sqls = explode(';', $sqls);
                    $errorList = [];
                    foreach ($sqls as $sql) {
                        $sql = trim($sql);
                        if (!empty($sql)) {
                            if ($db->exec($sql) === false) {
                                $errorList[] = $db->errorInfo();
                            }
                        }
                    }
                    if (!empty($errorList)) {
                        abort(500, '更新数据表错误：' . implode(' ', $errorList[0]));
                    } else {
                        if (!file_put_contents($this->installConfigPath('version.php'), '<?php' . PHP_EOL . 'return "' . $code . '";')) {
                            abort(500, '写入安装配置失败，请检测 /storage/install 是否有写入权限');
                        }
                    }
                }
                abort(200, '数据表更新成功！');
            }
        }
    }

    private function getUpdateList()
    {
        $list = [];
        $dir = base_path('install');
        $files = scandir($dir);
        foreach ($files as $file) {
            $file = explode('.', $file);
            if (count($file) === 5 && $file[4] === 'sql' && $file[0] === 'update') {
                $list[] = intval($file[1] . $file[2] . $file[3]);
            }
        }
        sort($list);
        return $list;
    }

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
                $db = new PDO(
                    'mysql:host='.$mysql['host'].';dbname='.$mysql['database'].';port='.$mysql['port'],
                    $mysql['username'],
                    $mysql['password']
                );
            } catch (Exception $e) {
                $result['message'] = '连接数据库失败:' . $e->getMessage();
                return $result;
            }
            if (!file_put_contents($this->installConfigPath('mysql.php'), '<?php' . PHP_EOL . 'return ' . var_export($mysql, true) . ';' . PHP_EOL)) {
                $result['message'] = '写入安装配置失败，请检测 /storage/install 是否有写入权限';
            } else {
                file_put_contents($this->installConfigPath('version.php'), '<?php' . PHP_EOL . 'return "' . $this->version . '";');

                $db->exec("set names utf8");
                $sqls = file_get_contents(base_path('install/install.sql'));
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
                'name' => 'PHP 版本 >= 8.2',
                'support' => version_compare(PHP_VERSION, '8.2.0', '>=')
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
                'name' => '/storage/install 目录写入权限',
                'support' => $this->checkPath(storage_path('install'))
            ],
            [
                'name' => '/storage 目录写入权限',
                'support' => $this->checkPath(storage_path())
            ]
        ];
        return $list;
    }

    private function checkPath(string $path): bool
    {
        if (is_dir($path) || mkdir($path, 0755, true)) {
            return true;
        }
        return false;
    }
}
