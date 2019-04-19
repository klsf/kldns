<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{$status==200?'提示':'错误提示'}}</title>
    <!-- Styles -->
    <style>
        html, body {
            background-color: #fff;
            color: #636b6f;
            font-family: 'Nunito', sans-serif;
            font-weight: 100;
            height: 100vh;
            margin: 0;
        }

        .full-height {
            height: 100vh;
        }

        .flex-center {
            align-items: center;
            display: flex;
            justify-content: center;
        }

        .position-ref {
            position: relative;
        }

        .code {
            border-right: 2px solid;
            font-size: 26px;
            padding: 0 15px 0 15px;
            text-align: center;
        }

        .message {
            font-size: 18px;
            text-align: center;
        }

        a {
            text-decoration: none;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="flex-center position-ref full-height">
    <div>
        <div class="flex-center">
            <div class="code">{{$status}}</div>
            <div class="message" style="padding: 10px;">
                {{ $error }}
            </div>
        </div>
        <div style="text-align: center;margin-top: 15px">
            <a href="{{ ($url && $url!='S')?$url:'javascript:history.back(-1)' }}"
               id="url">点击跳转@if(isset($url) && $url!='S')(<span id="s">4</span>)@endif</a>
            <a style="margin-left: 10px" href="/">返回首页</a>
        </div>
    </div>
</div>
<script type="text/javascript">
    function url() {
        var s = document.getElementById("s").innerText;
        s = Number(s);
        s--;
        if (s < 1) {
            document.getElementById("url").click();
        } else {
            document.getElementById("s").innerText = s;
            setTimeout(url, 1000);
        }
    }

    @if(isset($url) && $url!='S')
    url();
    @endif
</script>
</body>
</html>
