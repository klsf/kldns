<template>
  <section class="page-stack">
    <header class="page-header">
      <div>
        <h1>用户管理</h1>
        <p class="resource-note">查看平台账号、用户组、积分和账号状态。</p>
      </div>
    </header>
    <div class="toolbar-row">
      <el-input v-model="keyword" clearable placeholder="搜索账号或邮箱" class="toolbar-control wide-admin-search" />
      <el-select v-model="statusFilter" clearable placeholder="状态" class="toolbar-control">
        <el-option label="已审核" :value="2" />
        <el-option label="待审核" :value="1" />
        <el-option label="禁用" :value="0" />
      </el-select>
      <el-select v-model="groupFilter" clearable placeholder="用户组" class="toolbar-control">
        <el-option v-for="group in groups" :key="group.id" :label="group.name" :value="group.id" />
      </el-select>
      <el-button type="primary" @click="search">搜索</el-button>
      <el-button @click="resetFilters">重置</el-button>
    </div>
    <div class="resource-card">
      <el-table v-loading="loading" :data="users" class="responsive-table">
        <el-table-column prop="id" label="ID" width="90" />
        <el-table-column prop="username" label="账号" min-width="140" />
        <el-table-column prop="email" label="邮箱" min-width="200" show-overflow-tooltip />
        <el-table-column label="用户组" min-width="130" show-overflow-tooltip>
          <template #default="{ row }">{{ groupName(row.group_id) }}</template>
        </el-table-column>
        <el-table-column prop="points" label="积分" width="110" />
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag class="compact-tag" :type="statusType(row.status)">{{ statusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="196" fixed="right">
          <template #default="{ row }">
            <div class="table-actions">
              <el-button v-if="row.status === 1 && !isProtectedUser(row)" text type="primary" @click="approve(row)">审核通过</el-button>
              <el-button text type="primary" @click="openEdit(row)">编辑</el-button>
              <el-button text :disabled="isProtectedUser(row)" :type="row.status === 0 ? 'success' : 'danger'" @click="toggleDisabled(row)">
                {{ row.status === 0 ? '启用' : '禁用' }}
              </el-button>
              <el-button text type="danger" :disabled="isProtectedUser(row)" @click="remove(row)">删除</el-button>
            </div>
          </template>
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

    <el-dialog v-model="dialogVisible" title="编辑用户" width="min(560px, 94vw)">
      <el-form label-position="top">
        <div class="user-form-grid">
          <el-form-item label="账号">
            <el-input v-model="form.username" autocomplete="off" />
          </el-form-item>
          <el-form-item label="邮箱">
            <el-input v-model="form.email" autocomplete="off" />
          </el-form-item>
          <el-form-item label="用户组">
            <el-select v-model="form.group_id" class="full-control" :disabled="editing?.id === 1">
              <el-option v-for="group in groups" :key="group.id" :label="group.name" :value="group.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="审核状态">
            <el-select v-model="form.status" class="full-control" :disabled="editing?.id === 1">
              <el-option label="已审核" :value="2" />
              <el-option label="待审核" :value="1" />
              <el-option label="禁用" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item label="积分">
            <el-input-number v-model="form.points" :min="0" :max="100000000" class="full-control" />
          </el-form-item>
          <el-form-item label="重置密码">
            <el-input v-model="form.password" type="password" show-password autocomplete="new-password" placeholder="留空不修改" />
          </el-form-item>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="save">保存</el-button>
      </template>
    </el-dialog>
  </section>
</template>

<script setup lang="ts">
import { apiErrorMessage } from '../../api/errors'
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { deleteAdminUser, listAdminGroups, listAdminUsersPage, saveAdminUser, type AdminGroup, type AdminUser } from '../../api/admin'

const users = ref<AdminUser[]>([])
const groups = ref<AdminGroup[]>([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const keyword = ref('')
const statusFilter = ref<number | undefined>()
const groupFilter = ref<number | undefined>()
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)
const editing = ref<AdminUser | null>(null)
const form = reactive({
  username: '',
  email: '',
  group_id: 100,
  status: 1,
  points: 0,
  password: '',
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const [userResponse, groupResponse] = await Promise.all([
      listAdminUsersPage({ ...filterParams(), page: page.value, page_size: pageSize.value }),
      listAdminGroups(),
    ])
    users.value = userResponse.data.items
    total.value = userResponse.data.total
    groups.value = groupResponse.data
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
  statusFilter.value = undefined
  groupFilter.value = undefined
  page.value = 1
  void load()
}

function changePageSize() {
  page.value = 1
  void load()
}

function filterParams() {
  return {
    keyword: keyword.value.trim() || undefined,
    status: statusFilter.value,
    group_id: groupFilter.value,
  }
}

function groupName(groupID: number) {
  return groups.value.find((group) => group.id === groupID)?.name || `用户组 ${groupID}`
}

function statusText(status: number) {
  if (status === 2) return '已审核'
  if (status === 1) return '待审核'
  if (status === 0) return '禁用'
  return '未知'
}

function statusType(status: number) {
  if (status === 2) return 'success'
  if (status === 1) return 'warning'
  return 'danger'
}

function isProtectedUser(row: AdminUser) {
  return row.id === 1
}

function openEdit(row: AdminUser) {
  editing.value = row
  Object.assign(form, {
    username: row.username,
    email: row.email,
    group_id: row.group_id,
    status: row.status,
    points: row.points,
    password: '',
  })
  dialogVisible.value = true
}

async function approve(row: AdminUser) {
  await updateUser(row, { status: 2 }, '用户已审核')
}

async function toggleDisabled(row: AdminUser) {
  const nextStatus = row.status === 0 ? 1 : 0
  if (nextStatus === 0) {
    await ElMessageBox.confirm(`确认禁用账号 ${row.username}？`, '禁用用户', { type: 'warning' })
  }
  await updateUser(row, { status: nextStatus }, nextStatus === 0 ? '用户已禁用' : '用户已启用')
}

async function remove(row: AdminUser) {
  try {
    const { value } = await ElMessageBox.prompt(`请输入用户名 ${row.username} 确认删除。此操作会删除该会员的所有二级域名，以及本地和平台上的解析记录。`, '删除用户', {
      confirmButtonText: '确认删除',
      cancelButtonText: '取消',
      inputPlaceholder: row.username,
      inputPattern: new RegExp(`^${escapeRegExp(row.username)}$`, 'i'),
      inputErrorMessage: '请输入完整用户名',
      type: 'warning',
    })
    const response = await deleteAdminUser(row.id, { confirm_username: value.trim() })
    ElMessage.success(`用户已删除，已删除 ${response.data.subdomains_deleted} 个二级域名、${response.data.records_deleted} 条解析记录`)
    await load()
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') {
      ElMessage.error(apiErrorMessage(error, '删除用户失败'))
    }
  }
}

async function save() {
  if (!editing.value) return
  if (!form.username.trim()) {
    ElMessage.warning('请输入账号')
    return
  }
  if (form.password && form.password.length < 8) {
    ElMessage.warning('密码至少 8 位')
    return
  }
  saving.value = true
  try {
    await saveAdminUser(editing.value.id, {
      username: form.username.trim(),
      email: form.email.trim(),
      group_id: form.group_id,
      status: form.status,
      points: form.points,
      password: form.password,
    })
    ElMessage.success('用户已保存')
    dialogVisible.value = false
    await load()
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '保存用户失败'))
  } finally {
    saving.value = false
  }
}

async function updateUser(row: AdminUser, patch: Partial<Pick<AdminUser, 'status' | 'group_id' | 'points' | 'username' | 'email'>>, successMessage: string) {
  try {
    await saveAdminUser(row.id, {
      username: patch.username ?? row.username,
      email: patch.email ?? row.email,
      group_id: patch.group_id ?? row.group_id,
      status: patch.status ?? row.status,
      points: patch.points ?? row.points,
    })
    ElMessage.success(successMessage)
    await load()
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '操作失败'))
  }
}


function escapeRegExp(value: string) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
</script>

<style scoped>
.wide-admin-search {
  width: 320px;
}

.user-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

@media (max-width: 680px) {
  .user-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
