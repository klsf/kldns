@extends('admin.layout.index')
@section('title', '修改密码')
@section('content')
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="col-12 col-md-6 mt-2">
            <div class="card">
                <div class="card-header">
                    修改密码
                </div>
                <div class="card-body">
                    <form id="form-profile">
                        <input type="hidden" name="action" value="profile">
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">用户名</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" value="{{ auth()->user()->username }}" disabled>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">旧密码</label>
                            <div class="col-sm-9">
                                <input type="password" name="old_password" class="form-control" placeholder="输入旧密码">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">新密码</label>
                            <div class="col-sm-9">
                                <input type="password" name="new_password" class="form-control" placeholder="输入新密码">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <a class="btn btn-info text-white float-right" @click="form('profile')">修改密码</a>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('foot')
    <script>
        new Vue({
            el: '#vue',
            data: {},
            methods: {
                form: function (id) {
                    var vm = this;
                    this.$post("/admin", $("#form-" + id).serialize())
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
            },
            mounted: function () {
            }
        });
    </script>
@endsection