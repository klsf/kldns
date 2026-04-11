<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('sys.web.title',config('sys.web.name','网站首页')) }}</title>
    <meta name="keywords" content="{{ config('sys.web.keywords') }}"/>
    <meta name="description" content="{{ config('sys.web.description') }}"/>
    @php($assetVersion = filemtime(public_path('css/style.css')).'-'.filemtime(public_path('js/main.js')))
    <link href="/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/vendor/font-awesome/css/all.min.css" rel="stylesheet">
    <link href="/vendor/element-plus/index.css" rel="stylesheet">
    <link href="/css/style.css?v={{ $assetVersion }}" rel="stylesheet">
</head>
<body class="page-shell">
<header class="topbar">
    <div class="topbar-inner index-topbar">
        <div class="topbar-branding">
            <a class="brand" href="/">
                <span class="brand-mark brand-mark-dns" aria-hidden="true"><span class="brand-core"></span></span>
                <span>{{ config('sys.web.name', 'KLDNS') }}</span>
            </a>
        </div>
        <div class="topbar-meta">
            <nav class="topbar-nav">
                <a class="nav-pill active" href="/">首页</a>
                @foreach(\App\Helper::getIndexUrls() as $url)
                    <a class="nav-pill" href="{{ $url[1] }}" target="_blank">{{ $url[0] }}</a>
                @endforeach
            </nav>
            <div class="topbar-actions index-topbar-actions">
            @if(auth()->check())
                <div class="dropdown">
                    <a class="action-pill dropdown-toggle index-user-trigger" href="#" id="index_user_btns" data-toggle="dropdown">
                        <i class="fas fa-id-badge"></i>
                        {{ auth()->user()->username }}
                    </a>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="index_user_btns">
                        <a class="dropdown-item" href="/home">进入控制台</a>
                        <a class="dropdown-item" href="/logout" onclick="return confirm('确认退出登录？');">退出登录</a>
                    </div>
                </div>
            @else
                <a class="action-pill" href="/login"><i class="fas fa-sign-in-alt"></i> 登录</a>
                <a class="action-pill" href="/login?act=reg"><i class="fas fa-user-plus"></i> 注册</a>
            @endif
            </div>
        </div>
    </div>
</header>
<main id="content">
    <section class="hero">
        <div class="hero-grid">
            <div class="glass-card hero-card">
                <span class="hero-kicker"><i class="fas fa-project-diagram"></i> 二级域名分发与解析管理系统</span>
                <h1 class="hero-title">把二级域名的申请、开通和解析维护<br><span>统一到一个自助平台</span></h1>
                <p class="hero-copy">
                    适合做二级域名分发、用户自助解析和统一资源管理。管理员只需接入 DNS 平台并配置主域策略，用户就可以自行查询前缀、创建解析记录、管理账户和积分，减少人工代配和重复沟通。
                </p>
                <div class="hero-stats">
                    <div class="stat-card">
                        <strong>{{ count(\App\Helper::getAvailableDomains()) }}</strong>
                        <span>当前开放主域</span>
                    </div>
                    <div class="stat-card">
                        <strong>API</strong>
                        <span>多 DNS 平台接入</span>
                    </div>
                    <div class="stat-card">
                        <strong>24/7</strong>
                        <span>桌面与手机随时管理</span>
                    </div>
                </div>
            </div>
            <div class="glass-card search-card">
                <h3>先查可用前缀，再创建解析</h3>
                <p>输入前缀并选择主域，系统会立即检测当前是否可分配。确认可用后，用户即可进入控制台自助添加解析记录。</p>
                <div class="mt-2">
                    <div class="domain-form">
                        <el-input v-model="checkForm.name" class="domain-form-input" placeholder="例如 api、cdn、status"></el-input>
                        @php($availableDomains = \App\Helper::getAvailableDomains())
                        <el-select v-model="checkForm.did" class="domain-form-select" {{ $availableDomains->isEmpty() ? 'disabled' : '' }}>
                            @forelse($availableDomains as $domain)
                                <el-option :value="{{ $domain->did }}" label=".{{ $domain->domain }}"></el-option>
                            @empty
                                <el-option :value="0" label="暂无可用域名"></el-option>
                            @endforelse
                        </el-select>
                        <el-button type="primary" class="domain-form-button" @click="check" {{ $availableDomains->isEmpty() ? 'disabled' : '' }}>立即检测</el-button>
                    </div>
                </div>
                @if($availableDomains->isEmpty())
                    <div class="input_tips">
                        当前尚未检测到已接入的域名资源。请先完成系统安装并在后台配置 DNS 接口与主域名。
                    </div>
                @endif
                <div class="feature-grid mt-3">
                    <div class="feature-card">
                        <h4><i class="fas fa-robot mr-2"></i>减少人工代配</h4>
                        <p>用户可自行完成前缀查询、域名开通和解析提交，明显降低人工处理成本。</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-shield-alt mr-2"></i>策略统一可控</h4>
                        <p>支持按用户组、积分和主域范围控制资源开放，让分发和权限管理更清晰。</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-layer-group mr-2"></i>前后台一体化</h4>
                        <p>首页、用户中心和后台共用一套流程，管理员和用户都能快速完成常用操作。</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="content-wrap">
        @if(config('sys.html_header'))
            <div class="glass-card p-4 mb-4">
                {!! config('sys.html_header') !!}
            </div>
        @endif
        <div class="metrics-grid">
            @forelse($availableDomains as $domain)
                <div class="feature-card glass-card">
                    <h4><i class="fas fa-globe-asia mr-2"></i>{{ $domain->domain }}</h4>
                    <p>{{ $domain->desc ?: '该主域已开放二级域名前缀申请，用户可在控制台自助创建和维护解析记录。' }}</p>
                </div>
            @empty
                <div class="feature-card glass-card empty-state-card">
                    <h4><i class="fas fa-plug mr-2"></i>等待接入域名池</h4>
                    <p>完成安装后，在后台接入 DNS 平台并同步主域名，这里会自动展示当前已开放的解析资源。</p>
                </div>
            @endforelse
        </div>
    </section>
</main>
<script src="/vendor/vue/vue.global.prod.min.js"></script>
<script src="/vendor/element-plus/index.full.min.js"></script>
<script src="/js/main.js?v={{ $assetVersion }}"></script>
<script>
    createVuePage('#content', {
        data: function () {
            return {
                checkForm: {
                    name: '',
                    did: {{ $availableDomains->isEmpty() ? 0 : $availableDomains->first()->did }}
                }
            };
        },
        methods: {
            check: function () {
                var vm = this;
                this.$post('/check', this.checkForm)
                    .then(function (data) {
                        if (!data) {
                            return;
                        }

                        if (data.status === 0) {
                            vm.$confirm(data.message, '检测成功', {
                                confirmButtonText: '去解析',
                                cancelButtonText: '取消',
                                type: 'success'
                            }).then(function () {
                                window.location.href = '/home';
                            }).catch(function () {
                                return null;
                            });
                        } else {
                            vm.$alert(data.message);
                        }
                    });
            }
        },
        mounted: function () {
            var vm = this;
            document.addEventListener('keyup', function (event) {
                if (event.key === 'Enter') {
                    vm.check();
                }
            });
        }
    });
</script>
</html>
