<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>重设密码 - {{ config('sys.web.name','二级域名分发') }}</title>
    <meta name="keywords" content="{{ config('sys.web.keywords') }}"/>
    <meta name="description" content="{{ config('sys.web.description') }}"/>
    @php($assetVersion = (config('version') ?: '4.0.2').'-20260328')
    <link href="/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/vendor/font-awesome/css/all.min.css" rel="stylesheet">
    <link href="/vendor/element-plus/index.css" rel="stylesheet">
    <link href="/css/style.css?v={{ $assetVersion }}" rel="stylesheet">
</head>
<body class="page-shell">
<main id="content" class="auth-shell" v-cloak>
    <div class="auth-grid">
        <section class="glass-card auth-visual">
            <div>
                <span class="hero-kicker"><i class="fas fa-user-lock"></i> 安全重置</span>
                <h1 class="hero-title" style="font-size: clamp(1.8rem, 4vw, 3.2rem);">重新签发你的<br><span>账户访问凭据</span></h1>
                <p class="hero-copy">通过邮件验证链接重置密码，完成后可以直接返回登录页重新进入域名解析控制台。</p>
            </div>
            <div class="auth-badges">
                <span class="auth-badge">邮箱验证</span>
                <span class="auth-badge">密码轮换</span>
                <span class="auth-badge">账户恢复</span>
            </div>
        </section>
        <section class="glass-card auth-panel">
            <div class="page-header">
                <div>
                    <h1>重设密码</h1>
                    <p>为账号 {{ $user->username }} 设置新密码。</p>
                </div>
            </div>
            <div class="card-body px-0 pb-0">
                    <form id="form-password">
                        <div class="form-group">
                            <label>用户名</label>
                            <el-input value="{{ $user->username }}" disabled></el-input>
                        </div>
                        <div class="form-group">
                            <label>新密码</label>
                            <el-input v-model="form.password" type="password" show-password placeholder="输入新密码"></el-input>
                        </div>
                        <div class="form-group">
                            <label>重复密码</label>
                            <el-input v-model="form.re_password" type="password" show-password placeholder="重复一次新密码"></el-input>
                        </div>
                        <el-button type="primary" class="w-100" @click="password">提交重设</el-button>
                    </form>
            </div>
        </section>
    </div>
</main>
<script src="/vendor/vue/vue.global.prod.min.js"></script>
<script src="/vendor/element-plus/index.full.min.js"></script>
<script src="/js/main.js?v={{ $assetVersion }}"></script>
<script>
    createVuePage('#content', {
        data: function () {
            return {
                form: {
                    action: 'setPassword',
                    code: '{{ \Illuminate\Support\Facades\Crypt::encrypt($user->sid) }}',
                    password: '',
                    re_password: ''
                }
            };
        },
        methods: {
            password: function () {
                this.$post('/password', this.form)
                    .then(function (data) {
                        if (!data) {
                            return;
                        }

                        if (data.status === 0) {
                            this.$alert(data.message, '重设成功', {
                                closeOnClickModal: false,
                                closeOnPressEscape: false
                            }).then(function () {
                                window.location.href = "/login";
                            });
                        } else {
                            this.$alert(data.message);
                        }
                    }.bind(this));
            }
        },
        mounted: function () {
            var vm = this;
            document.addEventListener('keyup', function (e) {
                if (e.key === 'Enter') {
                    vm.password();
                }
            });
        }
    });
</script>
</html>
