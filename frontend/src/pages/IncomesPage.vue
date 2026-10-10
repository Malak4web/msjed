<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import api, { errorMessage, openPdf, fileUrl } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import PageHeader from '@/components/PageHeader.vue'
import DataTable from '@/components/DataTable.vue'
import MoneyAmount from '@/components/MoneyAmount.vue'
import EmptyState from '@/components/EmptyState.vue'
import Pagination from '@/components/Pagination.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import AppDrawer from '@/components/AppDrawer.vue'
import { date, shortDate, monthStart, monthEnd, today, money, num, ago } from '@/utils/format'

const auth = useAuthStore()
const meta = useMetaStore()
const ui = useUiStore()

const filters = reactive({ from: monthStart(), to: today(), income_source_id: '', payment_method_id: '', custodian_id: '', q: '' })
const rows = ref([])
const pageMeta = ref(null)
const summary = ref(null)
const loading = ref(true)
const page = ref(1)
const selected = ref(null)
const detailOpen = ref(false)
const showFilters = ref(false)

const periods = [
  { label: 'الشهر ده', from: () => monthStart(), to: () => today() },
  { label: 'الشهر اللي فات', from: () => monthStart(-1), to: () => monthEnd(-1) },
  { label: 'آخر 3 شهور', from: () => monthStart(-2), to: () => today() },
  { label: 'كل الفترات', from: () => '', to: () => '' },
]
const activePeriod = ref(0)

function setPeriod(i) {
  activePeriod.value = i
  filters.from = periods[i].from()
  filters.to = periods[i].to()
}

const columns = [
  { key: 'receipt_no', label: 'رقم الإيصال' },
  { key: 'date', label: 'التاريخ' },
  { key: 'source', label: 'المصدر' },
  { key: 'payment_method', label: 'طريقة الدفع' },
  { key: 'custodian', label: 'في عهدة' },
  { key: 'amount', label: 'المبلغ', align: 'end' },
  { key: 'actions', label: '', class: 'w-24' },
]

async function load() {
  loading.value = true
  try {
    const params = { ...filters, page: page.value }
    Object.keys(params).forEach((k) => params[k] === '' && delete params[k])
    const { data } = await api.get('/incomes', { params })
    rows.value = data.data
    pageMeta.value = data.meta
    summary.value = data.summary
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

let timer
watch(filters, () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    page.value = 1
    load()
  }, 250)
})
watch(page, load)
watch(() => ui.dataVersion, load)
onMounted(load)

async function show(row) {
  selected.value = row
  detailOpen.value = true
  try {
    const { data } = await api.get(`/incomes/${row.id}`)
    selected.value = data.data
  } catch (e) {
    ui.error(errorMessage(e))
  }
}

async function receipt(row, format) {
  try {
    await openPdf(`/incomes/${row.id}/receipt${format ? `?format=${format}` : ''}`)
  } catch (e) {
    ui.error(errorMessage(e))
  }
}

async function reverse(row) {
  const reason = await ui.ask({
    title: `إلغاء الإيصال ${row.receipt_no}؟`,
    message: `هيتعمل قيد عكسي بمبلغ ${money(row.amount)} من عهدة ${row.custodian.name}. العملية الأصلية هتفضل ظاهرة ومتعلّم عليها إنها ملغاة.`,
    confirmText: 'إلغاء بقيد عكسي',
    danger: true,
    reason: true,
    reasonLabel: 'سبب الإلغاء',
  })
  if (!reason) return
  try {
    const { data } = await api.post(`/incomes/${row.id}/reverse`, { reason })
    ui.success(data.message)
    selected.value = data.data
    ui.changed()
  } catch (e) {
    ui.error(errorMessage(e))
  }
}
</script>

<template>
  <div>
    <PageHeader title="الإيرادات" subtitle="كل الفلوس اللي دخلت المسجد، ومع مين">
      <template #actions>
        <button v-if="auth.can('incomes.create')" class="btn btn-primary btn-lg" @click="ui.incomeDrawer = true">
          <span class="material-symbols-outlined">add</span>
          تسجيل إيراد
        </button>
      </template>
    </PageHeader>

    <!-- ملخص لكل طريقة دفع -->
    <section class="mobile-scroller mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
      <div class="card relative col-span-2 overflow-hidden border border-line p-4 md:col-span-1">
        <div class="absolute inset-x-0 top-0 h-[3px] bg-gold"></div>
        <p class="text-label-md text-on-surface-variant">إجمالي الفترة</p>
        <div v-if="loading && !summary" class="skeleton mt-1 h-8 w-28"></div>
        <MoneyAmount v-else :value="summary?.total" size="lg" tone="in" />
        <p class="text-body-sm text-on-surface-variant">{{ num(summary?.count || 0) }} عملية</p>
      </div>
      <div v-for="m in summary?.by_method || []" :key="m.id" class="card border border-line p-4">
        <p class="flex items-center gap-1.5 text-label-md text-on-surface-variant">
          <span class="material-symbols-outlined text-[18px] text-primary-container">{{ m.icon }}</span>
          {{ m.name }}
        </p>
        <MoneyAmount :value="m.total" size="sm" />
        <p class="text-body-sm text-on-surface-variant">{{ num(m.count) }} عملية</p>
      </div>
    </section>

    <!-- الفلاتر -->
    <section class="no-print card mb-4 space-y-3 border border-line p-4">
      <div class="scrollbar-none -mx-1 flex gap-2 overflow-x-auto px-1" role="tablist">
        <button
          v-for="(p, i) in periods"
          :key="p.label"
          role="tab"
          :aria-selected="activePeriod === i"
          class="shrink-0 rounded-full px-4 py-2 text-label-md transition-colors"
          :class="activePeriod === i ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-highest text-on-surface-variant hover:bg-surface-container-high'"
          @click="setPeriod(i)"
        >
          {{ p.label }}
        </button>
      </div>
      <button class="btn btn-ghost min-h-[44px] w-full bg-surface-container-low md:hidden" @click="showFilters = !showFilters">
        <span class="material-symbols-outlined">tune</span>
        {{ showFilters ? 'إخفاء الفلاتر' : 'فلاتر أكتر' }}
      </button>
      <div class="grid-cols-2 gap-3 md:grid md:grid-cols-3 xl:grid-cols-6" :class="showFilters ? 'grid' : 'hidden'">
        <div>
          <label class="mb-1 block text-label-md text-on-surface-variant" for="f-from">من</label>
          <input id="f-from" v-model="filters.from" type="date" class="field min-h-[44px]" @change="activePeriod = -1" />
        </div>
        <div>
          <label class="mb-1 block text-label-md text-on-surface-variant" for="f-to">إلى</label>
          <input id="f-to" v-model="filters.to" type="date" class="field min-h-[44px]" @change="activePeriod = -1" />
        </div>
        <div>
          <label class="mb-1 block text-label-md text-on-surface-variant" for="f-src">المصدر</label>
          <select id="f-src" v-model="filters.income_source_id" class="field min-h-[44px]">
            <option value="">الكل</option>
            <option v-for="s in meta.income_sources" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-label-md text-on-surface-variant" for="f-pm">طريقة الدفع</label>
          <select id="f-pm" v-model="filters.payment_method_id" class="field min-h-[44px]">
            <option value="">الكل</option>
            <option v-for="m in meta.payment_methods" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-label-md text-on-surface-variant" for="f-c">الشخص</label>
          <select id="f-c" v-model="filters.custodian_id" class="field min-h-[44px]">
            <option value="">الكل</option>
            <option v-for="c in meta.custodians" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-label-md text-on-surface-variant" for="f-q">بحث</label>
          <input id="f-q" v-model="filters.q" type="search" class="field min-h-[44px]" placeholder="رقم إيصال أو اسم" />
        </div>
      </div>
    </section>

    <DataTable :columns="columns" :rows="rows" :loading="loading" clickable :row-class="(r) => (r.is_reversed ? 'opacity-60' : '')" @row-click="show">
      <template #cell-receipt_no="{ row }">
        <span class="font-bold text-tertiary" dir="ltr">{{ row.receipt_no }}</span>
        <StatusBadge v-if="row.is_reversed" status="reversed" class="mr-2" />
      </template>
      <template #cell-date="{ row }">{{ date(row.date) }}</template>
      <template #cell-source="{ row }">
        <span class="flex items-center gap-2"><span class="material-symbols-outlined text-[20px] text-primary-container">{{ row.source.icon }}</span>{{ row.source.name }}</span>
      </template>
      <template #cell-payment_method="{ row }">{{ row.payment_method.name }}</template>
      <template #cell-custodian="{ row }">{{ row.custodian.name }}</template>
      <template #cell-amount="{ row }">
        <MoneyAmount :value="row.amount" size="sm" :tone="row.is_reversed ? 'plain' : 'in'" :class="{ 'line-through': row.is_reversed }" />
      </template>
      <template #cell-actions="{ row }">
        <button class="btn btn-ghost min-h-[40px] px-2" title="طباعة الإيصال" aria-label="طباعة الإيصال" @click.stop="receipt(row)">
          <span class="material-symbols-outlined">print</span>
        </button>
      </template>

      <template #card="{ row }">
        <div class="flex items-start justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-inflow-bg text-inflow-text">
              <span class="material-symbols-outlined">{{ row.source.icon }}</span>
            </span>
            <div class="min-w-0">
              <p class="truncate text-label-lg font-bold">{{ row.source.name }}</p>
              <p class="text-body-sm text-on-surface-variant">{{ shortDate(row.date) }} · {{ row.payment_method.name }} · {{ row.custodian.name }}</p>
            </div>
          </div>
          <div class="text-left">
            <MoneyAmount :value="row.amount" size="sm" :tone="row.is_reversed ? 'plain' : 'in'" :class="{ 'line-through': row.is_reversed }" />
            <StatusBadge v-if="row.is_reversed" status="reversed" class="mt-1" />
          </div>
        </div>
      </template>

      <template #empty>
        <EmptyState icon="payments" title="مفيش إيرادات في الفترة دي" text="غيّر الفلاتر أو سجّل إيراد جديد.">
          <button v-if="auth.can('incomes.create')" class="btn btn-primary" @click="ui.incomeDrawer = true">
            <span class="material-symbols-outlined">add</span>تسجيل إيراد
          </button>
        </EmptyState>
      </template>
      <template #footer>
        <Pagination :meta="pageMeta" @page="page = $event" />
      </template>
    </DataTable>

    <!-- تفاصيل الإيراد -->
    <AppDrawer v-model="detailOpen" :title="selected ? `إيصال ${selected.receipt_no}` : ''" subtitle="تفاصيل الإيراد">
      <div v-if="selected" class="space-y-4">
        <div v-if="selected.is_reversed" class="rounded-xl border-2 border-outflow-border bg-outflow-bg p-4 text-outflow-text">
          <p class="flex items-center gap-2 font-bold"><span class="material-symbols-outlined">history</span>ملغى بقيد عكسي</p>
          <p class="mt-1 text-body-md">السبب: {{ selected.reversal_reason }}</p>
          <p class="text-body-sm">بواسطة {{ selected.reversed_by }} — {{ ago(selected.reversed_at) }}</p>
        </div>

        <div class="rounded-xl bg-inflow-bg/60 p-5 text-center">
          <p class="text-label-md text-inflow-text">المبلغ</p>
          <MoneyAmount :value="selected.amount" size="kpi" tone="in" />
        </div>

        <dl class="card divide-y divide-line border border-line">
          <div v-for="[k, v] in [
            ['التاريخ', date(selected.date)],
            ['المصدر', selected.source?.name],
            ['طريقة الدفع', selected.payment_method?.name],
            ['في عهدة', selected.custodian?.name],
            ['اسم الدافع', selected.payer_name || '—'],
            ['ملاحظات', selected.notes || '—'],
            ['سجّله', `${selected.created_by || ''} — ${ago(selected.created_at)}`],
          ]" :key="k" class="flex justify-between gap-4 px-4 py-3">
            <dt class="text-on-surface-variant">{{ k }}</dt>
            <dd class="text-left font-bold">{{ v }}</dd>
          </div>
        </dl>

        <div v-if="selected.attachments?.length" class="space-y-2">
          <p class="field-label">المرفقات</p>
          <a v-for="a in selected.attachments" :key="a.id" :href="fileUrl(a.url)" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-line bg-surface-container-lowest p-2 hover:border-primary-container">
            <img v-if="a.is_image" :src="fileUrl(a.url)" alt="" class="h-14 w-14 rounded-lg object-cover" />
            <span v-else class="material-symbols-outlined flex h-14 w-14 items-center justify-center rounded-lg bg-surface-container text-[28px]">description</span>
            <span class="flex-1 truncate text-label-md">{{ a.name }}</span>
            <span class="material-symbols-outlined text-primary">open_in_new</span>
          </a>
        </div>
      </div>
      <template #footer>
        <div v-if="selected" class="grid grid-cols-2 gap-2">
          <button class="btn btn-primary" @click="receipt(selected)"><span class="material-symbols-outlined">print</span>إيصال A5</button>
          <button class="btn btn-outline" @click="receipt(selected, 'thermal')"><span class="material-symbols-outlined">receipt</span>إيصال حراري</button>
          <button v-if="auth.can('entries.reverse') && !selected.is_reversed" class="btn btn-ghost col-span-2 text-error" @click="reverse(selected)">
            <span class="material-symbols-outlined">undo</span>إلغاء بقيد عكسي
          </button>
        </div>
      </template>
    </AppDrawer>
  </div>
</template>
