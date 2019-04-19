window.$_GET = function (name) {
    return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(location.href) || [, ""])[1].replace(/\+/g, '%20')) || '';
};
window.$post = function (url, params1, params2, func) {
    var str = '';
    if (typeof (params1) === 'object') {
        for (var k in params1) {
            str += k + '=' + params1[k] + '&'
        }
    } else if (typeof (params1) === 'string') {
        str += params1 + '&'
    }
    if (typeof (params2) === 'object') {
        for (var k in params2) {
            str += k + '=' + params2[k] + '&'
        }
    } else if (typeof (params2) === 'string') {
        str += params2
    }
    var load;
    return $.ajax({
        type: "POST",
        url: url,
        data: (params1 instanceof FormData) ? params1 : str,
        beforeSend: function (request) {
            var token = document.head.querySelector('meta[name="csrf-token"]');
            if (token) {
                request.setRequestHeader("X-CSRF-TOKEN", token.content);
            } else {
                console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
            }
            load = layer.load({
                type: 2, shadeClose: false
            });
        },
        error: function (request) {
            if (request.status === 419) {
                layer.alert('页面已过期，请刷新页面！', {
                    closeBtn: 0
                }, function (i) {
                    window.location.reload();
                });
            } else {
                layer.close(load);
                layer.alert('网络出错了，请稍后再试！' + request.status + ' ' + request.statusText);
            }
        },
        success: function (ret) {
            layer.close(load);
        }
    });
};
Vue.prototype.$post = window.$post;

Vue.prototype.$message = function (message, type) {
    layer.alert(message);
};