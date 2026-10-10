<script setup>
import { reactive, ref, watch, computed } from 'vue'
import api, { errorMessage, fieldErrors } from '@/api/client'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import AppDrawer from '@/components/AppDrawer.vue'
import AmountInput from '@/components/AmountInput.vue'
import FileInput from '@/components/FileInput.vue'
import MoneyAmount from '@/components/MoneyAmount.vue'
import { today, money } from '@/utils/format'

/** تأكيد التنفيذ: المبلغ الفعلي + تاريخ + صورة الفاتورة (كاميرا كبيرة) */
const open = defineModel({ type: Boolean, default: false })
const props = defineProps({ request: { type: Object, default: null } })
const emit = defineEmits(['done'])

const meta = useMetaStore()
const ui = useUiStore()
const form = reactive({ actual_amount: '', executed_date: today(), invoice: null })
const errors = ref({})
const saving = ref(false)

watch(open, (v) => {
  if (v && props.request) {
    Object.assign(form, { actual_amount: String(Number(props.request.amount)), executed_date: today(), invoice: null })
    errors.value = {}
  }
})

const over = computed(() => props.request && Number(form.actual_amount) > Number(props.request.amount))
const invoiceRequired = computed(() => meta.settings.require_invoice_on_execute)

async function save() {
  errors.value = {}
  if (!(Number(form.actual_amount) > 0)) errors.value.actual_amount = 'اكتب المبلغ اللي اتصرف فعلًا.'
  if (over.value) errors.value.actual_amount = `المبلغ أكبر من المعتمد (${money(props.request.amount)}). اعمل طلب جديد بالفرق.`
  if (invoiceRequired.value && !form.invoice) errors.value.invoice = 'صوّر الفاتورة أو ارفعها عشان تأكد التنفيذ.'
  if (Object.keys(errors.value).length) return

  saving.value = true
  try {
    const body = new FormData()
    body.append('actual_amount', form.actual_amount)
    body.append('executed_date', form.executed_date)
    if (form.invoice) body.append('invoice', form.invoice)
    const { data } = await api.post(`/expense-requests/${props.request.id}/execute`, body)
    ui.success(data.message)
    ui.changed()
    meta.load(true)
    open.value = false
    emit('done', data.data)
  } catch (e) {
    errors.value = fieldErrors(e)
    ui.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppDrawer v-model="open" title="تأكيد التنفيذ ورفع الفاتورة" :subtitle="request ? `${request.number} — ${request.description}` : ''">
    <div v-if="request" class="space-y-6">
      <div class="flex items-center justify-between rounded-xl bg-secondary-container/40 p-4">
        <span class="text-label-lg text-on-secondary-fixed-variant">المبلغ المعتمد</span>
        <MoneyAmount :value="request.amount" size="lg" tone="primary" />
      </div>

      <FileInput v-model="form.invoice" :label="invoiceRequired ? 'صورة الفاتورة (مطلوبة)' : 'صورة الفاتورة'" camera :error="errors.invoice" />

      <AmountInput v-model="form.actual_amount" label="المبلغ اللي اتصرف فعلًا" :error="errors.actual_amount" hint="لو صرفت أقل من المعتمد اكتب الرقم الحقيقي، والباقي يفضل في العهدة." />
      <p v-if="over" class="-mt-4 flex items-start gap-2 rounded-lg border border-outflow-border bg-outflow-bg p-3 text-body-sm font-bold text-outflow-text">
        <span class="material-symbols-outlined text-[20px]">warning</span>
        مينفعش تصرف أكتر من المعتمد. الفرق محتاج طلب صرف جديد وموافقة الإمام.
      </p>

      <div>
        <label class="field-label" for="exec-date">تاريخ الصرف</label>
        <input id="exec-date" v-model="form.executed_date" type="date" :max="today()" class="field" :class="{ 'field-error': errors.executed_date }" />
        <p v-if="errors.executed_date" class="error-text">{{ errors.executed_date }}</p>
      </div>
    </div>
    <template #footer>
      <button class="btn btn-primary btn-lg w-full" :disabled="saving" @click="save">
        <span class="material-symbols-outlined">task_alt</span>
        تأكيد التنفيذ وخصم {{ Number(form.actual_amount) > 0 ? money(form.actual_amount) : '' }}
      </button>
    </template>
  </AppDrawer>
</template>
