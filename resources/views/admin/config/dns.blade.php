@extends('admin.layout.index')
@section('title', '接口配置')
@section('content')
    <div class="page-header">
        <div>
            <h1>DNS 接口配置</h1>
            <p>接入第三方解析平台 API，供系统同步域名资源、线路信息和记录操作。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                域名解析平台接口配置
                <el-button class="float-right" type="primary" size="small" @click="openStoreModal">添加</el-button>
            </div>
            <div class="card-body">
                <el-table v-cloak border stripe :data="data.data || []" style="width: 100%">
                    <el-table-column label="平台" width="140">
                        <template v-slot:default="{ row }">
                            <span v-text="getDnsLabel(row.dns)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column label="配置" min-width="320">
                        <template v-slot:default="{ row }">
                            <div style="white-space: pre-line;" v-text="formatConfigMasked(row)"></div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="添加时间" min-width="170"></el-table-column>
                    <el-table-column label="操作" width="160">
                        <template v-slot:default="{ row }">
                            <div class="table-actions">
                                <el-button size="small" @click="editStore(row)">编辑</el-button>
                                <el-button size="small" type="danger" @click="del(row.dns)">删除</el-button>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <div class="card-footer pb-0 text-center">
                @include('admin.layout.pagination')
            </div>
        </div>
        <el-dialog v-model="storeDialogVisible" :title="storeForm.dns && storeForm.dns !== '0' ? '编辑接口配置' : '添加接口配置'" width="min(640px, calc(100vw - 24px))" class="app-dialog" top="5vh">
            <div class="form-group">
                <label>解析平台</label>
                <el-select v-model="storeForm.dns" style="width: 100%;">
                    <el-option value="0" label="请选择域名解析平台"></el-option>
                    <el-option v-for="(config, dns) in dnsList" :key="dns" :value="dns" :label="getDnsLabel(dns)"></el-option>
                </el-select>
            </div>
            <template v-if="storeForm.dns !== '0'">
                <div class="form-group" v-for="(config, index) in dnsList[storeForm.dns]" :key="index">
                    <label v-text="config.name"></label>
                    <el-input
                        v-if="config.type === 'textarea'"
                        v-model="storeForm.config[config.name]"
                        type="textarea"
                        :rows="config.rows || 6"
                        :placeholder="config.placeholder">
                    </el-input>
                    <el-input
                        v-else
                        v-model="storeForm.config[config.name]"
                        :placeholder="config.placeholder">
                    </el-input>
                    <div class="input_tips" v-if="config.tips" v-html="config.tips"></div>
                    <div class="input_tips" v-if="data.data && data.data.some(function (row) { return row.dns === storeForm.dns; })">留空则不修改当前配置</div>
                </div>
            </template>
            <template v-slot:footer>
                <el-button @click="storeDialogVisible = false">关闭</el-button>
                <el-button type="primary" @click="submitStore">保存</el-button>
            </template>
        </el-dialog>
    </div>
@endsection
@section('foot')
    <script>
        createVuePage('#vue', {
            data: function () {
                return {
                    search: {
                        page: 1
                    },
                    data: {},
                    storeInfo: {},
                    dnsList: [],
                    dnsLabels: @json(\App\Klsf\Dns\Helper::getLabelMap()),
                    storeDialogVisible: false,
                    storeForm: {
                        action: 'store',
                        dns: '0',
                        config: {}
                    }
                };
            },
            methods: {
                getDnsLabel: function (dns) {
                    return this.dnsLabels[dns] || dns || '-';
                },
                formatConfigMasked: function (row) {
                    if (!row || !row.config_masked) {
                        return '-';
                    }

                    return String(row.config_masked).trim() || '-';
                },
                openStoreModal: function () {
                    this.storeForm = { action: 'store', dns: '0', config: {} };
                    this.storeDialogVisible = true;
                },
                editStore: function (row) {
                    this.storeInfo = Object.assign({}, row);
                    this.storeForm = { action: 'store', dns: row.dns, config: {} };
                    this.storeDialogVisible = true;
                },
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    this.$post("/admin/config/dns", vm.search, {action: 'select'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.data = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                getAllDns: function () {
                    var vm = this;
                    this.$post("/admin/config/dns", vm.search, {action: 'all'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.dnsList = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                submitStore: function () {
                    var vm = this;
                    var formData = new FormData();
                    formData.append('action', 'store');
                    formData.append('dns', this.storeForm.dns);
                    Object.keys(this.storeForm.config || {}).forEach(function (key) {
                        formData.append('config[' + key + ']', vm.storeForm.config[key]);
                    });
                    this.$post("/admin/config/dns", formData)
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                vm.storeDialogVisible = false;
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
                del: function (id) {
                    var vm = this;
                    this.$confirmAction('确认删除这组 DNS 平台配置吗？', function () {
                        return vm.$post("/admin/config/dns", {action: 'delete', dns: id})
                            .then(function (data) {
                                if (data.status === 0) {
                                    vm.getList();
                                    vm.$message(data.message, 'success');
                                } else {
                                    vm.$message(data.message, 'error');
                                }
                            });
                    });
                },
            },
            mounted: function () {
                this.getList();
                this.getAllDns();
            }
        });
    </script>
@endsection
