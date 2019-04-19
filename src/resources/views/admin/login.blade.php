<!doctype html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>后台登录 - {{ config('app.name') }}</title>
    <meta name="keywords" content="{{ config('sys.web.keywords') }}"/>
    <meta name="description" content="{{ config('sys.web.description') }}"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.8.1/css/all.min.css" rel="stylesheet">
</head>
<body>
<div style="">
    <div id="content">
        <div class="col-12 col-md-4 offset-md-4 mt-3 mt-sm-5">
            <div class="card mb-3">
                <div class="card-header text-white bg-info ">后台登录</div>
                <div class="card-body">
                    <form id="form-login">
                        <div class="form-group row">
                            <label for="staticEmail" class="col-3 col-form-label">用户名</label>
                            <div class="col-9">
                                <input type="text" name="username" class="form-control" placeholder="输入管理员账号">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-3 col-form-label">密码</label>
                            <div class="col-9">
                                <input type="password" name="password" class="form-control" placeholder="输入管理员密码">
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
                        <div class="form-group row">
                            <label for="staticEmail" class="col-3 col-form-label"></label>
                            <div class="col-9">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                    <label class="form-check-label" for="remember">记住登录</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group text-center">
                            <a class="btn btn-info text-white" @click="login"> 登 录 </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.6.10/vue.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/layer/2.3/layer.js"></script>
<script src="/js/main.js"></script>
<script>
    new Vue({
        el: '#content',
        data: {},
        methods: {
            login: function () {
                var vm = this;
                this.$post('/admin/login', $("#form-login").serialize())
                    .then(function (data) {
                        $("#code").click();
                        if (data.status === 0) {
                            location.href = data.go ? data.go : "{{ request()->get('go','/') }}";
                        } else {
                            layer.alert(data.message);
                        }
                    });
            },
        },
        mounted: function () {
            var vm = this;
            document.onkeyup = function (e) {
                var code = parseInt(e.charCode || e.keyCode);
                if (code === 13) {
                    vm.login();
                }
            }
        }
    });
</script>
</html>