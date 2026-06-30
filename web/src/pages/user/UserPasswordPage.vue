<template>
  <section class="page-stack password-page">
    <header class="page-header">
      <div>
        <h1>账号安全</h1>
        <p class="resource-note">修改当前会员账号的登录密码。</p>
      </div>
    </header>

    <section class="resource-card password-panel">
      <div class="password-head">
        <div class="lock-mark">
          <LockKeyhole :size="24" />
        </div>
        <div>
          <h2>修改密码</h2>
          <span>{{ auth.user?.username || '当前账号' }}</span>
        </div>
      </div>

      <el-form label-position="top" class="password-form">
        <el-form-item label="旧密码">
          <el-input v-model="form.oldPassword" type="password" show-password autocomplete="current-password" placeholder="请输入旧密码" />
        </el-form-item>
        <el-form-item label="新密码">
          <el-input v-model="form.newPassword" type="password" show-password autocomplete="new-password" placeholder="至少 8 位" />
        </el-form-item>
        <el-form-item label="确认新密码">
          <el-input v-model="form.confirmPassword" type="password" show-password autocomplete="new-password" placeholder="再次输入新密码" @keyup.enter="submit" />
        </el-form-item>
      </el-form>

      <div class="password-actions">
        <el-button type="primary" :loading="saving" @click="submit"><ShieldCheck :size="16" />保存密码</el-button>
        <el-button @click="reset">清空</el-button>
      </div>
    </section>
  </section>
</template>

<script setup lang="ts">
import { apiErrorMessage } from '../../api/errors'
import { reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { LockKeyhole, ShieldCheck } from 'lucide-vue-next'
import { useAuthStore } from '../../app/stores/auth'
import { changePassword } from '../../api/auth'

const auth = useAuthStore()
const saving = ref(false)
const form = reactive({
  oldPassword: '',
  newPassword: '',
  confirmPassword: '',
})

async function submit() {
  const oldPassword = form.oldPassword.trim()
  const newPassword = form.newPassword.trim()
  const confirmPassword = form.confirmPassword.trim()
  if (!oldPassword || !newPassword || !confirmPassword) {
    ElMessage.warning('请完整填写密码信息')
    return
  }
  if (newPassword.length < 8) {
    ElMessage.warning('新密码至少 8 位')
    return
  }
  if (newPassword !== confirmPassword) {
    ElMessage.warning('两次输入的新密码不一致')
    return
  }
  saving.value = true
  try {
    await changePassword({ old_password: oldPassword, new_password: newPassword })
    ElMessage.success('密码已修改')
    reset()
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '修改密码失败'))
  } finally {
    saving.value = false
  }
}

function reset() {
  form.oldPassword = ''
  form.newPassword = ''
  form.confirmPassword = ''
}

</script>

<style scoped>
.password-page {
  max-width: 760px;
}

.password-panel {
  display: grid;
  gap: 20px;
  padding: 24px;
}

.password-head {
  display: flex;
  align-items: center;
  gap: 14px;
}

.lock-mark {
  width: 48px;
  height: 48px;
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 8px;
  color: var(--accent-strong);
  background: #e8faf3;
}

.password-head h2 {
  margin: 0 0 4px;
  color: #17282d;
  font-size: 20px;
}

.password-head span {
  color: var(--muted);
}

.password-form {
  display: grid;
  max-width: 520px;
  gap: 2px;
}

.password-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

@media (max-width: 680px) {
  .password-panel {
    padding: 16px;
  }

  .password-head {
    align-items: flex-start;
  }
}
</style>
