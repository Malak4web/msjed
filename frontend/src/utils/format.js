/** تنسيق الأرقام والتواريخ بالعربي (أرقام عربية زي الديزاين) */

const numberFmt = new Intl.NumberFormat('ar-EG', { maximumFractionDigits: 2 })
const dateFmt = new Intl.DateTimeFormat('ar-EG', { day: 'numeric', month: 'long', year: 'numeric' })
const shortDateFmt = new Intl.DateTimeFormat('ar-EG', { day: 'numeric', month: 'short' })
const dayFmt = new Intl.DateTimeFormat('ar-EG', { weekday: 'long', day: 'numeric', month: 'long' })
const timeFmt = new Intl.DateTimeFormat('ar-EG', { hour: 'numeric', minute: '2-digit' })
const monthFmt = new Intl.DateTimeFormat('ar-EG', { month: 'long', year: 'numeric' })
const monthShortFmt = new Intl.DateTimeFormat('ar-EG', { month: 'short' })
let hijriFmt = null
try {
  hijriFmt = new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura', { day: 'numeric', month: 'long', year: 'numeric' })
} catch {
  hijriFmt = null
}

export function num(value) {
  return numberFmt.format(Number(value || 0))
}

export function money(value, withCurrency = true) {
  const text = num(value)
  return withCurrency ? `${text} ج.م` : text
}

/** '2026-10-10' أو ISO → Date بالتوقيت المحلي */
export function toDate(value) {
  if (!value) return null
  if (value instanceof Date) return value
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    const [y, m, d] = value.split('-').map(Number)
    return new Date(y, m - 1, d)
  }
  return new Date(value)
}

export function date(value) {
  const d = toDate(value)
  return d ? dateFmt.format(d) : ''
}

export function shortDate(value) {
  const d = toDate(value)
  return d ? shortDateFmt.format(d) : ''
}

export function dayName(value) {
  const d = toDate(value)
  return d ? dayFmt.format(d) : ''
}

export function time(value) {
  const d = toDate(value)
  return d ? timeFmt.format(d) : ''
}

export function month(value) {
  const d = toDate(value)
  return d ? monthFmt.format(d) : ''
}

export function monthShort(value) {
  const d = toDate(value)
  return d ? monthShortFmt.format(d) : ''
}

export function hijri(value) {
  const d = toDate(value)
  return d && hijriFmt ? hijriFmt.format(d) : ''
}

/** "منذ ساعتين" */
export function ago(value) {
  const d = toDate(value)
  if (!d) return ''
  const diff = (Date.now() - d.getTime()) / 1000
  if (diff < 60) return 'دلوقتي'
  const minutes = Math.floor(diff / 60)
  if (minutes < 60) return minutes === 1 ? 'منذ دقيقة' : minutes === 2 ? 'منذ دقيقتين' : `منذ ${num(minutes)} دقيقة`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return hours === 1 ? 'منذ ساعة' : hours === 2 ? 'منذ ساعتين' : `منذ ${num(hours)} ساعات`
  const days = Math.floor(hours / 24)
  if (days === 1) return `أمس ${time(d)}`
  if (days < 7) return `منذ ${num(days)} أيام`
  return date(d)
}

/** "اليوم" / "أمس" / "منذ ٣ أيام" لتاريخ يوم (من غير وقت) */
export function relDay(value) {
  const d = toDate(value)
  if (!d) return ''
  const start = new Date()
  start.setHours(0, 0, 0, 0)
  const days = Math.round((start - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 864e5)
  if (days <= 0) return 'اليوم'
  if (days === 1) return 'أمس'
  if (days < 7) return days === 2 ? 'منذ يومين' : `منذ ${num(days)} أيام`
  return shortDate(d)
}

export function today() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export function monthStart(offset = 0) {
  const d = new Date()
  d.setDate(1)
  d.setMonth(d.getMonth() + offset)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}

export function monthEnd(offset = 0) {
  const d = new Date()
  d.setDate(1)
  d.setMonth(d.getMonth() + offset + 1)
  d.setDate(0)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/** أول حرفين من الاسم للأفاتار: "عم سيد" → "ع.س" */
export function initials(name = '') {
  const letters = (name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w.replace(/^ال(?=..)/u, '')[0])
  return letters.join('.')
}
