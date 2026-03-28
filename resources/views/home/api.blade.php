@extends('home.layout.index')
@section('title', '开放 API')
@section('content')
    <div class="page-header">
        <div>
            <h1>开放 API</h1>
            <p>为当前账号创建 API 令牌，供外部程序调用域名列表、解析记录和审核状态接口。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0 row">
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header">
                    API 令牌
                    <el-button class="float-right" type="primary" size="small" @click="openCreateDialog">新建令牌</el-button>
                </div>
                <div class="card-body">
                    <el-table v-cloak border stripe :data="data.data || []" style="width: 100%">
                        <el-table-column prop="id" label="ID" width="80"></el-table-column>
                        <el-table-column prop="name" label="名称" min-width="160"></el-table-column>
                        <el-table-column prop="token_hint" label="令牌标识" min-width="180"></el-table-column>
                        <el-table-column prop="last_used_at_text" label="最近使用" min-width="170"></el-table-column>
                        <el-table-column label="过期时间" min-width="170">
                            <template v-slot:default="{ row }">
                                <span v-text="row.expires_at_text"></span>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="100">
                            <template v-slot:default="{ row }">
                                <div class="table-actions">
                                    <el-button size="small" type="danger" @click="del(row.id)">删除</el-button>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
                <div class="card-footer pb-0 text-center">
                    @include('admin.layout.pagination')
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5 mt-3 mt-lg-0">
            <div class="card">
                <div class="card-header">接口说明</div>
                <div class="card-body">
                    <p><strong>认证方式：</strong>请求头携带 <code>Authorization: Bearer 你的令牌</code></p>
                    <p><strong>基础路径：</strong><code>/api/v1</code></p>
                    <p><strong>可用接口：</strong></p>
                    <ul>
                        <li><code>GET /api/v1/domains</code> 获取当前用户可用主域</li>
                        <li><code>GET /api/v1/records</code> 获取当前用户解析记录</li>
                        <li><code>GET /api/v1/reviews</code> 获取当前用户审核记录</li>
                        <li><code>POST /api/v1/records</code> 新增解析记录</li>
                        <li><code>PUT /api/v1/records/{id}</code> 修改解析记录</li>
                        <li><code>DELETE /api/v1/records/{id}</code> 删除解析记录</li>
                    </ul>
                    <div class="input_tips">如果主域开启了人工审核，API 调用新增、修改、删除时也会先进入审核流程。</div>
                </div>
            </div>
        </div>
        <el-dialog v-model="createDialogVisible" title="新建 API 令牌" width="min(520px, calc(100vw - 24px))" class="app-dialog" top="5vh">
            <div class="form-group">
                <label>令牌名称</label>
                <el-input v-model="createForm.name" placeholder="例如：自动化脚本、CI 发布机"></el-input>
            </div>
            <div class="form-group">
                <label>有效期</label>
                <el-select v-model="createForm.days" style="width: 100%;">
                    <el-option :value="0" label="不过期"></el-option>
                    <el-option :value="7" label="7 天"></el-option>
                    <el-option :value="30" label="30 天"></el-option>
                    <el-option :value="90" label="90 天"></el-option>
                </el-select>
            </div>
            <template v-slot:footer>
                <el-button @click="createDialogVisible = false">关闭</el-button>
                <el-button type="primary" @click="submitCreate">创建</el-button>
            </template>
        </el-dialog>
        <el-dialog v-model="tokenDialogVisible" title="令牌创建成功" width="min(560px, calc(100vw - 24px))" class="app-dialog" top="5vh">
            <div class="form-group">
                <label>令牌明文</label>
                <el-input v-model="plainToken" type="textarea" :rows="4" readonly></el-input>
                <div class="input_tips">该令牌只显示一次，请立即保存。</div>
            </div>
            <template v-slot:footer>
                <el-button type="primary" @click="tokenDialogVisible = false">我已保存</el-button>
            </template>
        </el-dialog>
    </div>
@endsection
@section('foot')
    <script>
        createVuePage('#vue', {
            data: function () {
                return {
                    search: {page: 1},
                    data: {},
                    createDialogVisible: false,
                    tokenDialogVisible: false,
                    plainToken: '',
                    createForm: {
                        name: '',
                        days: 30
                    }
                };
            },
            methods: {
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    vm.$post('/home', vm.search, {action: 'tokenList'}).then(function (data) {
                        if (data.status === 0) {
                            vm.data = data.data;
                        } else {
                            vm.$message(data.message, 'error');
                        }
                    });
                },
                openCreateDialog: function () {
                    this.createForm = {name: '', days: 30};
                    this.createDialogVisible = true;
                },
                submitCreate: function () {
                    var vm = this;
                    vm.$post('/home', Object.assign({action: 'tokenCreate'}, vm.createForm)).then(function (data) {
                        if (data.status === 0) {
                            vm.createDialogVisible = false;
                            vm.plainToken = data.data.token || '';
                            vm.tokenDialogVisible = true;
                            vm.getList();
                            vm.$message(data.message, 'success');
                        } else {
                            vm.$message(data.message, 'error');
                        }
                    });
                },
                del: function (id) {
                    var vm = this;
                    vm.$confirmAction('确认删除这枚 API 令牌吗？', function () {
                        return vm.$post('/home', {action: 'tokenDelete', id: id}).then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                    });
                }
            },
            mounted: function () {
                this.getList();
            }
        });
    </script>
@endsection
