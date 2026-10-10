<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api, { errorMessage, fieldErrors } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import PageHeader from '@/components/PageHeader.vue'
import MoneyAmount from '@/components/MoneyAmount.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import AppDrawer from '@/components/AppDrawer.vue'
import TileSelect from '@/components/TileSelect.vue'
import AmountInput from '@/components/AmountInput.vue'
import { num, money, month as monthName, shortDate } from '@/utils/format'

const auth = useAuthStore()
const meta = useMetaStore()
const ui = useUiStore()
const router = useRouter()

const current = new Date()
const month = ref(`${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, '0')}`)
const items = ref([])
const summary = ref(null)
const loading = ref(true)
const creating = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/recurring-expenses', { params: { month: month.value } })
    items.value = data.data
    summary.value = data.summary
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(month, load)
watch(() => ui.dataVersion, load)

function canCreate(item) {
  const r = item.period?.request
  return item.is_active && item.period && item.period.status !== 'paid' && (!r || ['rejected', 'cancelled'].includes(r.status)) && auth.can('expenses.create')
}

async function createRequest(item) {
  const ok = await ui.ask({
    title: `إنشاء طلب صرف لـ "${item.name}"؟`,
    message: `${money(item.amount)} من عهدة ${item.responsible.name}. ${item.pre_approved ? 'البند معتمد مسبقًا، فهيتوافق عليه تلقائيًا.' : 'هيتبعت للإمام يوافق عليه.'}`,
    confirmText: 'إنشاء الطلب',
    icon: 'request_quote',
  })
  if (!ok) return
  creating.value = item.id
  try {
    const { data } = await api.post(`/recurring-expenses/${item.id}/create-request`, { month: month.value })
    ui.success(data.message)
    ui.changed()
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    creating.value = null
  }
}

function shiftMonth(delta) {
  const [y, m] = month.value.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  month.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}
const isFuture = computed(() => month.value > `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, '0')}`)

// ── الإضافة/التعديل
const drawer = ref(false)
const editing = ref(null)
const errors = ref({})
const saving = ref(false)
const form = reactive({ name: '', expense_category_id: null, amount: '', due_day: 1, responsible_custodian_id: null, pre_approved: false, is_active: true, notes: '' })
const fixedCategories = computed(() => meta.expense_categories.filter((c) => c.type === 'fixed'))
const people = computed(() => meta.custodians.map((c) => ({ id: c.id, name: c.name, icon: 'person' })))

function openForm(item = null) {
  editing.value = item
  errors.value = {}
  Object.assign(form, item
    ? { name: item.name, expense_category_id: item.category.id, amount: String(Number(item.amount)), due_day: item.due_day, responsible_custodian_id: item.responsible.id, pre_approved: item.pre_approved, is_active: item.is_active, notes: item.notes || '' }
    : { name: '', expense_category_id: null, amount: '', due_day: 1, responsible_custodian_id: null, pre_approved: false, is_active: true, notes: '' })
  drawer.value = true
}

async function save() {
  saving.value = true
  try {
    const { data } = editing.value ? await api.put(`/recurring-expenses/${editing.value.id}`, form) : await api.post('/recurring-expenses', form)
    ui.success(data.message)
    drawer.value = false
    load()
  } catch (e) {
    errors.value = fieldErrors(e)
    ui.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader title="المصروفات الثابتة" subtitle="البنود اللي بتتكرر كل شهر، وحالة كل بند الشهر ده">
      <template #actions>
        <button v-if="auth.can('settings.manage')" class="btn btn-primary" @click="openForm()"><span class="material-symbols-outlined">add</span>بند ثابت جديد</button>
      </template>
    </PageHeader>

    <!-- الشهر -->
    <div class="mb-4 flex items-center justify-between gap-2 rounded-xl bg-surface-container-low p-2">
      <button class="btn btn-ghost min-h-[44px] px-3" aria-label="الشهر اللي فات" @click="shiftMonth(-1)"><span class="material-symbols-outlined">chevron_right</span></button>
      <p class="text-headline-sm font-bold">{{ monthName(month + '-01') }}</p>
      <button class="btn btn-ghost min-h-[44px] px-3" aria-label="الشهر الجاي" :disabled="isFuture" @click="shiftMonth(1)"><span class="material-symbols-outlined">chevron_left</span></button>
    </div>

    <section v-if="summary" class="mobile-scroller mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="card relative col-span-2 overflow-hidden border border-line p-4 lg:col-span-1">
        <div class="absolute inset-x-0 top-0 h-[3px] bg-gold"></div>
        <p class="text-label-md text-on-surface-variant">إجمالي الثابت الشهري</p>
        <MoneyAmount :value="summary.monthly_total" size="lg" tone="primary" />
      </div>
      <div class="card border border-inflow-border/60 bg-inflow-bg/40 p-4">
        <p class="text-label-md text-inflow-text">مدفوع</p>
        <p class="text-headline-lg text-inflow-text">{{ num(summary.paid) }}</p>
      </div>
      <div class="card border border-pending-border/60 bg-pending-bg/40 p-4">
        <p class="text-label-md text-pending-text">مستحق</p>
        <p class="text-headline-lg text-pending-text">{{ num(summary.due) }}</p>
      </div>
      <div class="card border border-outflow-border/60 bg-outflow-bg/40 p-4">
        <p class="text-label-md text-outflow-text">متأخر</p>
        <p class="text-headline-lg text-outflow-text">{{ num(summary.overdue) }}</p>
      </div>
    </section>

    <div v-if="loading && !items.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 6" :key="i" class="skeleton h-40"></div>
    </div>
    <div v-else-if="items.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="item in items"
        :key="item.id"
        class="card relative flex flex-col gap-3 overflow-hidden border p-4"
        :class="[item.period?.status === 'overdue' ? 'border-outflow-border' : 'border-line', { 'opacity-60': !item.is_active }]"
      >
        <div v-if="item.period?.status === 'overdue'" class="absolute inset-x-0 top-0 h-1.5 bg-error"></div>
        <header class="flex items-start gap-3">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-secondary-container text-on-secondary-fixed-variant">
            <span class="material-symbols-outlined">{{ item.category.icon || 'event_repeat' }}</span>
          </span>
          <div class="min-w-0 flex-1">
            <h3 class="text-label-lg font-bold">{{ item.name }}</h3>
            <p class="text-body-sm text-on-surface-variant">{{ item.category.name }} · يوم {{ num(item.due_day) }} من كل شهر</p>
          </div>
          <button v-if="auth.can('settings.manage')" class="flex h-10 w-10 items-center justify-center rounded-full hover:bg-surface-container" aria-label="تعديل" @click="openForm(item)">
            <span class="material-symbols-outlined text-[20px]">edit</span>
          </button>
        </header>

        <div class="flex items-end justify-between gap-2">
          <div>
            <p class="text-label-sm text-on-surface-variant">المبلغ المعتاد</p>
            <MoneyAmount :value="item.amount" size="md" />
          </div>
          <div class="text-left">
            <StatusBadge v-if="!item.is_active" status="inactive" />
            <StatusBadge v-else-if="item.period" :status="item.period.status" />
            <p v-if="item.period" class="mt-1 text-body-sm text-on-surface-variant">الاستحقاق {{ shortDate(item.period.due_date) }}</p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-body-sm">
          <span class="chip border-line bg-surface-container-low text-on-surface-variant"><span class="material-symbols-outlined text-[16px]">person</span>{{ item.responsible.name }}</span>
          <span v-if="item.pre_approved" class="chip border-tertiary-fixed-dim bg-tertiary-fixed/40 text-on-tertiary-fixed-variant"><span class="material-symbols-outlined text-[16px]">bolt</span>معتمد مسبقًا</span>
        </div>

        <div class="mt-auto pt-1">
          <button v-if="item.period?.request && !canCreate(item)" class="btn btn-ghost w-full bg-surface-container-low" @click="router.push({ path: '/requests', query: { status: item.period.request.status, id: item.period.request.id } })">
            <StatusBadge :status="item.period.request.status" />
            <span dir="ltr" class="text-body-sm">{{ item.period.request.number }}</span>
          </button>
          <button v-else-if="canCreate(item)" class="btn btn-primary w-full" :disabled="creating === item.id" @click="createRequest(item)">
            <span class="material-symbols-outlined">request_quote</span>
            إنشاء طلب صرف
          </button>
        </div>
      </article>
    </div>
    <div v-else class="card"><EmptyState icon="event_repeat" title="مفيش مصروفات ثابتة متسجلة" text="ضيف الكهرباء والمياه والمرتبات عشان النظام يفكّرك بيها كل شهر." /></div>

    <AppDrawer v-model="drawer" :title="editing ? `تعديل ${editing.name}` : 'بند ثابت جديد'" subtitle="النظام هيعمل مستحق للبند ده أول كل شهر">
      <form class="space-y-5" @submit.prevent="save">
        <div>
          <label class="field-label" for="r-name">اسم البند</label>
          <input id="r-name" v-model="form.name" class="field" :class="{ 'field-error': errors.name }" placeholder="مثلاً: فاتورة الكهرباء" />
          <p v-if="errors.name" class="error-text">{{ errors.name }}</p>
        </div>
        <TileSelect v-model="form.expense_category_id" label="بند الصرف" :options="fixedCategories" columns="grid-cols-2 sm:grid-cols-4" :error="errors.expense_category_id" />
        <AmountInput v-model="form.amount" label="المبلغ المعتاد" :error="errors.amount" />
        <div>
          <label class="field-label" for="r-day">يوم الاستحقاق في الشهر</label>
          <input id="r-day" v-model.number="form.due_day" type="number" min="1" max="28" class="field w-32" :class="{ 'field-error': errors.due_day }" />
          <p v-if="errors.due_day" class="error-text">{{ errors.due_day }}</p>
        </div>
        <TileSelect v-model="form.responsible_custodian_id" label="المسؤول عن الدفع" :options="people" columns="grid-cols-2" :error="errors.responsible_custodian_id" />
        <label class="flex min-h-[56px] cursor-pointer items-center justify-between gap-3 rounded-xl border border-line bg-surface-container-lowest px-4">
          <span><b>معتمد مسبقًا</b><br /><span class="text-body-sm text-on-surface-variant">الطلب الشهري يتوافق عليه تلقائيًا من غير ما يستنى الإمام</span></span>
          <input v-model="form.pre_approved" type="checkbox" class="h-6 w-6 shrink-0 accent-[#1f6f50]" />
        </label>
        <label class="flex min-h-[56px] cursor-pointer items-center justify-between rounded-xl border border-line bg-surface-container-lowest px-4">
          <span class="font-bold">نشط</span>
          <input v-model="form.is_active" type="checkbox" class="h-6 w-6 accent-[#1f6f50]" />
        </label>
        <div>
          <label class="field-label" for="r-notes">ملاحظات</label>
          <textarea id="r-notes" v-model="form.notes" rows="2" class="field py-3"></textarea>
        </div>
      </form>
      <template #footer>
        <button class="btn btn-primary btn-lg w-full" :disabled="saving" @click="save"><span class="material-symbols-outlined">check</span>حفظ</button>
      </template>
    </AppDrawer>
  </div>
</template>
