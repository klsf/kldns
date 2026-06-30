import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const UserLayout = () => import('../../layouts/UserLayout.vue')
const AdminLayout = () => import('../../layouts/AdminLayout.vue')
const LoginPage = () => import('../../pages/auth/LoginPage.vue')
const RegisterPage = () => import('../../pages/auth/RegisterPage.vue')
const HomePage = () => import('../../pages/public/HomePage.vue')
const UserRecordList = () => import('../../pages/user/UserRecordList.vue')
const UserSubdomainRegister = () => import('../../pages/user/UserSubdomainRegister.vue')
const UserDomainList = () => import('../../pages/user/UserDomainList.vue')
const UserTokenList = () => import('../../pages/user/UserTokenList.vue')
const UserPasswordPage = () => import('../../pages/user/UserPasswordPage.vue')
const AdminDashboard = () => import('../../pages/admin/AdminDashboard.vue')
const AdminDomainList = () => import('../../pages/admin/AdminDomainList.vue')
const AdminRecordList = () => import('../../pages/admin/AdminRecordList.vue')
const AdminSubdomainList = () => import('../../pages/admin/AdminSubdomainList.vue')
const AdminUserList = () => import('../../pages/admin/AdminUserList.vue')
const AdminGroupList = () => import('../../pages/admin/AdminGroupList.vue')
const AdminLogList = () => import('../../pages/admin/AdminLogList.vue')
const AdminSettings = () => import('../../pages/admin/AdminSettings.vue')

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: HomePage },
    { path: '/login', component: LoginPage },
    { path: '/register', component: RegisterPage },
    {
      path: '/home',
      component: UserLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/home/register' },
        { path: 'register', component: UserSubdomainRegister },
        { path: 'domains', component: UserDomainList },
        { path: 'records', component: UserRecordList },
        { path: 'tokens', component: UserTokenList },
        { path: 'password', component: UserPasswordPage },
      ],
    },
    {
      path: '/admin',
      component: AdminLayout,
      meta: { requiresAuth: true, requiresAdmin: true },
      children: [
        { path: '', component: AdminDashboard },
        { path: 'users', component: AdminUserList },
        { path: 'groups', component: AdminGroupList },
        { path: 'domains', component: AdminDomainList },
        { path: 'subdomains', component: AdminSubdomainList },
        { path: 'records', component: AdminRecordList },
        { path: 'providers', redirect: '/admin/domains' },
        { path: 'logs', component: AdminLogList },
        { path: 'settings', component: AdminSettings },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const needsIdentity = Boolean(to.meta.requiresAuth || to.meta.requiresAdmin)
  if (needsIdentity && auth.token && !auth.loaded) {
    try {
      await auth.loadMe()
    } catch {
      auth.logout()
    }
  }
  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return '/home/records'
  }
  return true
})
