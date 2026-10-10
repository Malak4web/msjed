import { defineStore } from 'pinia'
import api, { getToken, setToken } from '@/api/client'

export const USER_KEY = 'mosque.user'

function getStoredUser() {
  try {
    const raw = localStorage.getItem(USER_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function setStoredUser(user) {
  try {
    user ? localStorage.setItem(USER_KEY, JSON.stringify(user)) : localStorage.removeItem(USER_KEY)
  } catch {
    /* التخزين غير متاح */
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: getStoredUser(),
    token: getToken(),
    loaded: !!getStoredUser() && !!getToken(),
  }),

  getters: {
    isLoggedIn: (s) => !!s.token && !!s.user,
    isImam: (s) => !!s.user?.is_imam,
    can: (s) => (permission) => !!s.user?.permissions?.includes(permission),
  },

  actions: {
    async login(phone, password, remember = true) {
      const { data } = await api.post('/auth/login', {
        phone,
        password,
        remember,
        device: navigator.userAgent.slice(0, 90),
      })
      this.token = data.token
      setToken(data.token)
      this.user = data.user
      setStoredUser(data.user)
      this.loaded = true
    },

    async fetchMe() {
      if (!this.token) {
        this.loaded = true
        return
      }
      try {
        const { data } = await api.get('/auth/me')
        this.user = data.user
        setStoredUser(data.user)
      } catch (err) {
        // لا نحذف الجلسة إلا إذا رد السيرفر برمز 401 صريح (انتهاء أو إلغاء التوكن)
        if (err?.response?.status === 401) {
          this.clear()
        }
      } finally {
        this.loaded = true
      }
    },

    async logout() {
      try {
        await api.post('/auth/logout')
      } catch {
        /* حتى لو السيرفر مش متاح نخرج محلياً */
      }
      this.clear()
    },

    clear() {
      this.user = null
      this.token = null
      setToken(null)
      setStoredUser(null)
    },
  },
})
