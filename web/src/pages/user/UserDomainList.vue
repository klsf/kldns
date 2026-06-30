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
            <el-tag class="compact-tag" :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '正常' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <div class="table-actions">
              <el-button text type="primary" @click="$router.push({ path: '/home/records', query: { subdomain_id: row.id } })">解析</el-button>
              <el-button text type="danger" @click="remove(row)">删除</el-button>
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
import { ElMessage, ElMessageBox } from 'element-plus'
import { deleteSubdomain, listSubdomains } from '../../api/subdomains'
import type { Subdomain } from '../../types/domain'

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
    const { value } = await ElMessageBox.prompt(`请输入 ${row.full_domain} 确认删除。删除前请先清空该二级域名下的解析记录。`, '删除二级域名', {
      confirmButtonText: '确认删除',
      cancelButtonText: '取消',
      inputPlaceholder: row.full_domain,
      inputPattern: new RegExp(`^${escapeRegExp(row.full_domain)}$`, 'i'),
      inputErrorMessage: '请输入完整二级域名',
      type: 'warning',
    })
    await deleteSubdomain(row.id, { confirm_full_domain: value.trim() })
    ElMessage.success('二级域名已删除')
    await load()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(apiErrorMessage(error, '删除二级域名失败'))
    }
  }
}

function escapeRegExp(value: string) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

</script>
