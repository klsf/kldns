<template>
  <div v-if="enabled" class="turnstile-shell">
    <div v-if="!siteKey" class="turnstile-state turnstile-error">人机验证 Site Key 未配置</div>
    <div v-else-if="loadError" class="turnstile-state turnstile-error">
      <span>{{ loadError }}</span>
      <el-button text type="primary" @click="retry">重试</el-button>
    </div>
    <div v-else ref="container" class="turnstile-box" />
    <div v-if="loading" class="turnstile-state">正在加载人机验证...</div>
  </div>
</template>

<script setup lang="ts">
/* global window, document, HTMLElement, HTMLScriptElement */
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps<{
  enabled: boolean
  siteKey: string
  resetKey?: number
}>()

const emit = defineEmits<{
  'update:token': [value: string]
}>()

declare global {
  interface Window {
    turnstile?: {
      render: (container: HTMLElement, options: Record<string, unknown>) => string
      reset: (widgetID?: string) => void
      remove?: (widgetID: string) => void
    }
  }
}

const container = ref<HTMLElement | null>(null)
const loading = ref(false)
const loadError = ref('')
let widgetID = ''
let renderSeq = 0
let scriptPromise: Promise<void> | null = null

onMounted(render)
onBeforeUnmount(removeWidget)

watch(() => [props.enabled, props.siteKey], render, { flush: 'post' })
watch(
  () => props.resetKey,
  () => {
    emit('update:token', '')
    if (widgetID) window.turnstile?.reset(widgetID)
  },
)

async function render() {
  const seq = ++renderSeq
  emit('update:token', '')
  loadError.value = ''
  removeWidget()
  if (!props.enabled || !props.siteKey) return
  await nextTick()
  if (!container.value) return
  loading.value = true
  try {
    await loadScript()
    if (seq !== renderSeq || !container.value || !window.turnstile) return
    widgetID = window.turnstile.render(container.value, {
      sitekey: props.siteKey,
      callback: (token: string) => emit('update:token', token),
      'expired-callback': () => emit('update:token', ''),
      'error-callback': () => emit('update:token', ''),
    })
  } catch {
    if (seq === renderSeq) loadError.value = '人机验证加载失败，请检查网络或稍后重试'
  } finally {
    if (seq === renderSeq) loading.value = false
  }
}

function removeWidget() {
  if (widgetID && window.turnstile?.remove) {
    window.turnstile.remove(widgetID)
  }
  widgetID = ''
}

function retry() {
  void render()
}

function loadScript() {
  if (window.turnstile) return Promise.resolve()
  if (scriptPromise) return scriptPromise
  scriptPromise = new Promise((resolve, reject) => {
    const existing = document.querySelector<HTMLScriptElement>('script[data-turnstile="1"]')
    if (existing) {
      if (existing.dataset.loaded === '1') {
        if (window.turnstile) {
          resolve()
        } else {
          reject(new Error('turnstile script loaded without API'))
        }
        return
      }
      existing.addEventListener('load', () => resolve(), { once: true })
      existing.addEventListener('error', reject, { once: true })
      return
    }
    const script = document.createElement('script')
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    script.async = true
    script.defer = true
    script.dataset.turnstile = '1'
    script.onload = () => {
      script.dataset.loaded = '1'
      resolve()
    }
    script.onerror = reject
    document.head.appendChild(script)
  })
  scriptPromise.catch(() => {
    scriptPromise = null
  })
  return scriptPromise
}
</script>

<style scoped>
.turnstile-shell {
  display: grid;
  justify-content: center;
  gap: 8px;
  min-height: 65px;
  margin: 2px 0 14px;
}

.turnstile-box {
  min-width: 300px;
  min-height: 65px;
}

.turnstile-state {
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0 12px;
  border: 1px solid #d9e7ec;
  border-radius: 8px;
  color: #64748b;
  background: #f8fcfc;
  font-size: 13px;
  font-weight: 700;
}

.turnstile-error {
  color: #b45309;
  border-color: #fde68a;
  background: #fffbeb;
}

@media (max-width: 380px) {
  .turnstile-box {
    transform: scale(0.92);
    transform-origin: top center;
  }
}
</style>
