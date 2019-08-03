@extends('admin.layout.index')
@section('title', '关键词自动检测')
@section('content')
    <div id="vue" class="pt-3 pt-sm-0 row">
        <div class="col-12 col-md-6 mt-2">
            <div class="card">
                <div class="card-header">
                    关键词自动检测
                </div>
                <div class="card-body">
                    <form id="form-keywords">
                        <input type="hidden" name="action" value="config">
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">关键词</label>
                            <div class="col-sm-9">
                                <textarea class="form-control" name="keywords" placeholder="需要检测的关键词"
                                          rows="5"
                                >{{ config('sys.keywords') }}</textarea>
                                <div class="input_tips">一行一个关键词，当解析网站包含这些关键词时，则会自动删除此解析！</div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">监控密匙</label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <input class="form-control" type="text" :value="info.key" disabled>
                                    <div class="input-group-append" @click="changeKey"><span
                                                class="input-group-text btn btn-warning">更换</span></div>
                                </div>
                                <div class="input_tips" style="word-wrap:break-word">监控地址：http://{{ $_SERVER['HTTP_HOST'] }}/cron/check/@{{ info.key }}
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="staticEmail" class="col-sm-3 col-form-label">上次检测</label>
                            <div class="col-sm-9">
                                <input class="form-control" type="text" :value="info.checked_at" disabled>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <a class="btn btn-info text-white float-right" @click="form('keywords')">保存</a>
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
                info: {
                    key: null,
                    checked_at: null
                }
            },
            methods: {
                changeKey: function () {
                    var vm = this;
                    this.$post("/admin/config", {action: 'changeKey'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.$message(data.message, 'success');
                                vm.info.key = data.key
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                getKeywordsInfo: function (id) {
                    var vm = this;
                    this.$post("/admin/config", {action: 'getKeywordsInfo'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.info = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                form: function (id) {
                    var vm = this;
                    this.$post("/admin/config", $("#form-" + id).serialize())
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
                this.getKeywordsInfo();
            }
        });
    </script>
@endsection