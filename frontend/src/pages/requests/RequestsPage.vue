<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { errorMessage } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useRequestActions } from '@/composables/useRequestActions'
import ApprovalCard from '@/components/ApprovalCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import AppDrawer from '@/components/AppDrawer.vue'
import Pagination from '@/components/Pagination.vue'
import RequestDetail from './RequestDetail.vue'
import { num } from '@/utils/format'

const auth = useAuthStore()
const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const tabs = [
  { key: 'pending', label: 'بانتظار موافقة الإمام', icon: 'hourglass_top' },
  { key: 'approved', label: 'معتمد بانتظار التنفيذ', icon: 'verified' },
  { key: 'returned', label: 'مُرجَع للتعديل', icon: 'undo' },
  { key: 'executed', label: 'تم الصرف والتنفيذ', icon: 'task_alt' },
  { key: 'rejected', label: 'مرفوض', icon: 'cancel' },
  { key: 'cancelled', label: 'مُلغى', icon: 'block' },
]

const status = ref(tabs.some((t) => t.key === route.query.status) ? route.query.status : 'pending')
const rows = ref([])
const counts = ref({})
const pageMeta = ref(null)
const page = ref(1)
const loading = ref(true)
const available = ref({})
const selectedId = ref(route.query.id ? Number(route.query.id) : null)
const wideQuery = window.matchMedia('(min-width: 1280px)')
const isWide = ref(wideQuery.matches)
const onWide = (e) => (isWide.value = e.matches)
wideQuery.addEventListener('change', onWide)
onBeforeUnmount(() => wideQuery.removeEventListener('change', onWide))

const drawerOpen = computed({
  get: () => !isWide.value && !!selectedId.value,
  set: (v) => !v && select(null),
})

const { busy, approve, reject } = useRequestActions(() => load())

async function load() {
  loading.value = true
  try {
    const [r, c] = await Promise.all([
      api.get('/expense-requests', { params: { status: status.value, page: page.value } }),
      api.get('/custodians'),
    ])
    rows.value = r.data.data
    counts.value = r.data.counts
    pageMeta.value = r.data.meta
    available.value = Object.fromEntries(c.data.data.map((p) => [p.id, p.available]))
    if (isWide.value && !selectedId.value && rows.value.length) selectedId.value = rows.value[0].id
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}

function select(req) {
  selectedId.value = req?.id ?? null
  router.replace({ query: { ...route.query, id: req?.id } })
}

watch(status, (s) => {
  page.value = 1
  selectedId.value = null
  router.replace({ query: { status: s } })
  load()
})
watch(page, load)
watch(() => ui.dataVersion, load)
onMounted(load)
</script>

<template>
  <div>
    <!-- العنوان -->
    <div class="flex flex-col gap-2 pb-3 pt-2">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
          <div class="h-6 w-2.5 rounded-full bg-primary-container"></div>
          <h1 class="text-headline-lg-mobile text-on-surface md:text-headline-lg">طلبات الصرف — اعتماد الإمام</h1>
        </div>
        <div class="flex items-center gap-2">
          <span v-if="counts.pending" class="inline-flex items-center gap-1.5 rounded-full bg-tertiary-container/20 px-3 py-1 text-label-md font-bold text-on-tertiary-container shadow-sm">
            <span class="h-2 w-2 animate-pulse rounded-full bg-tertiary"></span>
            {{ num(counts.pending) }} طلبات بانتظار القرار
          </span>
          <RouterLink v-if="auth.can('expenses.create')" to="/requests/new" class="btn btn-primary hidden md:inline-flex">
            <span class="material-symbols-outlined">add</span>طلب صرف جديد
          </RouterLink>
        </div>
      </div>
      <p class="pr-4 text-body-sm text-on-surface-variant">مراجعة أمانة بيت مال المسجد والتحقق من الفواتير قبل الإذن بالصرف</p>
    </div>

    <!-- تنبيه الحوكمة -->
    <div class="sticky top-16 z-30 mb-4 rounded-xl bg-gradient-to-l from-surface-container-high via-surface-container to-surface-container-high p-3.5 shadow-sm md:top-2">
      <div class="flex items-start gap-3">
        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-container text-on-primary shadow-sm">
          <span class="material-symbols-outlined icon-fill text-[20px]">verified_user</span>
        </div>
        <div class="flex flex-col">
          <span class="text-label-md font-bold text-primary">صلاحية الاعتماد الحصرية</span>
          <span class="text-body-sm font-medium leading-relaxed text-on-surface">
            إمام المسجد — <span class="font-bold text-error">لا يُصرف أي مليم</span> من فلوس المسجد إلا بقرار موثق ومسبق.
          </span>
        </div>
      </div>
    </div>

    <!-- تابات الحالات -->
    <div class="scrollbar-none -mx-space-sm mb-3 flex items-center gap-2 overflow-x-auto px-space-sm pb-3 md:mx-0 md:px-0" role="tablist">
      <button
        v-for="t in tabs"
        :key="t.key"
        role="tab"
        :aria-selected="status === t.key"
        class="flex shrink-0 items-center gap-1.5 rounded-full py-2 text-label-md transition-colors active:scale-95"
        :class="status === t.key ? 'bg-primary px-4 text-on-primary shadow-sm' : 'bg-surface-container-highest px-3.5 text-on-surface-variant hover:bg-surface-container-high'"
        @click="status = t.key"
      >
        <span v-if="status === t.key" class="material-symbols-outlined text-[18px]">{{ t.icon }}</span>
        <span>{{ t.label }}</span>
        <span
          class="flex h-5 min-w-[20px] items-center justify-center rounded-full px-1 text-[11px] font-bold"
          :class="status === t.key ? 'bg-on-primary/20 text-on-primary' : 'bg-surface-variant text-on-surface-variant'"
        >
          {{ num(counts[t.key] || 0) }}
        </span>
      </button>
    </div>

    <div class="grid gap-5 xl:grid-cols-12">
      <!-- القائمة -->
      <section class="flex flex-col gap-4 xl:col-span-5">
        <template v-if="loading && !rows.length">
          <div v-for="i in 3" :key="i" class="card space-y-3 p-4">
            <div class="skeleton h-11 w-2/3"></div>
            <div class="skeleton h-10"></div>
          </div>
        </template>
        <ApprovalCard
          v-for="r in rows"
          v-else
          :key="r.id"
          :request="r"
          :available="available[r.custodian.id]"
          :active="isWide && selectedId === r.id"
          :busy="busy"
          @open="select"
          @approve="approve"
          @reject="reject"
        />
        <div v-if="!loading && !rows.length" class="card">
          <EmptyState icon="task_alt" :title="status === 'pending' ? 'مفيش طلبات مستنية قرار' : 'مفيش طلبات هنا'" :text="status === 'pending' ? 'الحمد لله، كل الطلبات اتراجعت.' : ''">
            <RouterLink v-if="auth.can('expenses.create')" to="/requests/new" class="btn btn-primary"><span class="material-symbols-outlined">add</span>طلب صرف جديد</RouterLink>
          </EmptyState>
        </div>
        <div v-if="pageMeta?.last_page > 1" class="card"><Pagination :meta="pageMeta" @page="page = $event" /></div>

        <div class="mt-2 flex items-center gap-3 rounded-xl bg-surface-container-low p-4">
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-secondary-container text-on-secondary-fixed-variant">
            <span class="material-symbols-outlined text-[18px]">shield</span>
          </div>
          <p class="text-body-sm leading-relaxed text-on-surface-variant">كل موافقة بتتسجل باسم الإمام ووقتها، وبيوصل إشعار لصاحب الطلب عشان يصرف ويرفع الفاتورة.</p>
        </div>
      </section>

      <!-- لوحة التفاصيل (ديسكتوب) -->
      <section v-if="isWide" class="xl:col-span-7">
        <div class="card sticky top-6 max-h-[calc(100vh-3rem)] overflow-y-auto border border-line p-5">
          <RequestDetail v-if="selectedId" :id="selectedId" :key="selectedId" @changed="load" />
          <EmptyState v-else icon="touch_app" title="اختار طلب من القائمة" text="هتظهر هنا كل تفاصيله والخط الزمني بتاعه." />
        </div>
      </section>
    </div>

    <!-- التفاصيل (موبايل/تابلت) -->
    <AppDrawer v-model="drawerOpen" title="تفاصيل طلب الصرف">
      <RequestDetail v-if="selectedId && !isWide" :id="selectedId" :key="selectedId" @changed="load" />
    </AppDrawer>
  </div>
</template>
