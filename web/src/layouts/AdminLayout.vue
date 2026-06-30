<template>
  <div :class="['app-shell', 'admin-shell', { 'admin-menu-open': menuOpen }]">
    <button class="admin-menu-mask" type="button" aria-label="关闭菜单" @click="closeMobileMenu" />
    <aside class="side-nav">
      <div class="brand-block">
        <span class="brand-mark">KD</span>
        <div>
          <strong>KLDNS</strong>
          <small>管理后台</small>
        </div>
      </div>
      <el-menu
        class="admin-nav-menu"
        :default-active="activeMenu"
        :default-openeds="openMenus"
        router
        unique-opened
        @select="closeMobileMenu"
      >
        <el-menu-item index="/admin">
          <Gauge :size="18" />
          <span>运行概览</span>
        </el-menu-item>
        <el-sub-menu index="users">
          <template #title>
            <UsersRound :size="18" />
            <span>用户管理</span>
          </template>
          <el-menu-item index="/admin/users">
            <span>用户列表</span>
          </el-menu-item>
          <el-menu-item index="/admin/groups">
            <span>分组列表</span>
          </el-menu-item>
        </el-sub-menu>
        <el-sub-menu index="domains">
          <template #title>
            <Globe2 :size="18" />
            <span>域名管理</span>
          </template>
          <el-menu-item index="/admin/domains">
            <span>主域管理</span>
          </el-menu-item>
          <el-menu-item index="/admin/subdomains">
            <span>二级域名</span>
          </el-menu-item>
          <el-menu-item index="/admin/records">
            <span>解析管理</span>
          </el-menu-item>
        </el-sub-menu>
        <el-menu-item index="/admin/logs">
          <ScrollText :size="18" />
          <span>日志审计</span>
        </el-menu-item>
        <el-menu-item index="/admin/settings">
          <Settings :size="18" />
          <span>系统设置</span>
        </el-menu-item>
      </el-menu>
      <RouterLink class="nav-action admin-return-link" to="/home/records" @click="closeMobileMenu"><Home :size="18" />用户中心</RouterLink>
      <button class="nav-action" type="button" @click="logout"><LogOut :size="18" />退出登录</button>
    </aside>
    <div class="app-main">
      <header class="top-bar">
        <button class="mobile-menu-toggle" type="button" :aria-label="menuOpen ? '收起菜单' : '展开菜单'" :aria-expanded="menuOpen" @click="toggleMenu">
          <X v-if="menuOpen" :size="20" />
          <Menu v-else :size="20" />
        </button>
        <div class="top-title">
          <strong>DNS 分发平台管理后台</strong>
        </div>
        <div class="top-meta">
          <span class="user-chip">{{ auth.user?.username || 'admin' }}</span>
        </div>
      </header>
      <main class="content-pane">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Gauge, Globe2, Home, LogOut, Menu, ScrollText, Settings, UsersRound, X } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../app/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const menuOpen = ref(false)
const userMenuPaths = ['/admin/users', '/admin/groups']
const domainMenuPaths = ['/admin/domains', '/admin/subdomains', '/admin/records', '/admin/providers']
const activeMenu = computed(() => (route.path === '/admin/' ? '/admin' : route.path))
const openMenus = computed(() => {
  if (userMenuPaths.some((path) => route.path.startsWith(path))) return ['users']
  if (domainMenuPaths.some((path) => route.path.startsWith(path))) return ['domains']
  return []
})

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
.admin-menu-mask,
.mobile-menu-toggle {
  display: none;
}

.admin-nav-menu {
  flex: 1 1 auto;
  min-width: 0;
  border-right: 0;
  background: transparent;
}

.admin-nav-menu :deep(.el-menu-item),
.admin-nav-menu :deep(.el-sub-menu__title) {
  height: 42px;
  margin: 0 0 8px;
  padding: 0 14px !important;
  border-radius: 8px;
  color: var(--nav-text);
  line-height: 42px;
  gap: 10px;
}

.admin-nav-menu :deep(.el-menu-item:hover),
.admin-nav-menu :deep(.el-sub-menu__title:hover) {
  color: #ffffff;
  background: rgba(87, 232, 196, 0.12);
}

.admin-return-link {
  text-decoration: none;
}

.admin-nav-menu :deep(.el-menu-item.is-active) {
  color: #ffffff;
  background: linear-gradient(135deg, rgba(13, 184, 141, 0.95), rgba(22, 132, 179, 0.74));
  box-shadow: 0 14px 28px rgba(8, 119, 96, 0.3);
}

.admin-nav-menu :deep(.el-sub-menu.is-active > .el-sub-menu__title) {
  color: #ffffff;
  background: rgba(87, 232, 196, 0.12);
}

.admin-nav-menu :deep(.el-sub-menu .el-menu) {
  padding: 0 0 4px 24px;
  background: transparent;
}

.admin-nav-menu :deep(.el-sub-menu .el-menu-item) {
  height: 36px;
  margin-bottom: 6px;
  padding-left: 18px !important;
  line-height: 36px;
  color: #b9cfd2;
}

.admin-nav-menu :deep(.el-sub-menu__icon-arrow) {
  right: 12px;
  color: #9db7bb;
}

@media (max-width: 980px) {
  .admin-shell {
    position: relative;
  }

  .admin-shell .side-nav {
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

  .admin-shell.admin-menu-open .side-nav {
    transform: translateX(0);
  }

  .admin-shell .brand-block {
    min-width: 0;
    min-height: 76px;
    padding: 8px 10px 20px;
  }

  .admin-shell .side-nav a,
  .admin-shell .admin-nav-menu,
  .admin-shell .nav-action {
    flex: initial;
    width: 100%;
    white-space: normal;
  }

  .admin-shell .nav-action {
    margin-top: auto;
  }

  .admin-menu-mask {
    position: fixed;
    inset: 0 0 0 248px;
    z-index: 35;
    border: 0;
    background: rgba(6, 24, 33, 0.44);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.18s ease;
  }

  .admin-shell.admin-menu-open .admin-menu-mask {
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

  .admin-shell .top-bar {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
  }

  .admin-shell .top-meta {
    min-width: 0;
    grid-column: 3;
    grid-row: 1;
    justify-content: flex-end;
  }

  .admin-shell .top-title {
    min-width: 0;
  }

  .admin-shell .top-title strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
