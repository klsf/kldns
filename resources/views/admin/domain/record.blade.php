@extends('admin.layout.index')
@section('title', '记录列表')
@section('content')
    <div class="page-header">
        <div>
            <h1>记录列表</h1>
            <p>查看全站解析记录，按域名、类型、UID 和记录值筛选，并直接执行清理操作。</p>
        </div>
    </div>
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                记录列表
            </div>
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <el-select v-model="search.did" placeholder="域名" style="width: 160px;">
                        <el-option :value="0" label="所有"></el-option>
                        @foreach(\App\Models\Domain::get() as $domain)
                            <el-option :value="{{ $domain->did }}" label="{{ $domain->domain }}"></el-option>
                        @endforeach
                    </el-select>
                    <el-select v-model="search.type" placeholder="记录类型" style="width: 140px;">
                        <el-option :value="0" label="所有"></el-option>
                        @foreach(\App\Models\Domain::getRecordTypeOptions() as $type => $label)
                            <el-option value="{{ $type }}" label="{{ $label }}"></el-option>
                        @endforeach
                    </el-select>
                    <el-input v-model="search.uid" placeholder="UID" clearable style="width: 120px;"></el-input>
                    <el-input v-model="search.name" placeholder="主机记录" clearable style="width: 180px;"></el-input>
                    <el-input v-model="search.value" placeholder="记录值" clearable style="width: 220px;"></el-input>
                    <el-button type="primary" size="small" class="toolbar-action" @click="getList(1)">搜索</el-button>
                </div>
            </div>
            <div class="card-body">
                <el-table v-cloak border stripe :data="data.data || []" style="width: 100%">
                    <el-table-column prop="id" label="ID" width="80"></el-table-column>
                    <el-table-column label="用户" min-width="160">
                        <template v-slot:default="{ row }">
                            <span v-text="formatRecordUser(row)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column label="域名" min-width="220">
                        <template v-slot:default="{ row }">
                            <a :href="getRecordUrl(row)" target="_blank">
                                <span v-text="formatRecordDomain(row)"></span>
                            </a>
                        </template>
                    </el-table-column>
                    <el-table-column label="备案状态" width="100">
                        <template v-slot:default="{ row }">
                            <span v-text="getRecordBeianText(row)"></span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="type" label="记录类型" width="110"></el-table-column>
                    <el-table-column prop="line" label="线路" min-width="120"></el-table-column>
                    <el-table-column prop="value" label="记录值" min-width="180"></el-table-column>
                    <el-table-column prop="created_at" label="添加时间" min-width="170"></el-table-column>
                    <el-table-column label="操作" width="100">
                        <template v-slot:default="{ row }">
                            <div class="table-actions">
                                <el-button size="small" type="danger" @click="del(row.id)">删除</el-button>
                            </div>
                        </template>
                    </el-table-column>
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
                        page: 1, did: 0, name: '', type: 0, value: '', uid: $_GET('uid')
                    },
                    data: {},
                };
            },
            methods: {
                formatRecordUser: function (row) {
                    var username = row && row.user && row.user.username ? String(row.user.username).trim() : '';
                    var uid = row && row.uid ? String(row.uid).trim() : '';

                    if (username && uid) {
                        return username + ' [UID:' + uid + ']';
                    }

                    return username || uid || '-';
                },
                formatRecordDomain: function (row) {
                    var host = row && row.name ? String(row.name).trim() : '';
                    var domain = row && row.domain && row.domain.domain ? String(row.domain.domain).trim() : '';

                    if (!host) {
                        return domain;
                    }

                    if (!domain) {
                        return host;
                    }

                    if (host === domain || host.slice(0 - (domain.length + 1)) === '.' + domain) {
                        return host;
                    }

                    return host + '.' + domain;
                },
                getRecordUrl: function (row) {
                    var domain = this.formatRecordDomain(row);
                    return domain ? 'http://' + domain : 'javascript:void(0)';
                },
                getRecordBeianText: function (row) {
                    return row && row.domain ? (row.domain.beian_text || '未备案') : '未备案';
                },
                getList: function (page) {
                    var vm = this;
                    vm.search.page = typeof page === 'undefined' ? vm.search.page : page;
                    this.$post("/admin/domain/record", vm.search, {action: 'select'})
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.data = data.data
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        })
                },
                del: function (id) {
                    var vm = this;
                    this.$confirmAction('确认删除这条解析记录吗？', function () {
                        return vm.$post("/admin/domain/record", {action: 'delete', id: id})
                            .then(function (data) {
                                if (data.status === 0) {
                                    vm.getList();
                                    vm.$message(data.message, 'success');
                                } else {
                                    vm.$message(data.message, 'error');
                                }
                            });
                    });
                },
            },
            mounted: function () {
                this.getList();
            }
        });
    </script>
@endsection
