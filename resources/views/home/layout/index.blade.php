<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('sys.web.name') }}</title>
    @php($assetVersion = filemtime(public_path('css/style.css')).'-'.filemtime(public_path('js/main.js')))
    <link href="/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/vendor/font-awesome/css/all.min.css" rel="stylesheet">
    <link href="/vendor/element-plus/index.css" rel="stylesheet">
    <link href="/css/style.css?v={{ $assetVersion }}" rel="stylesheet">
    @yield('head')
</head>
<body class="page-shell">
<header class="topbar">
    <div class="topbar-inner member-topbar">
        <div class="d-flex align-items-center member-topbar-brand" style="gap: .75rem;">
            <a class="mobile-toggle" href="javascript:void(0)" onclick="toggleSidebar()" aria-label="切换侧边栏">
                <i class="fas fa-bars"></i>
            </a>
            <a class="brand" href="/">
                <span class="brand-mark brand-mark-dns" aria-hidden="true"><span class="brand-core"></span></span>
                <span>{{ config('sys.web.name') }}</span>
            </a>
        </div>
        <div class="topbar-actions member-topbar-actions">
            <a class="action-pill member-home-trigger" href="/"><i class="fas fa-home"></i> 首页</a>
            <div class="dropdown">
                <a class="action-pill dropdown-toggle member-user-trigger" href="#" id="user_btns" data-toggle="dropdown">
                    <i class="fas fa-id-badge"></i>
                    {{ auth()->user()->username }}
                    <span class="d-none d-md-inline">[{{ auth()->user()->group?auth()->user()->group->name:'' }}]</span>
                </a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="user_btns">
                    <a class="dropdown-item" href="/home/profile">修改密码</a>
                    <a class="dropdown-item" href="/logout" onclick="return confirm('确认退出登录？');">退出登录</a>
                </div>
            </div>
        </div>
    </div>
</header>
<div class="mobile-sidebar-backdrop" onclick="closeSidebar()"></div>
<div class="panel-layout">
    <aside class="sidebar-panel glass-card">
        <div class="menu-group">
            <div class="menu-item">
                <a href="/" class="menu-link"><i class="fas fa-home"></i> 网站首页</a>
            </div>
            <div class="menu-item">
                <a href="/home" class="menu-link"><i class="fas fa-project-diagram"></i> 解析记录</a>
            </div>
            <div class="menu-item">
                <a href="/home/point" class="menu-link"><i class="fas fa-wallet"></i> 积分明细</a>
            </div>
            <div class="menu-item">
                <a href="/home/review" class="menu-link"><i class="fas fa-user-check"></i> 审核记录</a>
            </div>
            <div class="menu-item">
                <a href="/home/api" class="menu-link"><i class="fas fa-key"></i> 开放 API</a>
            </div>
            <div class="menu-item">
                <a href="/home/profile" class="menu-link"><i class="fas fa-id-card-alt"></i> 账户设置</a>
            </div>
        </div>
    </aside>
    <main class="content-panel">
        @yield('content')
    </main>
</div>
<script src="/vendor/vue/vue.global.prod.min.js"></script>
<script src="/vendor/element-plus/index.full.min.js"></script>
<script src="/js/main.js?v={{ $assetVersion }}"></script>
@yield('foot')
</html>
