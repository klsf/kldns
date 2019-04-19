<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>会员登录 - {{ config('sys.web.name','二级域名分发') }}</title>
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
</header>
<main class="bd-masthead" id="content">
    <div class="container" v-cloak="">
        <div class="row" v-if="act==='reg'">
            <div class="col-12 col-md-6 offset-md-3 mt-3 mt-sm-5">
                <div class="card mb-3">
                    <div class="card-header text-white bg-info ">注册会员</div>
                    <div class="card-body">
                        <form id="form-reg">
                            <div class="form-group row">
                                <label for="staticEmail" class="col-3 col-form-label">用户名</label>
                                <div class="col-9">
                                    <input type="text" name="username" class="form-control" placeholder="输入用户名">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-3 col-form-label">密码</label>
                                <div class="col-9">
                                    <input type="password" name="password" class="form-control" placeholder="设置登录密码">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-3 col-form-label">邮箱</label>
                                <div class="col-9">
                                    <input type="email" name="email" class="form-control" placeholder="输入你的邮箱地址">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-3 col-form-label">验证码</label>
                                <div class="col-9">
                                    <input type="text" name="code" class="form-control" placeholder="输入验证码">
                                    <img title="点击刷新" src="/captcha"
                                         style="float: left;width: 150px; margin: 5px auto"
                                         onclick="this.src='/captcha?_='+Math.random();" id="code">
                                </div>
                            </div>
                            <div class="form-group text-center">
                                <a class="btn btn-info text-white" @click="reg"> 注 册 </a>
                            </div>
                            <div class="form-group text-center">
                                <p>已有账号？<a @click="act='login'" href="#">马上登录</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row" v-else>
            <div class="col-12 col-md-6 offset-md-3 mt-3 mt-sm-5">
                <div class="card mb-3">
                    <div class="card-header text-white bg-info ">会员登录</div>
                    <div class="card-body">
                        <form id="form-login">
                            <div class="form-group row">
                                <label for="staticEmail" class="col-3 col-form-label">用户名</label>
                                <div class="col-9">
                                    <input type="text" name="username" class="form-control" placeholder="输入用户名">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-3 col-form-label">密码</label>
                                <div class="col-9">
                                    <input type="password" name="password" class="form-control" placeholder="输入用户密码">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-3 col-form-label"></label>
                                <div class="col-9">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                        <label class="form-check-label" for="remember">记住登录</label>
                                        <a class="float-right" href="#modal-password" data-toggle="modal">忘记密码？</a>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group text-center">
                                <a class="btn btn-info text-white" @click="login"> 登 录 </a>
                            </div>
                            <div class="form-group text-center">
                                <p>没有账号？<a @click="act='reg'" href="#">马上注册</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modal-password">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">找回密码</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="form-password">
                        <input type="hidden" name="action" value="sendPasswordEmail">
                        <div class="form-group row">
                            <label for="staticEmail" class="col-3 col-form-label">账号</label>
                            <div class="col-9">
                                <input type="text" name="username" class="form-control" placeholder="输入你要找回的账号或者邮箱">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-3 col-form-label">验证码</label>
                            <div class="col-9">
                                <input type="text" name="code" class="form-control" placeholder="输入验证码">
                                <img title="点击刷新" src="/captcha"
                                     style="float: left;width: 150px; margin: 5px auto"
                                     onclick="this.src='/captcha?_='+Math.random();" id="findCode">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                    <button type="button" class="btn btn-primary" @click="password">找回密码</button>
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
                        $("#findCode").click();
                        if (data.status === 0) {
                            layer.alert(data.message);
                        } else {
                            layer.alert(data.message);
                        }
                    });
            },
            login: function () {
                var vm = this;
                this.$post('/login', $("#form-login").serialize())
                    .then(function (data) {
                        if (data.status === 0) {
                            location.href = data.go ? data.go : "{{ request()->get('go','/') }}";
                        } else {
                            layer.alert(data.message);
                        }
                    });
            },
            reg: function () {
                var vm = this;
                this.$post('/reg', $("#form-reg").serialize())
                    .then(function (data) {
                        $("#code").click();
                        if (data.status === 0) {
                            layer.alert(data.message, {
                                closeBtn: 0
                            }, function (i) {
                                layer.close(i);
                            });
                        } else {
                            layer.alert(data.message);
                        }
                    });
            }
        },
        mounted: function () {
            if ($_GET('act') === 'reg') {
                this.act = 'reg';
            }
            var vm = this;
            document.onkeyup = function (e) {
                var code = parseInt(e.charCode || e.keyCode);
                if (code === 13) {
                    if (vm.act === 'reg') {
                        vm.reg();
                    } else {
                        vm.login();
                    }
                }
            }
        }
    });
</script>
</html>