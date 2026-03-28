@extends('admin.layout.index')
@section('title', '系统配置')
@section('content')
    <div class="page-header">
        <div>
            <h1>系统配置</h1>
            <p>管理站点文案、注册策略、邮件能力和保留前缀规则。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0 row">
        <div class="col-12 col-md-6 mt-2">
            <div class="card">
                <div class="card-header">
                    站点设置
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">站点名称</label>
                        <div class="col-sm-9">
                            <el-input v-model="webForm.web.name" placeholder="例如 KLDNS 管理平台"></el-input>
                            <div class="input_tips">用于首页、登录页、用户中心和邮件里的站点名称展示。</div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">首页标题</label>
                        <div class="col-sm-9">
                            <el-input v-model="webForm.web.title" placeholder="例如 KLDNS - 二级域名分发与解析管理平台"></el-input>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">网站关键词</label>
                        <div class="col-sm-9">
                            <el-input v-model="webForm.web.keywords" placeholder="例如 二级域名分发,DNS解析,域名管理平台"></el-input>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">网站描述</label>
                        <div class="col-sm-9">
                            <el-input v-model="webForm.web.description" type="textarea" :rows="3" placeholder="输入站点简介，用于搜索引擎描述和页面摘要"></el-input>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">首页公告</label>
                        <div class="col-sm-9">
                            <el-input v-model="webForm.html_header" type="textarea" :rows="5" placeholder="输入首页顶部公告内容，支持 HTML"></el-input>
                            <div class="input_tips">显示在首页内容区域顶部，适合放使用说明、服务范围和风险提示。</div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">用户公告</label>
                        <div class="col-sm-9">
                            <el-input v-model="webForm.html_home" type="textarea" :rows="5" placeholder="输入用户中心公告内容，支持 HTML"></el-input>
                            <div class="input_tips">显示在用户解析记录页顶部，适合放解析规范、值班说明和使用限制。</div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">首页链接</label>
                        <div class="col-sm-9">
                            <el-input v-model="webForm.index_urls" type="textarea" :rows="4" placeholder="输入首页顶部快捷链接"></el-input>
                            <div class="input_tips">
                                格式：链接名称|链接地址，一行一条。建议保留文档、帮助中心、GitHub 等正式入口。
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <el-button type="primary" @click="saveWeb">保存</el-button>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 mt-2">
            <div class="card">
                <div class="card-header">
                    用户配置
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">开启注册</label>
                        <div class="col-sm-9">
                            <el-select v-model="userForm.user.reg" style="width: 100%;">
                                <el-option :value="0" label="关闭注册"></el-option>
                                <el-option :value="1" label="开启注册"></el-option>
                            </el-select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">邮箱认证</label>
                        <div class="col-sm-9">
                            <el-select v-model="userForm.user.email" style="width: 100%;">
                                <el-option :value="0" label="不需要认证"></el-option>
                                <el-option :value="1" label="需要认证"></el-option>
                            </el-select>
                            <div class="input_tips">开启后，新用户注册成功仍需完成邮箱验证，适合正式开放场景。</div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">注册赠送积分</label>
                        <div class="col-sm-9">
                            <el-input v-model="userForm.user.point" type="number" placeholder="输入注册赠送积分"></el-input>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <el-button type="primary" @click="saveUser">保存</el-button>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 mt-2">
            <div class="card">
                <div class="card-header">
                    邮箱配置
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">SMTP服务器地址(host)</label>
                        <div class="col-sm-9">
                            <el-input v-model="mailForm.mail.host" placeholder="SMTP服务器地址"></el-input>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">SMTP服务器端口(port)</label>
                        <div class="col-sm-9">
                            <el-input v-model="mailForm.mail.port" placeholder="SMTP服务器端口"></el-input>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">加密类型</label>
                        <div class="col-sm-9">
                            <el-select v-model="mailForm.mail.encryption" style="width: 100%;">
                                <el-option value="ssl" label="SSL"></el-option>
                                <el-option value="tls" label="TLS"></el-option>
                                <el-option value="" label="不加密"></el-option>
                            </el-select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">邮箱账号</label>
                        <div class="col-sm-9">
                            <el-input v-model="mailForm.mail.username" placeholder="邮箱账号"></el-input>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">邮箱密码</label>
                        <div class="col-sm-9">
                            <el-input v-model="mailForm.mail.password" placeholder="邮箱密码"></el-input>
                            <div class="input_tips">多数邮箱需要填写 SMTP 授权码，不是网页登录密码。</div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">发送测试</label>
                        <div class="col-sm-9">
                            <el-input v-model="mailForm.mail.test" placeholder="输入一个邮箱地址"></el-input>
                            <div class="input_tips">保存时会向该地址发送测试邮件，用于验证 SMTP 配置是否可用。</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <el-button type="primary" @click="saveMail">保存</el-button>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 mt-2">
            <div class="card">
                <div class="card-header">
                    域名配置
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">保留前缀</label>
                        <div class="col-sm-9">
                            <el-input v-model="domainForm.reserve_domain_name" type="textarea" :rows="3" placeholder="输入你想保留的域名前缀"></el-input>
                            <div class="input_tips">多个前缀用英文逗号分隔，例如：www,m,api,admin。</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <el-button type="primary" @click="saveDomain">保存</el-button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('foot')
    <script>
        createVuePage('#vue', {
            data: function () {
                return {
                    webForm: {
                        action: 'config',
                        web: {
                            name: @json(config('sys.web.name')),
                            title: @json(config('sys.web.title')),
                            keywords: @json(config('sys.web.keywords')),
                            description: @json(config('sys.web.description'))
                        },
                        html_header: @json(config('sys.html_header')),
                        html_home: @json(config('sys.html_home')),
                        index_urls: @json(config('sys.index_urls'))
                    },
                    userForm: {
                        action: 'config',
                        user: {
                            reg: {{ (int) config('sys.user.reg', 0) }},
                            email: {{ (int) config('sys.user.email', 0) }},
                            point: {{ (int) config('sys.user.point', 0) }}
                        }
                    },
                    mailForm: {
                        action: 'config',
                        mail: {
                            host: @json(config('sys.mail.host','smtp.qq.com')),
                            port: @json(config('sys.mail.port','465')),
                            encryption: @json(config('sys.mail.encryption','ssl')),
                            username: @json(config('sys.mail.username')),
                            password: @json(config('sys.mail.password')),
                            test: @json(config('sys.mail.test','123456@qq.com'))
                        }
                    },
                    domainForm: {
                        action: 'config',
                        reserve_domain_name: @json(config('sys.reserve_domain_name'))
                    }
                };
            },
            methods: {
                submitConfig: function (payload) {
                    var vm = this;
                    this.$post("/admin/config", payload)
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
                saveWeb: function () {
                    this.submitConfig(this.webForm);
                },
                saveUser: function () {
                    this.submitConfig(this.userForm);
                },
                saveMail: function () {
                    this.submitConfig(this.mailForm);
                },
                saveDomain: function () {
                    this.submitConfig(this.domainForm);
                },
            }
        });
    </script>
@endsection
