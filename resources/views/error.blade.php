<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status == 200 ? '提示' : '错误提示' }}</title>
    <style>
        :root {
            --bg: #07111f;
            --panel: rgba(10, 22, 40, 0.9);
            --line: rgba(136, 197, 255, 0.2);
            --text: #e7f1ff;
            --muted: #91a6c3;
            --primary: #3ec5ff;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            min-height: 100vh;
            margin: 0;
            font-family: "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(62, 197, 255, 0.18), transparent 24%),
                linear-gradient(180deg, #081120 0%, #09172b 48%, #06101d 100%);
            color: var(--text);
        }

        body {
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .error-card {
            width: min(760px, 100%);
            padding: 32px;
            border-radius: 24px;
            background: var(--panel);
            border: 1px solid var(--line);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.35);
        }

        .label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(62, 197, 255, 0.1);
            color: #a7e8ff;
            font-size: 14px;
        }

        .headline {
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 22px 0 12px;
        }

        .code {
            min-width: 92px;
            padding-right: 20px;
            border-right: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 40px;
            font-weight: 800;
            text-align: center;
        }

        .message {
            font-size: 18px;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border-radius: 14px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .primary {
            background: linear-gradient(135deg, var(--primary), #1598db);
            border: 0;
        }

        @media (max-width: 640px) {
            .headline {
                flex-direction: column;
                align-items: flex-start;
            }

            .code {
                border-right: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.12);
                padding-right: 0;
                padding-bottom: 12px;
            }
        }
    </style>
</head>
<body>
<div class="error-card">
    <span class="label">{{ $status == 200 ? '操作提示' : '访问异常' }}</span>
    <div class="headline">
        <div class="code">{{ $status }}</div>
        <div class="message">
                {{ $error }}
        </div>
    </div>
    <div class="actions">
        <a class="primary" href="{{ ($url && $url != 'S') ? $url : 'javascript:history.back(-1)' }}"
           id="url">继续跳转@if(isset($url) && $url != 'S')（<span id="s">4</span>）@endif</a>
        <a href="/">返回首页</a>
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
