@extends('admin.layout.index')
@section('title', '用户组')
@section('content')
    <div class="page-header">
        <div>
            <h1>用户组</h1>
            <p>维护可分配给普通用户的组别，用于控制可用主域、资源权限和积分策略。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                用户组
                <el-button class="float-right" type="primary" size="small" @click="openStoreModal">添加</el-button>
            </div>
            <div class="card-body">
                <el-table v-cloak border stripe :data="data.data || []" style="width: 100%">
                    <el-table-column prop="gid" label="编号" width="100"></el-table-column>
                    <el-table-column prop="name" label="名称" min-width="220"></el-table-column>
                    <el-table-column label="操作" width="160">
                        <template v-slot:default="{ row }">
                            <div class="table-actions">
                                <el-button size="small" @click="editStore(row)">编辑</el-button>
                                <el-button size="small" type="danger" @click="del(row.gid)">删除</el-button>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <div class="card-footer pb-0 text-center">
                @include('admin.layout.pagination')
            </div>
        </div>
        <el-dialog v-model="storeDialogVisible" :title="storeInfo && storeInfo.gid ? '编辑用户组' : '添加用户组'" width="min(460px, calc(100vw - 24px))" class="app-dialog" top="5vh">
            <div class="form-group">
                <label>名称</label>
                <el-input v-model="storeInfo.name"></el-input>
            </div>
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
                    storeDialogVisible: false
                };
            },
            methods: {
                openStoreModal: function () {
                    this.storeInfo = {};
                    this.storeDialogVisible = true;
                },
                editStore: function (row) {
                    this.storeInfo = Object.assign({}, row);
                    this.storeDialogVisible = true;
                },
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    this.$post("/admin/user/group", vm.search, {action: 'select'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.data = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                submitStore: function () {
                    var vm = this;
                    this.$post("/admin/user/group", Object.assign({ action: 'store' }, this.storeInfo))
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
                    this.$confirmAction('确认删除这个用户组吗？', function () {
                        return vm.$post("/admin/user/group", {action: 'delete', id: id})
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
