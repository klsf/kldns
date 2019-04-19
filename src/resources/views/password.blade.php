<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>重设密码 - {{ config('sys.web.name','二级域名分发') }}</title>
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
        </ul>
    </div>
    <ul class="navbar-nav flex-row ml-md-auto d-md-flex">
        <li class="nav-item">
            <a class="nav-link p-2" href="/login">登录</a>
        </li>
        <li class="nav-item">
            <a class="nav-link p-2" href="/login?act=reg">注册</a>
        </li>
    </ul>
</header>
<main class="bd-masthead" id="content">
    <div class="container" v-cloak="">
        <div class="col-12 col-md-6 offset-md-3 mt-3 mt-sm-5">
            <div class="card mb-3">
                <div class="card-header text-white bg-info ">重设密码</div>
                <div class="card-body">
                    <form id="form-password">
                        <input type="hidden" name="action" value="setPassword">
                        <input type="hidden" name="code"
                               value="{{ \Illuminate\Support\Facades\Crypt::encrypt($user->sid) }}">
                        <div class="form-group row">
                            <label for="staticEmail" class="col-3 col-form-label">用户名</label>
                            <div class="col-9">
                                <input type="text" class="form-control" value="{{ $user->username }}" disabled>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-3 col-form-label">新密码</label>
                            <div class="col-9">
                                <input type="password" name="password" class="form-control" placeholder="输入新密码">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-3 col-form-label">重复密码</label>
                            <div class="col-9">
                                <input type="password" name="re_password" class="form-control" placeholder="重复一次新密码">
                            </div>
                        </div>
                        <div class="form-group text-center">
                            <a class="btn btn-info text-white" @click="password"> 重设密码 </a>
                        </div>
                    </form>
                </div>
            </div>
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
    new Vue({
        el: '#content',
        data: {
            act: 'login'
        },
        methods: {
            password: function () {
                var vm = this;
                this.$post('/password', $("#form-password").serialize())
                    .then(function (data) {
                        if (data.status === 0) {
                            layer.alert(data.message, {
                                closeBtn: 0
                            }, function (i) {
                                window.location.href = "/login";
                            });
                        } else {
                            layer.alert(data.message);
                        }
                    });
            }
        },
        mounted: function () {
            var vm = this;
            document.onkeyup = function (e) {
                var code = parseInt(e.charCode || e.keyCode);
                if (code === 13) {
                    vm.password();
                }
            }
        }
    });
</script>
</html>