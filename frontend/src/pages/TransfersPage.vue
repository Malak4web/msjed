<script setup>
import { reactive, ref, computed, watch, onMounted } from 'vue'
import api, { errorMessage, fieldErrors } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import PageHeader from '@/components/PageHeader.vue'
import TileSelect from '@/components/TileSelect.vue'
import AmountInput from '@/components/AmountInput.vue'
import MoneyAmount from '@/components/MoneyAmount.vue'
import DataTable from '@/components/DataTable.vue'
import EmptyState from '@/components/EmptyState.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import Pagination from '@/components/Pagination.vue'
import PersonAvatar from '@/components/PersonAvatar.vue'
import { today, date, shortDate, money } from '@/utils/format'

const auth = useAuthStore()
const meta = useMetaStore()
const ui = useUiStore()

const people = ref([])
const rows = ref([])
const pageMeta = ref(null)
const page = ref(1)
const loading = ref(true)
const saving = ref(false)
const errors = ref({})
const form = reactive({
  from_custodian_id: auth.user?.custodian?.id ?? null,
  to_custodian_id: null,
  payment_method_id: null,
  amount: '',
  date: today(),
  notes: '',
})

watch(
  () => meta.payment_methods,
  (methods) => {
    if (!form.payment_method_id && methods && methods.length) {
      const cash = methods.find((m) => m.name === 'كاش')
      form.payment_method_id = cash ? cash.id : methods[0].id
    }
  },
  { immediate: true }
)

async function load() {
  loading.value = true
  try {
    const [p, t] = await Promise.all([api.get('/custodians'), api.get('/transfers', { params: { page: page.value } })])
    people.value = p.data.data
    rows.value = t.data.data
    pageMeta.value = t.data.meta
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  meta.load()
  load()
})
watch(page, load)

const pendingForMe = computed(() => rows.value.filter((r) => r.can_accept))

const from = computed(() => people.value.find((p) => p.id === form.from_custodian_id))
const to = computed(() => people.value.find((p) => p.id === form.to_custodian_id))
const amount = computed(() => Number(form.amount) || 0)
const over = computed(() => from.value && amount.value > Number(from.value.available))

const fromOptions = computed(() => people.value.map((p) => ({ id: p.id, name: p.name, hint: `متاح ${money(p.available)}`, icon: 'north_east' })))
const toOptions = computed(() => people.value.filter((p) => p.id !== form.from_custodian_id).map((p) => ({ id: p.id, name: p.name, hint: `معاه ${money(p.balance)}`, icon: 'south_west' })))

watch(() => form.from_custodian_id, (id) => {
  if (form.to_custodian_id === id) form.to_custodian_id = null
})

async function submit() {
  const e = {}
  if (!form.from_custodian_id) e.from_custodian_id = 'اختار مين هيسلّم.'
  if (!form.to_custodian_id) e.to_custodian_id = 'اختار مين هيستلم.'
  if (!form.payment_method_id) e.payment_method_id = 'اختار طريقة التسليم.'
  if (!(amount.value > 0)) e.amount = 'اكتب المبلغ.'
  else if (over.value) e.amount = `${from.value.name} معاه ${money(from.value.available)} متاح بس.`
  errors.value = e
  if (Object.keys(e).length) return

  const pm = meta.payment_methods.find((m) => m.id === form.payment_method_id)
  const methodText = pm ? ` بطريقة (${pm.name})` : ''

  const ok = await ui.ask({
    title: 'تأكيد إرسال العهدة',
    message: `هيتم حجز ${money(amount.value)} من رصيد ${from.value.name} وتسليمها لـ ${to.value.name}${methodText} — ومش هتتضاف لرصيده إلا بعد موافقته على استلام العهدة.`,
    confirmText: 'إرسال للمستلم',
    icon: 'swap_horiz',
  })
  if (!ok) return

  saving.value = true
  try {
    const { data } = await api.post('/transfers', form)
    ui.success(data.message)
    ui.changed()
    meta.load(true)
    Object.assign(form, { to_custodian_id: null, amount: '', notes: '' })
    page.value = 1
    load()
  } catch (err) {
    errors.value = fieldErrors(err)
    ui.error(errorMessage(err))
  } finally {
    saving.value = false
  }
}

async function acceptTransfer(row) {
  const methodText = row.payment_method ? ` بطريقة (${row.payment_method.name})` : ''
  const ok = await ui.ask({
    title: `تأكيد استلام عهدة ${row.number}`,
    message: `هل استلمت بالفعل مبلغ ${money(row.amount)} من ${row.from.name}${methodText}؟ هيتم إضافة المبلغ لرصيدك فورًا.`,
    confirmText: 'تأكيد الاستلام',
    icon: 'task_alt',
  })
  if (!ok) return
  try {
    const { data } = await api.post(`/transfers/${row.id}/accept`)
    ui.success(data.message)
    ui.changed()
    meta.load(true)
    load()
  } catch (e) {
    ui.error(errorMessage(e))
  }
}

async function rejectTransfer(row) {
  const reason = await ui.ask({
    title: `رفض استلام عهدة ${row.number}`,
    message: `سيتم رفض استلام مبلغ ${money(row.amount)} من ${row.from.name} وإلغاء حجز المبلغ من حسابه.`,
    confirmText: 'رفض الاستلام',
    danger: true,
    reason: true,
    icon: 'cancel',
  })
  if (reason === false) return
  try {
    const { data } = await api.post(`/transfers/${row.id}/reject`, { reason })
    ui.success(data.message)
    ui.changed()
    meta.load(true)
    load()
  } catch (e) {
    ui.error(errorMessage(e))
  }
}

async function cancelTransfer(row) {
  const reason = await ui.ask({
    title: `إلغاء أمر تحويل عهدة ${row.number}`,
    message: `سيتم إلغاء تحويل مبلغ ${money(row.amount)} لـ ${row.to.name} وفك الحجز عن رصيدك.`,
    confirmText: 'إلغاء التحويل',
    danger: true,
    reason: true,
    icon: 'block',
  })
  if (reason === false) return
  try {
    const { data } = await api.post(`/transfers/${row.id}/cancel`, { reason })
    ui.success(data.message)
    ui.changed()
    meta.load(true)
    load()
  } catch (e) {
    ui.error(errorMessage(e))
  }
}

async function reverse(row) {
  const reason = await ui.ask({
    title: `إلغاء التسليم ${row.number}؟`,
    message: `${money(row.amount)} هترجع من ${row.to.name} لـ ${row.from.name} بقيد عكسي.`,
    confirmText: 'إلغاء بقيد عكسي',
    danger: true,
    reason: true,
  })
  if (!reason) return
  try {
    const { data } = await api.post(`/transfers/${row.id}/reverse`, { reason })
    ui.success(data.message)
    ui.changed()
    load()
  } catch (e) {
    ui.error(errorMessage(e))
  }
}

const columns = [
  { key: 'number', label: 'الرقم' },
  { key: 'status', label: 'الحالة' },
  { key: 'date', label: 'التاريخ' },
  { key: 'from', label: 'من' },
  { key: 'to', label: 'إلى' },
  { key: 'method', label: 'طريقة التسليم' },
  { key: 'notes', label: 'ملاحظات' },
  { key: 'amount', label: 'المبلغ', align: 'end' },
  { key: 'actions', label: '' },
]
</script>

<template>
  <div>
    <PageHeader title="تسليم فلوس بين الأشخاص" subtitle="نقل عهدة من شخص لشخص — بيتخصم المبلغ من رصيد الراسل كحجز، وبيتضاف للمستلم فور موافقته على الاستلام" />

    <!-- تنبيه للعهد المعلقة بانتظار استلام المستخدم -->
    <div v-if="pendingForMe.length" class="mb-6 flex items-start gap-3 rounded-2xl border border-pending-border bg-pending-bg p-4 text-pending-text shadow-sm">
      <span class="material-symbols-outlined text-[24px]">schedule</span>
      <div class="flex-1">
        <p class="font-bold">لديك {{ pendingForMe.length }} {{ pendingForMe.length === 1 ? 'عهدة معلقة بانتظار استلامك' : 'عهد معلقة بانتظار استلامك' }}</p>
        <p class="text-body-sm opacity-90">يجب عليك تأكيد استلام المبلغ يدويًا ليتم ترحيله وإضافته إلى رصيدك في الدفتر.</p>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-12">
      <form v-if="auth.can('transfers.create')" class="card space-y-5 border border-line p-4 sm:p-5 xl:col-span-7" @submit.prevent="submit">
        <TileSelect v-model="form.from_custodian_id" label="مين هيسلّم الفلوس؟" :options="fromOptions" columns="grid-cols-2" :error="errors.from_custodian_id" />
        <TileSelect v-model="form.to_custodian_id" label="لمين؟" :options="toOptions" columns="grid-cols-2 sm:grid-cols-3" :error="errors.to_custodian_id" />
        <TileSelect v-if="meta.payment_methods.length" v-model="form.payment_method_id" label="طريقة التسليم" :options="meta.payment_methods" columns="grid-cols-2 sm:grid-cols-3 xl:grid-cols-5" :error="errors.payment_method_id" />
        <AmountInput v-model="form.amount" :error="errors.amount" :presets="[500, 1000, 2000, 5000]" />
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="t-date">التاريخ</label>
            <input id="t-date" v-model="form.date" type="date" :max="today()" class="field" />
          </div>
          <div>
            <label class="field-label" for="t-notes">ملاحظات <span class="text-body-sm font-normal text-on-surface-variant">(اختياري)</span></label>
            <input id="t-notes" v-model="form.notes" class="field" placeholder="مثلاً: لدفع المرتبات" />
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="saving">
          <span class="material-symbols-outlined">swap_horiz</span>
          إرسال {{ amount ? money(amount) : 'العهدة' }} للمستلم
        </button>
      </form>

      <!-- معاينة الأرصدة بعد التسليم -->
      <aside class="space-y-3 xl:col-span-5">
        <h2 class="flex items-center gap-2 text-headline-sm font-bold">
          <span class="material-symbols-outlined text-primary-container">preview</span>
          الأرصدة بعد تأكيد الاستلام
        </h2>
        <div v-for="side in [{ p: from, sign: -1, label: 'هيسلّم' }, { p: to, sign: 1, label: 'هيستلم' }]" :key="side.label" class="card border border-line p-4">
          <template v-if="side.p">
            <div class="mb-3 flex items-center gap-3">
              <PersonAvatar :name="side.p.name" :id="side.p.id" size="sm" />
              <p class="flex-1 font-bold">{{ side.p.name }} <span class="text-body-sm font-normal text-on-surface-variant">({{ side.label }})</span></p>
            </div>
            <div class="flex items-center justify-between gap-2 text-center">
              <div class="flex-1 rounded-lg bg-surface-container-low p-2">
                <p class="text-label-sm text-on-surface-variant">دلوقتي</p>
                <MoneyAmount :value="side.p.balance" size="sm" />
              </div>
              <span class="material-symbols-outlined text-on-surface-variant">arrow_back</span>
              <div class="flex-1 rounded-lg p-2" :class="side.sign < 0 ? 'bg-outflow-bg/60' : 'bg-inflow-bg/60'">
                <p class="text-label-sm text-on-surface-variant">بعد الاستلام</p>
                <MoneyAmount :value="Number(side.p.balance) + side.sign * amount" size="sm" :tone="Number(side.p.balance) + side.sign * amount < 0 ? 'out' : side.sign < 0 ? 'plain' : 'in'" />
              </div>
            </div>
          </template>
          <p v-else class="py-4 text-center text-body-md text-on-surface-variant">اختار {{ side.sign < 0 ? 'مين هيسلّم' : 'مين هيستلم' }}</p>
        </div>
        <p v-if="over" class="flex items-start gap-2 rounded-lg border border-outflow-border bg-outflow-bg p-3 font-bold text-outflow-text">
          <span class="material-symbols-outlined">warning</span>المبلغ أكبر من المتاح مع {{ from.name }}.
        </p>
        <p class="rounded-lg bg-surface-container-low p-3 text-body-sm text-on-surface-variant">
          <span class="font-bold text-on-surface">ملاحظة:</span> عند إرسال العهدة، يُحجز المبلغ من رصيد الراسل ولا يُضاف لحساب المستلم حتى يوافق على استلام المبلغ.
        </p>
      </aside>
    </div>

    <h2 class="mb-3 mt-8 text-headline-sm font-bold">آخر التحويلات</h2>
    <DataTable :columns="columns" :rows="rows" :loading="loading" :row-class="(r) => (r.is_reversed || r.status === 'rejected' || r.status === 'cancelled' ? 'opacity-60' : '')">
      <template #cell-number="{ row }">
        <span class="font-bold text-tertiary" dir="ltr">{{ row.number }}</span>
      </template>
      <template #cell-status="{ row }">
        <StatusBadge v-if="row.is_reversed" status="reversed" />
        <StatusBadge v-else-if="row.status === 'pending'" status="transfer_pending" />
        <StatusBadge v-else-if="row.status === 'completed'" status="completed" />
        <StatusBadge v-else-if="row.status === 'rejected'" status="rejected" :label="row.rejection_reason ? `مرفوض: ${row.rejection_reason}` : 'مرفوض'" />
        <StatusBadge v-else-if="row.status === 'cancelled'" status="cancelled" :label="row.cancellation_reason ? `مُلغى: ${row.cancellation_reason}` : 'مُلغى'" />
      </template>
      <template #cell-date="{ row }">{{ date(row.date) }}</template>
      <template #cell-from="{ row }">{{ row.from.name }}</template>
      <template #cell-to="{ row }">{{ row.to.name }}</template>
      <template #cell-method="{ row }">
        <span v-if="row.payment_method" class="inline-flex items-center gap-1.5 text-body-sm font-medium">
          <span v-if="row.payment_method.icon" class="material-symbols-outlined text-[18px] text-primary">{{ row.payment_method.icon }}</span>
          <span>{{ row.payment_method.name }}</span>
        </span>
        <span v-else class="text-body-sm text-on-surface-variant">—</span>
      </template>
      <template #cell-notes="{ row }">
        <span class="text-body-sm text-on-surface-variant">{{ row.notes || '—' }}</span>
        <span v-if="row.received_by" class="block text-label-sm text-on-surface-variant">استلمها: {{ row.received_by }}</span>
      </template>
      <template #cell-amount="{ row }">
        <MoneyAmount :value="row.amount" size="sm" :class="{ 'line-through': row.is_reversed || row.status === 'rejected' || row.status === 'cancelled' }" />
      </template>
      <template #cell-actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <button v-if="row.can_accept" class="btn btn-primary min-h-[34px] px-2.5 py-1 text-label-md" title="تأكيد الاستلام" @click="acceptTransfer(row)">
            <span class="material-symbols-outlined text-[18px]">check</span>
            استلام
          </button>
          <button v-if="row.can_reject" class="btn btn-ghost min-h-[34px] px-2 text-error" title="رفض الاستلام" @click="rejectTransfer(row)">
            <span class="material-symbols-outlined text-[18px]">close</span>
          </button>
          <button v-if="row.can_cancel" class="btn btn-ghost min-h-[34px] px-2 text-on-surface-variant" title="إلغاء التحويل" @click="cancelTransfer(row)">
            <span class="material-symbols-outlined text-[18px]">block</span>
          </button>
          <button v-if="auth.can('entries.reverse') && row.status === 'completed' && !row.is_reversed" class="btn btn-ghost min-h-[34px] px-2 text-error" title="إلغاء بقيد عكسي" aria-label="إلغاء بقيد عكسي" @click="reverse(row)">
            <span class="material-symbols-outlined text-[18px]">undo</span>
          </button>
        </div>
      </template>
      <template #card="{ row }">
        <div class="space-y-2">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-bold">{{ row.from.name }} <span class="material-symbols-outlined align-middle text-[18px] text-on-surface-variant">arrow_back</span> {{ row.to.name }}</p>
              <p class="text-body-sm text-on-surface-variant">
                {{ shortDate(row.date) }}
                <span v-if="row.payment_method"> · {{ row.payment_method.name }}</span>
                · {{ row.notes || row.number }}
              </p>
              <div class="mt-1 flex flex-wrap gap-1">
                <StatusBadge v-if="row.is_reversed" status="reversed" />
                <StatusBadge v-else-if="row.status === 'pending'" status="transfer_pending" />
                <StatusBadge v-else-if="row.status === 'completed'" status="completed" />
                <StatusBadge v-else-if="row.status === 'rejected'" status="rejected" />
                <StatusBadge v-else-if="row.status === 'cancelled'" status="cancelled" />
              </div>
            </div>
            <MoneyAmount :value="row.amount" size="sm" :class="{ 'line-through': row.is_reversed || row.status === 'rejected' || row.status === 'cancelled' }" />
          </div>
          <div v-if="row.can_accept || row.can_reject || row.can_cancel || (auth.can('entries.reverse') && row.status === 'completed' && !row.is_reversed)" class="flex items-center justify-end gap-2 border-t border-line/60 pt-2">
            <button v-if="row.can_accept" class="btn btn-primary min-h-[32px] px-3 text-label-md" @click="acceptTransfer(row)">
              <span class="material-symbols-outlined text-[18px]">check</span>
              تأكيد الاستلام
            </button>
            <button v-if="row.can_reject" class="btn btn-ghost min-h-[32px] px-2 text-error" @click="rejectTransfer(row)">
              <span class="material-symbols-outlined text-[18px]">close</span>
              رفض
            </button>
            <button v-if="row.can_cancel" class="btn btn-ghost min-h-[32px] px-2 text-on-surface-variant" @click="cancelTransfer(row)">
              <span class="material-symbols-outlined text-[18px]">block</span>
              إلغاء
            </button>
            <button v-if="auth.can('entries.reverse') && row.status === 'completed' && !row.is_reversed" class="btn btn-ghost min-h-[32px] px-2 text-error" @click="reverse(row)">
              <span class="material-symbols-outlined text-[18px]">undo</span>
              قيد عكسي
            </button>
          </div>
        </div>
      </template>
      <template #empty><EmptyState icon="swap_horiz" title="مفيش تحويلات لسه" /></template>
      <template #footer><Pagination :meta="pageMeta" @page="page = $event" /></template>
    </DataTable>
  </div>
</template>
