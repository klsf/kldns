@extends('home.layout.index')
@section('title', '记录列表')
@section('content')
    @if(config('sys.html_home'))
        <div class="alert alert-primary">
            {!! config('sys.html_home') !!}
        </div>
    @endif
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                记录列表
                <a href="#modal-store" data-toggle="modal"
                   @click="storeInfo={did:domainList.length>0?domainList[0].did:0,line_id:0,type:'A'}"
                   class="float-right btn btn-sm btn-primary">添加</a>
            </div>
            <div class="card-header">
                <div class="form-inline">
                    <input type="text" disabled="disabled" class="d-none">
                    <div class="form-group">
                        <select class="form-control" v-model="search.did">
                            <option value="0">所有</option>
                            <option v-for="(domain,i) in domainList" :value="domain.did">@{{ domain.domain }}</option>
                        </select>
                    </div>
                    <div class="form-group ml-1">
                        <select class="form-control" v-model="search.type">
                            <option value="0">所有</option>
                            <option value="A">A记录</option>
                            <option value="CNAME">CANME</option>
                        </select>
                    </div>
                    <div class="form-group ml-1">
                        <input type="text" placeholder="主机记录" class="form-control" v-model="search.name">
                    </div>
                    <div class="form-group ml-1">
                        <input type="text" placeholder="记录值" class="form-control" v-model="search.value">
                    </div>
                    <a class="btn btn-info ml-1" @click="getList(1)"><i class="fa fa-search"></i> 搜索</a></div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>域名</th>
                            <th>记录类型</th>
                            <th>线路</th>
                            <th>记录值</th>
                            <th>添加时间</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody v-cloak="">
                        <tr v-for="(row,i) in data.data" :key="i">
                            <td>@{{ row.id }}</td>
                            <td>
                                <a :href="'http://'+row.name+'.'+(row.domain?row.domain.domain:'')" target="_blank">
                                    @{{ row.name }}.@{{ row.domain?row.domain.domain:'' }}
                                </a>
                            </td>
                            <td>@{{ row.type }}</td>
                            <td>@{{ row.line }}</td>
                            <td>@{{ row.value }}</td>
                            <td>@{{ row.created_at }}</td>
                            <td>
                                <a href="#modal-store" class="btn btn-sm btn-info" data-toggle="modal"
                                   @click="storeInfo=Object.assign({},row)">修改
                                </a>
                                <a class="btn btn-sm btn-danger" @click="del(row.id)">删除</a>
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
        <div class="modal fade" id="modal-store">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">记录添加/修改</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="form-store">
                            <input type="hidden" name="action" value="recordStore">
                            <input type="hidden" name="id" :value="storeInfo.id" v-if="storeInfo.id">
                            <input type="hidden" name="did" :value="storeInfo.did" v-if="storeInfo.id">
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">主机记录</label>
                                <div class="col-sm-10">
                                    <div class="input-group">
                                        <input type="text" name="name" class="form-control" v-model="storeInfo.name">
                                        <select class="form-control" name="did" style="flex: none;width: 100px;"
                                                v-model="storeInfo.did" :disabled="storeInfo.id">
                                            <option v-for="(domain,i) in domainList" :value="domain.did">
                                                @{{ domain.domain }}
                                            </option>
                                        </select>
                                    </div>
                                    <div style="border: 1px solid #ced4da;border-radius: .25rem;padding: .375rem .75rem;margin-top: .25rem;font-size: 14px;color: grey;" v-if="desc"
                                         v-html="desc"></div>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">记录类型</label>
                                <div class="col-sm-10">
                                    <select class="form-control" name="type" v-model="storeInfo.type">
                                        <option value="A">A</option>
                                        <option value="CNAME">CNAME</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">记录值</label>
                                <div class="col-sm-10">
                                    <input type="text" name="value" class="form-control" placeholder="输入记录值"
                                           v-model="storeInfo.value">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">线路</label>
                                <div class="col-sm-10">
                                    <select class="form-control" name="line_id" v-model="storeInfo.line_id">
                                        <option v-for="(line,i) in getLineList()" :value="line.Id">
                                            @{{ line.Name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="staticEmail" class="col-sm-2 col-form-label">积分</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" :value="getDomainPoint()+' 积分/条'" disabled>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">关闭</button>
                        <button type="button" class="btn btn-primary" @click="form('store')">确认</button>
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
                    page: 1, did: 0, name: '', type: 0, value: ''
                },
                domainList: [],
                data: {},
                storeInfo: {
                    did: 0,
                    line_id: 0
                },
                selectDid: 0,
                desc: ''
            },
            methods: {
                getDomainPoint: function () {
                    var vm = this;
                    for (var i = 0; i < this.domainList.length; i++) {
                        if (this.domainList[i].did === this.storeInfo.did) {
                            vm.desc = this.domainList[i].desc;
                            return this.domainList[i].point;
                        }
                    }
                    return 0;
                },
                getLineList: function () {
                    for (var i = 0; i < this.domainList.length; i++) {
                        if (this.domainList[i].did === this.storeInfo.did) {
                            if (this.selectDid != this.storeInfo.did) {
                                this.storeInfo.line_id = this.domainList[i].line[0].Id;
                                this.selectDid = this.storeInfo.did
                            }
                            return this.domainList[i].line;
                        }
                    }
                    return [{Name: '默认', Id: 0}];
                },
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    this.$post("/home", vm.search, {action: 'recordList'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.data = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                getDomainList: function (page) {
                    var vm = this;
                    this.$post("/home", vm.search, {action: 'domainList'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.domainList = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                form: function (id) {
                    var vm = this;
                    this.$post("/home", $("#form-" + id).serialize())
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
                    this.$post("/home", {action: 'recordDelete', id: id})
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
                this.getDomainList();
                this.getList();
            }
        });
    </script>
@endsection