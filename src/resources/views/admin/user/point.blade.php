@extends('admin.layout.index')
@section('title', '积分明细')
@section('content')
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                积分明细
            </div>
            <div class="card-header">
                <div class="form-inline">
                    <input type="text" disabled="disabled" class="d-none">
                    <div class="form-group">
                        <select class="form-control" v-model="search.act">
                            <option value="all">所有</option>
                            <option value="increase">增加</option>
                            <option value="reduce">减少</option>
                            <option value="消费">消费</option>
                        </select>
                    </div>
                    <div class="form-group ml-1">
                        <input type="number" placeholder="UID" class="form-control" v-model="search.uid">
                    </div>
                    <a class="btn btn-info ml-1" @click="getList(1)"><i class="fa fa-search"></i> 搜索</a></div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>UID</th>
                            <th>操作</th>
                            <th>积分</th>
                            <th>剩余</th>
                            <th>详情</th>
                            <th>时间</th>
                        </tr>
                        </thead>
                        <tbody v-cloak="">
                        <tr v-for="(row,i) in data.data" :key="i"
                            :class="{'text-danger':row.point<0,'text-success':row.point>0}">
                            <td>@{{ row.id }}</td>
                            <td>@{{ row.uid }}</td>
                            <td>@{{ row.action }}</td>
                            <td>@{{ row.point }}</td>
                            <td>@{{ row.rest }}</td>
                            <td>@{{ row.remark }}</td>
                            <td>@{{ row.created_at }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer pb-0 text-center">
                @include('admin.layout.pagination')
            </div>
        </div>
    </div>
@endsection
@section('foot')
    <script>
        new Vue({
            el: '#vue',
            data: {
                search: {
                    page: 1, uid: $_GET('uid'), act: 'all'
                },
                data: {},
                storeInfo: {}
            },
            methods: {
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    this.$post("/admin/user", vm.search, {action: 'pointRecord'})
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