<template>
  <section class="page-stack">
    <header class="page-header">
      <div>
        <h1>运行概览</h1>
      </div>
      <div class="dashboard-actions">
        <el-button type="primary" @click="$router.push('/admin/domains')"><Globe2 :size="17" />新增主域</el-button>
      </div>
    </header>
    <div v-loading="loading" class="metric-grid">
      <article><span>二级域名</span><strong>{{ subdomains.length }}</strong><small>已注册</small></article>
      <article><span>主域</span><strong>{{ domains.length }}</strong></article>
      <article><span>用户</span><strong>{{ users.length }}</strong></article>
      <article><span>解析记录</span><strong>{{ records.length }}</strong></article>
    </div>

    <div class="ops-grid">
      <section class="ops-panel subdomain-panel">
        <div class="panel-head">
          <div>
            <h2>最新注册域名</h2>
          </div>
          <el-button text type="primary" @click="$router.push('/admin/subdomains')">查看全部</el-button>
        </div>
        <div class="queue-list">
          <div v-for="item in subdomainPreview" :key="item.id" class="queue-row">
            <span>{{ item.username || `UID ${item.uid}` }}</span>
            <strong>{{ item.full_domain || `${item.name}.${item.domain}` }}</strong>
            <small>{{ item.record_count }} 条</small>
          </div>
          <div v-if="subdomainPreview.length === 0" class="queue-empty">
            <ShieldCheck :size="22" />
            <span>暂无注册域名</span>
          </div>
        </div>
      </section>

      <section class="ops-panel">
        <div class="panel-head">
          <div>
            <h2>操作日志</h2>
          </div>
          <el-button text type="primary" @click="$router.push('/admin/logs')">查看全部</el-button>
        </div>
        <ul class="audit-list">
          <li v-for="log in logPreview" :key="log.id">
            <span>{{ operatorName(log) }}</span>
            <strong>{{ log.message }}</strong>
            <small>{{ log.action }}</small>
          </li>
          <li v-if="logPreview.length === 0">
            <span>SYSTEM</span>
            <strong>暂无操作日志</strong>
            <small>系统会记录关键变更</small>
          </li>
        </ul>
      </section>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Globe2, ShieldCheck } from 'lucide-vue-next'
import { listAdminDomains, listAdminRecords, listAdminSubdomains, listAdminUsers, listLogs, type AdminDomain, type AdminSubdomain, type AdminUser, type LogItem } from '../../api/admin'
import type { RecordItem } from '../../types/record'

const users = ref<AdminUser[]>([])
const domains = ref<AdminDomain[]>([])
const records = ref<RecordItem[]>([])
const subdomains = ref<AdminSubdomain[]>([])
const logs = ref<LogItem[]>([])
const loading = ref(false)
const dashboardListLimit = 6
const subdomainPreview = computed(() => [...subdomains.value].sort((left, right) => right.created_at - left.created_at).slice(0, dashboardListLimit))
const logPreview = computed(() => logs.value.slice(0, dashboardListLimit))

onMounted(async () => {
  loading.value = true
  try {
    const [userResponse, domainResponse, recordResponse, subdomainResponse, logResponse] = await Promise.all([
      listAdminUsers(),
      listAdminDomains(),
      listAdminRecords(),
      listAdminSubdomains(),
      listLogs(),
    ])
    users.value = userResponse.data
    domains.value = domainResponse.data
    records.value = recordResponse.data
    subdomains.value = subdomainResponse.data
    logs.value = logResponse.data
  } finally {
    loading.value = false
  }
})

function operatorName(log: LogItem) {
  if (log.admin_uid) return log.admin_username || `管理员 ${log.admin_uid}`
  if (log.uid) return log.username || `用户 ${log.uid}`
  return '系统'
}
</script>

<style scoped>
.dashboard-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.metric-grid small {
  display: block;
  margin-top: 8px;
  color: var(--muted);
}

.ops-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  align-items: stretch;
  gap: 20px;
}

.ops-panel {
  min-width: 0;
  min-height: 456px;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  padding: 22px;
}

.panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}

.panel-head h2 {
  margin: 0;
  color: #17282d;
  font-size: 18px;
}

.panel-head > span {
  color: var(--muted);
  font-size: 13px;
}

.queue-list {
  min-height: 0;
  display: grid;
  gap: 10px;
  align-content: start;
}

.queue-row {
  min-height: 46px;
  display: grid;
  grid-template-columns: minmax(90px, 0.8fr) minmax(0, 1.5fr) auto;
  align-items: center;
  gap: 10px;
  padding: 12px 0;
  border-bottom: 1px solid var(--line);
}

.queue-row strong {
  color: var(--accent-blue);
}

.queue-row small {
  color: var(--muted);
}

.queue-empty {
  min-height: 214px;
  display: grid;
  place-items: center;
  gap: 8px;
  color: var(--muted);
  border: 1px dashed var(--line-strong);
  border-radius: 8px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(247, 251, 251, 0.92));
}

.queue-empty svg {
  color: #8ca2a8;
}

.audit-list {
  min-height: 0;
  display: grid;
  gap: 10px;
  align-content: start;
  margin: 0;
  padding: 0;
  list-style: none;
}

.audit-list li {
  min-height: 46px;
  display: grid;
  grid-template-columns: minmax(0, 112px) minmax(0, 1fr) minmax(72px, auto);
  align-items: center;
  gap: 12px;
  padding: 13px 0;
  border-bottom: 1px solid var(--line);
}

.audit-list span {
  min-width: 0;
  overflow: hidden;
  color: var(--accent-strong);
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.audit-list strong {
  min-width: 0;
  overflow: hidden;
  color: #233338;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.audit-list small {
  min-width: 0;
  overflow: hidden;
  color: var(--muted);
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 1180px) {
  .ops-grid {
    grid-template-columns: 1fr;
  }

  .ops-panel {
    min-height: 456px;
  }
}

@media (max-width: 620px) {
  .dashboard-actions,
  .dashboard-actions .el-button {
    width: 100%;
  }

  .queue-row,
  .audit-list li {
    grid-template-columns: 1fr;
  }

  .audit-list small {
    text-align: left;
  }
}
</style>
