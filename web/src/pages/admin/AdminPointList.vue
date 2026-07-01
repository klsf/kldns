<template>
  <section class="page-stack admin-points-page">
    <header class="page-header">
      <div>
        <h1>积分明细</h1>
        <p class="resource-note">查看用户积分增加、扣除和域名注册消费记录。</p>
      </div>
    </header>

    <div class="toolbar-row point-toolbar">
      <el-select v-model="filters.uid" clearable filterable placeholder="用户" class="toolbar-control">
        <el-option v-for="user in users" :key="user.id" :label="user.username" :value="user.id" />
      </el-select>
      <el-select v-model="filters.admin_uid" clearable filterable placeholder="操作人" class="toolbar-control">
        <el-option v-for="user in adminUsers" :key="user.id" :label="user.username" :value="user.id" />
      </el-select>
      <el-select v-model="filters.change" clearable placeholder="变动方向" class="toolbar-control small-control">
        <el-option label="增加" value="increase" />
        <el-option label="扣除" value="decrease" />
      </el-select>
      <el-select v-model="filters.action" clearable placeholder="类型" class="toolbar-control small-control">
        <el-option v-for="action in actionOptions" :key="action" :label="action" :value="action" />
      </el-select>
      <el-input v-model="filters.keyword" clearable placeholder="搜索用户、操作人或说明" class="toolbar-control wide-admin-search" @keyup.enter="search">
        <template #prefix>
          <Search :size="16" />
        </template>
      </el-input>
      <el-button type="primary" @click="search">搜索</el-button>
      <el-button @click="resetFilters">重置</el-button>
    </div>

    <div class="resource-card point-record-resource">
      <el-table v-loading="loading" :data="records" class="responsive-table points-admin-table">
        <el-table-column label="时间" min-width="168">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="用户" min-width="130" show-overflow-tooltip>
          <template #default="{ row }">{{ userName(row) }}</template>
        </el-table-column>
        <el-table-column label="操作人" min-width="130" show-overflow-tooltip>
          <template #default="{ row }">{{ adminName(row) }}</template>
        </el-table-column>
        <el-table-column label="类型" width="112">
          <template #default="{ row }">
            <el-tag class="compact-tag" :type="tagType(row.points)">{{ row.action }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="积分变动" width="120">
          <template #default="{ row }">
            <span :class="['point-change', row.points >= 0 ? 'positive' : 'negative']">{{ formatPoints(row.points) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="rest" label="剩余积分" width="120" />
        <el-table-column prop="remark" label="说明" min-width="260" show-overflow-tooltip />
      </el-table>

      <div v-loading="loading" class="points-admin-mobile-list">
        <article v-for="record in records" :key="record.id" class="point-record-item">
          <div class="record-item-head">
            <el-tag class="compact-tag" :type="tagType(record.points)">{{ record.action }}</el-tag>
            <span :class="['point-change', record.points >= 0 ? 'positive' : 'negative']">{{ formatPoints(record.points) }}</span>
          </div>
          <div class="record-subject">
            <strong>{{ userName(record) }}</strong>
            <span>{{ adminName(record) }}</span>
          </div>
          <div class="record-item-meta">
            <span>{{ formatTime(record.created_at) }}</span>
            <span>余额 {{ record.rest }}</span>
          </div>
          <p>{{ record.remark || '无说明' }}</p>
        </article>
      </div>

      <el-empty v-if="!loading && records.length === 0" description="暂无积分明细" />

      <div v-if="total > pageSize" class="resource-pagination">
        <span>共 {{ total }} 条</span>
        <el-pagination v-model:current-page="page" :page-size="pageSize" layout="prev, pager, next" :total="total" @current-change="loadRecords" />
        <el-select v-model="pageSize" class="page-size-select" @change="changePageSize">
          <el-option label="20 条/页" :value="20" />
          <el-option label="50 条/页" :value="50" />
        </el-select>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Search } from 'lucide-vue-next'
import { ElMessage } from 'element-plus'
import { apiErrorMessage } from '../../api/errors'
import { listAdminPointsPage, listAdminUsers, type AdminPointRecord, type AdminUser } from '../../api/admin'

type PointChangeFilter = '' | 'increase' | 'decrease'

const route = useRoute()
const records = ref<AdminPointRecord[]>([])
const users = ref<AdminUser[]>([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const actionOptions = ['后台增加', '后台扣除', '消费', '充值']
const filters = reactive({
  uid: undefined as number | undefined,
  admin_uid: undefined as number | undefined,
  action: '',
  change: '' as PointChangeFilter,
  keyword: '',
})
const adminUsers = computed(() => users.value.filter((user) => user.group_id === 99))

onMounted(async () => {
  applyRouteQuery()
  await Promise.all([loadUsers(), loadRecords()])
})

async function loadUsers() {
  try {
    const response = await listAdminUsers()
    users.value = response.data
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '获取用户列表失败'))
  }
}

async function loadRecords() {
  loading.value = true
  try {
    const response = await listAdminPointsPage({ ...filterParams(), page: page.value, page_size: pageSize.value })
    records.value = response.data.items
    total.value = response.data.total
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '获取积分明细失败'))
  } finally {
    loading.value = false
  }
}

async function search() {
  page.value = 1
  await loadRecords()
}

function resetFilters() {
  Object.assign(filters, {
    uid: undefined,
    admin_uid: undefined,
    action: '',
    change: '' as PointChangeFilter,
    keyword: '',
  })
  page.value = 1
  void loadRecords()
}

function changePageSize() {
  page.value = 1
  void loadRecords()
}

function filterParams() {
  return {
    uid: filters.uid,
    admin_uid: filters.admin_uid,
    action: filters.action || undefined,
    change: filters.change || undefined,
    keyword: filters.keyword.trim() || undefined,
  }
}

function applyRouteQuery() {
  const rawUID = Number(route.query.uid)
  if (Number.isInteger(rawUID) && rawUID > 0) {
    filters.uid = rawUID
  }
}

function userName(record: AdminPointRecord) {
  return record.username || `用户 ${record.uid}`
}

function adminName(record: AdminPointRecord) {
  if (!record.admin_uid) return '系统'
  return record.admin_username || `管理员 ${record.admin_uid}`
}

function formatTime(value: number) {
  if (!value) return '-'
  const date = new Date(value * 1000)
  const pad = (part: number) => String(part).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function formatPoints(value: number) {
  return value > 0 ? `+${value}` : String(value)
}

function tagType(points: number): 'success' | 'danger' | 'info' {
  if (points > 0) return 'success'
  if (points < 0) return 'danger'
  return 'info'
}
</script>

<style scoped>
.point-toolbar {
  align-items: center;
}

.small-control {
  flex: 0 0 130px;
  width: 130px;
}

.wide-admin-search {
  flex: 0 0 240px;
  width: 240px;
}

.point-record-resource {
  min-height: 340px;
}

.point-change {
  font-weight: 900;
}

.point-change.positive {
  color: var(--accent-strong);
}

.point-change.negative {
  color: #d92d20;
}

.points-admin-mobile-list {
  display: none;
}

@media (max-width: 980px) {
  .small-control,
  .wide-admin-search {
    flex: 1 1 100%;
    width: 100%;
  }
}

@media (max-width: 760px) {
  .points-admin-table {
    display: none;
  }

  .points-admin-mobile-list {
    min-height: 150px;
    display: grid;
    gap: 10px;
    padding: 14px;
  }

  .point-record-item {
    display: grid;
    gap: 9px;
    padding: 13px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #ffffff;
  }

  .record-item-head,
  .record-item-meta,
  .record-subject {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

  .record-subject {
    align-items: flex-start;
  }

  .record-subject strong,
  .record-subject span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .record-subject strong {
    color: #17282d;
  }

  .record-subject span,
  .record-item-meta {
    color: var(--muted);
    font-size: 12px;
  }

  .point-record-item p {
    margin: 0;
    color: #182327;
    line-height: 1.6;
    overflow-wrap: anywhere;
  }
}
</style>
