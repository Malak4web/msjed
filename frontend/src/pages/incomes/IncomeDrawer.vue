<script setup>
import { reactive, ref, computed, watch } from 'vue'
import api, { errorMessage, fieldErrors, openPdf } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import AppDrawer from '@/components/AppDrawer.vue'
import TileSelect from '@/components/TileSelect.vue'
import AmountInput from '@/components/AmountInput.vue'
import FileInput from '@/components/FileInput.vue'
import { today, money } from '@/utils/format'

const open = defineModel({ type: Boolean, default: false })

const auth = useAuthStore()
const meta = useMetaStore()
const ui = useUiStore()

const blank = () => ({
  income_source_id: null,
  amount: '',
  payment_method_id: meta.payment_methods[0]?.id ?? null,
  custodian_id: auth.user?.custodian?.id ?? null,
  date: today(),
  payer_name: '',
  notes: '',
  attachment: null,
})

const form = reactive(blank())
const errors = ref({})
const saving = ref(false)

watch(open, (v) => {
  if (v) {
    Object.assign(form, blank())
    errors.value = {}
  }
})

const custodianOptions = computed(() => meta.custodians.map((c) => ({ id: c.id, name: c.name, icon: 'person', hint: c.title })))

function validate() {
  const e = {}
  if (!form.income_source_id) e.income_source_id = 'اختار مصدر الفلوس.'
  if (!(Number(form.amount) > 0)) e.amount = 'اكتب المبلغ.'
  if (!form.payment_method_id) e.payment_method_id = 'اختار طريقة الدفع.'
  if (!form.custodian_id) e.custodian_id = 'اختار الفلوس دخلت مع مين.'
  errors.value = e
  return !Object.keys(e).length
}

async function save(print = false) {
  if (!validate()) return
  // نفتح شباك الطباعة جوه الضغطة عشان المتصفح ميمنعوش
  const printWindow = print ? window.open('', '_blank') : null
  saving.value = true
  try {
    const body = new FormData()
    Object.entries(form).forEach(([k, v]) => {
      if (v !== null && v !== '') body.append(k, v)
    })
    const { data } = await api.post('/incomes', body)
    ui.success(data.message)
    ui.changed()
    meta.load(true)
    open.value = false
    if (print) await openPdf(`/incomes/${data.data.id}/receipt`, { target: printWindow })
  } catch (e) {
    printWindow?.close()
    errors.value = fieldErrors(e)
    ui.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppDrawer v-model="open" title="تسجيل إيراد جديد" subtitle="فلوس دخلت المسجد — هتتسجل في عهدة الشخص اللي استلمها">
    <form class="space-y-6" @submit.prevent="save(false)">
      <TileSelect v-model="form.income_source_id" label="الفلوس جاية منين؟" :options="meta.income_sources" :error="errors.income_source_id" />

      <AmountInput v-model="form.amount" :error="errors.amount" :presets="[100, 200, 500, 1000]" />

      <TileSelect v-model="form.payment_method_id" label="طريقة الدفع" :options="meta.payment_methods" columns="grid-cols-3 sm:grid-cols-5" :error="errors.payment_method_id" />

      <TileSelect v-model="form.custodian_id" label="الفلوس دخلت في عهدة مين؟" :options="custodianOptions" columns="grid-cols-2" :error="errors.custodian_id" />

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="field-label" for="income-date">التاريخ</label>
          <input id="income-date" v-model="form.date" type="date" :max="today()" class="field" :class="{ 'field-error': errors.date }" />
          <p v-if="errors.date" class="error-text">{{ errors.date }}</p>
        </div>
        <div>
          <label class="field-label" for="payer">اسم الدافع <span class="text-body-sm font-normal text-on-surface-variant">(اختياري)</span></label>
          <input id="payer" v-model="form.payer_name" type="text" class="field" placeholder="مثلاً: فاعل خير" />
        </div>
      </div>

      <div>
        <label class="field-label" for="income-notes">ملاحظات <span class="text-body-sm font-normal text-on-surface-variant">(اختياري)</span></label>
        <textarea id="income-notes" v-model="form.notes" rows="2" class="field py-3" placeholder="أي تفاصيل زيادة..."></textarea>
      </div>

      <FileInput v-model="form.attachment" label="صورة الإيصال أو التحويل (اختياري)" camera :error="errors.attachment" />
    </form>

    <template #footer>
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <button class="btn btn-primary btn-lg" :disabled="saving" @click="save(false)">
          <span class="material-symbols-outlined">{{ saving ? 'progress_activity' : 'check' }}</span>
          حفظ <span v-if="Number(form.amount) > 0" class="font-normal">{{ money(form.amount) }}</span>
        </button>
        <button class="btn btn-outline btn-lg" :disabled="saving" @click="save(true)">
          <span class="material-symbols-outlined">print</span>
          حفظ وطباعة إيصال
        </button>
      </div>
    </template>
  </AppDrawer>
</template>
