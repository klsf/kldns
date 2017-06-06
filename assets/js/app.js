$.klsf = {
    layerAlert: function (title, message, func) {
        var layerIndex;
        func = (typeof func == 'function') ? func : function () {
            layer.close(layerIndex);
        }
        layerIndex = layer.alert(message, {
            title: title,
            skin: 'layui-layer-lan',
            closeBtn: 0,
            anim: 1,
            btnAlign: 'c'
        }, func);
    },
    ajaxUrl: '/index.php/index/ajax/',
    adminAjaxUrl: '/index.php/index/admin_ajax/',
    tipType: function (placement) {
        return function (msg, o, cssctl) {
            //msg：提示信息;
            //o:{obj:*,type:*,curform:*}, obj指向的是当前验证的表单元素（或表单对象），type指示提示的状态，值为1、2、3、4， 1：正在检测/提交数据，2：通过验证，3：验证失败，4：提示ignore状态, curform为当前form对象;
            //cssctl:内置的提示信息样式控制函数，该函数需传入两个参数：显示提示信息的对象 和 当前提示的状态（既形参o中的type）;
            if (!o.obj.is("form")) {//验证表单元素时o.obj为该表单元素，全部验证通过提交表单时o.obj为该表单对象;
                o.obj.tooltip("dispose");
                o.obj.tooltip({
                    placement: placement,
                    trigger: 'manual',
                    title: msg,
                });
                if (o.type == 2) {
                    o.obj.tooltip("hide");
                } else {
                    o.obj.tooltip("show");
                }
            }
        }
    },
    Validform: function () {
        $(".ajaxForm").Validform({
            tiptype: $.klsf.tipType(),
            beforeSubmit: function (form) {
                var load = layer.load(3);
                jQuery(form).ajaxSubmit({
                    dataType: "json",
                    success: function (json) {
                        layer.close(load);
                        $.klsf.layerAlert("信息", json.message);
                        if (json.code == 0) {
                            $('.modal').modal("hide");
                        }
                    }
                });
                return false;
            },
        });
    },
    getUserGroup: function (group) {
        switch (group) {
            case 1:
                return "<span class='badge badge-default'>普通</span>";
            case 2:
                return "<span class='badge' style='background: #FFD700'>金</span>";
            case 4:
                return "<span class='badge' style='background: #ceb086'>木</span>";
            case 8:
                return "<span class='badge' style='background: #46bce0'>水</span>";
            case 16:
                return "<span class='badge' style='background: #d81111'>火</span>";
            case 32:
                return "<span class='badge' style='background: #fc6501'>土</span>";
            default:
                return "<span class='badge badge-default'>普通</span>";

        }
    },
    findObjIndex: function (obj, key, value) {
        for (var i = 0; i < obj.length; i++) {
            if (obj[i][key] === value) {
                return i;
            }
        }
        return -1;
    },
}
var $_GET = (function () {
    var url = window.document.location.href.toString();
    var u = url.split("?");
    if (typeof(u[1]) == "string") {
        u = u[1].split("&");
        var get = {};
        for (var i in u) {
            var j = u[i].split("=");
            get[j[0]] = j[1];
        }
        return get;
    } else {
        return {};
    }
})();

var pageModel;
var pathName = window.location.pathname;
if (pathName.indexOf('control') > 0) {
    pageModel = {
        //用户控制中心
        el: '#app',
        data: {
            domainList: null,
            recordList: null,
            recordInfo: {},
            coin: 0,
            s_domain_id: 0,
            s_rr: null,
            s_type: 0,
            s_value: null,
            lineList: {},
            lineName: null,
            selectDomainId: null,
            page: 1,
        },
        created: function () {
            this.coin = $("#user_coin").attr('data-coin');
            this.getDomainList();
            this.getRecordList();
        },
        methods: {
            getDomainList: function () {
                this.$http.get($.klsf.ajaxUrl + "domainList.html").then(function (response) {
                    this.domainList = response.body;
                    if (this.domainList.length > 0) {
                        this.lineList = this.domainList[0].lines;
                        this.selectDomainId = this.domainList[0].domain_id;
                    }
                }, function () {
                    $.klsf.layerAlert("错误提醒", "加载域名列表失败");
                });
            },
            getRecordList: function () {
                var data = {
                    page: this.page,
                    domain_id: this.s_domain_id,
                    rr: this.s_rr,
                    type: this.s_type,
                    value: this.s_value,
                }
                var load = layer.load(3);
                this.$http.post($.klsf.ajaxUrl + "record/action/list.html", data).then(function (response) {
                    layer.close(load);
                    this.recordList = response.body.list;
                    this.coin = response.body.coin;

                    $('#pagination').twbsPagination({
                        startPage: response.body.page,
                        totalPages: response.body.totalPage,
                        visiblePages: 5,
                        onPageClick: function (event, page) {
                            vm.page = page;
                            vm.getRecordList();
                        }
                    });
                }, function () {
                    layer.close(load);
                    $.klsf.layerAlert("错误提醒", "加载记录列表失败");
                });
            },
            updateRecord: function (index) {
                var info = this.recordList[index];
                var i = $.klsf.findObjIndex(this.domainList, 'domain_id', info.domain_id);
                if (i >= 0) {
                    info.lineList = this.domainList[i].lines;
                }
                this.recordInfo = info;
            },
            delRecord: function (id) {
                if (!confirm("确认删除这条解析？")) return false;
                var load = layer.load(3);
                this.$http.post($.klsf.ajaxUrl + "record/action/del.html", {record_id: id}, {emulateJSON: true}).then(function (response) {
                    layer.close(load);
                    this.getRecordList();
                    $.klsf.layerAlert("提示", response.body.message);
                }, function () {
                    layer.close(load);
                    $.klsf.layerAlert("错误提醒", "请稍后再试！");
                });
            },
            selectDomain: function () {
                var i = $.klsf.findObjIndex(this.domainList, 'domain_id', this.selectDomainId);
                if (i >= 0) {
                    this.lineList = this.domainList[i].lines;
                }
            },
        }
    }
} else if (pathName.indexOf('domain_list') > 0) {
    pageModel = {
        //域名列表
        el: '#app',
        data: {
            domainList: null,
            apiDomainList: null,
            domainInfo: {},
            selectDomain: null,
            s_dns: 0,
            s_domain: null,
            page: 1,
        },
        created: function () {
            this.getDomainList();
        },
        methods: {
            getDomainList: function () {
                var data = {
                    page: this.page,
                    dns: this.s_dns,
                    domain: this.s_domain,
                }
                var load = layer.load(3);
                this.$http.post($.klsf.adminAjaxUrl + "domain/action/list.html", data).then(function (response) {
                    layer.close(load);
                    this.domainList = response.body.list;

                    $('#pagination').twbsPagination({
                        startPage: response.body.page,
                        totalPages: response.body.totalPage,
                        visiblePages: 5,
                        onPageClick: function (event, page) {
                            vm.page = page;
                            vm.getRecordList();
                        }
                    });
                }, function () {
                    layer.close(load);
                    $.klsf.layerAlert("错误提醒", "加载域名列表失败");
                });
            },
            updateDomain: function (index) {
                this.domainInfo = this.domainList[index];
            },
            delDomain: function (id) {
                if (!confirm("删除域名并不会删除解析记录，确认删除此域名？")) return false;
                var load = layer.load(3);
                this.$http.post($.klsf.adminAjaxUrl + "domain/action/del.html", {domain_id: id}, {emulateJSON: true}).then(function (response) {
                    layer.close(load);
                    this.getDomainList();
                    $.klsf.layerAlert("提示", response.body.message);
                }, function () {
                    layer.close(load);
                    $.klsf.layerAlert("错误提醒", "请稍后再试！");
                });
            },
            power: function (power) {
                var html = "";
                for (var i = 0; i < 6; i++) {
                    var p = Math.pow(2, i);
                    if (power & p) {
                        html += " " + $.klsf.getUserGroup(p);
                    }
                }
                return html;
            },
            getApiDomainList: function () {
                var load = layer.load(3);
                this.$http.post($.klsf.adminAjaxUrl + "domain/action/apiList.html", {dns: $("#addDomainForm select[name='dns']").val()}, {emulateJSON: true}).then(function (response) {
                    layer.close(load);
                    if (response.body.code == 0) {
                        this.selectDomain = response.body.list[0].DomainName;
                        this.apiDomainList = response.body.list;
                    } else {
                        $.klsf.layerAlert("错误提醒", response.body.message);
                    }
                }, function () {
                    layer.close(load);
                    $.klsf.layerAlert("错误提醒", "请稍后再试！");
                });
            },
            select: function () {
                this.selectDomain = $("#addDomainForm select[name='domain_id']").find("option:selected").text();
            },
        }
    }
} else if (pathName.indexOf('record_list') > 0) {
    pageModel = {
        //记录列表
        el: '#app',
        data: {
            domainList: null,
            recordList: null,
            recordInfo: {},
            s_domain_id: $_GET['domain_id'] ? $_GET['domain_id'] : 0,
            s_uid: $_GET['uid'],
            page: 1,
        },
        created: function () {
            this.getRecordList();
            this.getDomainList();
        },
        methods: {
            getDomainList: function () {
                this.$http.get($.klsf.adminAjaxUrl + "domain/action/list.html", {
                    page: 1,
                    pageSize: 100
                }).then(function (response) {
                    this.domainList = response.body.list;
                }, function () {
                    $.klsf.layerAlert("错误提醒", "加载域名列表失败");
                });
            },
            getRecordList: function () {
                var data = {
                    page: this.page,
                    domain_id: this.s_domain_id,
                    uid: this.s_uid,
                }
                var load = layer.load(3);
                this.$http.post($.klsf.adminAjaxUrl + "record/action/list.html", data).then(function (response) {
                    layer.close(load);
                    this.recordList = response.body.list;

                    $('#pagination').twbsPagination({
                        startPage: response.body.page,
                        totalPages: response.body.totalPage,
                        visiblePages: 5,
                        onPageClick: function (event, page) {
                            vm.page = page;
                            vm.getRecordList();
                        }
                    });
                }, function () {
                    layer.close(load);
                    $.klsf.layerAlert("错误提醒", "加载记录列表失败");
                });
            },
            updateRecord: function (index) {
                this.recordInfo = this.recordList[index];
            },
            delRecord: function (id) {
                if (!confirm("确认删除这条解析？")) return false;
                var load = layer.load(3);
                this.$http.post($.klsf.adminAjaxUrl + "record/action/del.html", {record_id: id}, {emulateJSON: true}).then(function (response) {
                    layer.close(load);
                    this.getRecordList();
                    $.klsf.layerAlert("提示", response.body.message);
                }, function () {
                    layer.close(load);
                    $.klsf.layerAlert("错误提醒", "请稍后再试！");
                });
            },
        }
    }
} else if (pathName.indexOf('user_list') > 0) {
    pageModel = {
        //用户列表
        el: '#app',
        data: {
            userList: null,
            userInfo: {},
            s_group: 0,
            s_user: null,
            s_uid: $_GET['uid'],
            page: 1,
        },
        created: function () {
            this.getUserList();
        },
        methods: {
            getUserList: function () {
                var data = {
                    page: this.page,
                    group: this.s_group,
                    user: this.s_user,
                    uid: this.s_uid,
                }
                var load = layer.load(3);
                this.$http.post($.klsf.adminAjaxUrl + "user/action/list.html", data).then(function (response) {
                    layer.close(load);
                    this.userList = response.body.list;

                    $('#pagination').twbsPagination({
                        startPage: response.body.page,
                        totalPages: response.body.totalPage,
                        visiblePages: 5,
                        onPageClick: function (event, page) {
                            vm.page = page;
                            vm.getUserList();
                        }
                    });
                }, function () {
                    layer.close(load);
                    $.klsf.layerAlert("错误提醒", "加载用户列表失败");
                });
            },
            updateUser: function (index) {
                this.userInfo = this.userList[index];
            },
            delUser: function (id) {
                if (!confirm("删除用户不会删除其解析记录，确认删除用户？")) return false;
                var load = layer.load(3);
                this.$http.post($.klsf.adminAjaxUrl + "user/action/del.html", {uid: id}, {emulateJSON: true}).then(function (response) {
                    layer.close(load);
                    this.getUserList();
                    $.klsf.layerAlert("提示", response.body.message);
                }, function () {
                    layer.close(load);
                    $.klsf.layerAlert("错误提醒", "请稍后再试！");
                });
            },
            getUserGroup: function (group) {
                return $.klsf.getUserGroup(group);
            },
        }
    }
} else {
    pageModel = {
        //登录页面
        el: '#app',
        data: {
            nav_title: '用户登录',
        },
        methods: {
            navSwitch: function (e) {
                $(".tooltip").tooltip("dispose");
                this.nav_title = e.target.innerHTML;
            },
        }
    }
}
var vm = new Vue(pageModel);

$(document).ready(function () {
    $.klsf.Validform();
    $(document).pjax('[data-pjax] a, a[data-pjax]', '#pjax-container', {});
    $(document).on('pjax:complete', function () {
        $(".tooltip").tooltip("dispose");//清楚所有tips
        //$.klsf.Validform();//重新绑定Validform
    });
});


/*用户登录注册页面--START*/
$("#loginForm").Validform({
    tiptype: $.klsf.tipType(),
    beforeSubmit: function (form) {
        var load = layer.load(3);
        jQuery(form).ajaxSubmit({
            dataType: "json",
            success: function (json) {
                layer.close(load);
                if (json.code == 0) {
                    $.klsf.layerAlert("登录成功", json.info.user + '，欢迎回来！', function () {
                        window.location.href = "/index.php/index/index/control.html";
                    });
                } else {
                    $.klsf.layerAlert("登录失败", json.message);
                }
            }
        });
        return false;
    },
});
$("#adminLoginForm").Validform({
    tiptype: $.klsf.tipType(),
    beforeSubmit: function (form) {
        var load = layer.load(3);
        jQuery(form).ajaxSubmit({
            dataType: "json",
            success: function (json) {
                layer.close(load);
                if (json.code == 0) {
                    $.klsf.layerAlert("登录成功", '管理员，您好！点击确定进入管理后台。', function () {
                        window.location.href = "/index.php/index/admin/index.html";
                    });
                } else {
                    $.klsf.layerAlert("登录失败", json.message);
                }
            }
        });
        return false;
    },
});
/*用户登录注册页面--END*/


/*用户控制中心--START*/
$(".recordForm").Validform({
    tiptype: $.klsf.tipType(),
    beforeSubmit: function (form) {
        var load = layer.load(3);
        jQuery(form).ajaxSubmit({
            dataType: "json",
            success: function (json) {
                layer.close(load);
                $.klsf.layerAlert("信息", json.message);
                if (json.code == 0) {
                    vm.getRecordList();
                    $('.modal').modal("hide");
                }
            }
        });
        return false;
    },
});
/*用户控制中心--END*/


/*管理后端--START*/
$(".domainForm").Validform({
    tiptype: $.klsf.tipType(),
    beforeSubmit: function (form) {
        var load = layer.load(3);
        jQuery(form).ajaxSubmit({
            dataType: "json",
            success: function (json) {
                layer.close(load);
                $.klsf.layerAlert("信息", json.message);
                if (json.code == 0) {
                    vm.getDomainList();
                    $('.modal').modal("hide");
                }
            }
        });
        return false;
    },
});
$(".userForm").Validform({
    tiptype: $.klsf.tipType(),
    beforeSubmit: function (form) {
        var load = layer.load(3);
        jQuery(form).ajaxSubmit({
            dataType: "json",
            success: function (json) {
                layer.close(load);
                $.klsf.layerAlert("信息", json.message);
                if (json.code == 0) {
                    vm.getUserList();
                    $('.modal').modal("hide");
                }
            }
        });
        return false;
    },
});

/*管理后端--END*/

