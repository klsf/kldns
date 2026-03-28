@extends('home.layout.index')
@section('title', '积分明细')
@section('content')
    <div class="page-header">
        <div>
            <h1>积分明细</h1>
            <p>查看积分增加、消费与剩余余额，便于追踪解析资源成本。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                <div>积分明细</div>
                <div class="d-flex flex-wrap align-items-center mt-3" style="gap: 8px;">
                    <el-select v-model="search.act" placeholder="操作类型" style="width: 160px;">
                        <el-option value="all" label="所有"></el-option>
                        <el-option value="increase" label="增加"></el-option>
                        <el-option value="reduce" label="减少"></el-option>
                        <el-option value="消费" label="消费"></el-option>
                    </el-select>
                    <el-button type="primary" size="small" class="toolbar-action" @click="getList(1)">搜索</el-button>
                </div>
            </div>
            <div class="card-body">
                <el-table v-cloak border stripe :data="data.data || []" :row-class-name="pointRowClassName" style="width: 100%">
                    <el-table-column prop="id" label="ID" width="80"></el-table-column>
                    <el-table-column prop="action" label="操作" width="120"></el-table-column>
                    <el-table-column prop="point" label="积分" width="100"></el-table-column>
                    <el-table-column prop="rest" label="剩余" width="100"></el-table-column>
                    <el-table-column prop="remark" label="详情" min-width="260"></el-table-column>
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
                    search: {
                        page: 1, uid: $_GET('uid'), act: 'all'
                    },
                    data: {},
                    storeInfo: {}
                };
            },
            methods: {
                pointRowClassName: function (options) {
                    if (options.row.point < 0) {
                        return 'text-danger';
                    }

                    if (options.row.point > 0) {
                        return 'text-success';
                    }

                    return '';
                },
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    this.$post("/home", vm.search, {action: 'pointRecord'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.data = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
            },
            mounted: function () {
                this.getList();
            }
        });
    </script>
@endsection
