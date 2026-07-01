<template>
  <section class="page-stack">
    <header class="page-header page-header-with-action">
      <div>
        <h1>域名列表</h1>
        <p class="resource-note">查看已注册的二级域名和解析数量。</p>
      </div>
      <el-button type="primary" @click="$router.push('/home/register')">注册域名</el-button>
    </header>

    <div class="resource-card">
      <el-table v-loading="loading" :data="pagedSubdomains" class="responsive-table">
        <el-table-column label="二级域名" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            <strong>{{ row.full_domain }}</strong>
          </template>
        </el-table-column>
        <el-table-column prop="domain" label="主域" min-width="160" show-overflow-tooltip />
        <el-table-column prop="record_count" label="解析数" width="100" />
        <el-table-column label="注册积分" width="110">
          <template #default="{ row }">{{ row.registration_cost }}</template>
        </el-table-column>
        <el-table-column label="状态" width="110">
          <template #default="{ row }">
            <el-tag class="compact-tag" :type="statusType(row.status)">{{ statusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="驳回原因" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">{{ row.reject_reason || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="174" fixed="right">
          <template #default="{ row }">
            <div class="table-actions">
              <el-button v-if="row.status === 1" text type="primary" @click="$router.push({ path: '/home/records', query: { subdomain_id: row.id } })">解析</el-button>
              <el-button v-if="row.status === 3" text type="primary" @click="reapply(row)">重新申请</el-button>
              <el-button text type="danger" @click="remove(row)">{{ deleteActionText(row.status) }}</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
      <div v-if="subdomains.length > pageSize" class="resource-pagination">
        <span>共 {{ subdomains.length }} 条</span>
        <el-pagination v-model:current-page="page" :page-size="pageSize" layout="prev, pager, next" :total="subdomains.length" />
        <el-select v-model="pageSize" class="page-size-select">
          <el-option label="10 条/页" :value="10" />
          <el-option label="20 条/页" :value="20" />
        </el-select>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { apiErrorMessage } from '../../api/errors'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { deleteSubdomain, listSubdomains } from '../../api/subdomains'
import type { Subdomain } from '../../types/domain'

const router = useRouter()
const subdomains = ref<Subdomain[]>([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(10)
const pagedSubdomains = computed(() => subdomains.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value))

watch(pageSize, () => {
  page.value = 1
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const response = await listSubdomains()
    subdomains.value = response.data
  } finally {
    loading.value = false
  }
}

async function remove(row: Subdomain) {
  try {
    const pending = row.status === 2
    const rejected = row.status === 3
    const message = pending
      ? `请输入 ${row.full_domain} 确认撤销申请。撤销后将退回注册积分并释放该名称。`
      : rejected
        ? `请输入 ${row.full_domain} 确认删除驳回记录。该操作不会再次退回积分。`
        : `请输入 ${row.full_domain} 确认删除。删除前请先清空该二级域名下的解析记录。`
    const { value } = await ElMessageBox.prompt(message, pending ? '撤销域名申请' : rejected ? '删除驳回记录' : '删除二级域名', {
      confirmButtonText: pending ? '确认撤销' : '确认删除',
      cancelButtonText: '取消',
      inputPlaceholder: row.full_domain,
      inputPattern: new RegExp(`^${escapeRegExp(row.full_domain)}$`, 'i'),
      inputErrorMessage: '请输入完整二级域名',
      type: 'warning',
    })
    await deleteSubdomain(row.id, { confirm_full_domain: value.trim() })
    ElMessage.success(pending ? '申请已撤销，注册积分已退回' : rejected ? '驳回记录已删除' : '二级域名已删除')
    await load()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(apiErrorMessage(error, '删除二级域名失败'))
    }
  }
}

function reapply(row: Subdomain) {
  router.push({
    path: '/home/register',
    query: {
      did: String(row.did),
      name: row.name,
      purpose: row.purpose || '',
    },
  })
}

function deleteActionText(status: number) {
  if (status === 2) return '撤销'
  if (status === 3) return '删除记录'
  return '删除'
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

function escapeRegExp(value: string) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

</script>
