import { ref } from 'vue'
import api, { errorMessage } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { money } from '@/utils/format'

/** إجراءات طلب الصرف بتأكيد واضح وأسباب إلزامية — onDone(updatedRequest) */
export function useRequestActions(onDone = () => {}) {
  const ui = useUiStore()
  const busy = ref(null)

  async function call(req, action, body, fallback) {
    busy.value = `${req.id}:${action}`
    try {
      const { data } = await api.post(`/expense-requests/${req.id}/${action}`, body)
      ui.success(data.message || fallback)
      ui.changed()
      onDone(data.data)
      return data.data
    } catch (e) {
      ui.error(errorMessage(e))
      return null
    } finally {
      busy.value = null
    }
  }

  async function approve(req) {
    const ok = await ui.ask({
      title: 'موافقة واعتماد الصرف؟',
      message: `صرف ${money(req.amount)} من عهدة ${req.custodian.name} لـ "${req.description}". المبلغ هيتحجز لحد ما يتنفذ.`,
      confirmText: '✓ موافقة',
      icon: 'verified',
    })
    return ok ? call(req, 'approve', {}) : null
  }

  async function reject(req) {
    const reason = await ui.ask({
      title: 'رفض الطلب',
      message: `"${req.description}" — ${money(req.amount)}`,
      confirmText: 'رفض الطلب',
      danger: true,
      reason: true,
      reasonLabel: 'سبب الرفض (هيوصل لصاحب الطلب)',
    })
    return reason ? call(req, 'reject', { reason }) : null
  }

  async function returnForEdit(req) {
    const reason = await ui.ask({
      title: 'إرجاع الطلب للتعديل',
      message: 'صاحب الطلب هيعدّله ويبعته تاني.',
      confirmText: 'إرجاع للتعديل',
      icon: 'undo',
      reason: true,
      reasonLabel: 'محتاج يعدّل إيه؟',
      reasonPlaceholder: 'مثلاً: محتاج عرض سعر من محلين',
    })
    return reason ? call(req, 'return', { reason }) : null
  }

  async function cancel(req) {
    const reason = await ui.ask({ title: 'إلغاء الطلب؟', confirmText: 'إلغاء الطلب', danger: true, reason: true, reasonLabel: 'سبب الإلغاء' })
    return reason ? call(req, 'cancel', { reason }) : null
  }

  async function reverse(req) {
    const reason = await ui.ask({
      title: `إلغاء المصروف ${req.number} بقيد عكسي؟`,
      message: `مبلغ ${money(req.actual_amount)} هيرجع لعهدة ${req.custodian.name}، والعملية الأصلية هتفضل ظاهرة.`,
      confirmText: 'إلغاء بقيد عكسي',
      danger: true,
      reason: true,
    })
    return reason ? call(req, 'reverse', { reason }) : null
  }

  return { busy, approve, reject, returnForEdit, cancel, reverse }
}
