<template>
  <div :class="['app-shell', 'user-shell', { 'user-menu-open': menuOpen }]">
    <button class="user-menu-mask" type="button" aria-label="关闭菜单" @click="closeMobileMenu" />
    <aside class="side-nav">
      <div class="brand-block">
        <span class="brand-mark">KD</span>
        <div>
          <strong>KLDNS</strong>
          <small>用户中心</small>
        </div>
      </div>
      <RouterLink to="/home/register" @click="closeMobileMenu"><BadgePlus :size="18" />域名注册</RouterLink>
      <RouterLink to="/home/domains" @click="closeMobileMenu"><Globe2 :size="18" />域名列表</RouterLink>
      <RouterLink to="/home/records" @click="closeMobileMenu"><ListTree :size="18" />域名解析</RouterLink>
      <RouterLink to="/home/tokens" @click="closeMobileMenu"><KeyRound :size="18" />开放 API</RouterLink>
      <RouterLink to="/home/password" @click="closeMobileMenu"><ShieldCheck :size="18" />账号安全</RouterLink>
      <RouterLink v-if="auth.isAdmin" to="/admin" @click="closeMobileMenu"><Gauge :size="18" />管理后台</RouterLink>
      <button class="nav-action" type="button" @click="logout"><LogOut :size="18" />退出</button>
    </aside>
    <div class="app-main">
      <header class="top-bar">
        <button class="mobile-menu-toggle" type="button" :aria-label="menuOpen ? '收起菜单' : '展开菜单'" :aria-expanded="menuOpen" @click="toggleMenu">
          <X v-if="menuOpen" :size="20" />
          <Menu v-else :size="20" />
        </button>
        <div class="top-title">
          <strong>二级域名解析工作台</strong>
        </div>
        <div class="top-meta">
          <span class="desktop-account-meta">积分 {{ auth.user?.points ?? 0 }}</span>
          <span class="user-chip">
            <span>{{ auth.user?.username || 'user' }}</span>
            <small>积分 {{ auth.user?.points ?? 0 }}</small>
          </span>
        </div>
      </header>
      <main class="content-pane">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { BadgePlus, Gauge, Globe2, KeyRound, ListTree, LogOut, Menu, ShieldCheck, X } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../app/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const menuOpen = ref(false)

watch(
  () => router.currentRoute.value.fullPath,
  () => {
    menuOpen.value = false
  },
)

function toggleMenu() {
  menuOpen.value = !menuOpen.value
}

function closeMobileMenu() {
  menuOpen.value = false
}

function logout() {
  closeMobileMenu()
  auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.user-menu-mask,
.mobile-menu-toggle {
  display: none;
}

.user-shell .user-chip small {
  display: none;
}

@media (max-width: 980px) {
  .user-shell {
    position: relative;
  }

  .user-shell .side-nav {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 40;
    width: 248px;
    height: 100vh;
    min-height: 0;
    flex-direction: column;
    align-items: stretch;
    overflow: hidden auto;
    padding: 18px 14px;
    transform: translateX(-106%);
    transition: transform 0.22s ease;
  }

  .user-shell.user-menu-open .side-nav {
    transform: translateX(0);
  }

  .user-shell .brand-block {
    min-width: 0;
    min-height: 76px;
    padding: 8px 10px 20px;
  }

  .user-shell .side-nav a,
  .user-shell .nav-action {
    flex: initial;
    width: 100%;
    white-space: normal;
  }

  .user-shell .nav-action {
    margin-top: auto;
  }

  .user-menu-mask {
    position: fixed;
    inset: 0 0 0 248px;
    z-index: 35;
    border: 0;
    background: rgba(6, 24, 33, 0.44);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.18s ease;
  }

  .user-shell.user-menu-open .user-menu-mask {
    display: block;
    opacity: 1;
    pointer-events: auto;
  }

  .mobile-menu-toggle {
    width: 38px;
    height: 38px;
    flex: 0 0 auto;
    display: inline-grid;
    place-items: center;
    border: 1px solid var(--line);
    border-radius: 8px;
    color: #17282d;
    background: #ffffff;
    cursor: pointer;
  }

  .user-shell .top-bar {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
  }

  .user-shell .top-meta {
    min-width: 0;
    grid-column: 3;
    grid-row: 1;
    justify-content: flex-end;
  }

  .user-shell .user-chip {
    display: grid;
    gap: 2px;
    justify-items: end;
    min-height: 38px;
    padding: 5px 10px;
    line-height: 1.15;
  }

  .user-shell .user-chip small {
    display: block;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
  }

  .user-shell .top-title {
    min-width: 0;
  }

  .user-shell .top-title strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .desktop-account-meta {
    display: none;
  }
}
</style>
