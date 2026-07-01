<template>
  <section class="page-stack">
    <header class="page-header">
      <div>
        <h1>二级域名</h1>
        <p class="resource-note">查看用户已注册的二级域名归属、主域和解析数量。</p>
      </div>
      <el-button @click="load">刷新</el-button>
    </header>

    <div class="toolbar-row">
      <el-select v-model="filters.did" clearable placeholder="主域" class="toolbar-control">
        <el-option v-for="domain in domains" :key="domain.id" :label="domain.domain" :value="domain.id" />
      </el-select>
      <el-select v-model="filters.status" clearable placeholder="状态" class="toolbar-control">
        <el-option label="待审核" :value="2" />
        <el-option label="正常" :value="1" />
        <el-option label="已驳回" :value="3" />
        <el-option label="停用" :value="0" />
      </el-select>
      <el-input v-model="keyword" clearable placeholder="搜索域名或用户" class="toolbar-control wide-search" />
      <el-button type="primary" @click="search">查询</el-button>
      <el-button @click="resetFilters">重置</el-button>
    </div>

    <div class="resource-card">
      <el-table v-loading="loading" :data="items" class="responsive-table">
        <el-table-column prop="id" label="ID" width="82" />
        <el-table-column label="二级域名" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            <strong>{{ row.full_domain }}</strong>
          </template>
        </el-table-column>
        <el-table-column prop="domain" label="主域" min-width="160" show-overflow-tooltip />
        <el-table-column label="用户" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ row.username || `UID ${row.uid}` }}</template>
        </el-table-column>
        <el-table-column prop="purpose" label="用途" min-width="220" show-overflow-tooltip />
        <el-table-column label="驳回原因" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">{{ row.reject_reason || '-' }}</template>
        </el-table-column>
        <el-table-column prop="record_count" label="解析数" width="100" />
        <el-table-column prop="registration_cost" label="注册积分" width="110" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag class="compact-tag" :type="statusType(row.status)">{{ statusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="156" fixed="right">
          <template #default="{ row }">
            <div class="table-actions">
              <template v-if="row.status === 2">
                <el-button text type="primary" @click="approve(row)">通过</el-button>
                <el-button text type="danger" @click="reject(row)">驳回</el-button>
              </template>
              <el-button v-else text type="danger" @click="remove(row)">删除</el-button>
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
  </section>
</template>

<script setup lang="ts">
import { apiErrorMessage } from '../../api/errors'
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { approveAdminSubdomain, deleteAdminSubdomain, listAdminDomains, listAdminSubdomainsPage, rejectAdminSubdomain, type AdminDomain, type AdminSubdomain } from '../../api/admin'

const items = ref<AdminSubdomain[]>([])
const domains = ref<AdminDomain[]>([])
const loading = ref(false)
const keyword = ref('')
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const filters = reactive<{ did?: number; status?: number }>({})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const [subdomainResponse, domainResponse] = await Promise.all([
      listAdminSubdomainsPage({
        did: filters.did,
        status: filters.status,
        keyword: keyword.value.trim() || undefined,
        page: page.value,
        page_size: pageSize.value,
      }),
      listAdminDomains(),
    ])
    items.value = subdomainResponse.data.items
    total.value = subdomainResponse.data.total
    domains.value = domainResponse.data
  } finally {
    loading.value = false
  }
}

async function search() {
  page.value = 1
  await load()
}

function resetFilters() {
  filters.did = undefined
  filters.status = undefined
  keyword.value = ''
  page.value = 1
  void load()
}

function changePageSize() {
  page.value = 1
  void load()
}

async function approve(row: AdminSubdomain) {
  try {
    await ElMessageBox.confirm(`确认通过 ${row.full_domain} 的注册申请？`, '审核通过', {
      confirmButtonText: '确认通过',
      cancelButtonText: '取消',
      type: 'warning',
    })
    await approveAdminSubdomain(row.id)
    ElMessage.success('申请已通过')
    await load()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(apiErrorMessage(error, '审核失败'))
    }
  }
}

async function reject(row: AdminSubdomain) {
  try {
    const { value } = await ElMessageBox.prompt(`请输入驳回 ${row.full_domain} 的原因。驳回后将退回注册积分并释放该名称。`, '驳回申请', {
      confirmButtonText: '确认驳回',
      cancelButtonText: '取消',
      inputPlaceholder: '请填写驳回原因',
      inputPattern: /^.{1,200}$/,
      inputErrorMessage: '请输入 1-200 个字符的驳回原因',
      type: 'warning',
    })
    const response = await rejectAdminSubdomain(row.id, { reason: value.trim() })
    ElMessage.success(`申请已驳回，已退回 ${response.data.refund || 0} 积分`)
    await load()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(apiErrorMessage(error, '驳回失败'))
    }
  }
}

async function remove(row: AdminSubdomain) {
  try {
    const rejected = row.status === 3
    await ElMessageBox.confirm(rejected ? `确认删除 ${row.full_domain} 的驳回记录？` : `此操作会删除该二级域名下本地和平台上的全部解析记录，是否删除 ${row.full_domain}？`, rejected ? '删除驳回记录' : '删除二级域名', {
      confirmButtonText: '确认删除',
      cancelButtonText: '取消',
      type: 'warning',
    })
    const response = await deleteAdminSubdomain(row.id)
    ElMessage.success(rejected ? '驳回记录已删除' : `二级域名已删除，已删除 ${response.data.records_deleted} 条解析记录`)
    await load()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(apiErrorMessage(error, '删除二级域名失败'))
    }
  }
}

function statusText(status: number) {
  if (status === 1) return '正常'
  if (status === 2) return '待审核'
  if (status === 3) return '已驳回'
  return '停用'
}

function statusType(status: number): 'success' | 'warning' | 'info' | 'danger' {
  if (status === 1) return 'success'
  if (status === 2) return 'warning'
  if (status === 3) return 'danger'
  return 'info'
}

</script>

<style scoped>
.wide-search {
  flex: 0 0 240px;
  width: 240px;
}

@media (max-width: 980px) {
  .wide-search {
    flex: 1 1 100%;
    width: 100%;
  }
}
</style>
