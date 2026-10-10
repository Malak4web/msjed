<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import PersonAvatar from '@/components/PersonAvatar.vue'
import { num, hijri, today, ago, toDate } from '@/utils/format'

/**
 * الرئيسية على الموبايل — مطابقة لشاشة Stitch:
 * تحية + تاريخ هجري، كارت "رصيدي في العهدة"، إجمالي فلوس المسجد، تنبيه الحوكمة،
 * "الفلوس مع مين؟" أفقي، وآخر الحركات.
 */
const props = defineProps({
  people: { type: Array, default: () => [] },
  dash: { type: Object, default: null },
  loading: { type: Boolean, default: false },
})

const auth = useAuthStore()
const meta = useMetaStore()

const mine = computed(() => props.people.find((p) => p.id === auth.user?.custodian?.id) || null)
const total = computed(() => props.people.reduce((s, p) => s + Number(p.balance), 0))
const pending = computed(() => props.dash?.pending_count || 0)
const weekday = computed(() => new Intl.DateTimeFormat('ar-EG', { weekday: 'long' }).format(new Date()))
const isFriday = new Date().getDay() === 5

const inflow = (t) => ['income', 'transfer_in'].includes(t)
const reversal = (t) => t === 'reversal'
const tag = { income: 'وارد', transfer_in: 'استلام', transfer_out: 'تسليم', expense: 'مصروف', reversal: 'قيد عكسي' }

function when(r) {
  const d = toDate(r.created_at)
  return d && Date.now() - d.getTime() < 7 * 864e5 ? ago(r.created_at) : new Intl.DateTimeFormat('ar-EG', { day: 'numeric', month: 'short' }).format(toDate(r.date))
}
</script>

<template>
  <div class="flex flex-col gap-space-sm pb-6">
    <!-- التحية والتاريخ الهجري -->
    <section class="flex items-center justify-between gap-3 rounded-xl bg-surface-container-low p-space-sm shadow-sm">
      <div class="flex min-w-0 items-center gap-space-xs">
        <div class="relative shrink-0">
          <PersonAvatar :name="auth.user?.name" :id="auth.user?.custodian?.id || 0" class="!h-12 !w-12 !rounded-full" />
          <span v-if="auth.isImam" class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary ring-2 ring-surface-container-low">
            <span class="material-symbols-outlined icon-fill text-[11px] text-on-primary">verified</span>
          </span>
        </div>
        <div class="flex min-w-0 flex-col">
          <span class="truncate text-label-sm text-on-surface-variant">{{ auth.user?.name }}</span>
          <h2 class="truncate text-headline-sm font-bold text-on-surface">{{ auth.user?.role_label }}</h2>
        </div>
      </div>
      <div class="flex shrink-0 flex-col items-end">
        <span class="inline-flex items-center gap-1 rounded-full bg-surface-container-high px-2.5 py-1 text-label-sm text-primary">
          <span class="material-symbols-outlined text-[14px]">calendar_month</span>
          {{ meta.settings.show_hijri_date && hijri(today()) ? hijri(today()) : new Intl.DateTimeFormat('ar-EG', { day: 'numeric', month: 'long' }).format(new Date()) }}
        </span>
        <span class="mt-0.5 text-label-sm text-on-surface-variant">{{ isFriday ? 'الجمعة المباركة' : weekday }}</span>
      </div>
    </section>

    <!-- رصيدي المتاح في العهدة -->
    <section class="relative overflow-hidden rounded-xl bg-primary-container p-space-md text-on-primary shadow-md">
      <div class="pointer-events-none absolute -bottom-6 -left-6 h-32 w-32 rounded-full bg-primary/20"></div>
      <div class="pointer-events-none absolute -top-8 left-8 h-24 w-24 rounded-full bg-primary-fixed/10"></div>

      <div v-if="loading && !people.length" class="relative space-y-3">
        <div class="skeleton h-5 w-40 bg-white/15"></div>
        <div class="skeleton h-12 w-48 bg-white/15"></div>
        <div class="grid grid-cols-2 gap-2"><div class="skeleton h-12 bg-white/15"></div><div class="skeleton h-12 bg-white/15"></div></div>
      </div>

      <div v-else class="relative z-10 flex flex-col gap-space-xs">
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[20px] text-primary-fixed">lock_clock</span>
            <span class="text-label-md font-medium text-on-primary-container">{{ mine ? 'رصيدي المتاح الحالي في العهدة' : 'إجمالي رصيد المسجد' }}</span>
          </div>
          <span v-if="mine" class="shrink-0 whitespace-nowrap rounded-full bg-primary px-2.5 py-0.5 text-label-sm font-bold text-primary-fixed">عهدتي</span>
        </div>

        <div class="my-1 flex items-baseline gap-2">
          <span class="tabular text-kpi-number font-extrabold tracking-tight text-surface-bright">{{ num(mine ? mine.available : total) }}</span>
          <span class="text-headline-md font-bold text-primary-fixed">ج.م</span>
        </div>
        <p v-if="mine && Number(mine.reserved) > 0" class="-mt-1 text-label-sm text-on-primary-container">منها {{ num(mine.reserved) }} ج.م محجوزة لطلبات معتمدة</p>

        <div class="mt-1 grid grid-cols-2 gap-2">
          <div class="flex items-center gap-2 rounded-lg bg-primary/40 p-2">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-secondary-container text-on-secondary-fixed">
              <span class="material-symbols-outlined text-[16px]">arrow_downward</span>
            </div>
            <div class="flex min-w-0 flex-col">
              <span class="text-label-sm text-on-primary-container">{{ mine ? 'استلمت هذا الشهر' : 'إيرادات الشهر' }}</span>
              <span class="tabular truncate text-label-md font-bold text-surface-bright">{{ num(mine ? mine.month_in : dash?.month.income) }} ج.م</span>
            </div>
          </div>
          <div class="flex items-center gap-2 rounded-lg bg-primary/40 p-2">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-error-container text-on-error-container">
              <span class="material-symbols-outlined text-[16px]">arrow_upward</span>
            </div>
            <div class="flex min-w-0 flex-col">
              <span class="text-label-sm text-on-primary-container">{{ mine ? 'صرفت هذا الشهر' : 'مصروفات الشهر' }}</span>
              <span class="tabular truncate text-label-md font-bold text-surface-bright">{{ num(mine ? mine.month_out : dash?.month.expense) }} ج.م</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- إجمالي أموال المسجد الموزعة -->
    <RouterLink v-if="mine" to="/balances" class="flex items-center justify-between rounded-xl bg-surface-container p-space-sm shadow-sm active:bg-surface-container-high">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-container-highest text-primary">
          <span class="material-symbols-outlined text-[22px]">account_balance</span>
        </div>
        <div class="flex flex-col">
          <span class="text-label-sm text-on-surface-variant">إجمالي أموال المسجد الموزعة كعُهد</span>
          <div class="flex items-baseline gap-1.5">
            <span class="tabular text-headline-sm font-bold text-primary">{{ num(total) }}</span>
            <span class="text-label-sm font-bold text-on-surface">ج.م</span>
          </div>
        </div>
      </div>
      <span class="flex items-center gap-1 rounded-full bg-surface-bright px-2.5 py-1 text-label-sm text-on-surface-variant shadow-sm">
        <span class="material-symbols-outlined text-[16px] text-secondary">groups</span>
        {{ num(people.length) }} حاملي عهد
      </span>
    </RouterLink>

    <!-- تنبيه الحوكمة -->
    <section v-if="pending" class="flex flex-col gap-2 rounded-xl bg-tertiary-fixed p-space-sm text-on-tertiary-fixed shadow-sm">
      <div class="flex items-start gap-2">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-tertiary-container text-on-tertiary shadow-sm">
          <span class="material-symbols-outlined text-[18px]">gavel</span>
        </div>
        <div class="flex flex-col">
          <div class="flex items-center gap-2">
            <span class="text-label-md font-bold">حوكمة واعتماد الصرف</span>
            <span class="flex h-5 min-w-[20px] items-center justify-center rounded-full bg-error px-1 text-label-sm font-bold text-on-error shadow-sm">{{ num(pending) }}</span>
          </div>
          <span class="text-body-sm leading-tight text-on-tertiary-fixed-variant">
            {{ auth.isImam ? `يوجد ${num(pending)} طلبات صرف جديدة بانتظار موافقتك واعتمادك` : `${num(pending)} طلبات صرف مستنية قرار الإمام` }}
          </span>
        </div>
      </div>
      <RouterLink to="/requests?status=pending" class="mt-1 flex w-full items-center justify-between rounded-lg bg-on-tertiary-fixed px-3 py-2.5 text-label-md text-tertiary-fixed active:opacity-90">
        <span class="flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[18px]">checklist_rtl</span>
          عرض ومراجعة الطلبات الآن
        </span>
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
      </RouterLink>
    </section>

    <!-- الفلوس مع مين؟ -->
    <section class="mt-1 flex flex-col gap-2">
      <div class="flex items-center justify-between px-1">
        <div class="flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[20px] text-primary">groups_3</span>
          <h3 class="text-headline-sm font-bold text-on-surface">الفلوس مع مين؟</h3>
          <span class="text-label-sm text-on-surface-variant">(الأرصدة الحالية)</span>
        </div>
        <RouterLink to="/balances" class="flex items-center gap-0.5 text-label-sm font-bold text-primary">
          الكل
          <span class="material-symbols-outlined text-[14px]">arrow_back</span>
        </RouterLink>
      </div>
      <div class="scrollbar-none -mx-space-sm flex snap-x gap-space-xs overflow-x-auto px-space-sm pb-1 pt-0.5">
        <template v-if="loading && !people.length">
          <div v-for="i in 3" :key="i" class="skeleton h-[118px] min-w-[210px] shrink-0 rounded-xl"></div>
        </template>
        <RouterLink
          v-for="p in people"
          v-else
          :key="p.id"
          :to="`/balances/${p.id}`"
          class="flex min-w-[210px] shrink-0 snap-start flex-col justify-between rounded-xl bg-surface-container-lowest p-space-sm shadow-sm active:scale-[.98]"
        >
          <div class="flex items-center gap-2">
            <PersonAvatar :name="p.name" :id="p.id" size="sm" class="!h-10 !w-10 !rounded-full" />
            <div class="flex min-w-0 flex-col">
              <h4 class="truncate text-label-md font-bold text-on-surface">{{ p.name }}</h4>
              <span class="truncate text-label-sm text-on-surface-variant">{{ p.title || 'حامل عهدة' }}</span>
            </div>
          </div>
          <div class="mt-3 flex items-baseline justify-between rounded-lg bg-surface-container-low p-2">
            <span class="text-label-sm text-on-surface-variant">العهدة:</span>
            <div class="flex items-baseline gap-1">
              <span class="tabular text-headline-sm font-bold text-primary">{{ num(p.balance) }}</span>
              <span class="text-label-sm font-bold text-on-surface">ج.م</span>
            </div>
          </div>
        </RouterLink>
      </div>
    </section>

    <!-- آخر الحركات -->
    <section class="mt-2 flex flex-col gap-2">
      <div class="flex items-center justify-between px-1">
        <div class="flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[20px] text-primary">receipt_long</span>
          <h3 class="text-headline-sm font-bold text-on-surface">آخر الحركات المالية</h3>
        </div>
        <RouterLink to="/incomes" class="text-label-sm font-bold text-primary">الإيرادات</RouterLink>
      </div>

      <div v-if="!dash" class="flex flex-col gap-2"><div v-for="i in 4" :key="i" class="skeleton h-[68px] rounded-xl"></div></div>
      <div v-else class="flex flex-col gap-2">
        <component
          :is="r.request_id ? 'RouterLink' : 'div'"
          v-for="r in dash.recent"
          :key="r.id"
          :to="r.request_id ? { path: '/requests', query: { status: 'executed', id: r.request_id } } : undefined"
          class="flex items-center justify-between gap-2 rounded-xl bg-surface-container-lowest p-space-sm shadow-sm"
        >
          <div class="flex min-w-0 items-center gap-3">
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
              :class="reversal(r.type) ? 'bg-tertiary-fixed text-on-tertiary-fixed-variant' : inflow(r.type) ? 'bg-secondary-container text-on-secondary-fixed-variant' : 'bg-error-container text-on-error-container'"
            >
              <span class="material-symbols-outlined text-[20px]">{{ r.icon }}</span>
            </div>
            <div class="flex min-w-0 flex-col">
              <span class="truncate text-label-md font-bold text-on-surface">{{ r.title }}</span>
              <div class="flex items-center gap-1.5 text-label-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[13px]">person</span>
                <span class="truncate">{{ r.custodian }}</span>
                <span>•</span>
                <span class="shrink-0">{{ when(r) }}</span>
              </div>
            </div>
          </div>
          <div class="flex shrink-0 flex-col items-end">
            <span class="tabular flex items-center gap-0.5 text-headline-sm font-bold" :class="Number(r.amount) > 0 ? 'text-secondary' : 'text-error'" dir="ltr">
              {{ Number(r.amount) > 0 ? '+' : '−' }} {{ num(Math.abs(r.amount)) }} <span class="text-label-sm font-bold">ج.م</span>
            </span>
            <span
              class="mt-0.5 rounded-full px-2 py-0.5 text-label-sm font-bold"
              :class="inflow(r.type) ? 'bg-secondary-container text-on-secondary-fixed-variant' : 'bg-surface-container-high text-on-surface-variant'"
            >
              {{ tag[r.type] }}
            </span>
          </div>
        </component>
        <p v-if="!dash.recent.length" class="rounded-xl bg-surface-container-lowest p-6 text-center text-on-surface-variant">مفيش حركات لسه</p>
      </div>
    </section>

    <!-- ختام -->
    <section class="mt-2 flex items-center gap-3 rounded-xl bg-surface-container p-space-sm shadow-sm">
      <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface-bright text-tertiary">
        <span class="material-symbols-outlined text-[20px]">verified_user</span>
      </div>
      <div>
        <p class="text-label-md font-bold text-on-surface">أمانة وحوكمة بيت الله</p>
        <p class="text-body-sm leading-snug text-on-surface-variant">«كُلُّكُمْ رَاعٍ وَكُلُّكُمْ مَسْؤُولٌ عَنْ رَعِيَّتِهِ» — كل المعاملات مسجلة وموثقة.</p>
      </div>
    </section>
  </div>
</template>
