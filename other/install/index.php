<?php
$databaseFile = __DIR__ . '/../../application/index/database.php';//数据库配额文件
@header('Content-Type: text/html; charset=UTF-8');
$step = isset($_GET['step']) ? $_GET['step'] : 1;
$action = isset($_POST['action']) ? $_POST['action'] : null;
if (file_exists($databaseFile)) {
    exit('你已经成功安装，如需重新安装，请手动删除 根目录/application/index/database.php 配置文件！');
}
if ($action == 'install') {
    $host = isset($_POST['host']) ? $_POST['host'] : null;
    $port = isset($_POST['port']) ? $_POST['port'] : null;
    $user = isset($_POST['user']) ? $_POST['user'] : null;
    $pwd = isset($_POST['pwd']) ? $_POST['pwd'] : null;
    $database = isset($_POST['database']) ? $_POST['database'] : null;
    $prefix = isset($_POST['prefix']) ? $_POST['prefix'] : null;
    if (empty($host) || empty($port) || empty($user) || empty($pwd) || empty($database)) {
        $errorMsg = '请填完所有数据库信息';
    } else {
        $mysql['hostname'] = $host;
        $mysql['hostport'] = $port;
        $mysql['database'] = $database;
        $mysql['username'] = $user;
        $mysql['password'] = $pwd;
        $mysql['prefix'] = $prefix;
        try {
            $db = new PDO("mysql:host=" . $mysql['hostname'] . ";dbname=" . $mysql['database'] . ";port=" . $mysql['hostport'], $mysql['username'], $mysql['password']);
        } catch (Exception $e) {
            $errorMsg = '链接数据库失败:' . $e->getMessage();
        }
        if (empty($errorMsg)) {
            @file_put_contents($databaseFile, '<?php' . PHP_EOL . 'return ' . var_export($mysql, true) . ';' . PHP_EOL . PHP_EOL . '?>');
            $db->exec("set names utf8");
            $sqls = file_get_contents('install.sql');
            $sqls = str_replace('`pre_', '`' . $prefix, $sqls);
            $sqls = explode(';', $sqls);

            $success = 0;
            $error = 0;
            $errorMsg = null;
            foreach ($sqls as $value) {
                $value = trim($value);
                if (!empty($value)) {
                    if ($db->exec($value) === false) {
                        $error++;
                        $dberror = $db->errorInfo();
                        $errorMsg .= $value . "<br>" . $dberror[2] . "<br>";
                    } else {
                        $success++;
                    }
                }
            }
            $step = 3;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>快乐二级域名分发-安装程序</title>
    <meta name="keywords" content="快乐二级域名分发"/>
    <meta name="description" content="快乐二级域名分发"/>
    <link href="/assets/plugin/bootstrap-4.0.0-alpha.6/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/plugin/font-awesome-4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <link href="/assets/plugin/animate-3.5.2/animate.min.css" rel="stylesheet">
    <script src="/assets/plugin/jquery-3.2.1/jquery.min.js"></script>
</head>
<body>
<div class="container" id="app">

    <div class="row">
        <div class="col-xl-12 mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">快速安装</li>
            </ol>
        </div>
    </div>
    <div class="row">
        <div class="col-xl-12">
            <div class="card bg-faded">
                <?php
                if (isset($errorMsg)) {
                    echo '<div class="card-header bg-warning text-center mb-2" role="alert">' . $errorMsg . '</div>';
                }
                if ($step == 2) {
                    ?>
                    <div class="card-header">MYSQL数据库信息配置</div>
                    <div class="card-block">
                        <form action="#" method="post">
                            <input type="hidden" name="action" class="form-control" value="install">
                            <div class="form-group row">
                                <label for="example-text-input" class="col-sm-2 col-form-label ">数据库地址</label>
                                <div class="col-sm-10">
                                    <input type="text" name="host" class="form-control" value="127.0.0.1">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-sm-2 col-form-label ">数据库端口</label>
                                <div class="col-sm-10">
                                    <input type="text" name="port" class="form-control" value="3306">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-sm-2 col-form-label ">数据库库名</label>
                                <div class="col-sm-10">
                                    <input type="text" name="database" class="form-control">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-sm-2 col-form-label ">数据库用户名</label>
                                <div class="col-sm-10">
                                    <input type="text" name="user" class="form-control">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-sm-2 col-form-label ">数据库密码</label>
                                <div class="col-sm-10">
                                    <input type="password" name="pwd" class="form-control">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="example-text-input" class="col-sm-2 col-form-label ">数据表前缀</label>
                                <div class="col-sm-10">
                                    <input type="text" name="prefix" class="form-control" value="kldns_">
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="offset-md-2 col-md-10">
                                    <button type="submit" class="btn btn-success btn-block">确认无误，下一步</button>
                                </div>
                            </div>
                        </form>

                    </div>
                    <?php
                } elseif ($step == 3) { ?>
                    <div class="card-header">数据导入完毕</div>
                    <div class="card-block">
                        <ul class="list-group">
                            <li class="list-group-item">成功执行SQL语句<?php echo $success; ?>条，失败<?php echo $error; ?>条！</li>
                            <li class="list-group-item">1、系统已成功安装完毕！</li>
                            <li class="list-group-item">2、系统管理员账号为 用户名：admin 密码:123456</li>
                            <li class="list-group-item">3、后台地址：域名/admin</li>
                            <a href="/" class="list-group-item list-group-item-info text-center"
                               style="display: block;">进入网站首页</a>
                        </ul>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="card-header">安装环境检测</div>
                    <div class="card-block">
                        <?php
                        $install = true;
                        if (!file_exists('../app/index/database.php')) {
                            $check[2] = '<span class="badge badge-info">未锁定</span>';
                        } else {
                            $check[2] = '<span class="badge badge-warning">已锁定</span>';
                            $install = false;
                        }
                        if (class_exists("PDO")) {
                            $check[0] = '<span class="badge badge-info">支持</span>';
                        } else {
                            $check[0] = '<span class="badge badge-warning">不支持</span>';
                            $install = false;
                        }
                        if ($fp = @fopen(dirname($databaseFile) . "/test.txt", 'w')) {
                            @fclose($fp);
                            @unlink(dirname($databaseFile) . "/test.txt");
                            $check[1] = '<span class="badge badge-info">支持</span>';
                        } else {
                            $check[1] = '<span class="badge badge-warning">不支持</span>';
                            $install = false;
                        }
                        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
                            $check[3] = '<span class="badge badge-warning">不支持</span>';
                        } else {
                            $check[3] = '<span class="badge badge-info">支持</span>';
                        }

                        ?>
                        <div class="list-group-item" style="display: block">
                            检测安装是否锁定
                            <span class="pull-right"><?php echo $check[2]; ?></span>
                        </div>
                        <div class="list-group-item" style="display: block">
                            PDO_MYSQL组件
                            <span class="pull-right"><?php echo $check[0]; ?></span>
                        </div>
                        <div class="list-group-item" style="display: block">
                            INC目录写入权限
                            <span class="pull-right"><?php echo $check[1]; ?></span>
                        </div>
                        <div class="list-group-item" style="display: block">
                            PHP版本>=5.4
                            <span class="pull-right"><?php echo $check[3]; ?></span>
                        </div>
                        <li class="list-group-item">成功安装后安装文件就会锁定，如需重新安装，请手动删除app/index目录下database.php配置文件！</li>
                        <?php
                        if ($install) echo '<a href="?step=2" class="list-group-item list-group-item-info text-center" style="display: block">检测通过，下一步</a>';
                        ?>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
    <footer class="footer bg-faded mt-3 p-2">
        <p class="text-center">Powered by <a href="https://github.com/klsf" target="_blank">klsf</a> v2.0</p></footer>
</div>
</body>
</html>