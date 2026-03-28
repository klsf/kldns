@extends('admin.layout.index')
@section('title', '操作日志')
@section('content')
    <div class="page-header">
        <div>
            <h1>操作日志</h1>
            <p>审计用户前台、开放 API 和后台管理动作，便于追踪记录变更与审核处理过程。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <el-input v-model="search.action_name" placeholder="动作名" clearable style="width: 180px;"></el-input>
                    <el-select v-model="search.source" placeholder="来源" style="width: 140px;">
                        <el-option value="" label="全部来源"></el-option>
                        <el-option value="web" label="前台"></el-option>
                        <el-option value="api" label="开放 API"></el-option>
                        <el-option value="admin" label="后台"></el-option>
                        <el-option value="review" label="审核流程"></el-option>
                    </el-select>
                    <el-input v-model="search.uid" placeholder="UID" clearable style="width: 120px;"></el-input>
                    <el-input v-model="search.admin_uid" placeholder="管理员 UID" clearable style="width: 140px;"></el-input>
                    <el-button type="primary" size="small" class="toolbar-action" @click="getList(1)">搜索</el-button>
                </div>
            </div>
            <div class="card-body">
                <el-table v-cloak border stripe :data="data.data || []" style="width: 100%">
                    <el-table-column prop="id" label="ID" width="80"></el-table-column>
                    <el-table-column prop="action" label="动作" min-width="150"></el-table-column>
                    <el-table-column prop="source" label="来源" width="100"></el-table-column>
                    <el-table-column label="用户" width="150">
                        <template v-slot:default="{ row }">
                            <span v-text="row.user ? row.user.username + ' [UID:' + row.uid + ']' : (row.uid || '-')"></span>
                        </template>
                    </el-table-column>
                    <el-table-column label="管理员" width="150">
                        <template v-slot:default="{ row }">
                            <span v-text="row.admin ? row.admin.username + ' [UID:' + row.admin_uid + ']' : (row.admin_uid || '-')"></span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="message" label="内容" min-width="260"></el-table-column>
                    <el-table-column prop="ip" label="IP" width="130"></el-table-column>
                    <el-table-column prop="created_at" label="时间" min-width="170"></el-table-column>
                </el-table>
            </div>
            <div class="card-footer pb-0 text-center">
                @include('admin.layout.pagination')
            </div>
        </div>
    </div>
@endsection
@section('foot')
    <script>
        createVuePage('#vue', {
            data: function () {
                return {
                    search: {page: 1, action_name: '', source: '', uid: '', admin_uid: ''},
                    data: {}
                };
            },
            methods: {
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    vm.$post('/admin/config/logs', vm.search, {action: 'select'}).then(function (data) {
                        if (data.status === 0) {
                            vm.data = data.data;
                        } else {
                            vm.$message(data.message, 'error');
                        }
                    });
                }
            },
            mounted: function () {
                this.getList();
            }
        });
    </script>
@endsection
