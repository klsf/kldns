<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>程序安装 - {{ config('app.name') }}</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.8.1/css/all.min.css" rel="stylesheet">
</head>
<body>
<div id="vue">
    <div class="col-12 col-md-4 offset-md-4 mt-3 mt-sm-5">
        @if(config('mysql'))
            <div class="card mb-3">
                <div class="card-header text-white bg-info ">安装提示</div>
                <div class="card-body text-center text-danger">
                    对不起，你已完成安装！如需重新安装，请删除 根目录/src/config/mysql.php 文件
                </div>
            </div>
        @else
            <div class="card mb-3" v-if="step===1">
                <div class="card-header text-white bg-info ">运行环境检测</div>
                <div class="card-body">
                    @if($check=true)@endif
                    @foreach($support as $row)
                        @if(!$row['support'] && $check=false)@endif
                        <div class="list-group-item {{ $row['support']?'text-success':'text-danger' }}">
                            {{ $row['name'] }}
                            <span class="float-right">
                                <i class="fa {{ $row['support']?'fa-check':'fa-window-close' }}"></i>
                            </span>
                        </div>
                    @endforeach
                    @if($check)
                        <div class="list-group-item">
                            <a class="btn btn-success text-white btn-block" @click="step=2">下一步，数据库配置</a>
                        </div>
                    @endif
                </div>
            </div>
            <div class="card mb-3" v-else-if="step===2">
                <div class="card-header text-white bg-info ">MYSQL数据库配置</div>
                <div class="card-body">
                    <form id="form-mysql">
                        <input type="hidden" name="action" value="mysql">
                        <div class="form-group row">
                            <label class="col-3 col-form-label">数据库地址</label>
                            <div class="col-9">
                                <input type="text" name="host" class="form-control" value="127.0.0.1">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">数据库端口</label>
                            <div class="col-9">
                                <input type="text" name="port" class="form-control" value="3306">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">数据库库名</label>
                            <div class="col-9">
                                <input type="text" name="database" class="form-control">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">数据库用户名</label>
                            <div class="col-9">
                                <input type="text" name="username" class="form-control">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">数据库密码</label>
                            <div class="col-9">
                                <input type="text" name="password" class="form-control">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label">数据表前缀</label>
                            <div class="col-9">
                                <input type="text" name="prefix" class="form-control" value="kldns_">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-3 col-form-label"></label>
                            <div class="col-9">
                                <a class="btn btn-success text-white" @click="mysql">确认无误，下一步</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card mb-3" v-else-if="step===3">
                <div class="card-header text-white bg-info ">安装完成</div>
                <div class="card-body">
                    <div class="list-group-item">
                        成功执行SQL语句@{{ data.success }}条，失败@{{ data.error }}条！
                    </div>
                    <div class="list-group-item list-group-item-warning" v-for="(msg,i) in data.msg" :key="i">
                        执行失败：@{{ msg }}
                    </div>
                    <li class="list-group-item">管理后台地址：域名/admin</li>
                    <li class="list-group-item">管理员账号 用户名：admin 密码:123456</li>
                    <li class="list-group-item text-center">
                        <a href="/admin" class="btn btn-info">进入后台</a>
                        <a href="/" class="btn btn-info">网站首页</a>
                    </li>
                </div>
            </div>
        @endif
    </div>
</div>
</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.6.10/vue.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/layer/2.3/layer.js"></script>
<script src="/js/main.js"></script>
<script>
    new Vue({
        'el': '#vue',
        data: {
            step: 1,
            data: {
                msg: []
            }
        },
        methods: {
            mysql: function () {
                var vm = this;
                this.$post('/install', $("#form-mysql").serialize())
                    .then(function (data) {
                        if (data.status === 0) {
                            vm.step = 3;
                            vm.data = data.data;
                        } else {
                            layer.alert(data.message);
                        }
                    });
            },
        },
        mounted: function () {

        }
    });
</script>
</html>