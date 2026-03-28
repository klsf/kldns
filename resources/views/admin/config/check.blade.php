@extends('admin.layout.index')
@section('title', '关键词自动检测')
@section('content')
    <div class="page-header">
        <div>
            <h1>关键词自动检测</h1>
            <p>配置巡检关键词和监控密钥，用于定时扫描并清理命中异常内容的解析记录。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0 row">
        <div class="col-12 col-lg-7 px-0 mt-2">
            <div class="card">
                <div class="card-header">
                    关键词自动检测
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">关键词</label>
                        <div class="col-sm-9">
                            <el-input v-model="keywordsForm.keywords" type="textarea" :rows="5" placeholder="需要检测的关键词"></el-input>
                            <div class="input_tips">一行一个关键词，当解析网站包含这些关键词时，则会自动删除此解析！</div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">监控密匙</label>
                        <div class="col-sm-9">
                            <div class="d-flex align-items-center" style="gap: 8px;">
                                <el-input :model-value="info.key" disabled></el-input>
                                <el-button type="warning" @click="changeKey">更换</el-button>
                            </div>
                            <div class="input_tips" style="word-wrap:break-word">
                                监控地址：<span v-text="cronCheckUrl()"></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="staticEmail" class="col-sm-3 col-form-label">上次检测</label>
                        <div class="col-sm-9">
                            <el-input :model-value="info.checked_at" disabled></el-input>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <el-button type="primary" @click="saveKeywords">保存</el-button>
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
                    info: {
                        key: null,
                        checked_at: null
                    },
                    keywordsForm: {
                        action: 'config',
                        keywords: @json(config('sys.keywords'))
                    }
                };
            },
            methods: {
                cronCheckUrl: function () {
                    return 'http://{{ $_SERVER['HTTP_HOST'] }}/cron/check/' + (this.info && this.info.key ? this.info.key : '');
                },
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
                saveKeywords: function () {
                    var vm = this;
                    this.$post("/admin/config", this.keywordsForm)
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
