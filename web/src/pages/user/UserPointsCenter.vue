<template>
  <section class="page-stack points-center-page">
    <header class="page-header">
      <div>
        <h1>积分中心</h1>
        <p class="resource-note">查看当前剩余积分和域名注册消费明细。</p>
      </div>
    </header>

    <section class="points-overview">
      <article class="balance-card">
        <div class="metric-icon">
          <Coins :size="28" />
        </div>
        <span>当前剩余积分</span>
        <strong>{{ overview.balance }}</strong>
      </article>
      <article class="summary-card">
        <div class="metric-icon blue">
          <ReceiptText :size="22" />
        </div>
        <span>本月消费</span>
        <strong>{{ overview.month_spent }}</strong>
      </article>
      <article class="summary-card">
        <div class="metric-icon blue">
          <TrendingDown :size="22" />
        </div>
        <span>累计消费</span>
        <strong>{{ overview.total_spent }}</strong>
      </article>
    </section>

    <section class="resource-card point-record-card">
      <div class="table-card-head">
        <h2>积分明细</h2>
        <div class="point-filter-row">
          <el-select v-model="actionFilter" class="filter-control" placeholder="全部类型">
            <el-option label="全部类型" value="" />
            <el-option v-for="action in actions" :key="action" :label="action" :value="action" />
          </el-select>
          <el-select v-model="rangeFilter" class="filter-control" placeholder="最近 30 天">
            <el-option label="全部时间" value="all" />
            <el-option label="最近 7 天" value="7" />
            <el-option label="最近 30 天" value="30" />
            <el-option label="最近 90 天" value="90" />
          </el-select>
          <el-input v-model="keyword" clearable class="search-control" placeholder="搜索说明">
            <template #prefix>
              <Search :size="16" />
            </template>
          </el-input>
        </div>
      </div>

      <el-table v-loading="loading" :data="filteredRecords" class="responsive-table points-table">
        <el-table-column label="时间" min-width="170">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="类型" width="110">
          <template #default="{ row }">
            <el-tag class="compact-tag" :type="tagType(row)">{{ row.action }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="积分变动" width="120">
          <template #default="{ row }">
            <span :class="['point-change', row.points >= 0 ? 'positive' : 'negative']">{{ formatPoints(row.points) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="rest" label="剩余积分" width="120" />
        <el-table-column prop="remark" label="说明" min-width="240" show-overflow-tooltip />
      </el-table>

      <div v-loading="loading" class="points-mobile-list">
        <article v-for="record in filteredRecords" :key="record.id" class="point-record-item">
          <div class="record-item-head">
            <el-tag class="compact-tag" :type="tagType(record)">{{ record.action }}</el-tag>
            <span :class="['point-change', record.points >= 0 ? 'positive' : 'negative']">{{ formatPoints(record.points) }}</span>
          </div>
          <div class="record-item-meta">
            <span>{{ formatTime(record.created_at) }}</span>
            <span>余额 {{ record.rest }}</span>
          </div>
          <p>{{ record.remark || '无说明' }}</p>
        </article>
      </div>

      <el-empty v-if="!loading && filteredRecords.length === 0" description="暂无积分明细" />
    </section>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Coins, ReceiptText, Search, TrendingDown } from 'lucide-vue-next'
import { apiErrorMessage } from '../../api/errors'
import { pointsOverview, type PointRecord, type PointsOverview } from '../../api/points'
import { useAuthStore } from '../../app/stores/auth'
import { ElMessage } from 'element-plus'

const auth = useAuthStore()
const loading = ref(false)
const keyword = ref('')
const actionFilter = ref('')
const rangeFilter = ref('30')
const overview = reactive<PointsOverview>({
  balance: auth.user?.points ?? 0,
  month_spent: 0,
  total_spent: 0,
  recent_records: [],
})

const actions = computed(() => Array.from(new Set(overview.recent_records.map((record) => record.action).filter(Boolean))))
const filteredRecords = computed(() => {
  const term = keyword.value.trim().toLowerCase()
  const since = rangeSince(rangeFilter.value)
  return overview.recent_records.filter((record) => {
    if (actionFilter.value && record.action !== actionFilter.value) return false
    if (since && record.created_at < since) return false
    if (term && !`${record.action} ${record.remark}`.toLowerCase().includes(term)) return false
    return true
  })
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const response = await pointsOverview()
    Object.assign(overview, response.data)
    auth.updatePoints(response.data.balance)
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '获取积分明细失败'))
  } finally {
    loading.value = false
  }
}

function rangeSince(value: string) {
  if (value === 'all') return 0
  const days = Number(value)
  if (!Number.isFinite(days) || days <= 0) return 0
  return Math.floor(Date.now() / 1000) - days * 86400
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

function tagType(record: PointRecord): 'success' | 'danger' | 'info' {
  if (record.points > 0) return 'success'
  if (record.points < 0) return 'danger'
  return 'info'
}
</script>

<style scoped>
.points-center-page {
  gap: 18px;
}

.points-overview {
  display: grid;
  grid-template-columns: minmax(260px, 1.4fr) repeat(2, minmax(180px, 1fr));
  gap: 16px;
}

.balance-card,
.summary-card {
  min-width: 0;
  min-height: 132px;
  display: grid;
  align-content: center;
  gap: 8px;
  padding: 24px 24px 22px 104px;
  position: relative;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: var(--shadow-strong);
}

.summary-card {
  min-height: 118px;
  padding-left: 86px;
}

.metric-icon {
  position: absolute;
  left: 26px;
  top: 50%;
  width: 58px;
  height: 58px;
  display: grid;
  place-items: center;
  transform: translateY(-50%);
  border-radius: 50%;
  color: var(--accent-strong);
  background: #dff9f0;
}

.summary-card .metric-icon {
  left: 22px;
  width: 48px;
  height: 48px;
}

.metric-icon.blue {
  color: #1674d1;
  background: #e5f1ff;
}

.balance-card span,
.summary-card span {
  color: var(--muted);
  font-weight: 800;
}

.balance-card strong,
.summary-card strong {
  color: #101d21;
  font-size: 42px;
  line-height: 1;
}

.summary-card strong {
  font-size: 32px;
}

.point-record-card {
  padding: 20px 22px 0;
}

.table-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}

.table-card-head h2 {
  margin: 0;
  color: #132124;
  font-size: 18px;
  line-height: 1.3;
}

.point-filter-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
}

.filter-control {
  width: 142px;
}

.search-control {
  width: 240px;
}

.points-table {
  margin: 0 -22px;
  width: calc(100% + 44px);
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

.points-mobile-list {
  display: none;
}

@media (max-width: 980px) {
  .points-overview {
    grid-template-columns: 1fr 1fr;
  }

  .balance-card {
    grid-column: 1 / -1;
  }

  .table-card-head {
    align-items: stretch;
    flex-direction: column;
  }

  .point-filter-row {
    justify-content: flex-start;
  }
}

@media (max-width: 680px) {
  .points-overview {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .balance-card,
  .summary-card {
    min-height: 104px;
    padding: 18px 18px 16px 86px;
  }

  .metric-icon {
    left: 20px;
    width: 50px;
    height: 50px;
  }

  .summary-card .metric-icon {
    left: 20px;
  }

  .balance-card strong,
  .summary-card strong {
    font-size: 30px;
  }

  .point-record-card {
    padding: 16px;
  }

  .point-filter-row,
  .filter-control,
  .search-control {
    width: 100%;
  }

  .points-table {
    display: none;
  }

  .points-mobile-list {
    min-height: 120px;
    display: grid;
    gap: 10px;
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
  .record-item-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }

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
