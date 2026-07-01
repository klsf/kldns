<template>
  <section class="page-stack">
    <header class="page-header page-header-with-action">
      <div>
        <h1>域名解析</h1>
        <p class="resource-note">{{ policyNote }}</p>
      </div>
      <el-button type="primary" :disabled="subdomains.length === 0" @click="openCreate"><CirclePlus :size="17" />新增记录</el-button>
    </header>

    <div class="resource-card">
      <div class="toolbar-row record-filter-row">
        <el-select v-model="filters.subdomain_id" clearable placeholder="二级域名" class="toolbar-control" @change="loadRecords">
          <el-option v-for="item in subdomains" :key="item.id" :label="item.full_domain" :value="item.id" />
        </el-select>
        <el-select v-model="filters.type" clearable placeholder="类型" class="toolbar-control type-filter" @change="loadRecords">
          <el-option v-for="type in recordTypes" :key="type" :label="type" :value="type">
            <div class="record-type-option">
              <strong>{{ type }}</strong>
              <span>{{ recordTypeUsage(type) }}</span>
            </div>
          </el-option>
        </el-select>
        <el-input v-model="keyword" placeholder="搜索主机记录或记录值" class="toolbar-control wide-search" clearable />
        <el-button @click="loadRecords">刷新</el-button>
      </div>

      <el-table v-loading="loading" :data="pagedRecords" class="responsive-table">
        <el-table-column label="主机记录" min-width="130">
          <template #default="{ row }">{{ row.host || relativeHost(row) }}</template>
        </el-table-column>
        <el-table-column label="完整域名" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">{{ row.full_name || fullName(row) }}</template>
        </el-table-column>
        <el-table-column prop="type" label="类型" width="96" />
        <el-table-column prop="value" label="记录值" min-width="220" show-overflow-tooltip />
        <el-table-column prop="line" label="线路" width="110" />
        <el-table-column label="状态" width="104">
          <template #default>
            <span class="status-chip"><span class="status-dot" />已生效</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="118" fixed="right">
          <template #default="{ row }">
            <div class="table-actions">
              <el-button text type="primary" @click="openEdit(row)">编辑</el-button>
              <el-button text type="danger" @click="remove(row)">删除</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
      <div v-if="records.length > pageSize" class="resource-pagination">
        <span>共 {{ records.length }} 条</span>
        <el-pagination v-model:current-page="page" :page-size="pageSize" layout="prev, pager, next" :total="records.length" />
        <el-select v-model="pageSize" class="page-size-select">
          <el-option label="10 条/页" :value="10" />
          <el-option label="20 条/页" :value="20" />
        </el-select>
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="editing?.id ? '编辑记录' : '新增记录'" width="min(560px, 94vw)">
      <el-form label-position="top">
        <el-form-item label="二级域名">
          <el-select v-model="form.subdomain_id" :disabled="Boolean(editing)" class="full-control" placeholder="请选择二级域名" @change="syncSubdomainForm">
            <el-option v-for="item in subdomains" :key="item.id" :label="item.full_domain" :value="item.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="主机记录">
          <el-input v-model="form.name" :disabled="!dnsPolicy.unlimitedSubdomainRecords" :placeholder="hostPlaceholder">
            <template #append>.{{ currentSubdomain?.full_domain || 'example.com' }}</template>
          </el-input>
          <p v-if="!dnsPolicy.unlimitedSubdomainRecords" class="field-help">当前系统已关闭无限下级解析，只能维护已注册域名本身的解析记录。</p>
        </el-form-item>
        <el-form-item label="记录类型">
          <el-select v-model="form.type" class="full-control">
            <el-option v-for="type in currentTypes" :key="type" :label="type" :value="type">
              <div class="record-type-option">
                <strong>{{ type }}</strong>
                <span>{{ recordTypeUsage(type) }}</span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="记录值">
          <el-input v-model="form.value" />
        </el-form-item>
        <el-form-item label="线路">
          <el-select v-model="form.line_id" class="full-control" placeholder="请选择线路">
            <el-option v-for="line in currentLineOptions" :key="line.id" :label="line.name" :value="line.id" />
          </el-select>
        </el-form-item>
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
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CirclePlus } from 'lucide-vue-next'
import { createRecord, deleteRecord, listRecords, updateRecord, type RecordPayload } from '../../api/records'
import { getDNSPolicy } from '../../api/settings'
import { listSubdomains } from '../../api/subdomains'
import type { Subdomain } from '../../types/domain'
import type { RecordItem } from '../../types/record'
import { recordTypeUsage } from '../../utils/recordTypes'

const route = useRoute()
const subdomains = ref<Subdomain[]>([])
const records = ref<RecordItem[]>([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const editing = ref<RecordItem | null>(null)
const filters = reactive<{ subdomain_id?: number; type?: string }>({})
const keyword = ref('')
const page = ref(1)
const pageSize = ref(10)
const form = reactive<RecordPayload>({
  subdomain_id: 0,
  name: '',
  type: 'A',
  value: '',
  line_id: '0',
})
const dnsPolicy = reactive({ unlimitedSubdomainRecords: true })

const recordTypes = computed(() => Array.from(new Set(subdomains.value.flatMap((item) => item.record_types || []))).sort())
const currentSubdomain = computed(() => subdomains.value.find((item) => item.id === form.subdomain_id))
const currentTypes = computed(() => currentSubdomain.value?.record_types || recordTypes.value)
const currentLineOptions = computed(() => [{ id: '0', name: '默认' }])
const policyNote = computed(() =>
  dnsPolicy.unlimitedSubdomainRecords
    ? '选择已注册二级域名，维护其解析记录。'
    : '选择已注册域名，维护该域名本身不同解析类型的记录。',
)
const hostPlaceholder = computed(() => (dnsPolicy.unlimitedSubdomainRecords ? '@ 或 www' : '@'))
const pagedRecords = computed(() => records.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value))

watch(pageSize, () => {
  page.value = 1
})
onMounted(async () => {
  await loadDNSPolicy()
  await loadSubdomains()
  const queryID = Number(route.query.subdomain_id || 0)
  if (queryID && subdomains.value.some((item) => item.id === queryID)) filters.subdomain_id = queryID
  await loadRecords()
})

async function loadDNSPolicy() {
  const response = await getDNSPolicy()
  dnsPolicy.unlimitedSubdomainRecords = response.data.unlimited_subdomain_records
}

async function loadSubdomains() {
  const response = await listSubdomains({ status: 1 })
  subdomains.value = response.data
}

async function loadRecords() {
  page.value = 1
  loading.value = true
  try {
    const response = await listRecords({ ...filters, keyword: keyword.value.trim() || undefined })
    records.value = response.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  const selected = subdomains.value.find((item) => item.id === filters.subdomain_id) || subdomains.value[0]
  Object.assign(form, { subdomain_id: selected?.id || 0, name: defaultHostName(), type: selected?.record_types?.[0] || 'A', value: '', line_id: '0' })
  dialogVisible.value = true
}

function openEdit(row: RecordItem) {
  editing.value = row
  Object.assign(form, { subdomain_id: row.subdomain_id || 0, name: editableHostName(row), type: row.type, value: row.value, line_id: row.line_id || '0' })
  dialogVisible.value = true
}

function syncSubdomainForm() {
  if (!currentTypes.value.includes(form.type)) {
    form.type = currentTypes.value[0] || 'A'
  }
  if (!dnsPolicy.unlimitedSubdomainRecords) {
    form.name = '@'
  }
}

async function save() {
  const hostName = dnsPolicy.unlimitedSubdomainRecords ? form.name.trim() : '@'
  if (!form.subdomain_id || !hostName || !form.type || !form.value.trim()) {
    ElMessage.warning('请完整填写记录信息')
    return
  }
  saving.value = true
  try {
    const payload = { ...form, name: hostName, value: form.value.trim() }
    if (editing.value) {
      await updateRecord(editing.value.id, payload)
    } else {
      await createRecord(payload)
    }
    ElMessage.success('解析记录已保存')
    dialogVisible.value = false
    await loadRecords()
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '保存记录失败，请稍后重试'))
  } finally {
    saving.value = false
  }
}

function defaultHostName() {
  return dnsPolicy.unlimitedSubdomainRecords ? '' : '@'
}

function editableHostName(row: RecordItem) {
  if (!dnsPolicy.unlimitedSubdomainRecords) return '@'
  return row.host || relativeHost(row)
}

async function remove(row: RecordItem) {
  await ElMessageBox.confirm(`确认删除 ${row.full_name || fullName(row)}？`, '删除记录', { type: 'warning' })
  await deleteRecord(row.id)
  ElMessage.success('解析记录已删除')
  await loadRecords()
}

function relativeHost(row: RecordItem) {
  const subdomainName = row.subdomain_name || ''
  if (row.name === subdomainName) return '@'
  if (subdomainName && row.name.endsWith(`.${subdomainName}`)) return row.name.slice(0, -subdomainName.length - 1)
  return row.name
}

function fullName(record: RecordItem) {
  const domain = record.domain || ''
  if (!domain) return record.name
  return record.name === '@' ? domain : `${record.name}.${domain}`
}

</script>

<style scoped>
.wide-search {
  flex: 1 1 260px;
}

@media (min-width: 981px) {
  .record-filter-row {
    flex-wrap: nowrap;
    align-items: center;
  }

  .record-filter-row .toolbar-control {
    width: 190px;
    flex: 0 0 190px;
  }

  .record-filter-row .type-filter {
    width: 116px;
    flex-basis: 116px;
  }

  .record-filter-row .wide-search {
    min-width: 0;
    flex: 1 1 auto;
  }

  .record-filter-row .el-button {
    flex: 0 0 auto;
  }
}

.field-help {
  margin: 7px 0 0;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.6;
}
</style>
