@extends('admin.layout.index')
@section('title', '用户列表')
@section('content')
    <div class="page-header">
        <div>
            <h1>用户列表</h1>
            <p>按用户组、UID、用户名和邮箱检索账号，并直接调整积分、状态与解析资源。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                用户列表
            </div>
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <el-select v-model="search.gid" placeholder="用户组" style="width: 160px;">
                        <el-option value="all" label="所有"></el-option>
                        @foreach(\App\Models\UserGroup::where('gid','!=',99)->get() as $group)
                            <el-option value="{{ $group->gid }}" label="{{ $group->name }}"></el-option>
                        @endforeach
                    </el-select>
                    <el-input v-model="search.uid" placeholder="UID" clearable style="width: 120px;"></el-input>
                    <el-input v-model="search.username" placeholder="用户名" clearable style="width: 160px;"></el-input>
                    <el-input v-model="search.email" placeholder="邮箱地址" clearable style="width: 220px;"></el-input>
                    <el-button type="primary" size="small" class="toolbar-action" @click="getList(1)">搜索</el-button>
                </div>
            </div>
            <div class="card-body">
                <el-table v-cloak border stripe :data="data.data || []" style="width: 100%">
                    <el-table-column prop="uid" label="UID" width="90"></el-table-column>
                    <el-table-column prop="username" label="用户名" min-width="140"></el-table-column>
                    <el-table-column label="组" min-width="120">
                        <template v-slot:default="{ row }">
                            <span v-text="groupText(row)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template v-slot:default="{ row }">
                            <span v-text="statusText(row.status)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column label="积分" width="90">
                        <template v-slot:default="{ row }">
                            <el-button size="small" @click="openPointModal(row)">
                                <span v-text="formatUserPoint(row)"></span>
                            </el-button>
                        </template>
                    </el-table-column>
                    <el-table-column prop="email" label="邮箱" min-width="220"></el-table-column>
                    <el-table-column prop="created_at" label="注册时间" min-width="170"></el-table-column>
                    <el-table-column label="操作" width="230">
                        <template v-slot:default="{ row }">
                            <div class="table-actions">
                                <el-button size="small" @click="openUpdateModal(row)">编辑</el-button>
                                <el-button size="small" type="success" tag="a" :href="'/admin/user/point?uid=' + row.uid">积分</el-button>
                                <el-button size="small" type="primary" tag="a" :href="'/admin/domain/record?uid=' + row.uid">域名</el-button>
                                <el-button size="small" type="danger" @click="del(row.uid)">删除</el-button>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <div class="card-footer pb-0 text-center">
                @include('admin.layout.pagination')
            </div>
        </div>
        <el-dialog v-model="pointDialogVisible" title="用户积分操作" width="min(520px, calc(100vw - 24px))" class="app-dialog" top="5vh">
            <div class="form-group">
                <label>操作</label>
                <el-select v-model="pointForm.act" style="width: 100%;">
                    <el-option :value="0" label="增加"></el-option>
                    <el-option :value="1" label="扣除"></el-option>
                </el-select>
            </div>
            <div class="form-group">
                <label>积分</label>
                <el-input v-model="pointForm.point" type="number" placeholder="输入积分数"></el-input>
            </div>
            <div class="form-group">
                <label>原因</label>
                <el-input v-model="pointForm.remark" type="textarea" :rows="3" placeholder="输入操作原因"></el-input>
            </div>
            <template v-slot:footer>
                <el-button @click="pointDialogVisible = false">关闭</el-button>
                <el-button type="primary" @click="submitPoint">保存</el-button>
            </template>
        </el-dialog>
        <el-dialog v-model="updateDialogVisible" :title="storeInfo && storeInfo.uid ? '编辑用户' : '添加用户'" width="min(560px, calc(100vw - 24px))" class="app-dialog" top="5vh">
            <div class="form-group">
                <label>用户名</label>
                <el-input :model-value="storeInfo.username" disabled></el-input>
            </div>
            <div class="form-group">
                <label>邮箱</label>
                <el-input v-model="updateForm.email"></el-input>
            </div>
            <div class="form-group">
                <label>状态</label>
                <el-select v-model="updateForm.status" style="width: 100%;">
                    <el-option :value="0" label="已禁用"></el-option>
                    <el-option :value="1" label="待认证"></el-option>
                    <el-option :value="2" label="已认证"></el-option>
                </el-select>
            </div>
            <div class="form-group">
                <label>用户组</label>
                <el-select v-model="updateForm.gid" style="width: 100%;">
                    @foreach(\App\Models\UserGroup::where('gid','!=',99)->get() as $group)
                        <el-option :value="{{ $group->gid }}" label="{{ $group->name }}"></el-option>
                    @endforeach
                </el-select>
            </div>
            <div class="form-group">
                <label>密码</label>
                <el-input v-model="updateForm.password" placeholder="不修改密码则留空"></el-input>
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
                        page: 1, gid: 'all', uid: $_GET('uid'), username: '', email: ''
                    },
                    data: {},
                    storeInfo: {},
                    pointDialogVisible: false,
                    updateDialogVisible: false,
                    pointForm: {
                        action: 'point',
                        uid: 0,
                        act: 0,
                        point: '',
                        remark: ''
                    },
                    updateForm: {
                        action: 'update',
                        uid: 0,
                        email: '',
                        status: 1,
                        gid: 100,
                        password: ''
                    }
                };
            },
            methods: {
                openPointModal: function (row) {
                    this.storeInfo = Object.assign({}, row);
                    this.pointForm = {
                        action: 'point',
                        uid: row.uid,
                        act: 0,
                        point: '',
                        remark: ''
                    };
                    this.pointDialogVisible = true;
                },
                openUpdateModal: function (row) {
                    this.storeInfo = Object.assign({}, row);
                    this.updateForm = {
                        action: 'update',
                        uid: row.uid,
                        email: row.email,
                        status: row.status,
                        gid: row.gid,
                        password: ''
                    };
                    this.updateDialogVisible = true;
                },
                groupText: function (row) {
                    return row && row.group && row.group.name ? String(row.group.name).trim() : '无';
                },
                formatUserPoint: function (row) {
                    return row && row.point !== undefined && row.point !== null ? String(row.point) : '0';
                },
                statusText: function (status) {
                    if (status === 0) {
                        return '已禁用';
                    }
                    if (status === 1) {
                        return '待认证';
                    }
                    if (status === 2) {
                        return '已认证';
                    }
                    return '未知';
                },
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    this.$post("/admin/user", vm.search, {action: 'select'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.data = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                submitPoint: function () {
                    var vm = this;
                    this.$post("/admin/user", this.pointForm)
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                vm.pointDialogVisible = false;
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
                submitUpdate: function () {
                    var vm = this;
                    this.$post("/admin/user", this.updateForm)
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
                    this.$confirmAction('确认删除这个用户吗？', function () {
                        return vm.$post("/admin/user", {action: 'delete', id: id})
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
