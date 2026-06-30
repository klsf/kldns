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
      <RouterLink to="/home/points" @click="closeMobileMenu"><Coins :size="18" />积分中心</RouterLink>
      <RouterLink to="/home/tokens" @click="closeMobileMenu"><KeyRound :size="18" />开放 API</RouterLink>
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
          <el-dropdown trigger="click" placement="bottom-end" @command="handleAccountCommand">
            <button class="user-chip account-trigger" type="button">
              <span>{{ auth.user?.username || 'user' }}</span>
              <ChevronDown :size="15" />
            </button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="password" class="account-menu-item">
                  <ShieldCheck :size="16" />
                  <span>修改密码</span>
                </el-dropdown-item>
                <el-dropdown-item v-if="auth.isAdmin" command="admin" class="account-menu-item">
                  <Gauge :size="16" />
                  <span>管理后台</span>
                </el-dropdown-item>
                <el-dropdown-item command="logout" class="account-menu-item">
                  <LogOut :size="16" />
                  <span>退出登录</span>
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </header>
      <main class="content-pane">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { BadgePlus, ChevronDown, Coins, Gauge, Globe2, KeyRound, ListTree, LogOut, Menu, ShieldCheck, X } from 'lucide-vue-next'
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

function handleAccountCommand(command: string | number | object) {
  if (command === 'password') {
    closeMobileMenu()
    router.push('/home/password')
  }
  if (command === 'admin') {
    closeMobileMenu()
    router.push('/admin')
  }
  if (command === 'logout') {
    logout()
  }
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

.account-trigger {
  gap: 6px;
  border: 1px solid var(--line);
  cursor: pointer;
}

.account-trigger svg {
  flex: 0 0 auto;
  color: var(--muted);
}

:global(.account-menu-item) {
  display: flex;
  align-items: center;
  gap: 8px;
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

  .user-shell .side-nav a {
    flex: initial;
    width: 100%;
    white-space: normal;
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
    display: inline-flex;
    min-height: 38px;
    max-width: 132px;
    padding: 0 10px;
  }

  .user-shell .user-chip > span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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
