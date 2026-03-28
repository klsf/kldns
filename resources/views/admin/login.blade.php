<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>后台登录 - {{ config('app.name') }}</title>
    <meta name="keywords" content="{{ config('sys.web.keywords') }}"/>
    <meta name="description" content="{{ config('sys.web.description') }}"/>
    @php($assetVersion = filemtime(public_path('css/style.css')).'-'.filemtime(public_path('js/main.js')))
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.8.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/element-plus/dist/index.css" rel="stylesheet">
    <link href="/css/style.css?v={{ $assetVersion }}" rel="stylesheet">
</head>
<body class="page-shell admin-shell">
<main id="content" class="auth-shell">
    <div class="auth-grid auth-grid-compact">
        <section class="glass-card auth-panel">
            <div class="page-header">
                <div>
                    <h1>后台登录</h1>
                    <p>使用管理员账号登录系统控制台。</p>
                </div>
            </div>
            <div class="card-body px-0 pb-0">
                    <form id="form-login">
                        <div class="form-group">
                            <label>用户名</label>
                            <el-input v-model="loginForm.username" placeholder="输入管理员账号"></el-input>
                        </div>
                        <div class="form-group">
                            <label>密码</label>
                            <el-input v-model="loginForm.password" type="password" show-password placeholder="输入管理员密码"></el-input>
                        </div>
                        <div class="form-group">
                            <label>验证码</label>
                            <el-input v-model="loginForm.code" placeholder="输入验证码"></el-input>
                            <img title="点击刷新" src="/captcha"
                                 style="width: 150px; margin-top: 10px; border-radius: 14px;"
                                 onclick="this.src='/captcha?_='+Math.random();" id="code">
                        </div>
                        <div class="form-group">
                            <el-checkbox v-model="loginForm.remember">记住登录</el-checkbox>
                        </div>
                        <el-button type="primary" class="w-100" @click="login">进入后台</el-button>
                    </form>
            </div>
        </section>
    </div>
</main>
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="https://unpkg.com/element-plus/dist/index.full.min.js"></script>
<script src="/js/main.js?v={{ $assetVersion }}"></script>
<script>
    createVuePage('#content', {
        data: function () {
            return {
                loginForm: {
                    username: '',
                    password: '',
                    code: '',
                    remember: false
                }
            };
        },
        methods: {
            login: function () {
                this.$post('/admin/login', this.loginForm)
                    .then(function (data) {
                        if (!data) {
                            return;
                        }

                        this.$refreshCaptcha('code');
                        if (data.status === 0) {
                            location.href = data.go ? data.go : "{{ request()->get('go','/') }}";
                        } else {
                            this.$alert(data.message);
                        }
                    }.bind(this));
            },
        },
        mounted: function () {
            var vm = this;
            document.addEventListener('keyup', function (e) {
                if (e.key === 'Enter') {
                    vm.login();
                }
            });
        }
    });
</script>
</html>
