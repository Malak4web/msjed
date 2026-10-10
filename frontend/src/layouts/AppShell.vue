<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import { NAV_ITEMS } from './nav'
import { useQuickActions } from '@/composables/useQuickActions'
import BottomSheet from '@/components/BottomSheet.vue'
import IncomeDrawer from '@/pages/incomes/IncomeDrawer.vue'
import { initials } from '@/utils/format'

const auth = useAuthStore()
const meta = useMetaStore()
const ui = useUiStore()
const route = useRoute()
const router = useRouter()
const { actions, run } = useQuickActions()

onMounted(() => meta.load().catch(() => ui.error('مقدرناش نحمّل البيانات الأساسية. اتأكد من الاتصال.')))
// عدّاد الطلبات المستنية في القائمة يتحدّث بعد أي عملية
watch(() => ui.dataVersion, () => auth.fetchMe())

const nav = computed(() => NAV_ITEMS.filter((i) => !i.permission || auth.can(i.permission)))
const pendingCount = computed(() => auth.user?.pending_requests || 0)
const unread = computed(() => auth.user?.unread_notifications || 0)

// تحديث العدّادات كل دقيقة لو التاب مفتوح
const poll = setInterval(() => document.visibilityState === 'visible' && auth.fetchMe(), 60_000)
onBeforeUnmount(() => clearInterval(poll))

function isActive(item) {
  return item.to === '/' ? route.path === '/' : route.path.startsWith(item.to)
}

// ── موبايل: هيدر حسب نوع الشاشة + زرار رجوع
const isTab = computed(() => !!route.meta.tab)
const unreadLabel = computed(() => (unread.value > 99 ? '99+' : unread.value))
const tabActive = (item) => (item.to === '/' ? route.path === '/' : route.path === item.to || route.path.startsWith(item.to + '/'))

function goBack() {
  // لو جاي من جوه التطبيق ارجع خطوة، غير كده روح للشاشة الأم
  if (window.history.state?.back) router.back()
  else router.push(route.meta.back || '/')
}

// ظل خفيف للهيدر أول ما المحتوى يتسحب تحته
const scrolled = ref(false)
const onScroll = () => (scrolled.value = window.scrollY > 4)
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

const bottomNav = [
  { to: '/', label: 'الرئيسية', icon: 'dashboard' },
  { to: '/requests', label: 'الطلبات', icon: 'assignment_late', badge: true },
  { to: '/balances', label: 'الأرصدة', icon: 'account_balance_wallet' },
  { to: '/more', label: 'المزيد', icon: 'widgets' },
]

async function logout() {
  if (await ui.ask({ title: 'تسجيل الخروج؟', message: 'هتحتاج تدخل رقم الموبايل وكلمة المرور تاني.', confirmText: 'خروج', icon: 'logout' })) {
    await auth.logout()
    router.replace({ name: 'login' })
  }
}
</script>

<template>
  <div class="min-h-screen bg-surface">
    <!-- السايدبار: كامل ≥1280، أيقونات 768–1279 -->
    <aside class="no-print fixed inset-y-0 right-0 z-40 hidden w-rail flex-col bg-forest text-on-primary md:flex xl:w-sidebar">
      <div class="flex h-20 items-center gap-3 border-b border-white/10 px-4 xl:px-5">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-container shadow-[0_2px_6px_rgba(0,0,0,0.25)]">
          <span class="material-symbols-outlined text-[28px]">mosque</span>
        </div>
        <div class="hidden min-w-0 xl:block">
          <p class="truncate text-headline-sm font-bold">{{ meta.settings.mosque_name }}</p>
          <p class="text-label-sm text-on-primary-container">نظام العهد والمالية</p>
        </div>
      </div>

      <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="القائمة الرئيسية">
        <RouterLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          :title="item.label"
          class="group relative flex min-h-[52px] items-center gap-3 rounded-xl px-3 text-label-lg transition-colors max-xl:justify-center"
          :class="isActive(item) ? 'bg-primary-container font-bold text-white shadow-md' : 'text-white/80 hover:bg-white/10 hover:text-white'"
        >
          <span v-if="isActive(item)" class="absolute -right-3 top-2 bottom-2 w-1 rounded-l-full bg-gold"></span>
          <span class="material-symbols-outlined text-[24px]" :class="{ 'icon-fill': isActive(item) }">{{ item.icon }}</span>
          <span class="hidden flex-1 xl:inline">{{ item.label }}</span>
          <span
            v-if="item.badge && pendingCount"
            class="flex h-6 min-w-[24px] items-center justify-center rounded-full bg-tertiary-container px-1.5 text-label-sm font-bold text-on-surface max-xl:absolute max-xl:left-1 max-xl:top-1 max-xl:h-5 max-xl:min-w-[20px]"
          >
            {{ pendingCount }}
          </span>
        </RouterLink>
      </nav>

      <div class="border-t border-white/10 p-3">
        <div class="flex items-center gap-3 rounded-xl bg-white/5 p-2 max-xl:justify-center">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-tertiary-container text-label-md font-bold text-on-surface">
            {{ initials(auth.user?.name) }}
          </div>
          <div class="hidden min-w-0 flex-1 xl:block">
            <p class="truncate text-label-md font-bold">{{ auth.user?.name }}</p>
            <p class="truncate text-label-sm text-on-primary-container">{{ auth.user?.role_label }}</p>
          </div>
          <RouterLink to="/notifications" class="relative hidden h-10 w-10 items-center justify-center rounded-full hover:bg-white/10 xl:flex" aria-label="التنبيهات" title="التنبيهات">
            <span class="material-symbols-outlined">notifications</span>
            <span v-if="unread" class="absolute right-1 top-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-tertiary-container px-1 text-[10px] font-bold text-on-surface">{{ unread }}</span>
          </RouterLink>
          <button class="hidden h-10 w-10 items-center justify-center rounded-full hover:bg-white/10 xl:flex" aria-label="تسجيل الخروج" title="تسجيل الخروج" @click="logout">
            <span class="material-symbols-outlined">logout</span>
          </button>
        </div>
      </div>
    </aside>

    <!-- هيدر الموبايل: الشاشات الرئيسية = هوية المسجد، والداخلية = رجوع + عنوان الشاشة -->
    <header
      class="pt-safe no-print fixed inset-x-0 top-0 z-50 bg-surface/90 backdrop-blur-xl transition-shadow duration-200 md:hidden"
      :class="scrolled ? 'shadow-[0_1px_8px_rgba(30,41,34,0.08)]' : ''"
    >
      <div v-if="!isTab" class="flex h-14 items-center gap-1 px-1.5">
        <button class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-on-surface active:bg-surface-container" aria-label="رجوع" @click="goBack">
          <span class="material-symbols-outlined text-[26px]">chevron_right</span>
        </button>
        <h1 class="min-w-0 flex-1 truncate text-headline-sm font-bold text-on-surface">{{ route.meta.title }}</h1>
        <RouterLink
          v-if="route.name !== 'notifications'"
          to="/notifications"
          aria-label="التنبيهات"
          class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-on-surface-variant active:bg-surface-container"
        >
          <span class="material-symbols-outlined text-[24px]">notifications</span>
          <span v-if="unread" class="absolute right-1.5 top-1.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-tertiary-container px-1 text-[10px] font-bold text-on-surface ring-2 ring-surface">{{ unreadLabel }}</span>
        </RouterLink>
      </div>
      <div v-else class="flex h-16 items-center justify-between px-space-sm">
        <div class="flex items-center gap-space-xs">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-container text-on-primary shadow-[0_2px_6px_rgba(31,111,80,0.2)]">
            <span class="material-symbols-outlined text-[26px]">mosque</span>
          </div>
          <div class="flex flex-col">
            <div class="flex items-center gap-1.5">
              <span class="text-headline-sm font-bold leading-tight text-on-surface">{{ meta.settings.mosque_name }}</span>
            </div>
            <span class="text-label-sm text-on-surface-variant">نظام العهد والمالية</span>
          </div>
        </div>
        <div class="flex items-center gap-space-xs">
          <RouterLink
            to="/notifications"
            aria-label="التنبيهات"
            class="relative flex h-11 w-11 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:text-primary active:bg-surface-container"
          >
            <span class="material-symbols-outlined text-[24px]">notifications</span>
            <span v-if="unread" class="absolute right-1.5 top-1.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-tertiary-container px-1 text-[10px] font-bold text-on-surface ring-2 ring-surface">{{ unreadLabel }}</span>
          </RouterLink>
          <RouterLink to="/more" class="flex h-11 w-11 items-center justify-center rounded-full bg-primary text-label-md font-bold text-on-primary" :aria-label="auth.user?.name">
            {{ initials(auth.user?.name) }}
          </RouterLink>
        </div>
      </div>
    </header>

    <!-- المحتوى -->
    <main
      class="relative px-space-sm md:mr-rail md:px-gutter md:pb-10 md:pt-8 xl:mr-sidebar xl:px-margin"
      :class="[isTab ? 'pt-[5.25rem]' : 'pt-[4.5rem]', route.meta.hideTabBar ? 'pb-8' : 'pb-28']"
    >
      <div class="mx-auto w-full max-w-[1600px]">
        <RouterView v-slot="{ Component, route: r }">
          <Transition name="page" mode="out-in">
            <component :is="Component" :key="r.path" />
          </Transition>
        </RouterView>
      </div>
    </main>

    <!-- الشريط السفلي في الموبايل -->
    <nav
      v-show="!route.meta.hideTabBar"
      class="pb-safe no-print fixed inset-x-0 bottom-0 bg-surface-container-lowest/95 shadow-nav backdrop-blur-xl md:hidden"
      :class="ui.actionSheet ? 'z-[85]' : 'z-50'"
      aria-label="التنقل"
    >
      <div class="relative flex h-[4.5rem] items-center justify-around px-space-xs">
        <template v-for="(item, i) in bottomNav" :key="item.to">
          <div v-if="i === 2" class="-mt-6 flex items-center justify-center">
            <button
              class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-container text-on-primary shadow-fab transition-all hover:bg-primary focus:outline-none focus:ring-4 focus:ring-tertiary-container/40 active:scale-95"
              :aria-label="ui.actionSheet ? 'إغلاق' : 'إضافة حركة'"
              :aria-expanded="ui.actionSheet"
              @click="ui.actionSheet = !ui.actionSheet"
            >
              <span class="material-symbols-outlined text-[32px] transition-transform duration-300" :class="{ 'rotate-90': ui.actionSheet }">{{ ui.actionSheet ? 'close' : 'add' }}</span>
            </button>
          </div>
          <RouterLink
            :to="item.to"
            class="group relative flex min-h-[52px] min-w-[64px] flex-col items-center justify-center gap-0.5 transition-colors"
            :class="tabActive(item) ? 'font-bold text-primary' : 'text-on-surface-variant'"
            :aria-current="tabActive(item) ? 'page' : undefined"
          >
            <!-- مؤشر Material 3: كبسولة ورا أيقونة التاب الحالي -->
            <div
              class="relative flex h-8 w-14 items-center justify-center rounded-full transition-all duration-300 ease-out group-active:scale-90"
              :class="tabActive(item) ? 'bg-secondary-container' : 'bg-transparent'"
            >
              <span class="material-symbols-outlined text-[24px]" :class="{ 'icon-fill': tabActive(item) }">{{ item.icon }}</span>
              <span
                v-if="item.badge && pendingCount"
                class="absolute right-1.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-tertiary-container px-1 text-[10px] font-bold text-on-surface ring-2 ring-surface-container-lowest"
              >
                {{ pendingCount }}
              </span>
            </div>
            <span class="text-label-sm">{{ item.label }}</span>
          </RouterLink>
        </template>
      </div>
    </nav>

    <!-- شاشة Stitch "إجراء عملية مالية سريعة" -->
    <BottomSheet v-model="ui.actionSheet" over-nav title="">
      <div class="mb-3 px-1">
        <h2 class="flex items-center gap-2 text-headline-lg-mobile font-bold text-on-surface">
          <span class="material-symbols-outlined text-primary">add_card</span>
          عملية مالية جديدة
        </h2>
        <p class="text-body-sm text-on-surface-variant">اختار الإجراء المطلوب توثيقه بدقة وأمانة في سجلات المسجد</p>
      </div>
      <div class="flex flex-col gap-2.5">
        <button
          v-for="a in actions"
          :key="a.key"
          class="flex w-full flex-col gap-2 rounded-xl bg-surface p-space-sm text-right shadow-sm transition-all active:scale-[0.985] active:bg-surface-container-low"
          @click="run(a)"
        >
          <div class="flex w-full items-start gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl shadow-md" :class="a.sheet.icon">
              <span class="material-symbols-outlined icon-fill text-[26px]">{{ a.icon }}</span>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-headline-sm font-bold leading-snug" :class="a.sheet.title">{{ a.title }}</span>
              <span class="block text-label-sm font-medium" :class="a.sheet.sub">{{ a.subtitle }}</span>
            </span>
            <span class="flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-label-sm font-bold" :class="a.sheet.badge">
              <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ a.badge }}
            </span>
          </div>
          <p class="text-body-sm leading-relaxed text-on-surface-variant">{{ a.description }}</p>
          <span class="flex items-center justify-end gap-1 pt-0.5 text-label-md font-bold" :class="a.sheet.cta">
            {{ a.cta }}
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
          </span>
        </button>
        <div class="mt-1 flex items-start gap-3 rounded-xl bg-surface-container-high p-space-sm">
          <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-container text-on-primary">
            <span class="material-symbols-outlined text-[18px]">verified_user</span>
          </span>
          <p class="text-body-sm leading-relaxed text-on-surface-variant"><b class="text-on-surface">ميثاق الأمانة:</b> كل عملية بتتسجل باسمك ووقتها، ومفيش أي صرف قبل موافقة الإمام.</p>
        </div>
      </div>
    </BottomSheet>

    <IncomeDrawer v-model="ui.incomeDrawer" />
  </div>
</template>
