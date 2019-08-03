<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('sys.web.title',config('sys.web.name','网站首页')) }}</title>
    <meta name="keywords" content="{{ config('sys.web.keywords') }}"/>
    <meta name="description" content="{{ config('sys.web.description') }}"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.8.1/css/all.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
</head>
<body>
<header class="navbar navbar-expand navbar-dark flex-column flex-md-row bd-navbar bd-navbar-index">
    <a class="navbar-brand mr-0 mr-md-2" href="/" aria-label="Bootstrap">
        <img src="/images/logo.png" width="36" height="36">
    </a>

    <div class="navbar-nav-scroll">
        <ul class="navbar-nav bd-navbar-nav flex-row">
            <li class="nav-item">
                <a class="nav-link active" href="/">首页</a>
            </li>
            @foreach(\App\Helper::getIndexUrls() as $url)
                <li class="nav-item">
                    <a href="{{ $url[1] }}" target="_blank" class="nav-link">{{ $url[0] }}</a>
                </li>
            @endforeach
        </ul>
    </div>

    @if(auth()->check())
        <ul class="navbar-nav flex-row ml-md-auto d-md-flex">
            <li class="nav-item">
                <a class="nav-item nav-link dropdown-toggle" href="#" id="user_btns"
                   data-toggle="dropdown">{{ auth()->user()->username }}<span
                            class="d-none d-sm-inline">[{{ auth()->user()->group?auth()->user()->group->name:'' }}
                        ]</span>
                </a>
                <div class="dropdown-menu" style="left: auto" aria-labelledby="user_btns">
                    <a class="dropdown-item" href="/home">解析记录</a>
                    <a class="dropdown-item" href="/home/profile">修改密码</a>
                    <a class="dropdown-item" href="/logout" onclick="return confirm('确认退出登录？');">退出登录</a>
                </div>
            </li>
        </ul>
    @else
        <ul class="navbar-nav flex-row ml-md-auto d-md-flex">
            <li class="nav-item">
                <a class="nav-link p-2" href="/login">登录</a>
            </li>
            <li class="nav-item">
                <a class="nav-link p-2" href="/login?act=reg">注册</a>
            </li>
        </ul>
    @endif
</header>
<main class="bd-masthead" id="content">
    <div class="container">
        <div class="row">
            <div class="col-12 mt-3">
                {!! config('sys.html_header') !!}
            </div>
            <div class="col-12 mt-0 mt-sm-3">
                <form id="form-check">
                    <input type="text" class="d-none">
                    <div class="input-group">
                        <input type="text" class="form-control" style="height: 3rem" name="name"
                               placeholder="输入你想要的二级域名前缀">
                        <select name="did" class="form-control" style="flex: none;width: 100px;height: 3rem">
                            @foreach(\App\Helper::getAvailableDomains() as $domain)
                                <option value="{{ $domain->did }}">.{{ $domain->domain }}</option>
                            @endforeach
                        </select>
                        <div class="input-group-append">
                        <span class="input-group-text" style="background: #563d7c;color: white;"
                              onclick="check()">查询</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.6.10/vue.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/layer/2.3/layer.js"></script>
<script src="/js/main.js"></script>
<script>
    function check() {
        $post("/check", $("#form-check").serialize())
            .then(function (data) {
                if (data.status === 0) {
                    layer.confirm(data.message, {
                        btn: ['解析', '取消']
                    }, function () {
                        window.location.href = "/home/"
                    }, function () {
                    });
                } else {
                    layer.alert(data.message)
                }
            });
    }

    document.onkeyup = function (e) {
        var code = parseInt(e.charCode || e.keyCode);
        if (code === 13) {
            check();
        }
    }
</script>
</html>