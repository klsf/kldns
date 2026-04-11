<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - 管理后台 - {{ config('app.name') }}</title>
    <meta name="keywords" content="{{ config('app.name') }}"/>
    <meta name="description" content="{{ config('app.name') }}"/>
    @php($assetVersion = filemtime(public_path('css/style.css')).'-'.filemtime(public_path('js/main.js')))
    <link href="/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/vendor/font-awesome/css/all.min.css" rel="stylesheet">
    <link href="/vendor/element-plus/index.css" rel="stylesheet">
    <link href="/css/style.css?v={{ $assetVersion }}" rel="stylesheet">
    @yield('head')
</head>
<body class="page-shell admin-shell">
<header class="topbar">
    <div class="topbar-inner">
        <div class="d-flex align-items-center" style="gap: .75rem;">
            <a class="mobile-toggle" href="javascript:void(0)" onclick="toggleSidebar()" aria-label="切换侧边栏">
                <i class="fas fa-bars"></i>
            </a>
            <a class="brand" href="/">
                <span class="brand-mark brand-mark-dns" aria-hidden="true"><span class="brand-core"></span></span>
                <span>DNS 管理台 V{{ config('version') }}</span>
            </a>
        </div>
        <div class="topbar-actions admin-topbar-actions">
            <div class="dropdown">
                <a class="action-pill dropdown-toggle admin-user-trigger" href="#" id="user_btns" data-toggle="dropdown">
                    <i class="fas fa-user-cog"></i> {{ auth('admin')->user()->username }}
                </a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="user_btns">
                    <a class="dropdown-item" href="/admin/profile">修改密码</a>
                    <a class="dropdown-item" href="/admin/logout" onclick="return confirm('确认退出登录？');">退出登录</a>
                </div>
            </div>
        </div>
    </div>
</header>
<div class="mobile-sidebar-backdrop" onclick="closeSidebar()"></div>
<div class="panel-layout">
    <aside class="sidebar-panel glass-card">
        <div class="menu-group">
            <div class="menu-title">总览</div>
            <div class="menu-item">
                <a href="/admin" class="menu-link"><i class="fas fa-tachometer-alt"></i> 管理首页</a>
            </div>
        </div>
        <div class="menu-group">
            <div class="menu-title">系统设置</div>
            <ul class="menu-sub-list">
                <li class="menu-item"><a href="/admin/config/sys" class="menu-sub-link"><i class="fas fa-sliders-h"></i> 系统配置</a></li>
                <li class="menu-item"><a href="/admin/config/check" class="menu-sub-link"><i class="fas fa-binoculars"></i> 自动检测</a></li>
                <li class="menu-item"><a href="/admin/config/dns" class="menu-sub-link"><i class="fas fa-plug"></i> 接口配置</a></li>
                <li class="menu-item"><a href="/admin/config/logs" class="menu-sub-link"><i class="fas fa-clipboard-list"></i> 操作日志</a></li>
            </ul>
        </div>
        <div class="menu-group">
            <div class="menu-title">用户管理</div>
            <ul class="menu-sub-list">
                <li class="menu-item"><a href="/admin/user/group" class="menu-sub-link"><i class="fas fa-user-tag"></i> 用户组</a></li>
                <li class="menu-item"><a href="/admin/user/list" class="menu-sub-link"><i class="fas fa-users"></i> 用户列表</a></li>
                <li class="menu-item"><a href="/admin/user/point" class="menu-sub-link"><i class="fas fa-wallet"></i> 积分明细</a></li>
            </ul>
        </div>
        <div class="menu-group">
            <div class="menu-title">域名管理</div>
            <ul class="menu-sub-list">
                <li class="menu-item"><a href="/admin/domain/list" class="menu-sub-link"><i class="fas fa-globe-asia"></i> 域名列表</a></li>
                <li class="menu-item"><a href="/admin/domain/record" class="menu-sub-link"><i class="fas fa-project-diagram"></i> 记录列表</a></li>
                <li class="menu-item"><a href="/admin/domain/review" class="menu-sub-link"><i class="fas fa-user-check"></i> 审核记录</a></li>
            </ul>
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
