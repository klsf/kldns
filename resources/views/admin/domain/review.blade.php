@extends('admin.layout.index')
@section('title', '审核记录')
@section('content')
    <div class="page-header">
        <div>
            <h1>审核记录</h1>
            <p>处理用户提交的新增、修改、删除解析审核单，并记录处理备注。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <el-select v-model="search.status" placeholder="审核状态" style="width: 140px;">
                        <el-option value="all" label="全部状态"></el-option>
                        <el-option :value="0" label="待审核"></el-option>
                        <el-option :value="1" label="已通过"></el-option>
                        <el-option :value="2" label="已驳回"></el-option>
                    </el-select>
                    <el-select v-model="search.review_action" placeholder="操作类型" style="width: 140px;">
                        <el-option value="" label="全部操作"></el-option>
                        <el-option value="create" label="新增"></el-option>
                        <el-option value="update" label="修改"></el-option>
                        <el-option value="delete" label="删除"></el-option>
                    </el-select>
                    <el-input v-model="search.uid" placeholder="UID" clearable style="width: 120px;"></el-input>
                    <el-button type="primary" size="small" class="toolbar-action" @click="getList(1)">搜索</el-button>
                </div>
            </div>
            <div class="card-body">
                <el-table v-cloak border stripe :data="data.data || []" style="width: 100%">
                    <el-table-column prop="id" label="ID" width="80"></el-table-column>
                    <el-table-column label="用户" width="150">
                        <template v-slot:default="{ row }">
                            <span v-text="formatUser(row)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column label="域名" min-width="220">
                        <template v-slot:default="{ row }">
                            <span v-text="formatDomain(row)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="action_text" label="操作" width="90"></el-table-column>
                    <el-table-column prop="status_text" label="状态" width="90"></el-table-column>
                    <el-table-column label="内容" min-width="240">
                        <template v-slot:default="{ row }">
                            <span v-text="formatPayload(row)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="review_remark" label="备注" min-width="160"></el-table-column>
                    <el-table-column prop="created_at" label="提交时间" min-width="170"></el-table-column>
                    <el-table-column label="操作" width="170">
                        <template v-slot:default="{ row }">
                            <div class="table-actions" v-if="row.status === 0">
                                <el-button size="small" type="success" @click="openReviewDialog(row, 'approve')">通过</el-button>
                                <el-button size="small" type="danger" @click="openReviewDialog(row, 'reject')">驳回</el-button>
                            </div>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <div class="card-footer pb-0 text-center">
                @include('admin.layout.pagination')
            </div>
        </div>
        <el-dialog v-model="reviewDialogVisible" :title="reviewAction === 'approve' ? '审核通过' : '审核驳回'" width="min(520px, calc(100vw - 24px))" class="app-dialog" top="5vh">
            <div class="form-group">
                <label>处理备注</label>
                <el-input v-model="reviewRemark" type="textarea" :rows="4" placeholder="输入处理备注，便于用户查看处理原因"></el-input>
            </div>
            <template v-slot:footer>
                <el-button @click="reviewDialogVisible = false">关闭</el-button>
                <el-button :type="reviewAction === 'approve' ? 'success' : 'danger'" @click="submitReview">确认</el-button>
            </template>
        </el-dialog>
    </div>
@endsection
@section('foot')
    <script>
        createVuePage('#vue', {
            data: function () {
                return {
                    search: {page: 1, status: 'all', review_action: '', uid: ''},
                    data: {},
                    reviewDialogVisible: false,
                    reviewAction: 'approve',
                    reviewRemark: '',
                    currentRow: null
                };
            },
            methods: {
                formatUser: function (row) {
                    return row.user ? row.user.username + ' [UID:' + row.uid + ']' : row.uid;
                },
                formatDomain: function (row) {
                    var payload = row.payload || {};
                    var host = payload.name ? String(payload.name).trim() : '';
                    var domain = row.domain && row.domain.domain ? String(row.domain.domain).trim() : '';
                    if (!host) {
                        return domain || '-';
                    }
                    return host + '.' + domain;
                },
                formatPayload: function (row) {
                    var payload = row.payload || {};
                    if (row.action === 'delete') {
                        return '删除现有解析记录';
                    }
                    return [payload.type || '-', payload.line || '-', payload.value || '-'].join(' | ');
                },
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    vm.$post('/admin/domain/review', vm.search, {action: 'select'}).then(function (data) {
                        if (data.status === 0) {
                            vm.data = data.data;
                        } else {
                            vm.$message(data.message, 'error');
                        }
                    });
                },
                openReviewDialog: function (row, action) {
                    this.currentRow = row;
                    this.reviewAction = action;
                    this.reviewRemark = '';
                    this.reviewDialogVisible = true;
                },
                submitReview: function () {
                    var vm = this;
                    vm.$post('/admin/domain/review', {
                        action: vm.reviewAction,
                        id: vm.currentRow.id,
                        remark: vm.reviewRemark
                    }).then(function (data) {
                        if (data.status === 0) {
                            vm.reviewDialogVisible = false;
                            vm.getList();
                            vm.$message(data.message, 'success');
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
