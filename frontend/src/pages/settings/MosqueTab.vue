<script setup>
import { ref, reactive, onMounted } from 'vue'
import api, { errorMessage, fieldErrors, API_BASE } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import TileSelect from '@/components/TileSelect.vue'
import FileInput from '@/components/FileInput.vue'
import { month, monthStart } from '@/utils/format'

const auth = useAuthStore()
const meta = useMetaStore()
const ui = useUiStore()

const form = reactive({
  mosque_name: '',
  mosque_address: '',
  mosque_phone: '',
  receipt_format: 'a5',
  imam_self_request_mode: 'auto',
  require_invoice_on_execute: true,
  show_hijri_date: true,
})
const errors = ref({})
const saving = ref(false)
const logo = ref(null)
const logoVersion = ref(Date.now())
const hasLogo = ref(false)

const locks = ref([])
const lockMonth = ref(monthStart(-1).slice(0, 7))

const receiptOptions = [
  { id: 'a5', name: 'ورقة A5', icon: 'description', hint: 'طابعة عادية' },
  { id: 'thermal', name: 'حراري 80mm', icon: 'receipt', hint: 'طابعة كاشير' },
]
const imamOptions = [
  { id: 'auto', name: 'موافقة تلقائية', icon: 'bolt', hint: 'وتتسجل في السجل' },
  { id: 'accountant', name: 'يوافق المحاسب', icon: 'calculate', hint: 'مراجعة إضافية' },
]

async function load() {
  try {
    const [s, l] = await Promise.all([api.get('/settings'), api.get('/period-locks')])
    const settings = s.data.settings
    Object.keys(form).forEach((k) => (form[k] = settings[k] ?? form[k]))
    hasLogo.value = !!settings.logo_path
    locks.value = l.data.data
  } catch (e) {
    ui.error(errorMessage(e))
  }
}
onMounted(load)

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const { data } = await api.put('/settings', form)
    if (logo.value) {
      const body = new FormData()
      body.append('logo', logo.value)
      await api.post('/settings/logo', body)
      logo.value = null
      hasLogo.value = true
      logoVersion.value = Date.now()
    }
    ui.success(data.message)
    meta.load(true)
  } catch (e) {
    errors.value = fieldErrors(e)
    ui.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}

async function lock() {
  const ok = await ui.ask({
    title: `إقفال شهر ${month(lockMonth.value + '-01')}؟`,
    message: 'بعد الإقفال محدش هيقدر يسجّل أو يعدّل أي عملية بتاريخ جوه الشهر ده.',
    confirmText: 'إقفال الشهر',
    icon: 'lock',
  })
  if (!ok) return
  try {
    const { data } = await api.post('/period-locks', { period: lockMonth.value })
    ui.success(data.message)
    load()
  } catch (e) {
    ui.error(errorMessage(e))
  }
}

async function unlock(period) {
  const ok = await ui.ask({ title: `فتح شهر ${month(period + '-01')}؟`, message: 'هيبقى ممكن تسجيل عمليات بتاريخه تاني. العملية هتتسجل في سجل التدقيق.', confirmText: 'فتح الشهر', danger: true })
  if (!ok) return
  try {
    const { data } = await api.delete(`/period-locks/${period}`)
    ui.success(data.message)
    load()
  } catch (e) {
    ui.error(errorMessage(e))
  }
}
</script>

<template>
  <div class="grid gap-6 xl:grid-cols-2">
    <section class="card space-y-5 border border-line p-5">
      <h2 class="text-headline-sm font-bold">بيانات المسجد والإيصال</h2>
      <div>
        <label class="field-label" for="m-name">اسم المسجد</label>
        <input id="m-name" v-model="form.mosque_name" class="field" :class="{ 'field-error': errors.mosque_name }" />
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="field-label" for="m-addr">العنوان</label>
          <input id="m-addr" v-model="form.mosque_address" class="field" />
        </div>
        <div>
          <label class="field-label" for="m-phone">تليفون المسجد</label>
          <input id="m-phone" v-model="form.mosque_phone" dir="ltr" class="field text-left" />
        </div>
      </div>
      <div class="flex items-center gap-4">
        <img v-if="hasLogo" :src="`${API_BASE}/settings/logo?v=${logoVersion}`" alt="شعار المسجد" class="h-20 w-20 rounded-xl border border-line bg-white object-contain p-1" />
        <FileInput v-model="logo" label="شعار الإيصال" accept="image/*" class="flex-1" />
      </div>
      <TileSelect v-model="form.receipt_format" label="مقاس الإيصال" :options="receiptOptions" columns="grid-cols-2" />
    </section>

    <section class="space-y-6">
      <div class="card space-y-5 border border-line p-5">
        <h2 class="text-headline-sm font-bold">خيارات النظام</h2>
        <TileSelect v-model="form.imam_self_request_mode" label="طلب الصرف بتاع الإمام نفسه" :options="imamOptions" columns="grid-cols-2" />
        <label class="flex min-h-[56px] cursor-pointer items-center justify-between gap-3 rounded-xl border border-line bg-surface-container-lowest px-4">
          <span><b>لازم صورة فاتورة عند التنفيذ</b><br /><span class="text-body-sm text-on-surface-variant">ميتأكدش الصرف من غير ما الفاتورة تترفع</span></span>
          <input v-model="form.require_invoice_on_execute" type="checkbox" class="h-6 w-6 shrink-0 accent-[#1f6f50]" />
        </label>
        <label class="flex min-h-[56px] cursor-pointer items-center justify-between gap-3 rounded-xl border border-line bg-surface-container-lowest px-4">
          <span class="font-bold">إظهار التاريخ الهجري جنب الميلادي</span>
          <input v-model="form.show_hijri_date" type="checkbox" class="h-6 w-6 shrink-0 accent-[#1f6f50]" />
        </label>
      </div>

      <div class="card space-y-4 border border-line p-5">
        <div>
          <h2 class="text-headline-sm font-bold">إقفال الشهور</h2>
          <p class="text-body-sm text-on-surface-variant">بعد مراجعة الشهر واعتماده، اقفله عشان محدش يغيّر فيه.</p>
        </div>
        <div v-if="auth.can('periods.lock')" class="flex flex-wrap items-end gap-2">
          <div class="flex-1">
            <label class="field-label" for="lock-m">الشهر</label>
            <input id="lock-m" v-model="lockMonth" type="month" class="field" />
          </div>
          <button class="btn btn-outline" @click="lock"><span class="material-symbols-outlined">lock</span>إقفال</button>
        </div>
        <ul v-if="locks.length" class="divide-y divide-line rounded-xl border border-line">
          <li v-for="l in locks" :key="l.period" class="flex items-center justify-between gap-3 px-4 py-3">
            <span class="flex items-center gap-2"><span class="material-symbols-outlined text-tertiary">lock</span><b>{{ month(l.period + '-01') }}</b></span>
            <span class="text-body-sm text-on-surface-variant">{{ l.locked_by }}</span>
            <button v-if="auth.can('periods.lock')" class="btn btn-ghost min-h-[40px] px-3 text-error" @click="unlock(l.period)">فتح</button>
          </li>
        </ul>
        <p v-else class="text-body-sm text-on-surface-variant">مفيش شهور مقفولة لسه.</p>
      </div>
    </section>

    <div class="xl:col-span-2">
      <button class="btn btn-primary btn-lg w-full sm:w-auto" :disabled="saving" @click="save"><span class="material-symbols-outlined">save</span>حفظ الإعدادات</button>
    </div>
  </div>
</template>
