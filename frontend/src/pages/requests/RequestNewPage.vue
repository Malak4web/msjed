<script setup>
import { reactive, ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { errorMessage, fieldErrors } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import FileInput from '@/components/FileInput.vue'
import PersonAvatar from '@/components/PersonAvatar.vue'
import { money, num } from '@/utils/format'

/** طلب صرف جديد — مطابق لشاشة Stitch "تسجيل طلب صرف جديد" */
const auth = useAuthStore()
const meta = useMetaStore()
const ui = useUiStore()
const route = useRoute()
const router = useRouter()

const editId = route.query.edit ? Number(route.query.edit) : null
const editing = ref(null)
const people = ref([])
const pickPerson = ref(false)
const form = reactive({
  custodian_id: auth.user?.custodian?.id ?? null,
  type: 'variable',
  expense_category_id: null,
  amount: '',
  description: '',
  attachment: null,
})
const errors = ref({})
const saving = ref(false)
const DESC_MAX = 300

onMounted(async () => {
  try {
    await meta.load()
    people.value = (await api.get('/custodians')).data.data
    if (!form.custodian_id && people.value.length) form.custodian_id = people.value[0].id
    if (editId) {
      const r = (await api.get(`/expense-requests/${editId}`)).data.data
      editing.value = r
      Object.assign(form, { custodian_id: r.custodian.id, type: r.type, expense_category_id: r.category.id, amount: String(Number(r.amount)), description: r.description })
    }
  } catch (e) {
    ui.error(errorMessage(e))
  }
})

const person = computed(() => people.value.find((p) => p.id === form.custodian_id))
const available = computed(() => Number(person.value?.available ?? 0))
const amount = computed(() => Number(form.amount) || 0)
const overBalance = computed(() => person.value && amount.value > available.value)
const categories = computed(() => meta.expense_categories.filter((c) => c.type === form.type))

function setType(t) {
  form.type = t
  if (!categories.value.some((c) => c.id === form.expense_category_id)) form.expense_category_id = null
}

function add(n) {
  form.amount = String(amount.value + n)
  errors.value.amount = null
}

function typeAmount(e) {
  const map = { '٠': 0, '١': 1, '٢': 2, '٣': 3, '٤': 4, '٥': 5, '٦': 6, '٧': 7, '٨': 8, '٩': 9 }
  const v = e.target.value.replace(/[٠-٩]/g, (d) => map[d]).replace(/[٫,]/g, '.').replace(/[^\d.]/g, '')
  form.amount = v
  e.target.value = v
}

async function submit() {
  const e = {}
  if (!form.custodian_id) e.custodian_id = 'اختار مين اللي هيصرف.'
  if (!form.expense_category_id) e.expense_category_id = 'اختار البند.'
  if (!(amount.value > 0)) e.amount = 'اكتب المبلغ.'
  else if (overBalance.value) e.amount = `المبلغ أكبر من الرصيد المتاح مع ${person.value.name} (${money(available.value)}).`
  if ((form.description || '').trim().length < 3) e.description = 'اكتب هتصرف في إيه بالظبط.'
  errors.value = e
  if (Object.keys(e).length) {
    document.querySelector('[data-error]')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  saving.value = true
  try {
    const body = new FormData()
    body.append('custodian_id', form.custodian_id)
    body.append('expense_category_id', form.expense_category_id)
    body.append('amount', form.amount)
    body.append('description', form.description.trim())
    if (form.attachment) body.append('attachments[]', form.attachment)
    const { data } = editId ? await api.post(`/expense-requests/${editId}/update`, body) : await api.post('/expense-requests', body)
    ui.success(data.message)
    ui.changed()
    router.replace({ path: '/requests', query: { status: data.data.status, id: data.data.id } })
  } catch (err) {
    errors.value = fieldErrors(err)
    ui.error(errorMessage(err))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl pb-28 md:pb-6">
    <h1 class="mb-4 hidden items-center gap-2 text-headline-lg md:flex">
      <span class="h-6 w-2.5 rounded-full bg-primary-container"></span>
      {{ editing ? `تعديل الطلب ${editing.number}` : 'طلب صرف جديد' }}
    </h1>

    <div v-if="editing?.return_reason" class="mb-space-sm flex items-start gap-2 rounded-xl bg-tertiary-fixed p-space-sm text-on-tertiary-fixed-variant shadow-sm">
      <span class="material-symbols-outlined">undo</span>
      <span><b>الإمام طلب تعديل:</b> {{ editing.return_reason }}</span>
    </div>

    <!-- الرصيد المتاح (والضغط عليه يغيّر الشخص) -->
    <button
      type="button"
      class="mb-space-sm flex w-full items-center justify-between rounded-xl bg-secondary-container p-space-sm text-right text-on-secondary-fixed-variant shadow-sm active:scale-[.99]"
      :class="{ 'ring-2 ring-error': errors.custodian_id }"
      :aria-expanded="pickPerson"
      @click="pickPerson = !pickPerson"
    >
      <div class="flex min-w-0 items-center gap-space-xs">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface text-primary shadow-sm">
          <span class="material-symbols-outlined icon-fill text-[24px]">account_balance_wallet</span>
        </div>
        <div class="min-w-0">
          <span class="block text-label-sm opacity-80">الرصيد المتاح للصرف حالياً</span>
          <div v-if="person" class="flex flex-wrap items-baseline gap-x-1">
            <span class="tabular text-headline-md font-bold text-primary">{{ num(available) }}</span>
            <span class="text-label-md font-semibold text-secondary">ج.م</span>
            <span class="mr-1 text-label-sm">(عهدة {{ person.name }})</span>
          </div>
          <span v-else class="text-label-md font-bold">اختار مين هيصرف</span>
        </div>
      </div>
      <span class="flex shrink-0 items-center gap-0.5 rounded-full bg-surface/70 px-2.5 py-1 text-label-sm font-bold text-primary">
        تغيير
        <span class="material-symbols-outlined text-[16px] transition-transform" :class="{ 'rotate-180': pickPerson }">expand_more</span>
      </span>
    </button>

    <Transition enter-from-class="opacity-0 -translate-y-2" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-150">
      <div v-if="pickPerson" class="mb-space-sm grid grid-cols-2 gap-2 rounded-xl bg-surface-container-lowest p-2 shadow-sm">
        <button
          v-for="p in people"
          :key="p.id"
          type="button"
          class="flex items-center gap-2 rounded-lg p-2 text-right transition"
          :class="form.custodian_id === p.id ? 'bg-secondary-container' : 'bg-surface-container-low active:bg-surface-container'"
          @click="form.custodian_id = p.id; pickPerson = false"
        >
          <PersonAvatar :name="p.name" :id="p.id" size="sm" class="!rounded-full" />
          <span class="min-w-0">
            <span class="block truncate text-label-md font-bold">{{ p.name }}</span>
            <span class="tabular block text-label-sm text-on-surface-variant">متاح {{ num(p.available) }}</span>
          </span>
        </button>
      </div>
    </Transition>

    <form class="flex flex-col gap-space-md" @submit.prevent="submit">
      <!-- 1) المبلغ -->
      <section class="flex flex-col items-center rounded-xl bg-surface-container-lowest p-space-md shadow-sm" :data-error="errors.amount ? '' : undefined">
        <label for="req-amount" class="mb-space-xs text-label-md text-on-surface-variant">المبلغ المراد صرفه</label>
        <div
          class="my-space-xs flex w-full items-center justify-center gap-2 rounded-lg py-2 transition-colors"
          :class="errors.amount || overBalance ? 'bg-error-container/50' : 'bg-surface-container-low'"
        >
          <input
            id="req-amount"
            :value="form.amount"
            inputmode="decimal"
            autocomplete="off"
            placeholder="0"
            class="tabular min-w-[3ch] max-w-[12ch] bg-transparent text-center text-kpi-number tracking-tight outline-none placeholder:text-outline-variant"
            :class="overBalance ? 'text-error' : 'text-primary'"
            :style="{ width: `${Math.max(3, (form.amount || '').length + 1)}ch` }"
            @input="typeAmount"
          />
          <span class="text-headline-sm font-bold text-secondary">ج.م</span>
        </div>
        <div class="mt-space-xs grid w-full grid-cols-4 gap-2">
          <button
            v-for="n in [50, 100, 200, 500]"
            :key="n"
            type="button"
            class="tabular flex h-12 items-center justify-center rounded-lg bg-surface-container text-label-md font-bold text-primary shadow-sm transition-all active:scale-95 active:bg-secondary-container"
            @click="add(n)"
          >
            +{{ num(n) }}
          </button>
        </div>
        <div class="mt-space-xs flex w-full items-center justify-between px-1">
          <button type="button" class="flex min-h-[36px] items-center gap-1 text-label-sm text-error active:opacity-70" @click="form.amount = ''">
            <span class="material-symbols-outlined text-[16px]">restart_alt</span>
            إعادة تعيين القيمة
          </button>
          <span v-if="person" class="tabular text-label-sm text-outline">الحد الأقصى للإذن {{ money(available) }}</span>
        </div>
        <p v-if="errors.amount || overBalance" class="error-text w-full" role="alert">
          <span class="material-symbols-outlined text-[18px]">error</span>
          {{ errors.amount || `المبلغ أكبر من الرصيد المتاح مع ${person.name}. سلّمه فلوس الأول أو قلّل المبلغ.` }}
        </p>
      </section>

      <!-- 2) نوع المصروف -->
      <section class="flex flex-col gap-space-xs rounded-xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="flex items-center gap-2 text-label-lg text-on-surface">
          <span class="material-symbols-outlined text-[20px] text-primary">tune</span>
          تصنيف طبيعة النفقة
        </span>
        <div class="grid grid-cols-2 gap-1 rounded-xl bg-surface-container p-1" role="radiogroup">
          <button
            v-for="t in [
              { id: 'variable', label: 'طارئ / تشغيلي', hint: '(غير ثابت)' },
              { id: 'fixed', label: 'دوري / راتب', hint: '(ثابت شهرياً)' },
            ]"
            :key="t.id"
            type="button"
            role="radio"
            :aria-checked="form.type === t.id"
            class="flex flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-3 text-center text-label-md font-bold transition-all"
            :class="form.type === t.id ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant active:bg-surface/60'"
            @click="setType(t.id)"
          >
            <span>{{ t.label }}</span>
            <span class="text-label-sm font-normal" :class="form.type === t.id ? 'opacity-90' : 'opacity-70'">{{ t.hint }}</span>
          </button>
        </div>
      </section>

      <!-- 3) البند -->
      <section class="flex flex-col gap-space-xs rounded-xl bg-surface-container-lowest p-space-md shadow-sm" :data-error="errors.expense_category_id ? '' : undefined">
        <span class="flex items-center justify-between text-label-lg text-on-surface">
          <span class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px] text-primary">category</span>
            بند المصروف
          </span>
          <span class="text-label-sm font-semibold" :class="errors.expense_category_id ? 'text-error' : 'text-primary'">مطلوب تحديد بند واحد</span>
        </span>
        <div class="mt-1 grid grid-cols-2 gap-2" role="radiogroup">
          <button
            v-for="c in categories"
            :key="c.id"
            type="button"
            role="radio"
            :aria-checked="form.expense_category_id === c.id"
            class="flex min-h-[56px] items-center gap-2 rounded-xl p-3 text-right transition-all active:scale-[.98]"
            :class="form.expense_category_id === c.id ? 'bg-secondary-container text-on-secondary-fixed-variant shadow-sm' : 'bg-surface-container-low text-on-surface'"
            @click="form.expense_category_id = c.id; errors.expense_category_id = null"
          >
            <span
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
              :class="form.expense_category_id === c.id ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-secondary'"
            >
              <span class="material-symbols-outlined text-[18px]">{{ c.icon || 'label' }}</span>
            </span>
            <span class="text-label-md leading-tight" :class="form.expense_category_id === c.id ? 'font-bold' : 'font-semibold'">{{ c.name }}</span>
          </button>
        </div>
      </section>

      <!-- 4) البيان -->
      <section class="flex flex-col gap-space-xs rounded-xl bg-surface-container-lowest p-space-md shadow-sm" :data-error="errors.description ? '' : undefined">
        <label for="req-desc" class="flex items-center gap-2 text-label-lg text-on-surface">
          <span class="material-symbols-outlined text-[20px] text-primary">description</span>
          البيان وتفاصيل الشراء
        </label>
        <textarea
          id="req-desc"
          v-model="form.description"
          rows="3"
          :maxlength="DESC_MAX"
          class="w-full resize-none rounded-lg border-0 bg-surface-container-low p-3 text-body-md text-on-surface placeholder:text-outline focus:ring-2 focus:ring-primary-container"
          :class="{ 'ring-2 ring-error': errors.description }"
          placeholder="مثلاً: صابون سائل ومعطرات أرضيات ومساحيق غسيل للميضأة"
        ></textarea>
        <div class="flex justify-between px-1 text-label-sm text-outline">
          <span :class="{ 'text-error': errors.description }">{{ errors.description || 'اذكر المحل أو التاجر لو أمكن' }}</span>
          <span class="tabular">{{ num(form.description.length) }} حرف</span>
        </div>
      </section>

      <!-- 5) المستند -->
      <section class="rounded-xl bg-surface-container-lowest p-space-md shadow-sm">
        <FileInput v-model="form.attachment" label="إرفاق المستند المؤيد (الفاتورة أو عرض السعر)" camera />
      </section>

      <!-- تنبيه -->
      <div class="flex items-start gap-3 rounded-xl bg-surface-container-high p-space-sm">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-tertiary-fixed text-tertiary">
          <span class="material-symbols-outlined icon-fill text-[18px]">shield</span>
        </span>
        <div>
          <p class="text-label-md font-bold text-tertiary">تنبيه شرعي وإداري للحفاظ على الوقف</p>
          <p class="text-body-sm leading-relaxed text-on-surface-variant">
            لن يتم خصم المبلغ من العهدة إلا بعد مراجعة وموافقة إمام المسجد، وبعدها تصرف وترفع الفاتورة.
            <template v-if="auth.isImam"> (طلبك كإمام بيتسجل حسب إعداد "طلب الإمام نفسه".)</template>
          </p>
        </div>
      </div>

      <!-- الإرسال: ثابت تحت على الموبايل -->
      <div class="pb-safe fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 px-space-sm pt-3 backdrop-blur-xl md:static md:border-0 md:bg-transparent md:p-0">
        <button type="submit" class="btn btn-primary btn-lg mb-3 w-full justify-between rounded-xl px-5 text-headline-sm md:mb-0" :disabled="saving">
          <span>{{ editing ? 'حفظ وإرسال للإمام تاني' : 'إرسال الطلب للإمام للاعتماد' }}</span>
          <span class="material-symbols-outlined" :class="{ 'animate-spin': saving }">{{ saving ? 'progress_activity' : 'arrow_back' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>
