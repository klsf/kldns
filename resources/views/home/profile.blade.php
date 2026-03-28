@extends('home.layout.index')
@section('title', '个人资料')
@section('content')
    <div class="page-header">
        <div>
            <h1>账户设置</h1>
            <p>查看账号状态、当前用户组与积分，并执行密码修改或认证操作。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="col-12 col-lg-7 px-0 mt-2">
            <div class="card">
                <div class="card-header">
                    个人资料
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
                            <label for="staticEmail" class="col-sm-3 col-form-label">用户组</label>
                            <div class="col-sm-9">
                                <el-input value="{{ auth()->user()->group?auth()->user()->group->name:'' }}" disabled></el-input>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">积分</label>
                            <div class="col-sm-9">
                                <el-input value="{{ auth()->user()->point }}" disabled></el-input>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">状态</label>
                            <div class="col-sm-9">
                                <div class="d-flex align-items-center" style="gap: 8px;">
                                    <el-input :value="statusText({{ auth()->user()->status }})" disabled></el-input>
                                    @if(auth()->user()->status==1)
                                        <el-button type="warning" @click="verify">认证</el-button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">邮箱</label>
                            <div class="col-sm-9">
                                <el-input value="{{ auth()->user()->email }}" disabled></el-input>
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
                statusText: function (status) {
                    if (status === 0) return '已禁用';
                    if (status === 1) return '待认证';
                    if (status === 2) return '已认证';
                    return '未知';
                },
                verify: function () {
                    var vm = this;
                    this.$post("/home", {action: 'verify'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
                submitProfile: function () {
                    var vm = this;
                    this.$post("/home", this.profileForm)
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
