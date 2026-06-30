<template>
  <section class="page-stack">
    <header class="page-header">
      <div>
        <h1>操作日志</h1>
        <p class="resource-note">审计用户操作、域名注册、DNS 同步和系统关键变更。</p>
      </div>
    </header>
    <div class="toolbar-row">
      <el-select v-model="filters.source" clearable placeholder="来源" class="toolbar-control">
        <el-option v-for="source in sourceOptions" :key="source" :label="source" :value="source" />
      </el-select>
      <el-select v-model="filters.action" clearable placeholder="动作" class="toolbar-control">
        <el-option v-for="action in actionOptions" :key="action" :label="action" :value="action" />
      </el-select>
      <el-input v-model="filters.keyword" clearable placeholder="搜索目标或说明" class="toolbar-control wide-admin-search" />
      <el-button type="primary" @click="search">查询</el-button>
      <el-button @click="resetFilters">重置</el-button>
    </div>
    <div class="resource-card">
      <el-table v-loading="loading" :data="logs" class="responsive-table">
        <el-table-column prop="id" label="ID" width="110" />
        <el-table-column label="来源" width="130">
          <template #default="{ row }">
            <el-tag class="compact-tag" effect="plain">{{ row.source || '-' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作用户" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ operatorName(row) }}</template>
        </el-table-column>
        <el-table-column label="动作" width="150">
          <template #default="{ row }">
            <el-tag class="compact-tag" :type="actionTagType(row.action)">{{ row.action || '-' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="target_type" label="目标" width="140" />
        <el-table-column prop="message" label="说明" min-width="280" show-overflow-tooltip />
        <el-table-column label="时间" width="190">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
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
import { computed, onMounted, reactive, ref } from 'vue'
import { listLogsPage, type LogItem } from '../../api/admin'

const logs = ref<LogItem[]>([])
const loading = ref(false)
const filters = reactive({ source: '', action: '', keyword: '' })
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)

const sourceOptions = computed(() => Array.from(new Set(logs.value.map((item) => item.source).filter(Boolean))).sort())
const actionOptions = computed(() => Array.from(new Set(logs.value.map((item) => item.action).filter(Boolean))).sort())

onMounted(load)

async function load() {
  loading.value = true
  try {
    const response = await listLogsPage({ ...filterParams(), page: page.value, page_size: pageSize.value })
    logs.value = response.data.items
    total.value = response.data.total
  } finally {
    loading.value = false
  }
}

async function search() {
  page.value = 1
  await load()
}

function formatTime(value: number) {
  return value ? new Date(value * 1000).toLocaleString() : '-'
}

function actionTagType(action: string) {
  if (['delete', 'reject', 'failed'].includes(action)) return 'danger'
  if (['approve', 'create', 'sync'].includes(action)) return 'success'
  if (['update', 'login'].includes(action)) return 'warning'
  return 'info'
}

function resetFilters() {
  filters.source = ''
  filters.action = ''
  filters.keyword = ''
  page.value = 1
  void load()
}

function changePageSize() {
  page.value = 1
  void load()
}

function filterParams() {
  return {
    source: filters.source || undefined,
    action: filters.action || undefined,
    keyword: filters.keyword.trim() || undefined,
  }
}

function operatorName(log: LogItem) {
  if (log.admin_uid) return log.admin_username || `管理员 ${log.admin_uid}`
  if (log.uid) return log.username || `用户 ${log.uid}`
  return '系统'
}
</script>

<style scoped>
.wide-admin-search {
  width: 320px;
}

</style>
