import { ref } from 'vue'
import api from '@/api/client'

/**
 * تفعيل/إيقاف إشعارات Push على الجهاز الحالي.
 * محتاج HTTPS (أو localhost) ومتصفح بيدعم Service Worker + Push.
 */
const supported = typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
const enabled = ref(false)
const busy = ref(false)

function urlBase64ToUint8Array(base64) {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'))
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)))
}

async function registration() {
  return navigator.serviceWorker.getRegistration() || navigator.serviceWorker.ready
}

async function refresh() {
  if (!supported) return
  const reg = await registration()
  enabled.value = !!(reg && (await reg.pushManager.getSubscription())) && Notification.permission === 'granted'
}

/** بيرجّع رسالة خطأ بالعربي أو null لو نجح */
async function enable() {
  if (!supported) return 'المتصفح ده مش بيدعم الإشعارات. جرّب Chrome أو ثبّت التطبيق على الشاشة الرئيسية.'
  busy.value = true
  try {
    const permission = await Notification.requestPermission()
    if (permission !== 'granted') return 'لازم تسمح بالإشعارات من إعدادات المتصفح.'

    const { data } = await api.get('/push/key')
    if (!data.key) return 'الإشعارات مش متظبطة على السيرفر لسه.'

    const reg = await registration()
    if (!reg) return 'التطبيق محتاج يتفتح من النسخة المثبّتة أو بعد ريفريش.'

    const sub = (await reg.pushManager.getSubscription()) || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(data.key) }))
    await api.post('/push/subscribe', sub.toJSON())
    enabled.value = true
    return null
  } catch (e) {
    return e?.response?.data?.message || 'مقدرناش نفعّل الإشعارات. جرّب تاني.'
  } finally {
    busy.value = false
  }
}

async function disable() {
  busy.value = true
  try {
    const reg = await registration()
    const sub = await reg?.pushManager.getSubscription()
    if (sub) {
      await api.post('/push/unsubscribe', { endpoint: sub.endpoint }).catch(() => {})
      await sub.unsubscribe()
    }
    enabled.value = false
  } finally {
    busy.value = false
  }
}

export function usePush() {
  return { supported, enabled, busy, refresh, enable, disable }
}
