<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { errorMessage, openPdf, downloadFile } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import PageHeader from '@/components/PageHeader.vue'
import PeriodFilter from '@/components/PeriodFilter.vue'
import MoneyAmount from '@/components/MoneyAmount.vue'
import EmptyState from '@/components/EmptyState.vue'
import IncomeExpenseChart from '@/components/IncomeExpenseChart.vue'
import DonutChart from '@/components/DonutChart.vue'
import { money, num, monthStart, today } from '@/utils/format'

const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const types = [
  { key: 'monthly', label: 'شهري: إيرادات ومصروفات', icon: 'bar_chart' },
  { key: 'expenses-by-category', label: 'المصروفات حسب البند', icon: 'category' },
  { key: 'incomes-by-source', label: 'الإيرادات حسب المصدر والدفع', icon: 'payments' },
  { key: 'balances', label: 'أرصدة الأشخاص', icon: 'account_balance_wallet' },
  { key: 'approvals', label: 'سجل الموافقات والرفض', icon: 'gavel' },
  { key: 'audit', label: 'سجل التعديلات', icon: 'history' },
]

const type = ref(types.some((t) => t.key === route.query.type) ? route.query.type : 'monthly')
const period = ref({ from: monthStart(-5), to: today() })
const report = ref(null)
const loading = ref(true)
const busy = ref('')

async function load() {
  loading.value = true
  try {
    report.value = (await api.get(`/reports/${type.value}`, { params: period.value })).data
  } catch (e) {
    report.value = null
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch([type, period], () => {
  router.replace({ query: { type: type.value } })
  load()
})

const query = computed(() => new URLSearchParams({ from: period.value.from || '', to: period.value.to || '' }).toString())

async function exportAs(format) {
  busy.value = format
  try {
    const url = `/reports/${type.value}?${query.value}&format=${format}`
    format === 'pdf' ? await openPdf(url) : await downloadFile(url, `${report.value?.title || type.value}.xlsx`)
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    busy.value = ''
  }
}
const print = () => window.print()

const isNumeric = (c) => ['money', 'number'].includes(c.type)
function fmt(value, col) {
  if (value === null || value === undefined || value === '') return '—'
  if (col.type === 'money') return money(value, false)
  if (col.type === 'number') return num(value)
  return value
}
const toneClass = (col) => (col.class === 'in' ? 'text-inflow-text' : col.class === 'out' ? 'text-error' : '')

const barRows = computed(() => (report.value?.chart?.kind === 'bar' ? report.value.rows.map((r) => ({ month: r.month_key + '-01', income: r.income, expense: r.expense })) : []))
const donutItems = computed(() => {
  const c = report.value?.chart
  if (c?.kind !== 'doughnut') return []
  return report.value.rows.map((r) => ({ name: r[c.label], total: Number(r[c.series[0].key]) })).filter((i) => i.total > 0)
})

function openRow(row) {
  if (row.request_id) router.push({ path: '/requests', query: { id: row.request_id, status: 'executed' } })
}
</script>

<template>
  <div>
    <PageHeader title="التقارير" subtitle="كل تقرير يتطبع ويتصدّر PDF أو Excel">
      <template #actions>
        <button class="btn btn-ghost bg-surface-container-lowest" @click="print"><span class="material-symbols-outlined">print</span>طباعة</button>
        <button class="btn btn-outline" :disabled="!!busy || !report" @click="exportAs('pdf')"><span class="material-symbols-outlined">picture_as_pdf</span>PDF</button>
        <button class="btn btn-outline" :disabled="!!busy || !report" @click="exportAs('xlsx')"><span class="material-symbols-outlined">table_view</span>Excel</button>
      </template>
    </PageHeader>

    <!-- نوع التقرير -->
    <div class="no-print mobile-scroller mb-4 grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6" role="tablist">
      <button
        v-for="t in types"
        :key="t.key"
        role="tab"
        :aria-selected="type === t.key"
        class="flex min-h-[64px] items-center gap-2 rounded-xl border-2 px-3 text-right transition"
        :class="type === t.key ? 'border-primary-container bg-secondary-container/60 text-primary' : 'border-transparent bg-surface-container-lowest text-on-surface-variant shadow-card hover:border-primary-fixed-dim'"
        @click="type = t.key"
      >
        <span class="material-symbols-outlined" :class="{ 'icon-fill': type === t.key }">{{ t.icon }}</span>
        <span class="text-label-md font-bold leading-tight">{{ t.label }}</span>
      </button>
    </div>

    <PeriodFilter v-model="period" :allow-all="false" class="mb-5" />

    <div v-if="loading && !report" class="space-y-3"><div class="skeleton h-24"></div><div class="skeleton h-64"></div></div>

    <template v-else-if="report">
      <div class="mb-4">
        <h2 class="text-headline-md font-bold">{{ report.title }}</h2>
        <p class="text-body-sm text-on-surface-variant">{{ report.subtitle }}</p>
      </div>

      <section v-if="report.summary?.length" class="mobile-scroller mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div v-for="(s, i) in report.summary" :key="s.label" class="card relative overflow-hidden border border-line p-4">
          <div v-if="i === 0" class="absolute inset-x-0 top-0 h-[3px] bg-gold"></div>
          <p class="text-label-md text-on-surface-variant">{{ s.label }}</p>
          <MoneyAmount v-if="(s.type || 'money') === 'money'" :value="s.value" size="md" :tone="i === 0 ? 'primary' : 'plain'" />
          <p v-else class="text-headline-md font-bold">{{ num(s.value) }}</p>
        </div>
      </section>

      <section v-if="barRows.length || donutItems.length" class="card mb-5 border border-line p-4 sm:p-5">
        <IncomeExpenseChart v-if="barRows.length" :rows="barRows" />
        <div v-else class="mx-auto max-w-md"><DonutChart :items="donutItems" /></div>
      </section>

      <!-- الجدول -->
      <section class="card overflow-hidden border border-line">
        <div v-if="!report.rows.length"><EmptyState icon="inbox" title="مفيش بيانات في الفترة دي" /></div>
        <template v-else>
          <div class="hidden overflow-x-auto md:block print:block">
            <table class="w-full text-right">
              <thead class="bg-[#F6F8F6]">
                <tr>
                  <th v-for="c in report.columns" :key="c.key" class="whitespace-nowrap px-4 py-3 text-label-md font-bold" :class="{ 'text-left': isNumeric(c) }">{{ c.label }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in report.rows" :key="i" class="h-[56px] border-t border-line hover:bg-[#FAF9F5]" :class="{ 'cursor-pointer': row.request_id }" @click="openRow(row)">
                  <td v-for="c in report.columns" :key="c.key" class="px-4 py-2 text-body-md" :class="[isNumeric(c) ? 'tabular text-left font-bold' : '', toneClass(c), c.key === 'changes' ? 'max-w-md text-body-sm' : '']">
                    {{ fmt(row[c.key], c) }}
                  </td>
                </tr>
              </tbody>
              <tfoot v-if="report.totals && Object.keys(report.totals).length">
                <tr class="border-t-2 border-primary-container bg-surface-container-low font-bold">
                  <td v-for="(c, i) in report.columns" :key="c.key" class="px-4 py-3" :class="isNumeric(c) ? 'tabular text-left' : ''">
                    {{ i === 0 ? 'الإجمالي' : c.key in report.totals ? fmt(report.totals[c.key], c) : '' }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- موبايل: كروت -->
          <div class="divide-y divide-line md:hidden print:hidden">
            <div v-for="(row, i) in report.rows" :key="i" class="p-4" @click="openRow(row)">
              <p class="mb-2 text-label-lg font-bold">{{ fmt(row[report.columns[0].key], report.columns[0]) }}</p>
              <dl class="grid grid-cols-2 gap-x-3 gap-y-1">
                <template v-for="c in report.columns.slice(1)" :key="c.key">
                  <dt class="text-body-sm text-on-surface-variant">{{ c.label }}</dt>
                  <dd class="text-left text-body-sm font-bold" :class="toneClass(c)">{{ fmt(row[c.key], c) }}</dd>
                </template>
              </dl>
            </div>
            <div v-if="report.totals && Object.keys(report.totals).length" class="bg-surface-container-low p-4">
              <p class="mb-2 font-bold">الإجمالي</p>
              <dl class="grid grid-cols-2 gap-x-3 gap-y-1">
                <template v-for="c in report.columns.filter((x) => x.key in report.totals)" :key="c.key">
                  <dt class="text-body-sm text-on-surface-variant">{{ c.label }}</dt>
                  <dd class="text-left text-body-sm font-bold">{{ fmt(report.totals[c.key], c) }}</dd>
                </template>
              </dl>
            </div>
          </div>
        </template>
      </section>
    </template>
  </div>
</template>
