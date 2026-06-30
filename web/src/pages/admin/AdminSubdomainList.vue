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
        <el-table-column prop="record_count" label="解析数" width="100" />
        <el-table-column prop="registration_cost" label="注册积分" width="110" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag class="compact-tag" :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '正常' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="90" fixed="right">
          <template #default="{ row }">
            <div class="table-actions">
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
  </section>
</template>

<script setup lang="ts">
import { apiErrorMessage } from '../../api/errors'
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { deleteAdminSubdomain, listAdminDomains, listAdminSubdomainsPage, type AdminDomain, type AdminSubdomain } from '../../api/admin'

const items = ref<AdminSubdomain[]>([])
const domains = ref<AdminDomain[]>([])
const loading = ref(false)
const keyword = ref('')
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const filters = reactive<{ did?: number }>({})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const [subdomainResponse, domainResponse] = await Promise.all([
      listAdminSubdomainsPage({
        did: filters.did,
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
  keyword.value = ''
  page.value = 1
  void load()
}

function changePageSize() {
  page.value = 1
  void load()
}

async function remove(row: AdminSubdomain) {
  try {
    await ElMessageBox.confirm(`此操作会删除该二级域名下本地和平台上的全部解析记录，是否删除 ${row.full_domain}？`, '删除二级域名', {
      confirmButtonText: '确认删除',
      cancelButtonText: '取消',
      type: 'warning',
    })
    const response = await deleteAdminSubdomain(row.id)
    ElMessage.success(`二级域名已删除，已删除 ${response.data.records_deleted} 条解析记录`)
    await load()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(apiErrorMessage(error, '删除二级域名失败'))
    }
  }
}

</script>

<style scoped>
.wide-search {
  flex: 1 1 280px;
}

</style>
