<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>程序安装 - {{ config('app.name') }}</title>
    @php($assetVersion = (config('version') ?: '4.0.2').'-20260328')
    <link href="/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/vendor/font-awesome/css/all.min.css" rel="stylesheet">
    <link href="/vendor/element-plus/index.css" rel="stylesheet">
    <link href="/css/style.css?v={{ $assetVersion }}" rel="stylesheet">
</head>
<body class="page-shell">
<main id="vue" class="install-shell">
    <div class="auth-grid">
        <section class="glass-card auth-visual">
            <div>
                <span class="hero-kicker"><i class="fas fa-cogs"></i> 安装向导</span>
                <h1 class="hero-title" style="font-size: clamp(1.8rem, 4vw, 3.2rem);">部署你的<br><span>域名解析平台</span></h1>
                <p class="hero-copy">按步骤完成环境检测、数据库初始化与后台开通。安装完成后，你就可以接入 DNS 平台、同步主域名并开放用户自助解析。</p>
            </div>
            <div class="auth-badges">
                <span class="auth-badge">第 1 步 环境检测</span>
                <span class="auth-badge">第 2 步 数据库初始化</span>
                <span class="auth-badge">第 3 步 后台开通</span>
            </div>
        </section>
        <section class="glass-card auth-panel">
        @if(config('mysql'))
            <div class="card mb-0">
                <div class="card-header">安装提示</div>
                <div class="card-body text-center text-danger">
                    系统已经存在安装配置。如需重新安装，请先删除 `/storage/install/mysql.php` 文件，再重新打开安装页。
                </div>
            </div>
        @else
            <div class="card mb-0" v-if="step===1">
                <div class="card-header">运行环境检测</div>
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
                            <el-button type="success" class="w-100" @click="step=2">环境通过，继续配置数据库</el-button>
                        </div>
                    @endif
                </div>
            </div>
            <div class="card mb-0" v-else-if="step===2">
                <div class="card-header">MySQL 数据库配置</div>
                <div class="card-body">
                    <div class="input_tips mb-3">
                        请填写一个空数据库，安装程序会自动创建数据表并写入系统初始数据。
                    </div>
                    <form id="form-mysql">
                        <div class="form-group">
                            <label>数据库地址</label>
                            <el-input v-model="mysqlForm.host" placeholder="例如 127.0.0.1"></el-input>
                        </div>
                        <div class="form-group">
                            <label>数据库端口</label>
                            <el-input v-model="mysqlForm.port" placeholder="默认 3306"></el-input>
                        </div>
                        <div class="form-group">
                            <label>数据库库名</label>
                            <el-input v-model="mysqlForm.database" placeholder="输入数据库名称"></el-input>
                        </div>
                        <div class="form-group">
                            <label>数据库用户名</label>
                            <el-input v-model="mysqlForm.username" placeholder="输入数据库用户名"></el-input>
                        </div>
                        <div class="form-group">
                            <label>数据库密码</label>
                            <el-input v-model="mysqlForm.password" type="password" show-password placeholder="输入数据库密码"></el-input>
                        </div>
                        <div class="form-group">
                            <label>数据表前缀</label>
                            <el-input v-model="mysqlForm.prefix" placeholder="例如 kldns_"></el-input>
                        </div>
                        <el-button type="success" class="w-100" @click="mysql">写入配置并开始安装</el-button>
                    </form>
                </div>
            </div>
            <div class="card mb-0" v-else-if="step===3">
                <div class="card-header">安装完成</div>
                <div class="card-body">
                    <div class="list-group-item">
                        安装脚本执行完成：成功 @{{ data.success }} 条，失败 @{{ data.error }} 条。
                    </div>
                    <div class="list-group-item list-group-item-warning" v-for="(msg,i) in data.msg" :key="i">
                        执行失败：@{{ msg }}
                    </div>
                    <li class="list-group-item">管理后台地址：当前域名 `/admin`</li>
                    <li class="list-group-item">默认管理员账号：`admin`，默认密码：`123456`</li>
                    <li class="list-group-item">首次登录后，请立即修改管理员密码并配置 DNS 接口。</li>
                    <li class="list-group-item text-center">
                        <el-button type="primary" @click="go('/admin')">进入后台</el-button>
                        <el-button @click="go('/')">网站首页</el-button>
                    </li>
                </div>
            </div>
        @endif
        </section>
    </div>
</main>
<script src="/vendor/vue/vue.global.prod.min.js"></script>
<script src="/vendor/element-plus/index.full.min.js"></script>
<script src="/js/main.js?v={{ $assetVersion }}"></script>
<script>
    createVuePage('#vue', {
        data: function () {
            return {
                step: 1,
                mysqlForm: {
                    action: 'mysql',
                    host: '127.0.0.1',
                    port: '3306',
                    database: '',
                    username: '',
                    password: '',
                    prefix: 'kldns_'
                },
                data: {
                    msg: []
                }
            };
        },
        methods: {
            go: function (url) {
                window.location.href = url;
            },
            mysql: function () {
                var vm = this;
                this.$post('/install', this.mysqlForm)
                    .then(function (data) {
                        if (!data) {
                            return;
                        }

                        if (data.status === 0) {
                            vm.step = 3;
                            vm.data = data.data;
                        } else {
                            vm.$alert(data.message);
                        }
                    });
            },
        }
    });
</script>
</html>
