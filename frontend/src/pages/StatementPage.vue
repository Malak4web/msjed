<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { errorMessage, fileUrl, openPdf, downloadFile, sharePdf, canShareFiles } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import PersonAvatar from '@/components/PersonAvatar.vue'
import MoneyAmount from '@/components/MoneyAmount.vue'
import DataTable from '@/components/DataTable.vue'
import DonutChart from '@/components/DonutChart.vue'
import EmptyState from '@/components/EmptyState.vue'
import PeriodFilter from '@/components/PeriodFilter.vue'
import { date, dayName, time, monthStart, today, money, num, relDay as ago } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()

const id = computed(() => Number(route.params.id))
const period = ref({ from: monthStart(-2), to: today() })
const data = ref(null)
const loading = ref(true)
const busy = ref('')

async function load() {
  loading.value = true
  try {
    data.value = (await api.get(`/custodians/${id.value}/statement`, { params: period.value })).data
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch([period, id], load)
watch(() => ui.dataVersion, load)

const query = computed(() => new URLSearchParams({ from: period.value.from || '', to: period.value.to || '' }).toString())
const baseName = computed(() => `كشف-حساب-${data.value?.custodian.name || ''}`)

async function exportAs(format) {
  busy.value = format
  try {
    const url = `/custodians/${id.value}/statement?${query.value}&format=${format}`
    format === 'pdf' ? await openPdf(url) : await downloadFile(url, `${baseName.value}.xlsx`)
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    busy.value = ''
  }
}

async function share() {
  busy.value = 'share'
  try {
    const ok = await sharePdf(`/custodians/${id.value}/statement?${query.value}&format=pdf`, `${baseName.value}.pdf`, baseName.value)
    if (!ok) ui.error('الجهاز ده مش بيدعم المشاركة. استخدم زرار PDF ونزّله.')
  } catch (e) {
    if (e?.name !== 'AbortError') ui.error(errorMessage(e))
  } finally {
    busy.value = ''
  }
}

const columns = [
  { key: 'date', label: 'التاريخ' },
  { key: 'description', label: 'البيان', class: 'min-w-[240px]' },
  { key: 'type', label: 'النوع', class: 'whitespace-nowrap' },
  { key: 'in', label: 'وارد', align: 'end' },
  { key: 'out', label: 'منصرف', align: 'end' },
  { key: 'balance', label: 'الرصيد بعد الحركة', align: 'end' },
  { key: 'invoice', label: 'الفاتورة' },
]

const typeIcon = { income: 'south_west', transfer_in: 'south_west', transfer_out: 'north_east', expense: 'shopping_cart', reversal: 'undo' }
const typeTone = (t) => (['income', 'transfer_in'].includes(t) ? 'bg-inflow-bg text-inflow-text' : t === 'reversal' ? 'bg-tertiary-fixed text-on-tertiary-fixed-variant' : 'bg-outflow-bg text-outflow-text')

// الموبايل: Timeline متجمّع حسب اليوم (الأحدث فوق)
const days = computed(() => {
  const groups = {}
  for (const r of [...(data.value?.rows || [])].reverse()) (groups[r.date] ||= []).push(r)
  return Object.entries(groups)
})

const print = () => window.print()

function openRelated(row) {
  if (row.related?.type === 'ExpenseRequest') router.push({ path: '/requests', query: { id: row.related.id, status: 'executed' } })
}
</script>

<template>
  <div>
    <!-- موبايل: كارت الشخص (شاشة Stitch "كشف حساب عهدة شخص") -->
    <section class="relative mb-space-sm overflow-hidden rounded-2xl bg-surface-container-lowest p-space-md pt-6 shadow-md md:hidden">
      <div class="absolute inset-x-0 top-0 h-2 bg-gradient-to-l from-primary-container via-gold to-primary-container"></div>
      <div v-if="!data" class="space-y-3"><div class="skeleton h-14 w-2/3"></div><div class="skeleton h-28"></div></div>
      <template v-else>
        <div class="flex items-center gap-3">
          <div class="relative shrink-0">
            <PersonAvatar :name="data.custodian.name" :id="data.custodian.id" size="lg" class="!h-16 !w-16 !rounded-xl !text-headline-md" />
            <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-primary ring-2 ring-surface-container-lowest">
              <span class="material-symbols-outlined icon-fill text-[12px] text-on-primary">verified</span>
            </span>
          </div>
          <div class="min-w-0">
            <h2 class="truncate text-headline-md font-bold text-on-surface">{{ data.custodian.name }}</h2>
            <p class="truncate text-body-md text-on-surface-variant">{{ data.custodian.title || 'حامل عهدة' }}</p>
          </div>
        </div>

        <div class="mt-4 rounded-xl bg-surface-container-low p-space-sm">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-label-md text-on-surface-variant">الرصيد الفعلي الحالي في العهدة</p>
              <p class="tabular flex items-baseline gap-1.5">
                <span class="text-kpi-number font-extrabold tracking-tight text-primary">{{ num(data.current.balance) }}</span>
                <span class="text-headline-sm font-bold text-secondary">ج.م</span>
              </p>
              <p v-if="Number(data.current.reserved)" class="text-label-sm text-pending-text">محجوز {{ money(data.current.reserved) }} · متاح {{ money(data.current.available) }}</p>
            </div>
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-secondary-container text-primary">
              <span class="material-symbols-outlined icon-fill text-[30px]">account_balance_wallet</span>
            </span>
          </div>
          <div class="mt-3 grid grid-cols-2 gap-2 border-t border-outline-variant/40 pt-3">
            <div class="flex items-center gap-2 rounded-lg bg-surface-container-lowest p-2.5">
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-secondary-container text-primary">
                <span class="material-symbols-outlined text-[20px]">south_west</span>
              </span>
              <div class="min-w-0">
                <p class="truncate text-label-sm text-on-surface-variant">إجمالي المستلم</p>
                <p class="tabular truncate text-label-lg font-bold text-primary">{{ num(data.total_in) }} ج.م</p>
              </div>
            </div>
            <div class="flex items-center gap-2 rounded-lg bg-surface-container-lowest p-2.5">
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-error-container text-error">
                <span class="material-symbols-outlined text-[20px]">north_east</span>
              </span>
              <div class="min-w-0">
                <p class="truncate text-label-sm text-on-surface-variant">إجمالي المنصرف</p>
                <p class="tabular truncate text-label-lg font-bold text-error">{{ num(data.total_out) }} ج.م</p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </section>

    <!-- الرأس (ديسكتوب) -->
    <div class="mb-5 hidden flex-wrap items-center justify-between gap-3 md:flex">
      <div class="flex items-center gap-3">
        <button class="no-print flex h-11 w-11 items-center justify-center rounded-full hover:bg-surface-container" aria-label="رجوع" @click="router.push('/balances')">
          <span class="material-symbols-outlined">chevron_right</span>
        </button>
        <PersonAvatar v-if="data" :name="data.custodian.name" :id="data.custodian.id" size="lg" />
        <div>
          <h1 class="text-headline-lg-mobile md:text-headline-lg">كشف حساب {{ data?.custodian.name }}</h1>
          <p class="text-body-sm text-on-surface-variant">{{ data?.custodian.title }}</p>
        </div>
      </div>
      <div class="no-print flex flex-wrap gap-2">
        <button class="btn btn-ghost bg-surface-container-lowest" @click="print"><span class="material-symbols-outlined">print</span>طباعة</button>
        <button class="btn btn-outline" :disabled="!!busy" @click="exportAs('pdf')"><span class="material-symbols-outlined">picture_as_pdf</span>PDF</button>
        <button class="btn btn-outline" :disabled="!!busy" @click="exportAs('xlsx')"><span class="material-symbols-outlined">table_view</span>Excel</button>
        <button v-if="canShareFiles()" class="btn btn-primary" :disabled="!!busy" @click="share"><span class="material-symbols-outlined">share</span>مشاركة</button>
      </div>
    </div>

    <PeriodFilter v-model="period" class="mb-5" />

    <!-- الأرقام (ديسكتوب) -->
    <section class="mb-5 hidden grid-cols-2 gap-3 md:grid lg:grid-cols-4">
      <div class="card relative col-span-2 overflow-hidden border border-line p-4 lg:col-span-1">
        <div class="absolute inset-x-0 top-0 h-[3px] bg-gold"></div>
        <p class="text-label-md text-on-surface-variant">الرصيد الحالي</p>
        <div v-if="!data" class="skeleton mt-1 h-9 w-32"></div>
        <MoneyAmount v-else :value="data.current.balance" size="lg" tone="primary" />
        <p v-if="data && Number(data.current.reserved)" class="text-body-sm text-pending-text">محجوز {{ money(data.current.reserved) }} · متاح {{ money(data.current.available) }}</p>
      </div>
      <div class="card border border-line p-4">
        <p class="text-label-md text-on-surface-variant">إجمالي ما استلمه</p>
        <MoneyAmount :value="data?.total_in" size="md" tone="in" />
        <p class="text-body-sm text-on-surface-variant">في الفترة</p>
      </div>
      <div class="card border border-line p-4">
        <p class="text-label-md text-on-surface-variant">إجمالي ما صرفه وسلّمه</p>
        <MoneyAmount :value="data?.total_out" size="md" tone="out" />
        <p class="text-body-sm text-on-surface-variant">في الفترة</p>
      </div>
      <div class="card border border-line p-4">
        <p class="text-label-md text-on-surface-variant">رصيد أول المدة ← آخرها</p>
        <p class="tabular text-label-lg font-bold">{{ money(data?.opening) }} ← {{ money(data?.closing) }}</p>
      </div>
    </section>

    <div class="grid gap-5 2xl:grid-cols-12">
      <!-- الحركات -->
      <section class="2xl:col-span-8">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-headline-sm font-bold">
            <span class="md:hidden">حركات العهدة والتحويلات</span>
            <span class="max-md:hidden">الحركات</span>
          </h2>
          <span v-if="data" class="rounded-full bg-surface-container-high px-3 py-1 text-label-md text-on-surface-variant">{{ num(data.rows.length) }} عملية</span>
        </div>

        <div class="hidden md:block">
          <DataTable :columns="columns" :rows="data?.rows || []" :loading="loading && !data" :row-class="(r) => (r.reversed ? 'opacity-60' : '')">
            <template #cell-date="{ row }"><span class="whitespace-nowrap">{{ date(row.date) }}</span></template>
            <template #cell-description="{ row }">
              <button v-if="row.related?.type === 'ExpenseRequest'" class="text-right hover:text-primary hover:underline" @click="openRelated(row)">{{ row.description }}</button>
              <span v-else>{{ row.description }}</span>
              <span v-if="row.reference" class="block whitespace-nowrap text-right text-body-sm text-tertiary" dir="ltr">{{ row.reference }}</span>
            </template>
            <template #cell-type="{ row }">
              <span class="chip whitespace-nowrap border-transparent" :class="typeTone(row.type)">{{ row.type_label }}</span>
            </template>
            <template #cell-in="{ row }"><span v-if="row.in" class="font-bold text-inflow-text">{{ money(row.in, false) }}</span></template>
            <template #cell-out="{ row }"><span v-if="row.out" class="font-bold text-error">{{ money(row.out, false) }}</span></template>
            <template #cell-balance="{ row }"><b>{{ money(row.balance, false) }}</b></template>
            <template #cell-invoice="{ row }">
              <a v-if="row.invoice_url" :href="fileUrl(row.invoice_url)" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-primary hover:underline">
                <span class="material-symbols-outlined text-[20px]">receipt_long</span>عرض
              </a>
            </template>
            <template #empty><EmptyState icon="receipt_long" title="مفيش حركات في الفترة دي" /></template>
          </DataTable>
        </div>

        <!-- موبايل: الحركات متجمّعة حسب اليوم (الأحدث فوق) -->
        <div class="space-y-5 md:hidden">
          <div v-if="loading && !data" class="space-y-2"><div v-for="i in 4" :key="i" class="skeleton h-28 rounded-xl"></div></div>
          <div v-for="[day, items] in days" :key="day">
            <div class="sticky top-14 z-10 -mx-space-sm mb-2 flex items-center justify-between bg-surface/95 px-space-sm py-1.5 backdrop-blur">
              <p class="flex items-center gap-2 text-headline-sm font-bold text-on-surface">
                <span class="h-2.5 w-2.5 rounded-full bg-outline"></span>
                {{ dayName(day) }}
              </p>
              <span class="text-label-sm text-on-surface-variant">{{ ago(day) }}</span>
            </div>
            <ul class="space-y-2">
              <li
                v-for="r in items"
                :key="r.id"
                class="rounded-xl bg-surface-container-lowest p-space-sm shadow-sm"
                :class="{ 'opacity-60': r.reversed, 'active:bg-surface-container-low': r.related?.type === 'ExpenseRequest' }"
                @click="openRelated(r)"
              >
                <div class="flex items-start gap-3">
                  <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="typeTone(r.type)">
                    <span class="material-symbols-outlined text-[22px]">{{ typeIcon[r.type] }}</span>
                  </span>
                  <div class="min-w-0 flex-1">
                    <p class="line-clamp-2 text-label-lg font-bold leading-snug text-on-surface" :class="{ 'line-through': r.reversed }">{{ r.description }}</p>
                    <p class="mt-0.5 text-body-sm text-on-surface-variant">
                      <span v-if="r.reference" dir="ltr">{{ r.reference }}</span>
                      <template v-if="r.reference"> • </template>{{ time(r.created_at) }}
                    </p>
                  </div>
                  <div class="flex shrink-0 flex-col items-end gap-1">
                    <span class="tabular text-headline-sm font-bold" :class="r.in ? 'text-primary' : 'text-error'" dir="ltr">{{ r.in ? '+' : '−' }} {{ num(r.in || r.out) }} <span class="text-label-sm">ج.م</span></span>
                    <span class="rounded-full px-2.5 py-0.5 text-label-sm font-bold" :class="typeTone(r.type)">{{ r.type_label }}</span>
                  </div>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-outline-variant/40 pt-2.5 text-label-sm">
                  <a v-if="r.invoice_url" :href="fileUrl(r.invoice_url)" target="_blank" rel="noopener" class="flex min-h-[32px] items-center gap-1 font-semibold text-primary" @click.stop>
                    <span class="material-symbols-outlined text-[16px]">receipt_long</span>عرض الفاتورة
                  </a>
                  <span v-else class="flex items-center gap-1 text-on-surface-variant">
                    <span class="material-symbols-outlined text-[16px]">schedule</span>{{ date(r.date) }}
                  </span>
                  <span class="tabular font-bold text-on-surface">الرصيد بعدها: {{ money(r.balance) }}</span>
                </div>
              </li>
            </ul>
          </div>
          <div v-if="data && !data.rows.length" class="card"><EmptyState icon="receipt_long" title="مفيش حركات في الفترة دي" /></div>

          <!-- تصدير ومشاركة -->
          <div v-if="data" class="space-y-2 pt-1">
            <button class="btn btn-lg w-full rounded-2xl bg-primary text-on-primary shadow-md active:bg-primary-container" :disabled="!!busy" @click="exportAs('pdf')">
              <span class="material-symbols-outlined">print</span>
              تصدير وطباعة كشف الحساب (PDF)
              <span class="material-symbols-outlined text-[20px]">download</span>
            </button>
            <div class="grid grid-cols-2 gap-2">
              <button v-if="canShareFiles()" class="btn btn-soft" :disabled="!!busy" @click="share"><span class="material-symbols-outlined">share</span>مشاركة</button>
              <button class="btn btn-ghost bg-surface-container-lowest" :class="{ 'col-span-2': !canShareFiles() }" :disabled="!!busy" @click="exportAs('xlsx')"><span class="material-symbols-outlined">table_view</span>Excel</button>
            </div>
          </div>
        </div>
      </section>

      <!-- المصروف حسب البند -->
      <section class="2xl:col-span-4 2xl:max-w-none xl:max-w-xl">
        <div class="card border border-line p-4 sm:p-5">
          <h2 class="mb-3 flex items-center gap-2 text-headline-sm font-bold">
            <span class="material-symbols-outlined text-primary-container">donut_large</span>
            المصروف حسب البند
          </h2>
          <DonutChart v-if="data?.by_category.length" :items="data.by_category" center-label="إجمالي المصروف" />
          <p v-else class="py-8 text-center text-on-surface-variant">مفيش مصروفات في الفترة دي</p>
        </div>
      </section>
    </div>
  </div>
</template>
