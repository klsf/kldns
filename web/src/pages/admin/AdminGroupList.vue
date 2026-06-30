<template>
  <section class="page-stack">
    <header class="page-header">
      <div>
        <h1>用户组</h1>
        <p class="resource-note">维护开放主域策略可引用的用户分组。</p>
      </div>
      <el-button type="primary" @click="openCreate"><Layers3 :size="17" />新增用户组</el-button>
    </header>
    <div class="toolbar-row">
      <el-input v-model="keyword" clearable placeholder="搜索用户组名称" class="toolbar-control" />
      <el-button type="primary" @click="search">搜索</el-button>
      <el-button @click="resetFilters">重置</el-button>
    </div>
    <div class="resource-card">
      <el-table v-loading="loading" :data="pagedGroups" class="responsive-table">
        <el-table-column prop="id" label="ID" width="120" />
        <el-table-column prop="name" label="名称" min-width="180" />
        <el-table-column label="保护状态" width="140">
          <template #default="{ row }">
            <el-tag class="compact-tag" :type="row.id <= 100 ? 'info' : 'success'">{{ row.id <= 100 ? '系统内置' : '可维护' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="118" fixed="right">
          <template #default="{ row }">
            <div class="table-actions">
              <el-button text type="primary" @click="openEdit(row)">编辑</el-button>
              <el-button :disabled="row.id <= 100" text type="danger" @click="remove(row.id)">删除</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
      <div v-if="groups.length > pageSize" class="resource-pagination">
        <span>共 {{ groups.length }} 条</span>
        <el-pagination v-model:current-page="page" :page-size="pageSize" layout="prev, pager, next" :total="groups.length" />
        <el-select v-model="pageSize" class="page-size-select">
          <el-option label="10 条/页" :value="10" />
          <el-option label="20 条/页" :value="20" />
        </el-select>
      </div>
    </div>
    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑用户组' : '新增用户组'" width="min(420px, 94vw)">
      <el-form label-position="top">
        <el-form-item label="名称">
          <el-input v-model="form.name" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Layers3 } from 'lucide-vue-next'
import { deleteAdminGroup, listAdminGroups, saveAdminGroup, type AdminGroup } from '../../api/admin'

const groups = ref<AdminGroup[]>([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const form = reactive({ id: 0, name: '' })
const keyword = ref('')
const page = ref(1)
const pageSize = ref(10)

const pagedGroups = computed(() => groups.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value))

watch(pageSize, () => {
  page.value = 1
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    groups.value = (await listAdminGroups({ keyword: keyword.value.trim() || undefined })).data
  } finally {
    loading.value = false
  }
}

async function search() {
  page.value = 1
  await load()
}

function resetFilters() {
  keyword.value = ''
  page.value = 1
  void load()
}

function openCreate() {
  Object.assign(form, { id: 0, name: '' })
  dialogVisible.value = true
}

function openEdit(row: AdminGroup) {
  Object.assign(form, row)
  dialogVisible.value = true
}

async function save() {
  if (!form.name.trim()) {
    ElMessage.warning('请输入用户组名称')
    return
  }
  saving.value = true
  try {
    await saveAdminGroup(form)
    dialogVisible.value = false
    ElMessage.success('用户组已保存')
    await load()
  } finally {
    saving.value = false
  }
}

async function remove(id: number) {
  await ElMessageBox.confirm('删除后该组用户会回到默认用户组，确认继续？', '删除用户组', { type: 'warning' })
  await deleteAdminGroup(id)
  ElMessage.success('用户组已删除')
  await load()
}
</script>

<style scoped>
</style>
