<template>
  <section class="page-stack">
    <header class="page-header">
      <div>
        <h1>主域管理</h1>
        <p class="resource-note">配置开放主域、DNS 平台、记录类型和二级域名注册积分。</p>
      </div>
      <el-button type="primary" @click="editorRef?.openCreate()"><Globe2 :size="17" />新增主域</el-button>
    </header>

    <div class="toolbar-row">
      <el-select v-model="filters.provider" clearable placeholder="DNS 平台" class="toolbar-control">
        <el-option v-for="provider in providers" :key="provider.key" :label="provider.label" :value="provider.key" />
      </el-select>
      <el-input v-model="filters.keyword" placeholder="搜索主域" class="toolbar-control" />
      <el-button type="primary" @click="search">搜索</el-button>
      <el-button @click="resetFilters">重置</el-button>
    </div>

    <DomainTable
      v-model:page="page"
      v-model:page-size="pageSize"
      :domains="domains"
      :loading="loading"
      :provider-label="providerLabel"
      :syncing-id="syncingID"
      :total="total"
      @edit="openEdit"
      @remove="remove"
      @sync="syncRecords"
      @update:page="load"
      @update:page-size="changePageSize"
    />

    <DomainEditorDialog ref="editorRef" :groups="groups" :providers="providers" @saved="load" />
  </section>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Globe2 } from 'lucide-vue-next'
import { apiErrorMessage } from '../../api/errors'
import {
  deleteAdminDomain,
  listAdminDomainsPage,
  listAdminGroups,
  listProviders,
  syncAdminDomainRecords,
  type AdminDomain,
  type AdminGroup,
  type ProviderSummary,
} from '../../api/admin'
import DomainEditorDialog from '../../features/admin-domains/DomainEditorDialog.vue'
import DomainTable from '../../features/admin-domains/DomainTable.vue'

type DomainEditorInstance = InstanceType<typeof DomainEditorDialog>

const domains = ref<AdminDomain[]>([])
const providers = ref<ProviderSummary[]>([])
const groups = ref<AdminGroup[]>([])
const loading = ref(false)
const syncingID = ref(0)
const filters = reactive({ provider: '', keyword: '' })
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const editorRef = ref<DomainEditorInstance>()

onMounted(load)

async function load() {
  loading.value = true
  try {
    const [domainResponse, providerResponse, groupResponse] = await Promise.all([
      listAdminDomainsPage({ ...filterParams(), page: page.value, page_size: pageSize.value }),
      listProviders(),
      listAdminGroups(),
    ])
    domains.value = domainResponse.data.items
    total.value = domainResponse.data.total
    providers.value = providerResponse.data
    groups.value = groupResponse.data
  } finally {
    loading.value = false
  }
}

async function search() {
  page.value = 1
  await load()
}

function openEdit(row: AdminDomain) {
  editorRef.value?.openEdit(row)
}

async function remove(row: AdminDomain) {
  let deleteMode: 'local_subdomains' | 'platform_records' = 'local_subdomains'
  try {
    await ElMessageBox.confirm(`确认删除主域 ${row.domain}？请选择是否同时删除 DNS 平台上的解析记录。`, '删除主域', {
      type: 'warning',
      confirmButtonText: '删除本地和平台记录',
      cancelButtonText: '仅删除本地数据',
      distinguishCancelAndClose: true,
    })
    deleteMode = 'platform_records'
  } catch (error) {
    if (error === 'cancel') {
      deleteMode = 'local_subdomains'
    } else {
      return
    }
  }
  try {
    const response = await deleteAdminDomain(row.id, { delete_mode: deleteMode })
    const modeText = deleteMode === 'platform_records' ? `，已删除 ${response.data.records_deleted} 条平台解析记录` : ''
    ElMessage.success(`主域已删除，已删除 ${response.data.subdomains_deleted} 个本地二级域名${modeText}`)
    await load()
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '删除主域失败'))
  }
}

async function syncRecords(row: AdminDomain) {
  try {
    await ElMessageBox.confirm(`确认从 ${providerLabel(row.provider_key)} 同步 ${row.domain} 的全部远端解析记录？本地已有记录会跳过。`, '同步解析记录', {
      type: 'warning',
    })
    syncingID.value = row.id
    const response = await syncAdminDomainRecords(row.id)
    ElMessage.success(`同步完成：远端 ${response.data.total} 条，新增 ${response.data.created} 条，跳过 ${response.data.skipped} 条`)
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(apiErrorMessage(error, '同步解析记录失败'))
    }
  } finally {
    syncingID.value = 0
  }
}

function resetFilters() {
  filters.provider = ''
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
    provider: filters.provider || undefined,
    keyword: filters.keyword.trim() || undefined,
  }
}

function providerLabel(key: string) {
  return providers.value.find((provider) => provider.key === key)?.label || key || '未配置'
}
</script>
