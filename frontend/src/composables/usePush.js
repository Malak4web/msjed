import { ref, computed } from 'vue'
import api from '@/api/client'

/**
 * إشعارات Push على الجهاز الحالي.
 *
 * شروط المتصفح (مش بإيدينا): الصفحة لازم تكون HTTPS (أو localhost)، وفيه Service Worker شغال،
 * وعلى الآيفون لازم التطبيق يكون متثبّت على الشاشة الرئيسية (iOS 16.4+).
 * status بيقول بالظبط إيه اللي ناقص عشان نعرضه للمستخدم بدل ما الزرار يختفي بصمت.
 */
const isIOS = typeof navigator !== 'undefined' && /iphone|ipad|ipod/i.test(navigator.userAgent)
const isStandalone = () => window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true

const status = ref('checking') // insecure | ios-install | unsupported | denied | off | on | checking
const busy = ref(false)
const enabled = computed(() => status.value === 'on')
const supported = computed(() => ['off', 'on', 'denied'].includes(status.value))

function urlBase64ToUint8Array(base64) {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'))
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)))
}

/**
 * يرجّع الـ Service Worker الشغال، ويسجّله لو مش متسجّل.
 * أول فتحة بيحمّل ملفات التطبيق للأوفلاين فممكن ياخد وقت على النت البطيء — بنستنى لحد دقيقة.
 */
async function registration(timeout = 60000) {
  let reg = await navigator.serviceWorker.getRegistration('/')
  if (!reg) reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' })
  if (reg.active) return reg
  return Promise.race([navigator.serviceWorker.ready, new Promise((_, reject) => setTimeout(() => reject(new Error('sw-timeout')), timeout))])
}

function baseStatus() {
  if (!window.isSecureContext) return 'insecure'
  if (isIOS && !isStandalone()) return 'ios-install'
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return isIOS ? 'ios-install' : 'unsupported'
  if (Notification.permission === 'denied') return 'denied'
  return null
}

async function refresh() {
  const blocked = baseStatus()
  if (blocked) {
    status.value = blocked
    return
  }
  try {
    // متعلّقش الشاشة على "بنتأكد...": لو الـ SW لسه بيتسطّب نعرض زرار التفعيل، والتفعيل نفسه هيستناه
    const existing = await navigator.serviceWorker.getRegistration('/')
    if (!existing?.active) {
      status.value = 'off'
      registration().catch(() => {})
      return
    }
    const reg = existing
    const sub = await reg.pushManager.getSubscription()
    status.value = sub && Notification.permission === 'granted' ? 'on' : 'off'
    // لو الجهاز مشترك نتأكد إن السيرفر عارفه (مثلًا بعد مسح قاعدة البيانات أو تسجيل دخول بحساب تاني)
    if (status.value === 'on') api.post('/push/subscribe', sub.toJSON()).catch(() => {})
  } catch {
    status.value = 'off'
  }
}

/** بيرجّع رسالة خطأ بالعربي أو null لو نجح — لازم يتنادى من ضغطة زرار */
async function enable() {
  const blocked = baseStatus()
  if (blocked) {
    status.value = blocked
    return MESSAGES[blocked]
  }
  busy.value = true
  try {
    const permission = await Notification.requestPermission()
    if (permission !== 'granted') {
      status.value = permission === 'denied' ? 'denied' : 'off'
      return permission === 'denied' ? MESSAGES.denied : 'لازم تضغط "سماح" عشان الإشعارات توصلك.'
    }

    const { data } = await api.get('/push/key')
    if (!data.key) return 'الإشعارات مش متظبطة على السيرفر (مفاتيح VAPID ناقصة).'

    const reg = await registration()
    let sub = await reg.pushManager.getSubscription()
    // لو المفاتيح على السيرفر اتغيّرت، الاشتراك القديم مش هينفع — نعمل واحد جديد
    if (sub && sub.options?.applicationServerKey) {
      const current = new Uint8Array(sub.options.applicationServerKey)
      const wanted = urlBase64ToUint8Array(data.key)
      if (current.length !== wanted.length || current.some((b, i) => b !== wanted[i])) {
        await sub.unsubscribe()
        sub = null
      }
    }
    sub ||= await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(data.key) })

    await api.post('/push/subscribe', sub.toJSON())
    status.value = 'on'
    return null
  } catch (e) {
    if (e?.message === 'sw-timeout') return 'التطبيق لسه بيحمّل ملفاته أول مرة. استنى دقيقة وجرّب تاني (أو اقفله وافتحه من الأيقونة).'
    return e?.response?.data?.message || `مقدرناش نفعّل الإشعارات: ${e?.message || 'خطأ غير معروف'}`
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
    status.value = 'off'
  } finally {
    busy.value = false
  }
}

/** إشعار تجريبي من السيرفر — بيرجّع { ok, message, results } */
async function test() {
  busy.value = true
  try {
    const { data } = await api.post('/push/test')
    return { ok: true, ...data }
  } catch (e) {
    return { ok: false, message: e?.response?.data?.message || 'الإرسال فشل', results: e?.response?.data?.results || [] }
  } finally {
    busy.value = false
  }
}

export const MESSAGES = {
  insecure: 'الإشعارات محتاجة رابط آمن يبدأ بـ https. الرابط الحالي (http) المتصفح بيمنع عليه الإشعارات تمامًا.',
  'ios-install': 'على الآيفون: افتح الموقع من Safari ← زرار المشاركة ← "إضافة إلى الشاشة الرئيسية"، وبعدين افتح التطبيق من الأيقونة وفعّل من هنا.',
  unsupported: 'المتصفح ده مش بيدعم الإشعارات. استخدم Chrome على أندرويد أو Safari على آيفون.',
  denied: 'الإشعارات مقفولة للموقع ده. افتح إعدادات الموبايل ← التطبيقات/Chrome ← الإشعارات، واسمح بيها للموقع، وبعدين ارجع هنا.',
}

export function usePush() {
  return { status, supported, enabled, busy, isIOS, refresh, enable, disable, test, MESSAGES }
}
