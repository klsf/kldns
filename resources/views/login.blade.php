<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>会员登录 - {{ config('sys.web.name','二级域名分发') }}</title>
    <meta name="keywords" content="{{ config('sys.web.keywords') }}"/>
    <meta name="description" content="{{ config('sys.web.description') }}"/>
    @php($assetVersion = (config('version') ?: '4.0.1').'-20260328')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.8.1/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/element-plus/dist/index.css" rel="stylesheet">
    <link href="/css/style.css?v={{ $assetVersion }}" rel="stylesheet">
</head>
<body class="page-shell">
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/">
            <span class="brand-mark brand-mark-dns" aria-hidden="true"><span class="brand-core"></span></span>
            <span>{{ config('sys.web.name','二级域名分发') }}</span>
        </a>
        <div class="topbar-actions">
            <a class="action-pill" href="/"><i class="fas fa-home"></i> 首页</a>
            @foreach(\App\Helper::getIndexUrls() as $url)
                <a href="{{ $url[1] }}" target="_blank" class="action-pill">{{ $url[0] }}</a>
            @endforeach
        </div>
    </div>
</header>
<main id="content" class="auth-shell" v-cloak>
    <div class="auth-grid auth-grid-compact">
        <section class="glass-card auth-panel">
            <div v-if="act==='reg'">
                <div class="page-header">
                    <div>
                        <h1>注册会员</h1>
                        <p>创建一个新账号，进入域名解析控制台。</p>
                    </div>
                </div>
                <div class="card-body px-0 pb-0">
                        <form id="form-reg">
                            <div class="form-group">
                                <label>用户名</label>
                                <el-input v-model="regForm.username" placeholder="输入用户名"></el-input>
                            </div>
                            <div class="form-group">
                                <label>密码</label>
                                <el-input v-model="regForm.password" type="password" show-password placeholder="设置登录密码"></el-input>
                            </div>
                            <div class="form-group">
                                <label>邮箱</label>
                                <el-input v-model="regForm.email" placeholder="输入你的邮箱地址"></el-input>
                            </div>
                            <div class="form-group">
                                <label>验证码</label>
                                <el-input v-model="regForm.code" placeholder="输入验证码"></el-input>
                                <img title="点击刷新" src="/captcha"
                                     style="width: 150px; margin-top: 10px; border-radius: 14px;"
                                     onclick="this.src='/captcha?_='+Math.random();" id="code">
                            </div>
                            <el-button type="primary" class="w-100" @click="reg">立即注册</el-button>
                            <div class="mt-3 text-center text-muted">
                                已有账号？<a @click.prevent="act='login'" href="#">马上登录</a>
                            </div>
                        </form>
                </div>
            </div>
            <div v-else>
                <div class="page-header">
                    <div>
                        <h1>会员登录</h1>
                        <p>进入用户控制台，管理解析记录和积分。</p>
                    </div>
                </div>
                <div class="card-body px-0 pb-0">
                        <form id="form-login">
                            <div class="form-group">
                                <label>用户名</label>
                                <el-input v-model="loginForm.username" placeholder="输入用户名"></el-input>
                            </div>
                            <div class="form-group">
                                <label>密码</label>
                                <el-input v-model="loginForm.password" type="password" show-password placeholder="输入用户密码"></el-input>
                            </div>
                            <div class="form-group d-flex align-items-center justify-content-between flex-wrap" style="gap: .75rem;">
                                <el-checkbox v-model="loginForm.remember">记住登录</el-checkbox>
                                <el-link type="primary" @click="openPasswordModal">忘记密码？</el-link>
                            </div>
                            <el-button type="primary" class="w-100" @click="login">立即登录</el-button>
                            <div class="mt-3 text-center text-muted">
                                没有账号？<a @click.prevent="act='reg'" href="#">马上注册</a>
                            </div>
                        </form>
                </div>
            </div>
        </section>
    </div>
    <el-dialog v-model="passwordDialogVisible" title="找回密码" width="min(460px, calc(100vw - 24px))" class="app-dialog" top="5vh">
        <div class="form-group">
            <label>账号</label>
            <el-input v-model="passwordForm.username" placeholder="输入你要找回的账号或者邮箱"></el-input>
        </div>
        <div class="form-group">
            <label>验证码</label>
            <el-input v-model="passwordForm.code" placeholder="输入验证码"></el-input>
            <img title="点击刷新" src="/captcha"
                 style="width: 150px; margin-top: 10px; border-radius: 14px;"
                 onclick="this.src='/captcha?_='+Math.random();" id="findCode">
        </div>
        <template v-slot:footer>
            <el-button @click="passwordDialogVisible = false">关闭</el-button>
            <el-button type="primary" @click="password">找回密码</el-button>
        </template>
    </el-dialog>
</main>

</body>
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
<script src="https://unpkg.com/element-plus/dist/index.full.min.js"></script>
<script src="/js/main.js?v={{ $assetVersion }}"></script>
<script>
    createVuePage('#content', {
        data: function () {
            return {
                act: 'login',
                passwordDialogVisible: false,
                loginForm: {
                    username: '',
                    password: '',
                    remember: false
                },
                regForm: {
                    username: '',
                    password: '',
                    email: '',
                    code: ''
                },
                passwordForm: {
                    username: '',
                    code: ''
                }
            };
        },
        methods: {
            openPasswordModal: function () {
                this.passwordDialogVisible = true;
            },
            password: function () {
                this.$post('/password', Object.assign({ action: 'sendPasswordEmail' }, this.passwordForm))
                    .then(function (data) {
                        if (!data) {
                            return;
                        }

                        this.$refreshCaptcha('findCode');
                        if (data.status === 0) {
                            this.passwordDialogVisible = false;
                            this.$alert(data.message);
                        } else {
                            this.$alert(data.message);
                        }
                    }.bind(this));
            },
            login: function () {
                this.$post('/login', this.loginForm)
                    .then(function (data) {
                        if (!data) {
                            return;
                        }

                        if (data.status === 0) {
                            location.href = data.go ? data.go : "{{ request()->get('go','/') }}";
                        } else {
                            this.$alert(data.message);
                        }
                    }.bind(this));
            },
            reg: function () {
                this.$post('/reg', this.regForm)
                    .then(function (data) {
                        if (!data) {
                            return;
                        }

                        this.$refreshCaptcha('code');
                        if (data.status === 0) {
                            this.$alert(data.message, '注册成功', {
                                closeOnClickModal: false,
                                closeOnPressEscape: false
                            });
                        } else {
                            this.$alert(data.message);
                        }
                    }.bind(this));
            }
        },
        mounted: function () {
            if ($_GET('act') === 'reg') {
                this.act = 'reg';
            }
            var vm = this;
            document.addEventListener('keyup', function (e) {
                if (e.key === 'Enter') {
                    if (vm.act === 'reg') {
                        vm.reg();
                    } else {
                        vm.login();
                    }
                }
            });
        }
    });
</script>
</html>
