import { defineStore } from 'pinia'
import { me, login as requestLogin, type User } from '../../api/auth'
import { TOKEN_STORAGE_KEY, USER_STORAGE_KEY } from '../../api/http'

interface AuthState {
  token: string
  user: User | null
  loaded: boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: localStorage.getItem(TOKEN_STORAGE_KEY) || '',
    user: readStoredUser(),
    loaded: false,
  }),
  getters: {
    isLoggedIn: (state) => Boolean(state.token),
    isAdmin: (state) => state.user?.group_id === 99,
  },
  actions: {
    async login(login: string, password: string, admin = false, turnstileToken = '') {
      const response = await requestLogin({ login, password, turnstile_token: turnstileToken || undefined }, admin)
      this.token = response.data.token
      this.user = response.data.user
      localStorage.setItem(TOKEN_STORAGE_KEY, this.token)
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
      return response.data.user
    },
    async loadMe() {
      if (!this.token) {
        this.loaded = true
        return null
      }
      const response = await me()
      this.user = response.data
      this.loaded = true
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
      return this.user
    },
    updatePoints(points: number) {
      if (!this.user) return
      this.user = { ...this.user, points }
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
    },
    logout() {
      this.token = ''
      this.user = null
      this.loaded = true
      localStorage.removeItem(TOKEN_STORAGE_KEY)
      localStorage.removeItem(USER_STORAGE_KEY)
    },
  },
})

function readStoredUser(): User | null {
  const raw = localStorage.getItem(USER_STORAGE_KEY)
  if (!raw) return null
  try {
    return JSON.parse(raw) as User
  } catch {
    localStorage.removeItem(USER_STORAGE_KEY)
    return null
  }
}
