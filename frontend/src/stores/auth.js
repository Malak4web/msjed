import { defineStore } from 'pinia'
import api, { getToken, setToken } from '@/api/client'

export const USER_KEY = 'mosque.user'
export const IMPERSONATOR_KEY = 'mosque.impersonator'

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

function getStoredImpersonator() {
  try {
    const raw = localStorage.getItem(IMPERSONATOR_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function setStoredImpersonator(data) {
  try {
    data ? localStorage.setItem(IMPERSONATOR_KEY, JSON.stringify(data)) : localStorage.removeItem(IMPERSONATOR_KEY)
  } catch {
    /* التخزين غير متاح */
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: getStoredUser(),
    token: getToken(),
    impersonator: getStoredImpersonator(),
    loaded: !!getStoredUser() && !!getToken(),
  }),

  getters: {
    isLoggedIn: (s) => !!s.token && !!s.user,
    isImam: (s) => !!s.user?.is_imam,
    isImpersonating: (s) => !!s.impersonator,
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
      this.impersonator = null
      setStoredImpersonator(null)
      this.loaded = true
    },

    async impersonate(userId) {
      const originalSession = {
        token: this.token,
        user: this.user,
      }

      const { data } = await api.post(`/users/${userId}/impersonate`)

      // حفظ جلسة الإمام الأصلية
      setStoredImpersonator(originalSession)
      this.impersonator = originalSession

      // التبديل للمستخدم المستهدف
      this.token = data.token
      setToken(data.token)
      this.user = data.user
      setStoredUser(data.user)
      this.loaded = true

      return data
    },

    async stopImpersonation() {
      if (!this.impersonator) return

      const original = { ...this.impersonator }

      // استعادة جلسة الإمام الأصلية
      this.token = original.token
      setToken(original.token)
      this.user = original.user
      setStoredUser(original.user)
      this.impersonator = null
      setStoredImpersonator(null)

      // جلب أحدث بيانات لحساب الإمام للتأكيد
      await this.fetchMe()
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
      this.impersonator = null
      setToken(null)
      setStoredUser(null)
      setStoredImpersonator(null)
    },
  },
})
