<template>
  <main class="auth-screen">
    <RouterLink class="auth-brand" to="/">
      <span>KD</span>
      <strong>KLDNS</strong>
    </RouterLink>
    <section class="auth-panel">
      <div class="auth-heading">
        <h1>注册账号</h1>
        <span>提交后按系统审核策略启用账号。</span>
      </div>
      <el-form label-position="top" @submit.prevent="submit">
        <el-form-item label="用户名">
          <el-input v-model="form.username" autocomplete="username" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="form.email" autocomplete="email" placeholder="可选，后续可用于绑定邮箱" />
        </el-form-item>
        <el-form-item label="密码">
          <el-input v-model="form.password" type="password" autocomplete="new-password" show-password @keyup.enter="submit" />
        </el-form-item>
        <TurnstileWidget
          v-model:token="turnstileToken"
          :enabled="turnstile.register_enabled"
          :site-key="turnstile.site_key"
          :reset-key="turnstileResetKey"
        />
        <el-alert v-if="turnstileError" class="auth-alert" type="warning" show-icon :closable="false" :title="turnstileError" />
        <el-button type="primary" class="wide-button" :loading="loading" @click="submit">注册</el-button>
        <div class="auth-links">
          <RouterLink to="/">返回首页</RouterLink>
          <RouterLink to="/login">已有账号登录</RouterLink>
        </div>
      </el-form>
      <aside class="auth-signal">
        <b>自助申请解析</b>
        <span>主域策略、记录类型、积分消耗会在用户中心展示。</span>
      </aside>
    </section>
  </main>
</template>

<script setup lang="ts">
import { apiErrorMessage } from '../../api/errors'
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { register } from '../../api/auth'
import { getTurnstileSettings, type TurnstilePublicSettings } from '../../api/settings'
import TurnstileWidget from '../../components/TurnstileWidget.vue'

const router = useRouter()
const loading = ref(false)
const form = reactive({ username: '', email: '', password: '' })
const turnstileToken = ref('')
const turnstileResetKey = ref(0)
const turnstileError = ref('')
const turnstile = reactive<TurnstilePublicSettings>({
  site_key: '',
  register_enabled: false,
  login_enabled: false,
})

onMounted(loadTurnstile)

async function loadTurnstile() {
  try {
    const response = await getTurnstileSettings()
    Object.assign(turnstile, response.data)
    turnstileError.value = ''
  } catch (error) {
    turnstileError.value = apiErrorMessage(error, '人机验证配置加载失败')
  }
}

async function submit() {
  if (turnstileError.value) {
    ElMessage.warning('人机验证配置加载失败，请刷新页面后重试')
    return
  }
  const username = form.username.trim()
  const email = form.email.trim()
  const password = form.password
  if (!username || !password) {
    ElMessage.warning('请填写用户名和密码')
    return
  }
  if (username.length < 4) {
    ElMessage.warning('用户名至少 4 位')
    return
  }
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    ElMessage.warning('邮箱格式不正确')
    return
  }
  if (password.length < 8) {
    ElMessage.warning('密码至少 8 位')
    return
  }
  if (turnstile.register_enabled && !turnstileToken.value) {
    ElMessage.warning('请完成人机验证')
    return
  }
  loading.value = true
  try {
    const response = await register({ username, email, password, turnstile_token: turnstileToken.value })
    if (response.data.review_required) {
      ElMessage.success('注册成功，请等待管理员审核')
    } else {
      ElMessage.success('注册成功')
    }
    await router.push('/login')
  } catch (error) {
    ElMessage.error(apiErrorMessage(error, '注册失败，请稍后重试'))
  } finally {
    turnstileToken.value = ''
    turnstileResetKey.value += 1
    loading.value = false
  }
}

</script>

<style scoped>
@import './auth-shared.css';
</style>
