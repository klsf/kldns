@extends('home.layout.index')
@section('title', '记录列表')
@section('content')
    <div class="page-header">
        <div>
            <h1>解析记录</h1>
            <p>集中管理当前账号下的二级域名记录，支持移动端直接增删改查。</p>
        </div>
    </div>
    @if(config('sys.html_home'))
        <div class="alert alert-primary">
            {!! config('sys.html_home') !!}
        </div>
    @endif
    <div id="vue" class="pt-3 pt-sm-0">
        <div class="card">
            <div class="card-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
                    <div>记录列表</div>
                    <el-button type="primary" size="small" @click="openStoreModal">添加</el-button>
                </div>
                <div class="d-flex flex-wrap align-items-center mt-3" style="gap: 8px;">
                    <el-select v-model="search.did" placeholder="选择域名" style="width: 160px;">
                        <el-option :value="0" label="所有"></el-option>
                        <el-option v-for="domain in domainList" :key="domain.did" :value="domain.did" :label="getDomainOptionLabel(domain)"></el-option>
                    </el-select>
                    <el-select v-model="search.type" placeholder="记录类型" style="width: 140px;">
                        <el-option :value="0" label="所有"></el-option>
                        <el-option v-for="type in recordTypeOptions" :key="type" :value="type" :label="getRecordTypeLabel(type)"></el-option>
                    </el-select>
                    <el-input v-model="search.name" placeholder="主机记录" clearable style="width: 180px;"></el-input>
                    <el-input v-model="search.value" placeholder="记录值" clearable style="width: 220px;"></el-input>
                    <el-button type="primary" size="small" class="toolbar-action" @click="getList(1)">搜索</el-button>
                </div>
            </div>
            <div class="card-body">
                <el-table v-cloak border stripe :data="data.data || []" style="width: 100%">
                    <el-table-column prop="id" label="ID" width="80"></el-table-column>
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
                    <el-table-column label="操作" width="170">
                        <template v-slot:default="{ row }">
                            <div class="table-actions">
                                <el-button size="small" @click="editStore(row)">修改</el-button>
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
        <el-dialog
            v-model="storeDialogVisible"
            :title="storeInfo && storeInfo.id ? '编辑记录' : '添加记录'"
            width="min(680px, calc(100vw - 24px))"
            class="app-dialog record-dialog"
            top="5vh"
        >
            <div class="form-group">
                <label>主机记录</label>
                <div class="record-dialog-host">
                    <el-input v-model="storeInfo.name"></el-input>
                    <el-select v-model="storeInfo.did" :disabled="!!storeInfo.id" class="record-dialog-domain">
                        <el-option v-for="domain in domainList" :key="domain.did" :value="domain.did" :label="getDomainOptionLabel(domain)"></el-option>
                    </el-select>
                </div>
                <div class="input_tips" v-if="desc" v-html="desc"></div>
            </div>
            <div class="form-group">
                <label>记录类型</label>
                <el-select v-model="storeInfo.type" style="width: 100%;">
                    <el-option v-for="type in getRecordTypeList()" :key="type" :value="type" :label="getRecordTypeLabel(type)"></el-option>
                </el-select>
                <div class="input_tips" v-if="storeInfo.type" v-text="getRecordTypeDescription(storeInfo.type)"></div>
            </div>
            <div class="form-group">
                <label>记录值</label>
                <el-input v-model="storeInfo.value" :placeholder="getRecordValueTip(storeInfo.type) || '输入记录值'"></el-input>
                <div class="input_tips" v-if="storeInfo.type" v-text="getRecordValueTip(storeInfo.type)"></div>
            </div>
            <div class="form-group">
                <label>线路</label>
                <el-select v-model="storeInfo.line_id" style="width: 100%;">
                    <el-option v-for="line in getLineList()" :key="line.Id" :value="line.Id" :label="line.Name"></el-option>
                </el-select>
            </div>
            <div class="form-group">
                <label>积分</label>
                <el-input :value="getDomainPoint() + ' 积分/条'" disabled></el-input>
                <div class="input_tips" v-if="getDomainBeianText()" v-text="'当前主域备案状态：' + getDomainBeianText()"></div>
                <div class="input_tips" v-if="getDomainReviewModeText()" v-text="'当前主域审核模式：' + getDomainReviewModeText()"></div>
            </div>
            <template v-slot:footer>
                <el-button @click="storeDialogVisible = false">关闭</el-button>
                <el-button type="primary" @click="submitStore">确认</el-button>
            </template>
        </el-dialog>
    </div>
@endsection
@section('foot')
    <script>
        createVuePage('#vue', {
            data: function () {
                return {
                    search: {
                        page: 1, did: 0, name: '', type: 0, value: ''
                    },
                    domainList: [],
                    recordTypeOptions: @json(array_keys(\App\Models\Domain::getRecordTypeOptions())),
                    recordTypeMeta: @json(\App\Models\Domain::getRecordTypeDescriptions()),
                    recordValueTips: @json(\App\Models\Domain::getRecordValueTips()),
                    data: {},
                    storeInfo: {
                        did: 0,
                        line_id: 0
                    },
                    storeDialogVisible: false,
                    selectDid: 0,
                    desc: ''
                };
            },
            methods: {
                openStoreModal: function () {
                    var firstDid = this.domainList.length > 0 ? this.domainList[0].did : 0;
                    this.storeInfo = {did: firstDid, line_id: 0, type: this.getDefaultRecordType(firstDid)};
                    this.storeDialogVisible = true;
                },
                editStore: function (row) {
                    this.storeInfo = Object.assign({}, row);
                    if (!this.storeInfo.type) {
                        this.storeInfo.type = this.getDefaultRecordType(this.storeInfo.did);
                    }
                    this.storeDialogVisible = true;
                },
                getDomainInfo: function (did) {
                    for (var i = 0; i < this.domainList.length; i++) {
                        if (this.domainList[i].did === did) {
                            return this.domainList[i];
                        }
                    }
                    return null;
                },
                getRecordTypeList: function () {
                    var domain = this.getDomainInfo(this.storeInfo.did);
                    if (domain && domain.record_types && domain.record_types.length) {
                        if (domain.record_types.indexOf(this.storeInfo.type) === -1) {
                            this.storeInfo.type = domain.record_types[0];
                        }
                        return domain.record_types;
                    }
                    return this.recordTypeOptions;
                },
                getDefaultRecordType: function (did) {
                    var domain = this.getDomainInfo(did);
                    if (domain && domain.record_types && domain.record_types.length) {
                        return domain.record_types[0];
                    }
                    return 'A';
                },
                getRecordTypeLabel: function (type) {
                    var description = this.getRecordTypeDescription(type);
                    return description ? type + ' - ' + description : type;
                },
                getRecordTypeDescription: function (type) {
                    return this.recordTypeMeta[type] || '';
                },
                getDomainOptionLabel: function (domain) {
                    if (!domain) {
                        return '';
                    }
                    return domain.domain + ' [' + (domain.beian_text || '未备案') + ']';
                },
                getRecordValueTip: function (type) {
                    return this.recordValueTips[type] || '';
                },
                isValidDnsTarget: function (value) {
                    var normalized = String(value || '').trim().replace(/\.$/, '').toLowerCase();

                    if (!normalized || normalized.length > 253) {
                        return false;
                    }

                    if (/^(?:\d{1,3}\.){3}\d{1,3}$/.test(normalized) || normalized.indexOf(':') > -1) {
                        return false;
                    }

                    return /^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/.test(normalized);
                },
                validateRecordValue: function () {
                    var type = String(this.storeInfo.type || '').trim().toUpperCase();
                    var value = String(this.storeInfo.value || '').trim();

                    if (!value) {
                        return '请输入记录值';
                    }

                    switch (type) {
                        case 'A':
                            if (!/^(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/.test(value)) {
                                return this.getRecordValueTip(type);
                            }
                            break;
                        case 'AAAA':
                            if (!/^[0-9a-fA-F:]+$/.test(value) || value.indexOf(':') === -1) {
                                return this.getRecordValueTip(type);
                            }
                            break;
                        case 'CNAME':
                        case 'MX':
                        case 'NS':
                            if (!this.isValidDnsTarget(value)) {
                                return this.getRecordValueTip(type);
                            }
                            break;
                        case 'TXT':
                            if (value.length > 1024) {
                                return 'TXT 记录值过长，请控制在 1024 个字符内';
                            }
                            break;
                        case 'SRV':
                            var srv = value.match(/^\s*(\d{1,5})\s+(\d{1,5})\s+(\d{1,5})\s+(.+)\s*$/);
                            if (!srv) {
                                return this.getRecordValueTip(type);
                            }
                            if (parseInt(srv[1], 10) > 65535 || parseInt(srv[2], 10) > 65535 || parseInt(srv[3], 10) < 1 || parseInt(srv[3], 10) > 65535 || !this.isValidDnsTarget(srv[4])) {
                                return this.getRecordValueTip(type);
                            }
                            break;
                        case 'CAA':
                            var caa = value.match(/^\s*(\d{1,3})\s+([A-Za-z0-9-]+)\s+(.+)\s*$/);
                            if (!caa) {
                                return this.getRecordValueTip(type);
                            }
                            if (parseInt(caa[1], 10) > 255 || ['issue', 'issuewild', 'iodef'].indexOf(String(caa[2]).toLowerCase()) === -1 || !String(caa[3]).trim().replace(/^['"]|['"]$/g, '')) {
                                return this.getRecordValueTip(type);
                            }
                            break;
                    }

                    return '';
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
                getDomainPoint: function () {
                    var vm = this;
                    var domain = this.getDomainInfo(this.storeInfo.did);
                    if (domain) {
                        vm.desc = domain.desc;
                        return domain.point;
                    }
                    return 0;
                },
                getLineList: function () {
                    var domain = this.getDomainInfo(this.storeInfo.did);
                    if (domain) {
                        if (this.selectDid != this.storeInfo.did) {
                            this.storeInfo.line_id = domain.line[0].Id;
                            this.selectDid = this.storeInfo.did
                        }
                        return domain.line;
                    }
                    return [{Name: '默认', Id: 0}];
                },
                getDomainReviewModeText: function () {
                    var domain = this.getDomainInfo(this.storeInfo.did);
                    return domain ? domain.review_mode_text || '' : '';
                },
                getDomainBeianText: function () {
                    var domain = this.getDomainInfo(this.storeInfo.did);
                    return domain ? domain.beian_text || '' : '';
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
                submitStore: function () {
                    var vm = this;
                    var error = this.validateRecordValue();
                    if (error) {
                        vm.$message(error, 'error');
                        return;
                    }
                    this.$post("/home", Object.assign({ action: 'recordStore' }, this.storeInfo))
                        .then(function (data) {
                            if (data.status === 0) {
                                vm.getList();
                                vm.storeDialogVisible = false;
                                vm.$message(data.message, 'success');
                            } else {
                                vm.$message(data.message, 'error');
                            }
                        });
                },
                del: function (id) {
                    var vm = this;
                    this.$confirmAction('确认删除这条解析记录吗？', function () {
                        return vm.$post("/home", {action: 'recordDelete', id: id})
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
                this.getDomainList();
                this.getList();
            }
        });
    </script>
@endsection
