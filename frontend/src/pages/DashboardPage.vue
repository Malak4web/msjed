<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api, { errorMessage } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useMetaStore } from '@/stores/meta'
import { useQuickActions } from '@/composables/useQuickActions'
import { useRequestActions } from '@/composables/useRequestActions'
import MoneyAmount from '@/components/MoneyAmount.vue'
import PersonCard from '@/components/PersonCard.vue'
import ApprovalCard from '@/components/ApprovalCard.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import IncomeExpenseChart from '@/components/IncomeExpenseChart.vue'
import MobileHome from './dashboard/MobileHome.vue'
import { date, hijri, month, num, today, shortDate } from '@/utils/format'

const auth = useAuthStore()
const meta = useMetaStore()
const ui = useUiStore()
const router = useRouter()
const { actions, run } = useQuickActions()

const people = ref([])
const dash = ref(null)
const loading = ref(true)

const total = computed(() => people.value.reduce((s, p) => s + Number(p.balance), 0))

async function load() {
  loading.value = true
  try {
    const [p, d] = await Promise.all([api.get('/custodians'), api.get('/dashboard')])
    people.value = p.data.data
    dash.value = d.data
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => ui.dataVersion, load)

const { busy, approve, reject } = useRequestActions(() => {})

const greeting = computed(() => (new Date().getHours() < 12 ? 'صباح الخير' : 'مساء الخير'))

function dueText(u) {
  if (u.status === 'overdue' || u.days_left < 0) return `متأخر ${num(Math.abs(u.days_left))} يوم`
  if (u.days_left === 0) return 'النهارده'
  if (u.days_left === 1) return 'بكرة'
  return `بعد ${num(u.days_left)} أيام`
}
</script>

<template>
  <div>
  <!-- الموبايل: تصميم Stitch "الرئيسية — تطبيق مسجد خالد بن الوليد" -->
  <MobileHome class="md:hidden" :people="people" :dash="dash" :loading="loading" />

  <div class="hidden md:block">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-2">
      <div>
        <p class="text-body-md text-on-surface-variant">{{ greeting }}،</p>
        <h1 class="text-headline-lg-mobile md:text-headline-lg">{{ auth.user?.name }}</h1>
      </div>
      <p class="text-body-sm text-on-surface-variant">
        {{ date(today()) }}
        <span v-if="meta.settings.show_hijri_date && hijri(today())" class="mr-1 text-tertiary">· {{ hijri(today()) }}</span>
      </p>
    </div>

    <!-- إجمالي رصيد المسجد -->
    <section class="relative mb-5 overflow-hidden rounded-2xl bg-forest p-5 text-on-primary shadow-md md:p-6">
      <div class="absolute inset-x-0 top-0 h-1.5 bg-gold"></div>
      <div class="pointer-events-none absolute -left-10 -top-10 h-48 w-48 rounded-full opacity-10" style="background: radial-gradient(circle, #c9a227 0%, transparent 70%)"></div>
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="flex items-center gap-2 text-label-lg text-on-primary-container">
            <span class="material-symbols-outlined icon-fill text-gold">account_balance</span>
            إجمالي رصيد المسجد
          </p>
          <div v-if="loading && !people.length" class="skeleton mt-2 h-12 w-56 bg-white/20"></div>
          <p v-else class="mt-1"><MoneyAmount :value="total" size="kpi" class="!text-white" /></p>
          <p class="mt-1 text-body-sm text-white/70">موزّع على {{ num(people.length) }} أشخاص — شوف تحت مين معاه كام</p>
        </div>
        <div v-if="dash" class="grid grid-cols-2 gap-3 text-center">
          <div class="rounded-xl bg-white/10 px-4 py-2">
            <p class="text-label-sm text-white/75">إيرادات {{ month(today()) }}</p>
            <p class="tabular text-headline-sm font-bold text-primary-fixed">+{{ num(dash.month.income) }}</p>
          </div>
          <div class="rounded-xl bg-white/10 px-4 py-2">
            <p class="text-label-sm text-white/75">مصروفات {{ month(today()) }}</p>
            <p class="tabular text-headline-sm font-bold text-error-container">−{{ num(dash.month.expense) }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- 3 أزرار سريعة -->
    <section class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
      <button
        v-for="a in actions"
        :key="a.key"
        class="card flex min-h-[80px] items-center gap-4 border border-line p-4 text-right transition hover:shadow-pop active:scale-98"
        @click="run(a)"
      >
        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl" :class="a.tone">
          <span class="material-symbols-outlined icon-fill text-[30px]">{{ a.icon }}</span>
        </span>
        <span class="flex-1">
          <span class="block text-headline-sm font-bold">{{ a.label }}</span>
          <span class="block text-body-sm text-on-surface-variant">{{ a.hint }}</span>
        </span>
      </button>
    </section>

    <!-- طلبات مستنية موافقة الإمام -->
    <section v-if="dash?.pending.length" class="mb-6">
      <div class="mb-3 flex items-center justify-between">
        <h2 class="flex items-center gap-2 text-headline-md font-bold">
          <span class="material-symbols-outlined text-tertiary">hourglass_top</span>
          طلبات مستنية موافقة الإمام
          <span class="rounded-full bg-tertiary-container/25 px-2.5 py-0.5 text-label-md text-on-tertiary-container">{{ num(dash.pending_count) }}</span>
        </h2>
        <RouterLink to="/requests" class="text-label-lg text-primary hover:underline">كل الطلبات</RouterLink>
      </div>
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        <ApprovalCard
          v-for="r in dash.pending"
          :key="r.id"
          :request="r"
          :available="dash.pending_available[r.id]"
          :busy="busy"
          @open="router.push({ path: '/requests', query: { status: 'pending', id: r.id } })"
          @approve="approve"
          @reject="reject"
        />
      </div>
    </section>

    <!-- الفلوس مع مين؟ -->
    <section class="mb-6">
      <div class="mb-3 flex items-center justify-between">
        <h2 class="flex items-center gap-2 text-headline-md font-bold">
          <span class="material-symbols-outlined text-primary-container">groups</span>
          الفلوس مع مين؟
        </h2>
        <RouterLink to="/balances" class="text-label-lg text-primary hover:underline">كل الأرصدة</RouterLink>
      </div>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <template v-if="loading && !people.length">
          <div v-for="i in 4" :key="i" class="card space-y-3 p-4">
            <div class="skeleton h-11 w-2/3"></div>
            <div class="skeleton h-20"></div>
            <div class="skeleton h-12"></div>
          </div>
        </template>
        <PersonCard v-for="p in people" v-else :key="p.id" :person="p" @statement="router.push(`/balances/${p.id}`)" />
      </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-12">
      <!-- الرسم البياني -->
      <section class="card border border-line p-4 sm:p-5 xl:col-span-8">
        <h2 class="mb-2 flex items-center gap-2 text-headline-sm font-bold">
          <span class="material-symbols-outlined text-primary-container">bar_chart</span>
          الإيرادات مقابل المصروفات — آخر 6 شهور
        </h2>
        <div v-if="!dash" class="skeleton h-72"></div>
        <IncomeExpenseChart v-else :rows="dash.chart" />
      </section>

      <!-- المصروفات الثابتة اللي ميعادها قرّب -->
      <section class="card border border-line p-4 sm:p-5 xl:col-span-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="flex items-center gap-2 text-headline-sm font-bold">
            <span class="material-symbols-outlined text-primary-container">event_repeat</span>
            مصروفات ثابتة قرّب ميعادها
          </h2>
          <RouterLink to="/recurring" class="text-label-md text-primary hover:underline">الكل</RouterLink>
        </div>
        <div v-if="!dash" class="space-y-2"><div v-for="i in 3" :key="i" class="skeleton h-14"></div></div>
        <ul v-else-if="dash.upcoming.length" class="space-y-2">
          <li v-for="u in dash.upcoming" :key="u.id" class="flex items-center gap-3 rounded-xl border p-3" :class="u.status === 'overdue' ? 'border-outflow-border bg-outflow-bg/40' : 'border-line bg-surface-container-lowest'">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-secondary-container text-on-secondary-fixed-variant">
              <span class="material-symbols-outlined">{{ u.icon || 'event' }}</span>
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-bold">{{ u.name }}</p>
              <p class="text-body-sm text-on-surface-variant">{{ shortDate(u.due_date) }} · {{ u.responsible }}</p>
            </div>
            <div class="text-left">
              <MoneyAmount :value="u.amount" size="sm" />
              <p class="text-label-sm font-bold" :class="u.status === 'overdue' ? 'text-error' : 'text-pending-text'">{{ dueText(u) }}</p>
            </div>
          </li>
        </ul>
        <div v-else class="flex flex-col items-center py-8 text-center text-on-surface-variant">
          <span class="material-symbols-outlined mb-2 text-[36px] text-primary-container">event_available</span>
          مفيش مصروفات ثابتة قريبة
        </div>
        <p v-if="dash?.upcoming.some((u) => u.request)" class="mt-3 flex flex-wrap gap-1 text-body-sm text-on-surface-variant">
          <template v-for="u in dash.upcoming.filter((x) => x.request)" :key="u.id">
            <StatusBadge :status="u.request.status" :label="`${u.name}: ${u.request.number}`" />
          </template>
        </p>
      </section>
    </div>
  </div>
  </div>
</template>
