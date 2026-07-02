<template>
  <section class="page-stack">
    <header class="page-header page-header-with-action">
      <div>
        <h1>开放 API</h1>
        <p class="resource-note">创建 API Token 后，可通过开放 API 查询二级域名并维护解析记录。</p>
      </div>
      <div class="header-actions">
        <el-button @click="$router.push('/home/api-docs')"><BookOpen :size="16" />API 文档</el-button>
        <el-button type="primary" @click="dialogVisible = true"><KeyRound :size="17" />创建令牌</el-button>
      </div>
    </header>
    <el-alert type="warning" show-icon title="令牌明文只在创建时显示一次" />
    <div class="resource-card">
      <el-table v-loading="loading" :data="pagedTokens" class="responsive-table">
        <el-table-column prop="name" label="名称" min-width="140" />
        <el-table-column prop="token_hint" label="令牌" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            <code class="token-hint">{{ row.token_hint }}</code>
          </template>
        </el-table-column>
        <el-table-column label="过期时间" width="180">
          <template #default="{ row }">{{ formatTime(row.expires_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <div class="table-actions">
              <el-button text type="danger" @click="remove(row.id)">删除</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
      <div v-if="tokens.length > pageSize" class="resource-pagination">
        <span>共 {{ tokens.length }} 条</span>
        <el-pagination v-model:current-page="page" :page-size="pageSize" layout="prev, pager, next" :total="tokens.length" />
        <el-select v-model="pageSize" class="page-size-select">
          <el-option label="10 条/页" :value="10" />
          <el-option label="20 条/页" :value="20" />
        </el-select>
      </div>
    </div>

    <el-dialog v-model="dialogVisible" title="创建 API Token" width="min(480px, 94vw)">
      <el-form label-position="top">
        <el-form-item label="名称">
          <el-input v-model="form.name" />
        </el-form-item>
        <el-form-item label="有效天数">
          <el-input-number v-model="form.days" :min="0" :max="3650" class="full-control" />
        </el-form-item>
      </el-form>
      <el-alert v-if="createdToken" class="token-result" type="success" :closable="false" title="令牌已创建">
        <code>{{ createdToken }}</code>
      </el-alert>
      <template #footer>
        <el-button @click="dialogVisible = false">关闭</el-button>
        <el-button type="primary" :loading="saving" @click="save">创建</el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { BookOpen, KeyRound } from 'lucide-vue-next'
import { createToken, deleteToken, listTokens, type TokenItem } from '../../api/tokens'

const tokens = ref<TokenItem[]>([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const createdToken = ref('')
const form = reactive({ name: '', days: 0 })
const page = ref(1)
const pageSize = ref(10)
const pagedTokens = computed(() => tokens.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value))

watch(pageSize, () => {
  page.value = 1
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const response = await listTokens()
    tokens.value = response.data
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!form.name.trim()) {
    ElMessage.warning('请输入令牌名称')
    return
  }
  saving.value = true
  try {
    const response = await createToken(form)
    createdToken.value = response.data.token
    ElMessage.success('令牌已创建')
    await load()
  } finally {
    saving.value = false
  }
}

async function remove(id: number) {
  await ElMessageBox.confirm('确认删除这个 API Token？', '删除令牌', { type: 'warning' })
  await deleteToken(id)
  ElMessage.success('令牌已删除')
  await load()
}

function formatTime(value: number) {
  return value ? new Date(value * 1000).toLocaleString() : '永不过期'
}
</script>

<style scoped>
.token-hint {
  font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  font-size: 12px;
  white-space: nowrap;
}

.header-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
}

@media (max-width: 680px) {
  .header-actions {
    width: 100%;
    justify-content: flex-start;
  }
}
</style>
