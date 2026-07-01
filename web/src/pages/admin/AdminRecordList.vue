<template>
  <section class="page-stack">
    <header class="page-header">
      <div>
        <h1>解析管理</h1>
        <p class="resource-note">集中查看和维护所有用户的解析记录，后台操作会直接同步 DNS 平台。</p>
      </div>
      <el-button type="primary" @click="openCreate"><CirclePlus :size="17" />新增解析</el-button>
    </header>

    <div class="toolbar-row">
      <el-select v-model="filters.did" clearable placeholder="主域" class="toolbar-control">
        <el-option v-for="domain in domains" :key="domain.id" :label="domain.domain" :value="domain.id" />
      </el-select>
      <el-select v-model="filters.subdomain_id" clearable filterable placeholder="二级域名" class="toolbar-control">
        <el-option v-for="item in subdomains" :key="item.id" :label="item.full_domain" :value="item.id" />
      </el-select>
      <el-select v-model="filters.uid" clearable placeholder="用户" class="toolbar-control">
        <el-option v-for="user in users" :key="user.id" :label="user.username" :value="user.id" />
      </el-select>
      <el-select v-model="filters.type" clearable placeholder="类型" class="toolbar-control">
        <el-option v-for="type in allRecordTypes" :key="type" :label="type" :value="type">
          <div class="record-type-option">
            <strong>{{ type }}</strong>
            <span>{{ recordTypeUsage(type) }}</span>
          </div>
        </el-option>
      </el-select>
      <el-input v-model="keyword" clearable placeholder="搜索记录、解析值或用户" class="toolbar-control wide-admin-search" />
      <el-button type="primary" @click="search">查询</el-button>
      <el-button @click="resetFilters">重置</el-button>
    </div>

    <div class="resource-card">
      <el-table v-loading="loading" :data="records" class="responsive-table">
        <el-table-column prop="id" label="ID" width="82" />
        <el-table-column label="用户" min-width="130" show-overflow-tooltip>
          <template #default="{ row }">{{ row.username || `UID ${row.uid}` }}</template>
        </el-table-column>
        <el-table-column label="主域" min-width="170" show-overflow-tooltip>
          <template #default="{ row }">{{ row.domain || domainName(row.did) }}</template>
        </el-table-column>
        <el-table-column label="二级域名" min-width="190" show-overflow-tooltip>
          <template #default="{ row }">{{ row.subdomain || '-' }}</template>
        </el-table-column>
        <el-table-column label="解析记录" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            <div class="record-cell">
              <strong>{{ row.full_name || fullName(row) }}</strong>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="92">
          <template #default="{ row }">
            <el-tag class="compact-tag" effect="plain">{{ row.type }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="解析值" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            <code class="record-value">{{ row.value }}</code>
          </template>
        </el-table-column>
        <el-table-column prop="line" label="线路" width="110" />
        <el-table-column label="操作" width="118" fixed="right">
          <template #default="{ row }">
            <div class="table-actions">
              <el-button text type="primary" @click="openEdit(row)">编辑</el-button>
              <el-button text type="danger" @click="remove(row)">删除</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
      <div v-if="total > pageSize" class="resource-pagination">
        <span>共 {{ total }} 条</span>
        <el-pagination v-model:current-page="page" :page-size="pageSize" layout="prev, pager, next" :total="total" @current-change="load" />
        <el-select v-model="pageSize" class="page-size-select" @change="changePageSize">
          <el-option label="10 条/页" :value="10" />
          <el-option label="20 条/页" :value="20" />
        </el-select>
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="editing ? '编辑解析' : '新增解析'" width="min(620px, 94vw)">
      <el-form label-position="top">
        <div class="record-form-grid">
          <el-form-item label="所属用户">
            <el-select v-model="form.uid" :disabled="Boolean(editing)" class="full-control" placeholder="请选择用户">
              <el-option v-for="user in users" :key="user.id" :label="`${user.username}（${statusText(user.status)}）`" :value="user.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="主域">
            <el-select v-model="form.did" :disabled="Boolean(editing)" class="full-control" placeholder="请选择主域" @change="syncDomainForm">
              <el-option v-for="domain in domains" :key="domain.id" :label="domain.domain" :value="domain.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="主机记录">
            <el-input v-model="form.name" placeholder="@ 或 www" />
          </el-form-item>
          <el-form-item label="解析类型">
            <el-select v-model="form.type" class="full-control">
              <el-option v-for="type in currentTypes" :key="type" :label="type" :value="type">
                <div class="record-type-option">
                  <strong>{{ type }}</strong>
                  <span>{{ recordTypeUsage(type) }}</span>
                </div>
              </el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="解析值" class="record-value-item">
            <el-input v-model="form.value" />
          </el-form-item>
          <el-form-item label="线路">
            <el-select v-model="form.line_id" class="full-control">
              <el-option v-for="line in currentLineOptions" :key="line.id" :label="line.name" :value="line.id" />
            </el-select>
          </el-form-item>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup lang="ts">
import { apiErrorMessage } from '../../api/errors'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CirclePlus } from 'lucide-vue-next'
import {
  deleteAdminRecord,
  listAdminDomains,
  listAdminRecordsPage,
  listAdminSubdomains,
  listAdminUsers,
  saveAdminRecord,
  type AdminDomain,
  type AdminSubdomain,
  type AdminUser,
} from '../../api/admin'
import type { RecordItem } from '../../types/record'
import { recordTypeUsage } from '../../utils/recordTypes'

interface LineOption {
  id: string
  name: string
}

const users = ref<AdminUser[]>([])
const domains = ref<AdminDomain[]>([])
const subdomains = ref<AdminSubdomain[]>([])
const records = ref<RecordItem[]>([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const editing = ref<RecordItem | null>(null)
const keyword = ref('')
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const filters = reactive<{ did?: number; subdomain_id?: number; uid?: number; type?: string }>({})
const form = reactive({
  uid: 0,
  did: 0,
  name: '',
  type: 'A',
  value: '',
  line_id: '0',
})

const allRecordTypes = computed(() => Array.from(new Set(domains.value.flatMap((domain) => domainTypes(domain)))).sort())
const currentTypes = computed(() => domainTypes(domains.value.find((domain) => domain.id === form.did)))
const currentLineOptions = computed(() => {
  const options = lineOptions(editing.value)
  if (!options.some((line) => line.id === form.line_id)) {
    return [...options, { id: form.line_id, name: editing.value?.line || form.line_id }]
  }
  return options
})
onMounted(load)

async function load() {
  loading.value = true
  try {
    const [recordResponse, domainResponse, userResponse, subdomainResponse] = await Promise.all([
      listAdminRecordsPage({ ...filterParams(), page: page.value, page_size: pageSize.value }),
      listAdminDomains(),
      listAdminUsers(),
      listAdminSubdomains(),
    ])
    records.value = recordResponse.data.items
    total.value = recordResponse.data.total
    domains.value = domainResponse.data
    users.value = userResponse.data
    subdomains.value = subdomainResponse.data
  } finally {
    loading.value = false
  }
}

async function search() {
  page.value = 1
  await load()
}

function openCreate() {
  editing.value = null
  const domain = domains.value[0]
  const user = users.value.find((item) => item.status !== 0) || users.value[0]
  Object.assign(form, {
    uid: user?.id || 0,
    did: domain?.id || 0,
    name: '',
    type: domainTypes(domain)[0] || 'A',
    value: '',
    line_id: '0',
  })
  dialogVisible.value = true
}

function openEdit(row: RecordItem) {
  editing.value = row
  Object.assign(form, {
    uid: row.uid || 0,
    did: row.did,
    name: row.name,
    type: row.type,
    value: row.value,
    line_id: row.line_id || '0',
  })
  dialogVisible.value = true
}

function syncDomainForm() {
  if (!currentTypes.value.includes(form.type)) {
    form.type = currentTypes.value[0] || 'A'
  }
  form.line_id = '0'
}

async function save() {
  if (!form.uid || !form.did || !form.name.trim() || !form.type || !form.value.trim()) {
    ElMessage.warning('请完整填写解析记录')
    return
  }
  saving.value = true
  try {
    await saveAdminRecord({
      id: editing.value?.id,
      uid: form.uid,
      did: form.did,
      name: form.name.trim(),
      type: form.type,
      value: form.value.trim(),
      line_id: form.line_id,
    })
    ElMessage.success('解析记录已保存')
    dialogVisible.value = false
    await load()
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '保存解析记录失败'))
  } finally {
    saving.value = false
  }
}

async function remove(row: RecordItem) {
  try {
    await ElMessageBox.confirm(`确认删除 ${row.full_name || fullName(row)}？`, '删除解析', { type: 'warning' })
    await deleteAdminRecord(row.id)
    ElMessage.success('解析记录已删除')
    await load()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(apiErrorMessage(error, '删除解析记录失败'))
    }
  }
}

function resetFilters() {
  filters.did = undefined
  filters.subdomain_id = undefined
  filters.uid = undefined
  filters.type = ''
  keyword.value = ''
  page.value = 1
  void load()
}

function changePageSize() {
  page.value = 1
  void load()
}

function filterParams() {
  return {
    did: filters.did,
    subdomain_id: filters.subdomain_id,
    uid: filters.uid,
    type: filters.type || undefined,
    keyword: keyword.value.trim() || undefined,
  }
}

function domainTypes(domain?: AdminDomain) {
  return (domain?.record_types || 'A,CNAME')
    .split(',')
    .map((type) => type.trim().toUpperCase())
    .filter(Boolean)
}

function lineOptions(record?: RecordItem | null): LineOption[] {
  const options: LineOption[] = [{ id: '0', name: '默认' }]
  if (record?.line_id && record.line_id !== '0') {
    options.push({ id: record.line_id, name: record.line || record.line_id })
  }
  return options
}

function domainName(did: number) {
  return domains.value.find((domain) => domain.id === did)?.domain || `主域 ${did}`
}

function fullName(record: RecordItem) {
  const name = record.name === '@' ? '' : record.name
  const domain = record.domain || domainName(record.did)
  return name ? `${name}.${domain}` : domain
}

function statusText(status: number) {
  if (status === 2) return '已审核'
  if (status === 1) return '待审核'
  return '禁用'
}

</script>

<style scoped>
.wide-admin-search {
  flex: 0 0 240px;
  width: 240px;
}

.record-cell {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.record-cell strong {
  overflow: hidden;
  color: var(--text);
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.record-cell span {
  overflow: hidden;
  color: var(--muted);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.record-value {
  color: var(--text);
  font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  font-size: 12px;
  white-space: nowrap;
}

.record-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.record-value-item {
  grid-column: 1 / -1;
}

@media (max-width: 980px) {
  .wide-admin-search {
    flex: 1 1 100%;
    width: 100%;
  }
}

@media (max-width: 680px) {
  .record-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
