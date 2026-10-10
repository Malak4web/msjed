import { defineStore } from 'pinia'

let toastId = 0

/** رسائل النجاح/الخطأ، ونافذة التأكيد، والأدراج العامة (تسجيل إيراد...) */
export const useUiStore = defineStore('ui', {
  state: () => ({
    toasts: [],
    confirm: null,
    incomeDrawer: false,
    actionSheet: false,
    // بيزيد كل ما عملية مالية تتم عشان الصفحات تعمل refresh
    dataVersion: 0,
  }),

  actions: {
    toast(message, type = 'success', timeout = 4000) {
      const id = ++toastId
      this.toasts.push({ id, message, type })
      setTimeout(() => this.dismiss(id), timeout)
    },
    success(message) {
      this.toast(message, 'success')
    },
    error(message) {
      this.toast(message, 'error', 6000)
    },
    dismiss(id) {
      this.toasts = this.toasts.filter((t) => t.id !== id)
    },

    /**
     * ask({ title, message, confirmText, danger, reason: true }) → Promise<false | true | string>
     * لو reason: true بيطلب سبب إلزامي ويرجّعه.
     */
    ask(options) {
      return new Promise((resolve) => {
        this.confirm = { ...options, resolve }
      })
    },
    closeConfirm(result) {
      this.confirm?.resolve(result)
      this.confirm = null
    },

    changed() {
      this.dataVersion++
    },
  },
})
