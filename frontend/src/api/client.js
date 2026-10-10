import axios from 'axios'

/**
 * VITE_API_URL: في التطوير '/api' (عن طريق proxy)، وفي Capacitor/Electron الرابط الكامل للسيرفر.
 */
export const API_BASE = import.meta.env.VITE_API_URL || '/api'
export const TOKEN_KEY = 'mosque.token'

export function getToken() {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function setToken(token) {
  try {
    token ? localStorage.setItem(TOKEN_KEY, token) : localStorage.removeItem(TOKEN_KEY)
  } catch {
    /* التخزين مش متاح */
  }
}

const api = axios.create({
  baseURL: API_BASE,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

let onUnauthorized = () => {}
export function handleUnauthorized(fn) {
  onUnauthorized = fn
}

api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) onUnauthorized()
    return Promise.reject(error)
  },
)

/** رسالة خطأ واحدة بالعربي البسيط من أي رد */
export function errorMessage(error) {
  if (!error?.response) return 'مفيش اتصال بالسيرفر. اتأكد من الإنترنت وجرّب تاني.'
  const { status, data } = error.response
  if (status === 403) return data?.message && data.message !== 'This action is unauthorized.' ? data.message : 'معندكش صلاحية تعمل كده.'
  if (status === 404) return 'العنصر ده مش موجود.'
  if (status === 429) return 'محاولات كتير ورا بعض. استنى دقيقة وجرّب تاني.'
  if (status >= 500) return 'حصلت مشكلة في السيرفر. جرّب تاني بعد شوية.'
  return data?.message || 'حصلت مشكلة. جرّب تاني.'
}

/** أخطاء الحقول: { field: 'رسالة' } */
export function fieldErrors(error) {
  const errors = error?.response?.data?.errors || {}
  return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]))
}

/** رابط ملف من السيرفر (المرفقات الموقّعة بترجع relative) */
export function fileUrl(path) {
  if (!path) return null
  if (/^https?:/.test(path)) return path
  const origin = API_BASE.startsWith('http') ? new URL(API_BASE).origin : ''
  return origin + path
}

/** فتح PDF محمي بالتوكن في تاب جديد أو تنزيله */
export async function openPdf(url, { download = false, filename = 'file.pdf', target = null } = {}) {
  // لازم الشباك يتفتح جوه الضغطة نفسها وإلا المتصفح يمنعه — عشان كده ممكن يتبعت جاهز
  const win = download ? null : target || window.open('', '_blank')
  try {
    const res = await api.get(url, { responseType: 'blob' })
    const blobUrl = URL.createObjectURL(res.data)
    if (win) {
      win.location.href = blobUrl
    } else {
      const a = document.createElement('a')
      a.href = blobUrl
      a.download = filename
      a.click()
    }
    setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000)
  } catch (e) {
    win?.close()
    throw e
  }
}

/** تنزيل ملف محمي (Excel/PDF) */
export function downloadFile(url, filename) {
  return openPdf(url, { download: true, filename })
}

/**
 * مشاركة PDF من الموبايل (واتساب مثلًا) عن طريق Web Share API.
 * بيرجّع false لو الجهاز مش بيدعم مشاركة الملفات.
 */
export async function sharePdf(url, filename, title) {
  const res = await api.get(url, { responseType: 'blob' })
  const file = new File([res.data], filename, { type: 'application/pdf' })
  if (navigator.canShare?.({ files: [file] })) {
    await navigator.share({ files: [file], title })
    return true
  }
  return false
}

export const canShareFiles = () => typeof navigator !== 'undefined' && !!navigator.canShare

export default api
