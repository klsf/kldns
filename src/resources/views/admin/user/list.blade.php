@extends('admin.layout.index')
@section('title', '用户列表')
@section('content')
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                用户列表
            </div>
            <div class="card-header">
                <div class="form-inline">
                    <input type="text" disabled="disabled" class="d-none">
                    <div class="form-group">
                        <select class="form-control" v-model="search.gid">
                            <option value="all">所有</option>
                            @foreach(\App\Models\UserGroup::where('gid','!=',99)->get() as $group)
                                <option value="{{ $group->gid }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group ml-1">
                        <input type="number" placeholder="UID" class="form-control" v-model="search.uid">
                    </div>
                    <div class="form-group ml-1">
                        <input type="text" placeholder="用户名" class="form-control" v-model="search.username">
                    </div>
                    <div class="form-group ml-1">
                        <input type="email" placeholder="邮箱地址" class="form-control" v-model="search.email">
                    </div>
                    <a class="btn btn-info ml-1" @click="getList(1)"><i class="fa fa-search"></i> 搜索</a></div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>UID</th>
                            <th>用户名</th>
                            <th>组</th>
                            <th>状态</th>
                            <th>积分</th>
                            <th>邮箱</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody v-cloak="">
                        <tr v-for="(row,i) in data.data" :key="i">
                            <td>@{{ row.uid }}</td>
                            <td>@{{ row.username }}</td>
                            <td>@{{ row.group?row.group.name:'无' }}</td>
                            <td>
                                <span v-if="row.status===0">已禁用</span>
                                <span v-else-if="row.status===1">待认证</span>
                                <span v-else-if="row.status===2">已认证</span>
                            </td>
                            <td>
                                <a href="#modal-point" class="btn btn-sm btn-info" data-toggle="modal"
                                   @click="storeInfo=Object.assign({},row)">
                                    @{{ row.point }}
                                </a>
                            </td>
                            <td>@{{ row.email }}</td>
                            <td>@{{ row.created_at }}</td>
                            <td>
                                <a href="#modal-update" class="btn btn-sm btn-info" data-toggle="modal"
                                   @click="storeInfo=Object.assign({},row)">编辑
                                </a>
                                <a class="btn btn-sm btn-warning" :href="'/admin/user/point?uid='+row.uid">积分</a>
                                <a class="btn btn-sm btn-primary" :href="'/admin/domain/record?uid='+row.uid">域名</a>
                                <a class="btn btn-sm btn-danger" @click="del(row.uid)">删除</a>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer pb-0 text-center">
                @include('admin.layout.pagination')
            </div>
        </div>
        <div class="modal fade" id="modal-point">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">用户积分操作</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="form-point">
                            <input type="hidden" name="action" value="point">
                            <input type="hidden" name="uid" :value="storeInfo.uid">
                            <div class="form-group row">
                                <label for="inputPassword" class="col-sm-2 col-form-label">操作</label>
                                <div class="col-sm-10">
                                    <select name="act" class="form-control">
                                        <option value="0">增加</option>
                                        <option value="1">扣除</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">积分</label>
                                <div class="col-sm-10">
                                    <input type="number" name="point" class="form-control" placeholder="输入积分数">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">原因</label>
                                <div class="col-sm-10">
                                    <textarea class="form-control" name="remark" rows="3"
                                              placeholder="输入操作原因"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                        <button type="button" class="btn btn-primary" @click="form('point')">保存</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="modal-update">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">用户修改</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="form-update">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="uid" :value="storeInfo.uid">
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">用户名</label>
                                <div class="col-sm-10">
                                    <input type="text" name="username" class="form-control"
                                           :value="storeInfo.username" disabled>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">邮箱</label>
                                <div class="col-sm-10">
                                    <input type="text" name="email" class="form-control"
                                           :value="storeInfo.email">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="inputPassword" class="col-sm-2 col-form-label">状态</label>
                                <div class="col-sm-10">
                                    <select name="status" class="form-control" :value="storeInfo.status">
                                        <option value="0">已禁用</option>
                                        <option value="1">待认证</option>
                                        <option value="2">已认证</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="inputPassword" class="col-sm-2 col-form-label">用户组</label>
                                <div class="col-sm-10">
                                    <select name="gid" class="form-control" :value="storeInfo.gid">
                                        @foreach(\App\Models\UserGroup::where('gid','!=',99)->get() as $group)
                                            <option value="{{ $group->gid }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">密码</label>
                                <div class="col-sm-10">
                                    <input type="text" name="password" class="form-control" placeholder="不修改密码则留空">
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                        <button type="button" class="btn btn-primary" @click="form('update')">保存</button>
                    </div>
                </div>
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
                    page: 1, gid: 'all', uid: $_GET('uid'), username: '', email: ''
                },
                data: {},
                storeInfo: {}
            },
            methods: {
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
                form: function (id) {
                    var vm = this;
                    this.$post("/admin/user", $("#form-" + id).serialize())
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                $("#modal-" + id).modal('hide');
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
                del: function (id) {
                    if (!confirm('确认删除？')) return;
                    var vm = this;
                    this.$post("/admin/user", {action: 'delete', id: id})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
            },
            mounted: function () {
                this.getList();
            }
        });
    </script>
@endsection