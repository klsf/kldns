@extends('admin.layout.index')
@section('title', '修改密码')
@section('content')
    <div class="page-header">
        <div>
            <h1>管理员密码</h1>
            <p>更新管理员后台登录口令。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="col-12 col-lg-7 px-0 mt-2">
            <div class="card">
                <div class="card-header">
                    修改密码
                </div>
                <div class="card-body">
                    <form id="form-profile">
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">用户名</label>
                            <div class="col-sm-9">
                                <el-input value="{{ auth()->user()->username }}" disabled></el-input>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">旧密码</label>
                            <div class="col-sm-9">
                                <el-input v-model="profileForm.old_password" type="password" show-password placeholder="输入旧密码"></el-input>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">新密码</label>
                            <div class="col-sm-9">
                                <el-input v-model="profileForm.new_password" type="password" show-password placeholder="输入新密码"></el-input>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <el-button type="primary" @click="submitProfile">修改密码</el-button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('foot')
    <script>
        createVuePage('#vue', {
            data: function () {
                return {
                    profileForm: {
                        action: 'profile',
                        old_password: '',
                        new_password: ''
                    }
                };
            },
            methods: {
                submitProfile: function () {
                    var vm = this;
                    this.$post("/admin", this.profileForm)
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
            }
        });
    </script>
@endsection
