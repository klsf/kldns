<template>
  <main class="auth-screen">
    <RouterLink class="auth-brand" to="/">
      <span>KD</span>
      <strong>KLDNS</strong>
    </RouterLink>
    <section class="auth-panel">
      <div class="auth-form-panel">
        <div class="auth-heading">
          <h1>DNS 分发管理</h1>
          <span>登录后进入用户中心，管理组账号可切换到管理后台。</span>
        </div>
        <el-form label-position="top" @submit.prevent="submit">
          <el-form-item label="账号">
            <el-input v-model="form.login" autocomplete="username">
              <template #prefix>
                <UserRound :size="22" />
              </template>
            </el-input>
          </el-form-item>
          <el-form-item label="密码">
            <el-input v-model="form.password" type="password" autocomplete="current-password" show-password @keyup.enter="submit">
              <template #prefix>
                <LockKeyhole :size="22" />
              </template>
            </el-input>
          </el-form-item>
          <TurnstileWidget
            v-model:token="turnstileToken"
            :enabled="turnstile.login_enabled"
            :site-key="turnstile.site_key"
            :reset-key="turnstileResetKey"
          />
          <el-alert v-if="turnstileError" class="auth-alert" type="warning" show-icon :closable="false" :title="turnstileError" />
          <el-alert v-if="formError" class="auth-alert" type="error" show-icon :closable="false" :title="formError" />
          <el-button type="primary" class="wide-button" :loading="loading" @click="submit">登录</el-button>
          <div class="auth-links">
            <RouterLink to="/">返回首页</RouterLink>
            <RouterLink to="/register">注册新账号</RouterLink>
          </div>
        </el-form>
      </div>
      <aside class="auth-signal provider-signal">
        <div class="provider-map" aria-hidden="true">
          <span class="provider-hub" />
          <i />
          <i />
          <i />
          <i />
        </div>
        <div class="provider-grid">
          <span><em>☁</em>Cloudflare<i /></span>
          <span><em>D</em>DNSPod<i /></span>
          <span><em>A</em>Aliyun<i /></span>
          <span><em>aws</em>Route53<i /></span>
        </div>
        <div class="signal-copy">
          <b>多平台 DNS 接入</b>
          <span>Cloudflare / DNSPod / Aliyun / Route53</span>
        </div>
      </aside>
    </section>
    <p class="auth-version">版本 {{ APP_VERSION }}</p>
  </main>
</template>

<script setup lang="ts">
import { LockKeyhole, UserRound } from 'lucide-vue-next'
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useAuthStore } from '../../app/stores/auth'
import { apiErrorMessage } from '../../api/errors'
import { getTurnstileSettings, type TurnstilePublicSettings } from '../../api/settings'
import TurnstileWidget from '../../components/TurnstileWidget.vue'
import { APP_VERSION } from '../../app/version'

const form = reactive({
  login: '',
  password: '',
})
const loading = ref(false)
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const turnstileToken = ref('')
const turnstileResetKey = ref(0)
const turnstileError = ref('')
const formError = ref('')
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
  formError.value = ''
  if (turnstileError.value) {
    formError.value = '人机验证配置加载失败，请刷新页面后重试'
    ElMessage.warning(formError.value)
    return
  }
  if (!form.login || !form.password) {
    formError.value = '请输入账号和密码'
    ElMessage.warning(formError.value)
    return
  }
  if (turnstile.login_enabled && !turnstileToken.value) {
    formError.value = '请完成人机验证'
    ElMessage.warning(formError.value)
    return
  }
  loading.value = true
  try {
    await auth.login(form.login, form.password, false, turnstileToken.value)
    await router.push((route.query.redirect as string) || '/home/records')
  } catch (error) {
    formError.value = apiErrorMessage(error, '登录失败')
    ElMessage.error(formError.value)
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
