import { defineStore } from 'pinia'
import api from '@/api/client'

/** القوائم المشتركة (المصادر، طرق الدفع، البنود، الأشخاص) */
export const useMetaStore = defineStore('meta', {
  state: () => ({
    income_sources: [],
    payment_methods: [],
    expense_categories: [],
    custodians: [],
    settings: { mosque_name: 'مسجد خالد بن الوليد' },
    roles: {},
    permissions: {},
    request_statuses: {},
    ledger_types: {},
    loaded: false,
  }),

  actions: {
    async load(force = false) {
      if (this.loaded && !force) return
      const { data } = await api.get('/meta')
      Object.assign(this, data, { loaded: true })
    },
  },
})
