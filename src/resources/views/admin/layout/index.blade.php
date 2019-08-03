<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - 管理后台 - {{ config('app.name') }}</title>
    <meta name="keywords" content="{{ config('app.name') }}"/>
    <meta name="description" content="{{ config('app.name') }}"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.8.1/css/all.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
    @yield('head')
</head>
<body>
<header class="navbar navbar-expand flex-md-row bd-navbar">
    <div class="navbar-nav-scroll">
        <ul class="navbar-nav bd-navbar-nav flex-row">
            <li class="nav-item d-sm-none" id="menu">
                <a class="nav-link nav-menu" href="#">
                    <i class="fa fa-bars fa-lg"></i>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link nav-logo-name d-none d-sm-block" href="/"><i class="fa fa-cloud"></i> 快乐二级域名分发系统
                    V{{ config('version') }}</a>
                <a class="nav-link nav-logo-name d-sm-none" href="/"><i class="fa fa-cloud"></i> 域名分发</a>
            </li>
        </ul>
    </div>

    <ul class="navbar-nav flex-row ml-auto d-md-flex">
        <li class="nav-item dropdown">
            <a class="nav-item nav-link dropdown-toggle" href="#" id="user_btns"
               data-toggle="dropdown">{{ auth('admin')->user()->username }}</a>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="user_btns">
                <a class="dropdown-item" href="/admin/profile">修改密码</a>
                <a class="dropdown-item" href="/admin/logout" onclick="return confirm('确认退出登录？');">退出登录</a>
            </div>
        </li>
    </ul>
</header>
<div class="container-fluid">
    <div class="row flex-xl-nowrap">
        <div class="col-12 col-md-3 col-xl-2 bd-sidebar">
            <div class="menu-item">
                <a href="/admin" class="menu-link">
                    <i class="fa fa-home"></i> 管理后台
                </a>
            </div>
            <div class="menu-item">
                <a class="menu-link" data-toggle="collapse" href="#menu-config">
                    <i class="fa fa-cog"></i> 系统设置
                    <i class="fa fa-caret-down float-right"></i>
                </a>

                <ul class="collapse" id="menu-config">
                    <li>
                        <a href="/admin/config/sys">
                            <i class="fa fa-caret-right"></i> 系统配置
                        </a>
                    </li>
                    <li>
                        <a href="/admin/config/check">
                            <i class="fa fa-caret-right"></i> 自动检测
                        </a>
                    </li>
                    <li>
                        <a href="/admin/config/dns">
                            <i class="fa fa-caret-right"></i> 接口配置
                        </a>
                    </li>
                </ul>
            </div>
            <div class="menu-item">
                <a class="menu-link" data-toggle="collapse" href="#menu-user">
                    <i class="fa fa-user"></i> 用户管理
                    <i class="fa fa-caret-down float-right"></i>
                </a>

                <ul class="collapse" id="menu-user">
                    <li>
                        <a href="/admin/user/group">
                            <i class="fa fa-caret-right"></i> 用户组
                        </a>
                    </li>
                    <li>
                        <a href="/admin/user/list">
                            <i class="fa fa-caret-right"></i> 用户列表
                        </a>
                    </li>
                    <li>
                        <a href="/admin/user/point">
                            <i class="fa fa-caret-right"></i> 积分明细
                        </a>
                    </li>
                </ul>
            </div>
            <div class="menu-item">
                <a class="menu-link" data-toggle="collapse" href="#menu-domain">
                    <i class="fa fa-cube"></i> 域名管理
                    <i class="fa fa-caret-down float-right"></i>
                </a>

                <ul class="collapse" id="menu-domain">
                    <li>
                        <a href="/admin/domain/list">
                            <i class="fa fa-caret-right"></i> 域名列表
                        </a>
                    </li>
                    <li>
                        <a href="/admin/domain/record">
                            <i class="fa fa-caret-right"></i> 记录列表
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <main class="col-12 col-md-9 col-xl-10 py-md-3 pl-md-5 bd-content">
            @yield('content')
        </main>
    </div>
</div>
</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.6.10/vue.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/layer/2.3/layer.js"></script>
<script src="/js/main.js"></script>
<script>
    var showMenu = false;
    $(document).ready(function () {
        $("#menu").click(function () {
            if (showMenu) {
                $(".bd-sidebar").removeClass('openMenu');
                $(".bd-content").removeClass('moveRight');
                $(".bd-content").addClass('moveAnimation');
                showMenu = false;
            } else {
                $(".bd-content").removeClass('moveAnimation');
                $(".bd-sidebar").addClass('openMenu');
                $(".bd-content").addClass('moveRight');
                showMenu = true;
            }
        });
        /*菜单栏*/
        $(".bd-sidebar a").each(function () {
            var pathname = window.location.pathname;
            var href = $(this).attr('href');
            if (href == pathname) {
                $(this).parent().addClass('active');
                $(this).parent().parent().addClass('show');
            }
        })
    });

</script>
@yield('foot')
</html>