@extends('admin.layout.index')
@section('title', '域名列表')
@section('content')
    <div class="page-header">
        <div>
            <h1>域名列表</h1>
            <p>把已接入平台的主域名加入资源池，配置开放用户组、消耗积分和对外说明。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                域名列表
                <el-button class="float-right" type="primary" size="small" @click="openAddModal">添加</el-button>
            </div>
            <div class="card-body">
                <el-table v-cloak border stripe :data="data.data || []" style="width: 100%">
                    <el-table-column prop="did" label="编号" width="80"></el-table-column>
                    <el-table-column label="平台" width="140">
                        <template v-slot:default="{ row }">
                            <span v-text="getDnsLabel(row.dns)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="domain_id" label="DomainId" width="120"></el-table-column>
                    <el-table-column prop="domain" label="域名" min-width="180"></el-table-column>
                    <el-table-column prop="beian_text" label="备案状态" width="100"></el-table-column>
                    <el-table-column prop="record_type_text" label="解析类型" min-width="200"></el-table-column>
                    <el-table-column label="用户组" min-width="180">
                        <template v-slot:default="{ row }">
                            <span v-html="getDomainGroups(row.groups)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="review_mode_text" label="审核模式" width="110"></el-table-column>
                    <el-table-column prop="point" label="消耗积分" width="110"></el-table-column>
                    <el-table-column label="介绍" min-width="220">
                        <template v-slot:default="{ row }">
                            <span v-html="row.desc"></span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="添加时间" min-width="170"></el-table-column>
                    <el-table-column label="操作" width="160">
                        <template v-slot:default="{ row }">
                            <div class="table-actions">
                                <el-button size="small" @click="openUpdateModal(row)">修改</el-button>
                                <el-button size="small" type="danger" @click="del(row.did)">删除</el-button>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <div class="card-footer pb-0 text-center">
                @include('admin.layout.pagination')
            </div>
        </div>
        <el-dialog v-model="addDialogVisible" title="添加域名" width="min(640px, calc(100vw - 24px))" class="app-dialog" top="5vh">
            <div class="form-group">
                <label>解析平台</label>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <el-select v-model="addForm.dns" style="width: 100%;">
                        <el-option value="0" label="请选择域名解析平台"></el-option>
                        @foreach(\App\Models\DnsConfig::whereIn('dns', \App\Klsf\Dns\Helper::getList())->get() as $dns)
                            <el-option value="{{ $dns->dns }}" label="{{ \App\Klsf\Dns\Helper::getLabel($dns->dns) }}"></el-option>
                        @endforeach
                    </el-select>
                    <el-button type="success" size="small" class="toolbar-action" @click="getDomainList">获取</el-button>
                </div>
            </div>
            <div class="form-group">
                <label>选择域名</label>
                <el-select v-model="addForm.domain" style="width: 100%;">
                    <el-option v-for="domain in domainList" :key="domain.domain_id" :value="domain.domain_id + ',' + domain.domain" :label="domain.domain"></el-option>
                </el-select>
            </div>
            <div class="form-group">
                <label>用户组</label>
                <el-checkbox-group v-model="addForm.groups">
                    <el-checkbox label="0">所有组</el-checkbox>
                    @foreach(\App\Models\UserGroup::where('gid','>',99)->get() as $group)
                        <el-checkbox label="{{ $group->gid }}">{{ $group->name }}</el-checkbox>
                    @endforeach
                </el-checkbox-group>
                <div class="input_tips">选择可使用此域名的用户组</div>
            </div>
            <div class="form-group">
                <label>解析类型</label>
                <el-checkbox-group v-model="addForm.record_types">
                    @foreach(\App\Models\Domain::getRecordTypeOptions() as $type => $label)
                        <el-checkbox label="{{ $type }}">{{ $label }}</el-checkbox>
                    @endforeach
                </el-checkbox-group>
                <div class="input_tips">选择当前主域允许用户使用的解析类型</div>
            </div>
            <div class="form-group">
                <label>消耗积分</label>
                <el-input v-model="addForm.point" type="number" placeholder="输入用户添加每条解析消耗积分"></el-input>
            </div>
            <div class="form-group">
                <label>备案状态</label>
                <el-select v-model="addForm.beian" style="width: 100%;">
                    <el-option :value="0" label="未备案"></el-option>
                    <el-option :value="1" label="已备案"></el-option>
                </el-select>
                <div class="input_tips">用于前台展示当前主域的备案状态。</div>
            </div>
            <div class="form-group">
                <label>审核模式</label>
                <el-select v-model="addForm.review_mode" style="width: 100%;">
                    <el-option :value="0" label="自动通过"></el-option>
                    <el-option :value="1" label="人工审核"></el-option>
                </el-select>
                <div class="input_tips">开启后，用户新增、修改、删除解析都会先进入审核列表。</div>
            </div>
            <div class="form-group">
                <label>域名介绍</label>
                <el-input v-model="addForm.desc" type="textarea" :rows="5" placeholder="输入域名介绍内容"></el-input>
            </div>
            <template v-slot:footer>
                <el-button @click="addDialogVisible = false">关闭</el-button>
                <el-button type="primary" @click="submitAdd">添加</el-button>
            </template>
        </el-dialog>
        <el-dialog v-model="updateDialogVisible" title="编辑域名" width="min(640px, calc(100vw - 24px))" class="app-dialog" top="5vh">
            <div class="form-group">
                <label>用户组</label>
                <el-checkbox-group v-model="updateForm.groups">
                    <el-checkbox label="0">所有组</el-checkbox>
                    @foreach(\App\Models\UserGroup::where('gid','>',99)->get() as $group)
                        <el-checkbox label="{{ $group->gid }}">{{ $group->name }}</el-checkbox>
                    @endforeach
                </el-checkbox-group>
                <div class="input_tips">选择可使用此域名的用户组</div>
            </div>
            <div class="form-group">
                <label>解析类型</label>
                <el-checkbox-group v-model="updateForm.record_types">
                    @foreach(\App\Models\Domain::getRecordTypeOptions() as $type => $label)
                        <el-checkbox label="{{ $type }}">{{ $label }}</el-checkbox>
                    @endforeach
                </el-checkbox-group>
                <div class="input_tips">选择当前主域允许用户使用的解析类型</div>
            </div>
            <div class="form-group">
                <label>消耗积分</label>
                <el-input v-model="updateForm.point" type="number" placeholder="输入用户添加每条解析消耗积分"></el-input>
            </div>
            <div class="form-group">
                <label>备案状态</label>
                <el-select v-model="updateForm.beian" style="width: 100%;">
                    <el-option :value="0" label="未备案"></el-option>
                    <el-option :value="1" label="已备案"></el-option>
                </el-select>
                <div class="input_tips">用于前台展示当前主域的备案状态。</div>
            </div>
            <div class="form-group">
                <label>审核模式</label>
                <el-select v-model="updateForm.review_mode" style="width: 100%;">
                    <el-option :value="0" label="自动通过"></el-option>
                    <el-option :value="1" label="人工审核"></el-option>
                </el-select>
                <div class="input_tips">开启后，用户新增、修改、删除解析都会先进入审核列表。</div>
            </div>
            <div class="form-group">
                <label>域名介绍</label>
                <el-input v-model="updateForm.desc" type="textarea" :rows="5" placeholder="输入域名介绍内容"></el-input>
            </div>
            <template v-slot:footer>
                <el-button @click="updateDialogVisible = false">关闭</el-button>
                <el-button type="primary" @click="submitUpdate">保存</el-button>
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
                    dns: 0,
                    dnsLabels: @json(\App\Klsf\Dns\Helper::getLabelMap()),
                    domainList: [],
                    addDialogVisible: false,
                    updateDialogVisible: false,
                    addForm: {
                        action: 'add',
                        dns: '0',
                        domain: '',
                        groups: [],
                        record_types: ['A', 'CNAME'],
                        review_mode: 0,
                        beian: 0,
                        point: '',
                        desc: ''
                    },
                    updateForm: {
                        action: 'update',
                        did: 0,
                        groups: [],
                        record_types: ['A', 'CNAME'],
                        review_mode: 0,
                        beian: 0,
                        point: '',
                        desc: ''
                    }
                };
            },
            methods: {
                getDnsLabel: function (dns) {
                    return this.dnsLabels[dns] || dns || '-';
                },
                openAddModal: function () {
                    this.addForm = {
                        action: 'add',
                        dns: '0',
                        domain: '',
                        groups: [],
                        record_types: ['A', 'CNAME'],
                        review_mode: 0,
                        beian: 0,
                        point: '',
                        desc: ''
                    };
                    this.domainList = [];
                    this.addDialogVisible = true;
                },
                openUpdateModal: function (row) {
                    this.storeInfo = Object.assign({}, row);
                    this.updateForm = {
                        action: 'update',
                        did: row.did,
                        groups: row.groups === '0' ? ['0'] : row.groups.split(','),
                        record_types: row.record_type_list && row.record_type_list.length ? row.record_type_list : ['A', 'CNAME'],
                        review_mode: row.review_mode ? 1 : 0,
                        beian: row.beian ? 1 : 0,
                        point: row.point,
                        desc: row.desc
                    };
                    this.updateDialogVisible = true;
                },
                getDomainGroups: function (groups) {
                    if (groups === '0') {
                        return ' <span class="badge badge-danger">所有组</span>';
                    }
                    var str = '';
                    groups = groups.split(',');
                    @foreach(\App\Models\UserGroup::where('gid','>',99)->get() as $group)
                    if (groups.indexOf('{{ $group->gid }}') > -1) {
                        str += ' <span class="badge badge-info">{{ $group->name }}</span>';
                    }
                    @endforeach
                        return str;
                },
                getDomainList: function () {
                    var vm = this;
                    if (!this.addForm.dns || this.addForm.dns === '0') {
                        vm.$message('请选择域名解析平台', 'error');
                        return;
                    }
                    this.$post("/admin/domain", {action: 'domainList', dns: this.addForm.dns})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.domainList = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    this.$post("/admin/domain", vm.search, {action: 'select'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.data = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                submitAdd: function () {
                    var vm = this;
                    this.$post("/admin/domain", this.addForm)
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                vm.addDialogVisible = false;
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
                submitUpdate: function () {
                    var vm = this;
                    this.$post("/admin/domain", this.updateForm)
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                vm.updateDialogVisible = false;
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
                del: function (id) {
                    var vm = this;
                    this.$confirmAction('确认删除这个主域名配置吗？', function () {
                        return vm.$post("/admin/domain", {action: 'delete', id: id})
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
            }
        });
    </script>
@endsection
