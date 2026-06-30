<template>
  <section class="page-stack">
    <header class="page-header">
      <div>
        <h1>系统设置</h1>
        <p class="resource-note">维护注册审核、人机验证、解析策略和预留前缀配置。</p>
      </div>
      <el-button type="primary" :loading="saving" @click="save">保存设置</el-button>
    </header>

    <div v-loading="loading" class="settings-workbench">
      <el-form class="settings-form" label-position="top">
        <section class="settings-panel">
          <div class="panel-head">
            <div>
              <h2>注册审核</h2>
              <span>控制新用户是否可注册、注册后的审核方式和初始积分。</span>
            </div>
          </div>
          <div class="settings-grid">
            <el-form-item label="开放注册">
              <el-switch v-model="form.user.registerOpen" active-text="开启" inactive-text="关闭" />
            </el-form-item>
            <el-form-item label="注册会员审核">
              <el-segmented v-model="form.user.reviewMode" :options="reviewModeOptions" />
            </el-form-item>
            <el-form-item label="注册初始积分">
              <el-input-number v-model="form.user.initialPoints" :min="0" :max="1000000" class="full-control" />
            </el-form-item>
          </div>
        </section>

        <section class="settings-panel">
          <div class="panel-head">
            <div>
              <h2>Cloudflare Turnstile</h2>
              <span>配置人机验证，开启后注册和登录请求都会由后端校验一次性 token。</span>
            </div>
            <el-tag v-if="form.turnstile.secretConfigured" class="compact-tag" type="success">已保存密钥</el-tag>
          </div>
          <div class="settings-grid turnstile-grid">
            <el-form-item label="Site Key">
              <el-input v-model="form.turnstile.siteKey" autocomplete="off" />
            </el-form-item>
            <el-form-item label="Secret Key">
              <el-input v-model="form.turnstile.secretKey" type="password" show-password autocomplete="new-password" placeholder="留空保留当前密钥" />
            </el-form-item>
            <el-form-item label="开启场景">
              <el-checkbox-group v-model="form.turnstile.scenes">
                <el-checkbox label="注册" value="register" />
                <el-checkbox label="登录" value="login" />
              </el-checkbox-group>
            </el-form-item>
          </div>
        </section>

        <section class="settings-panel">
          <div class="panel-head">
            <div>
              <h2>解析配置</h2>
              <span>控制用户注册域名后的解析范围，以及不能被注册的保留前缀。</span>
            </div>
          </div>
          <div class="settings-grid">
            <el-form-item label="无限下级解析">
              <el-switch v-model="form.dns.allowUnlimitedSubdomainRecords" active-text="支持" inactive-text="关闭" />
            </el-form-item>
            <el-form-item label="保留主机前缀" class="reserved-field">
              <el-input v-model="form.reservedNames" type="textarea" :rows="4" placeholder="www, w, m, 3g, 4g, qq" />
            </el-form-item>
          </div>
        </section>
      </el-form>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { listSettings, saveSettings, type SettingItem } from '../../api/admin'

type SettingMap = Record<string, string>

const loading = ref(false)
const saving = ref(false)
const reviewModeOptions = [
  { label: '自动审核', value: 'auto' },
  { label: '人工审核', value: 'manual' },
]
const form = reactive({
  user: {
    registerOpen: true,
    reviewMode: 'auto',
    initialPoints: 100,
  },
  turnstile: {
    siteKey: '',
    secretKey: '',
    secretConfigured: false,
    scenes: [] as string[],
  },
  dns: {
    allowUnlimitedSubdomainRecords: true,
  },
  reservedNames: 'www, w, m, 3g, 4g, qq',
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const response = await listSettings()
    applySettings(response.data)
  } finally {
    loading.value = false
  }
}

async function save() {
  if (form.turnstile.scenes.length > 0 && !turnstileConfigComplete()) {
    ElMessage.warning('开启人机验证前必须填写 Site Key 和 Secret Key')
    return
  }
  saving.value = true
  try {
    await saveSettings(buildPayload())
    ElMessage.success('设置已保存')
    form.turnstile.secretKey = ''
    await load()
  } finally {
    saving.value = false
  }
}

function applySettings(items: SettingItem[]) {
  const values = Object.fromEntries(items.map((item) => [item.key, item.value]))
  const user = parseObject(values.array_user)
  form.user.registerOpen = user.reg !== '0'
  form.user.reviewMode = user.review_mode === 'manual' ? 'manual' : 'auto'
  form.user.initialPoints = numberValue(user.point, 100, 0, 1000000)

  const turnstile = parseObject(values.array_turnstile)
  form.turnstile.siteKey = turnstile.site_key || ''
  form.turnstile.secretKey = ''
  form.turnstile.secretConfigured = truthy(turnstile.secret_configured)
  form.turnstile.scenes = [
    turnstile.register_enabled !== '0' && truthy(turnstile.register_enabled) ? 'register' : '',
    turnstile.login_enabled !== '0' && truthy(turnstile.login_enabled) ? 'login' : '',
  ].filter(Boolean)

  const dns = parseObject(values.array_dns)
  form.dns.allowUnlimitedSubdomainRecords = dns.unlimited_subdomain_records !== '0'

  form.reservedNames = values.reserve_domain_name || 'www, w, m, 3g, 4g, qq'
}

function buildPayload(): SettingMap {
  return {
    array_user: JSON.stringify({
      reg: boolValue(form.user.registerOpen),
      review_mode: form.user.reviewMode,
      point: String(form.user.initialPoints),
    }),
    array_turnstile: JSON.stringify({
      site_key: form.turnstile.siteKey.trim(),
      secret_key: form.turnstile.secretKey,
      register_enabled: boolValue(form.turnstile.scenes.includes('register')),
      login_enabled: boolValue(form.turnstile.scenes.includes('login')),
    }),
    array_dns: JSON.stringify({
      unlimited_subdomain_records: boolValue(form.dns.allowUnlimitedSubdomainRecords),
    }),
    reserve_domain_name: normalizeList(form.reservedNames),
  }
}

function parseObject(raw?: string): SettingMap {
  if (!raw?.trim()) return {}
  try {
    const parsed = JSON.parse(raw) as Record<string, unknown>
    return Object.fromEntries(Object.entries(parsed).map(([key, value]) => [key, String(value ?? '')]))
  } catch {
    return {}
  }
}

function truthy(value?: string) {
  return ['1', 'true', 'yes', 'on', 'enabled'].includes(String(value || '').trim().toLowerCase())
}

function boolValue(value: boolean) {
  return value ? '1' : '0'
}

function turnstileConfigComplete() {
  return Boolean(form.turnstile.siteKey.trim() && (form.turnstile.secretKey.trim() || form.turnstile.secretConfigured))
}

function numberValue(value: string | undefined, fallback: number, min: number, max: number) {
  const parsed = Number.parseInt(String(value || ''), 10)
  if (!Number.isFinite(parsed)) return fallback
  return Math.min(Math.max(parsed, min), max)
}

function normalizeList(value: string) {
  return value
    .split(/[\s,，、|]+/)
    .map((item) => item.trim())
    .filter(Boolean)
    .join(',')
}
</script>

<style scoped>
.settings-workbench {
  min-height: 360px;
}

.settings-form {
  display: grid;
  gap: 18px;
}

.settings-panel {
  display: grid;
  gap: 18px;
  padding: 22px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: var(--shadow-strong);
}

.panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.panel-head h2 {
  margin: 0 0 6px;
  color: #17282d;
  font-size: 18px;
}

.panel-head span {
  color: var(--muted);
  line-height: 1.6;
}

.settings-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.turnstile-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.reserved-field {
  grid-column: span 2;
}

@media (max-width: 1120px) {
  .settings-grid,
  .turnstile-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .reserved-field {
    grid-column: 1 / -1;
  }
}

@media (max-width: 680px) {
  .settings-panel {
    padding: 16px;
  }

  .panel-head {
    flex-direction: column;
  }

  .settings-grid,
  .turnstile-grid {
    grid-template-columns: 1fr;
  }
}
</style>
