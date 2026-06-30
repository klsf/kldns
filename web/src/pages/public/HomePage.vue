<template>
  <main class="public-page">
    <PublicHomeHero
      :account-link="accountLink"
      :available-domains="availableDomains"
      :is-logged-in="auth.isLoggedIn"
      :primary-action="primaryAction"
      :secondary-action="secondaryAction"
      :username="auth.user?.username"
    />
    <CapabilityBand />
    <WorkflowStrip />
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '../../app/stores/auth'
import { listPublicDomains } from '../../api/domains'
import type { Domain } from '../../types/domain'
import CapabilityBand from '../../features/public-home/CapabilityBand.vue'
import PublicHomeHero from '../../features/public-home/PublicHomeHero.vue'
import WorkflowStrip from '../../features/public-home/WorkflowStrip.vue'

const auth = useAuthStore()
const availableDomains = ref<Domain[]>([])
const accountLink = computed(() => (auth.isAdmin ? '/admin' : '/home/records'))
const primaryAction = computed(() =>
  auth.isLoggedIn ? { to: '/home/records', label: '进入用户中心' } : { to: '/register', label: '注册账号' },
)
const secondaryAction = computed(() =>
  auth.isLoggedIn
    ? { to: auth.isAdmin ? '/admin' : '/home/register', label: auth.isAdmin ? '进入管理后台' : '注册域名' }
    : { to: '/login', label: '已有账号登录' },
)

onMounted(() => {
  if (auth.token && !auth.loaded) {
    void auth.loadMe().catch(() => auth.logout())
  }
  void loadPublicDomains()
})

async function loadPublicDomains() {
  try {
    const response = await listPublicDomains()
    availableDomains.value = response.data
  } catch {
    availableDomains.value = []
  }
}
</script>

<style scoped>
.public-page {
  min-height: 100vh;
  overflow-x: clip;
  color: #15282d;
  background: linear-gradient(180deg, #f4f8f7 0%, #eef5f4 48%, #f7faf9 100%);
}
</style>
