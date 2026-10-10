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
const form = reactive({ from_custodian_id: auth.user?.custodian?.id ?? null, to_custodian_id: null, amount: '', date: today(), notes: '' })

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
onMounted(load)
watch(page, load)

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
  if (!(amount.value > 0)) e.amount = 'اكتب المبلغ.'
  else if (over.value) e.amount = `${from.value.name} معاه ${money(from.value.available)} متاح بس.`
  errors.value = e
  if (Object.keys(e).length) return

  const ok = await ui.ask({
    title: 'تأكيد تسليم الفلوس',
    message: `${from.value.name} هيسلّم ${money(amount.value)} لـ ${to.value.name}.`,
    confirmText: 'تأكيد التسليم',
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
  { key: 'date', label: 'التاريخ' },
  { key: 'from', label: 'من' },
  { key: 'to', label: 'إلى' },
  { key: 'notes', label: 'ملاحظات' },
  { key: 'amount', label: 'المبلغ', align: 'end' },
  { key: 'actions', label: '' },
]
</script>

<template>
  <div>
    <PageHeader title="تسليم فلوس بين الأشخاص" subtitle="نقل عهدة من شخص لشخص — الرصيد بيتخصم من الأول ويتضاف للتاني فورًا" />

    <div class="grid gap-6 xl:grid-cols-12">
      <form v-if="auth.can('transfers.create')" class="card space-y-5 border border-line p-4 sm:p-5 xl:col-span-7" @submit.prevent="submit">
        <TileSelect v-model="form.from_custodian_id" label="مين هيسلّم الفلوس؟" :options="fromOptions" columns="grid-cols-2" :error="errors.from_custodian_id" />
        <TileSelect v-model="form.to_custodian_id" label="لمين؟" :options="toOptions" columns="grid-cols-2 sm:grid-cols-3" :error="errors.to_custodian_id" />
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
          تسليم {{ amount ? money(amount) : 'الفلوس' }}
        </button>
      </form>

      <!-- معاينة الأرصدة بعد التسليم -->
      <aside class="space-y-3 xl:col-span-5">
        <h2 class="flex items-center gap-2 text-headline-sm font-bold">
          <span class="material-symbols-outlined text-primary-container">preview</span>
          الأرصدة بعد التسليم
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
                <p class="text-label-sm text-on-surface-variant">بعد التسليم</p>
                <MoneyAmount :value="Number(side.p.balance) + side.sign * amount" size="sm" :tone="Number(side.p.balance) + side.sign * amount < 0 ? 'out' : side.sign < 0 ? 'plain' : 'in'" />
              </div>
            </div>
          </template>
          <p v-else class="py-4 text-center text-body-md text-on-surface-variant">اختار {{ side.sign < 0 ? 'مين هيسلّم' : 'مين هيستلم' }}</p>
        </div>
        <p v-if="over" class="flex items-start gap-2 rounded-lg border border-outflow-border bg-outflow-bg p-3 font-bold text-outflow-text">
          <span class="material-symbols-outlined">warning</span>المبلغ أكبر من المتاح مع {{ from.name }}.
        </p>
      </aside>
    </div>

    <h2 class="mb-3 mt-8 text-headline-sm font-bold">آخر التحويلات</h2>
    <DataTable :columns="columns" :rows="rows" :loading="loading" :row-class="(r) => (r.is_reversed ? 'opacity-60' : '')">
      <template #cell-number="{ row }">
        <span class="font-bold text-tertiary" dir="ltr">{{ row.number }}</span>
        <StatusBadge v-if="row.is_reversed" status="reversed" class="mr-2" />
      </template>
      <template #cell-date="{ row }">{{ date(row.date) }}</template>
      <template #cell-from="{ row }">{{ row.from.name }}</template>
      <template #cell-to="{ row }">{{ row.to.name }}</template>
      <template #cell-notes="{ row }"><span class="text-body-sm text-on-surface-variant">{{ row.notes || '—' }}</span></template>
      <template #cell-amount="{ row }"><MoneyAmount :value="row.amount" size="sm" :class="{ 'line-through': row.is_reversed }" /></template>
      <template #cell-actions="{ row }">
        <button v-if="auth.can('entries.reverse') && !row.is_reversed" class="btn btn-ghost min-h-[40px] px-2 text-error" title="إلغاء بقيد عكسي" aria-label="إلغاء بقيد عكسي" @click="reverse(row)">
          <span class="material-symbols-outlined">undo</span>
        </button>
      </template>
      <template #card="{ row }">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="font-bold">{{ row.from.name }} <span class="material-symbols-outlined align-middle text-[18px] text-on-surface-variant">arrow_back</span> {{ row.to.name }}</p>
            <p class="text-body-sm text-on-surface-variant">{{ shortDate(row.date) }} · {{ row.notes || row.number }}</p>
            <StatusBadge v-if="row.is_reversed" status="reversed" class="mt-1" />
          </div>
          <MoneyAmount :value="row.amount" size="sm" :class="{ 'line-through': row.is_reversed }" />
        </div>
      </template>
      <template #empty><EmptyState icon="swap_horiz" title="مفيش تحويلات لسه" /></template>
      <template #footer><Pagination :meta="pageMeta" @page="page = $event" /></template>
    </DataTable>
  </div>
</template>
