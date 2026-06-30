<template>
  <section class="page-stack subdomain-register-page">
    <header class="page-header">
      <div>
        <h1>域名注册</h1>
        <p class="resource-note">选择主域并设置二级域名前缀，注册后即可在域名解析中维护全部下级记录。</p>
      </div>
    </header>

    <section class="resource-card domain-table-card">
      <div class="table-card-head">
        <h2>可用主域列表</h2>
        <div class="domain-search-row">
          <el-input v-model="keyword" clearable placeholder="搜索主域" class="domain-search" />
          <el-button @click="load">搜索</el-button>
        </div>
      </div>
      <el-table :data="pagedDomains" class="responsive-table">
        <el-table-column label="主域" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <div class="main-domain-cell">
              <strong>{{ row.domain }}</strong>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="注册积分" width="118">
          <template #default="{ row }">
            <span class="cost-inline"><Coins :size="14" />{{ row.registration_cost ?? row.points_cost ?? 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="支持类型" min-width="220">
          <template #default="{ row }">
            <div class="type-tags">
              <el-tag v-for="type in row.record_types" :key="type" class="compact-tag" effect="plain">{{ type }}</el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="备案" width="110">
          <template #default="{ row }">{{ row.beian ? '已备案' : '未备案' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="116">
          <template #default>
            <span class="status-chip"><span class="status-dot" />可注册</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="104" fixed="right" align="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="openRegister(row)"><BadgePlus :size="15" />注册</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div v-if="domains.length > pageSize" class="resource-pagination">
        <span>共 {{ domains.length }} 条</span>
        <el-pagination v-model:current-page="page" :page-size="pageSize" layout="prev, pager, next" :total="domains.length" />
        <el-select v-model="pageSize" class="page-size-select">
          <el-option label="10 条/页" :value="10" />
          <el-option label="20 条/页" :value="20" />
        </el-select>
      </div>
    </section>

    <el-dialog v-model="dialogVisible" title="注册域名" width="min(560px, 94vw)" @closed="reset">
      <el-form label-position="top" class="register-dialog-form">
        <el-form-item label="选择主域">
          <div class="selected-domain-box">
            <Globe2 :size="18" />
            <strong>{{ selectedDomain?.domain || '-' }}</strong>
            <span>{{ selectedCost }} 积分</span>
          </div>
        </el-form-item>
        <el-form-item label="域名前缀">
          <el-input v-model="form.name" placeholder="例如 test" class="domain-input" @keyup.enter="submit">
            <template #append>.{{ selectedDomain?.domain || 'example.com' }}</template>
          </el-input>
          <p class="field-help">支持小写字母、数字和连字符。注册后可在“域名解析”中添加 @、www 等记录。</p>
        </el-form-item>
        <el-form-item label="注册预览">
          <div class="domain-preview-inline">
            <strong>{{ previewDomain }}</strong>
            <span>余额 {{ auth.user?.points ?? 0 }} 积分</span>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <div class="dialog-actions">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="saving" @click="submit"><Check :size="16" />确认注册</el-button>
        </div>
      </template>
    </el-dialog>
  </section>
</template>

<script setup lang="ts">
import { apiErrorMessage } from '../../api/errors'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { BadgePlus, Check, Coins, Globe2 } from 'lucide-vue-next'
import { useAuthStore } from '../../app/stores/auth'
import { listDomains } from '../../api/domains'
import { registerSubdomain } from '../../api/subdomains'
import type { Domain } from '../../types/domain'

const auth = useAuthStore()
const domains = ref<Domain[]>([])
const keyword = ref('')
const page = ref(1)
const pageSize = ref(10)
const saving = ref(false)
const dialogVisible = ref(false)
const form = reactive({ did: 0, name: '' })

const selectedDomain = computed(() => domains.value.find((domain) => domain.id === form.did))
const selectedCost = computed(() => selectedDomain.value?.registration_cost ?? selectedDomain.value?.points_cost ?? 0)
const previewDomain = computed(() => {
  const prefix = form.name.trim() || 'test'
  return selectedDomain.value ? `${prefix}.${selectedDomain.value.domain}` : `${prefix}.example.com`
})
const pagedDomains = computed(() => domains.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value))

watch(pageSize, () => {
  page.value = 1
})
onMounted(load)

async function load() {
  page.value = 1
  const response = await listDomains({ keyword: keyword.value.trim() || undefined })
  domains.value = response.data
}

function openRegister(domain: Domain) {
  form.did = domain.id
  form.name = ''
  dialogVisible.value = true
}

async function submit() {
  const name = form.name.trim().toLowerCase()
  if (!form.did || !name) {
    ElMessage.warning('请选择主域并填写二级域名前缀')
    return
  }
  saving.value = true
  try {
    await registerSubdomain({ did: form.did, name })
    ElMessage.success('域名注册成功')
    await auth.loadMe()
    dialogVisible.value = false
    reset()
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '注册失败'))
  } finally {
    saving.value = false
  }
}

function reset() {
  form.name = ''
}

</script>

<style scoped>
.subdomain-register-page {
  gap: 18px;
}

.domain-table-card {
  padding: 20px 22px;
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

.domain-table-card {
  display: grid;
  gap: 12px;
}

.domain-search {
  width: 240px;
}

.domain-search-row {
  display: flex;
  gap: 10px;
}

.domain-table-card :deep(.el-table) {
  border: 0;
  box-shadow: none;
}

.domain-table-card :deep(.el-table__inner-wrapper::before) {
  display: none;
}

.main-domain-cell {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.main-domain-cell strong {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cost-inline,
.type-tags {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
}

.type-tags {
  flex-wrap: wrap;
}

.cost-inline {
  color: #26383d;
  font-weight: 800;
}

.register-dialog-form {
  display: grid;
  gap: 2px;
}

.selected-domain-box,
.domain-preview-inline {
  width: 100%;
  min-height: 44px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 13px;
  border: 1px solid #cfe8df;
  border-radius: 8px;
  background: #f3fbf8;
}

.selected-domain-box svg {
  flex: 0 0 auto;
  color: var(--accent);
}

.selected-domain-box strong,
.domain-preview-inline strong {
  min-width: 0;
  overflow: hidden;
  color: #122226;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.selected-domain-box span,
.domain-preview-inline span {
  margin-left: auto;
  color: var(--accent-strong);
  font-size: 13px;
  font-weight: 800;
  white-space: nowrap;
}

.domain-input :deep(.el-input-group__append) {
  min-width: 138px;
  color: #43555a;
  font-weight: 700;
}

.field-help {
  margin: 7px 0 0;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.6;
}

.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

@media (max-width: 680px) {
  .domain-table-card {
    padding: 16px;
  }

  .table-card-head {
    align-items: stretch;
    flex-direction: column;
  }

  .domain-search,
  .domain-search-row {
    width: 100%;
  }

  .domain-search-row {
    flex-direction: column;
  }

  .selected-domain-box,
  .domain-preview-inline {
    align-items: flex-start;
    flex-direction: column;
    justify-content: center;
    padding: 10px 12px;
  }

  .selected-domain-box span,
  .domain-preview-inline span {
    margin-left: 0;
  }
}
</style>
