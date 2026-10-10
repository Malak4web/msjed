<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api, { errorMessage, fileUrl } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { useRequestActions } from '@/composables/useRequestActions'
import PersonAvatar from '@/components/PersonAvatar.vue'
import MoneyAmount from '@/components/MoneyAmount.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import RequestTimeline from '@/components/RequestTimeline.vue'
import ExecuteDrawer from './ExecuteDrawer.vue'
import { date, time, ago, money } from '@/utils/format'

/** تفاصيل طلب الصرف بنفس الكارت الموسّع في ديزاين اعتماد الإمام */
const props = defineProps({ id: { type: Number, required: true } })
const emit = defineEmits(['changed'])

const ui = useUiStore()
const router = useRouter()
const req = ref(null)
const loading = ref(true)
const executeOpen = ref(false)

const { busy, approve, reject, returnForEdit, cancel, reverse } = useRequestActions((updated) => {
  req.value = updated
  emit('changed', updated)
})

async function load() {
  loading.value = true
  try {
    req.value = (await api.get(`/expense-requests/${props.id}`)).data.data
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}
watch(() => props.id, load, { immediate: true })

const covers = () => Number(req.value.custodian.available) >= Number(req.value.amount)
</script>

<template>
  <div v-if="loading && !req" class="space-y-3 p-4">
    <div class="skeleton h-14 w-2/3"></div>
    <div class="skeleton h-24"></div>
    <div class="skeleton h-32"></div>
  </div>

  <div v-else-if="req" class="flex flex-col">
    <!-- صاحب الطلب -->
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-3">
        <PersonAvatar :name="req.custodian.name" :id="req.custodian.id" size="lg" />
        <div>
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-label-lg font-bold">{{ req.custodian.name }}</span>
            <span v-if="req.custodian.title" class="rounded-full bg-primary-fixed/40 px-2 py-0.5 text-label-sm font-bold text-primary">{{ req.custodian.title }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-2 text-body-sm text-on-surface-variant">
            <span dir="ltr" class="font-bold text-tertiary">{{ req.number }}</span>
            <span>•</span>
            <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[14px]">history</span>{{ ago(req.created_at) }}</span>
          </div>
        </div>
      </div>
      <StatusBadge :status="req.is_reversed ? 'reversed' : req.status" />
    </div>

    <!-- البند -->
    <div class="mt-3.5 flex flex-col gap-1 rounded-xl bg-surface-container-low p-3">
      <div class="flex items-center gap-1.5 text-label-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-[16px]">{{ req.category.icon || 'shopping_cart' }}</span>
        <span>بند المصروف: {{ req.category.name }} · {{ req.type === 'fixed' ? 'مصروف ثابت' : 'غير ثابت' }}</span>
      </div>
      <p class="text-body-lg font-bold text-on-surface">{{ req.description }}</p>
      <div class="flex items-center gap-1 text-body-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-[14px]">schedule</span>
        <span>طلبه {{ req.requester.name }} — {{ date(req.created_at) }} {{ time(req.created_at) }}</span>
      </div>
    </div>

    <!-- المبلغ وحالة العهدة -->
    <div class="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
      <div class="flex flex-col items-center justify-center rounded-xl bg-error-container/40 p-3.5 text-center">
        <span class="text-label-sm font-semibold text-on-error-container">{{ req.status === 'executed' ? 'المبلغ اللي اتصرف' : 'المبلغ المطلوب خصمه من العهدة' }}</span>
        <MoneyAmount :value="req.status === 'executed' ? req.actual_amount : req.amount" size="kpi" tone="out" />
        <span v-if="req.status === 'executed' && req.actual_amount !== req.amount" class="text-body-sm text-on-surface-variant">من المعتمد {{ money(req.amount) }}</span>
      </div>
      <div class="flex flex-col justify-center rounded-xl bg-secondary-container/40 p-3.5">
        <div class="flex items-center gap-1 text-label-sm font-bold text-on-secondary-fixed-variant">
          <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
          حالة عهدة {{ req.custodian.name }}:
        </div>
        <div class="mt-1">
          <MoneyAmount :value="req.custodian.available" size="md" tone="primary" />
          <span class="mr-1 text-label-sm font-bold text-primary">رصيد متاح</span>
        </div>
        <div v-if="req.status === 'pending'" class="mt-1 flex items-center gap-1 text-body-sm font-bold" :class="covers() ? 'text-on-secondary-fixed-variant' : 'text-error'">
          <span class="material-symbols-outlined text-[16px]" :class="covers() ? 'text-primary' : ''">{{ covers() ? 'check_circle' : 'warning' }}</span>
          {{ covers() ? 'يغطي المبلغ بالكامل' : 'الرصيد المتاح مش كفاية' }}
        </div>
        <div v-else-if="Number(req.custodian.reserved) > 0" class="mt-1 text-body-sm text-pending-text">محجوز: {{ money(req.custodian.reserved) }}</div>
      </div>
    </div>

    <!-- أسباب -->
    <div v-if="req.auto_approved" class="mt-3 flex items-center gap-2 rounded-xl border border-tertiary-fixed-dim bg-tertiary-fixed/40 p-3 text-body-md text-on-tertiary-fixed-variant">
      <span class="material-symbols-outlined icon-fill">bolt</span>
      <span><b>اتوافق تلقائيًا:</b> {{ req.auto_approval_reason }} — ومتسجّل في السجل.</span>
    </div>
    <div v-if="req.status === 'rejected'" class="mt-3 rounded-xl border border-outflow-border bg-outflow-bg p-3 text-outflow-text">
      <b>سبب الرفض:</b> {{ req.rejection_reason }}
    </div>
    <div v-if="req.status === 'returned'" class="mt-3 rounded-xl border border-tertiary-fixed-dim bg-tertiary-fixed/40 p-3 text-on-tertiary-fixed-variant">
      <b>مطلوب تعديل:</b> {{ req.return_reason }}
    </div>
    <div v-if="req.status === 'cancelled'" class="mt-3 rounded-xl bg-surface-container p-3"><b>سبب الإلغاء:</b> {{ req.cancel_reason }}</div>
    <div v-if="req.is_reversed" class="mt-3 rounded-xl border border-outflow-border bg-outflow-bg p-3 text-outflow-text"><b>ملغى بقيد عكسي:</b> {{ req.reversal_reason }}</div>

    <!-- المرفقات -->
    <div v-if="req.attachments.length" class="mt-3.5 flex flex-col gap-2 rounded-xl bg-surface-container p-3">
      <span class="flex items-center gap-1.5 text-label-md font-bold">
        <span class="material-symbols-outlined text-[18px] text-primary">receipt_long</span>
        المستندات والمرفقات
      </span>
      <a
        v-for="a in req.attachments"
        :key="a.id"
        :href="fileUrl(a.url)"
        target="_blank"
        rel="noopener"
        class="flex items-center gap-3 rounded-lg bg-surface-container-lowest p-2"
      >
        <img v-if="a.is_image" :src="fileUrl(a.url)" alt="" class="h-14 w-14 shrink-0 rounded-lg object-cover" />
        <span v-else class="material-symbols-outlined flex h-14 w-14 items-center justify-center rounded-lg bg-surface-container text-[28px]">description</span>
        <span class="min-w-0 flex-1">
          <span class="block truncate text-label-md font-bold">{{ a.kind === 'invoice' ? 'الفاتورة النهائية' : 'عرض سعر / مستند' }}</span>
          <span class="block truncate text-body-sm text-on-surface-variant">{{ a.name }}</span>
        </span>
        <span class="flex shrink-0 items-center gap-1 rounded-lg bg-secondary-container px-3 py-2 text-label-sm font-bold text-on-secondary-fixed-variant">
          <span class="material-symbols-outlined text-[16px]">zoom_in</span>معاينة
        </span>
      </a>
    </div>

    <!-- مسار الحوكمة -->
    <div class="mt-4 flex flex-col gap-2 pt-3">
      <span class="flex items-center gap-1 text-label-md font-bold">
        <span class="material-symbols-outlined text-[18px] text-primary">timeline</span>
        مسار الحوكمة وتدقيق الصرف:
      </span>
      <RequestTimeline :request="req" />
    </div>

    <!-- الإجراءات -->
    <div class="no-print mt-5 flex flex-col gap-2.5 pt-3">
      <template v-if="req.can.decide">
        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
          <button class="flex h-14 items-center justify-center gap-2 rounded-xl bg-primary text-label-lg font-bold text-on-primary shadow-md transition-all hover:bg-primary-container active:scale-98" :disabled="!!busy" @click="approve(req)">
            <span class="material-symbols-outlined text-[24px]">verified</span>
            ✓ موافقة واعتماد الصرف
          </button>
          <button class="flex h-14 items-center justify-center gap-2 rounded-xl bg-error text-label-lg font-bold text-on-error shadow-md transition-all hover:opacity-90 active:scale-98" :disabled="!!busy" @click="reject(req)">
            <span class="material-symbols-outlined text-[24px]">cancel</span>
            ✕ رفض الطلب
          </button>
        </div>
        <button class="flex items-center justify-center gap-1.5 py-2.5 text-label-md text-on-surface-variant transition-colors hover:text-primary" :disabled="!!busy" @click="returnForEdit(req)">
          <span class="material-symbols-outlined text-[18px]">chat_bubble_outline</span>
          طلب تعديل أو استيفاء بيان من مقدم الطلب
        </button>
      </template>

      <div v-else-if="req.status === 'pending'" class="flex items-center gap-2 rounded-xl bg-pending-bg p-3 text-body-md text-pending-text">
        <span class="material-symbols-outlined">hourglass_top</span>
        الطلب مستني قرار الإمام. هيوصل إشعار لصاحب الطلب أول ما يتاخد قرار.
      </div>

      <button v-if="req.can.execute" class="btn btn-primary btn-lg w-full" @click="executeOpen = true">
        <span class="material-symbols-outlined">photo_camera</span>
        تأكيد التنفيذ ورفع الفاتورة
      </button>
      <button v-if="req.can.update && req.status === 'returned'" class="btn btn-primary btn-lg w-full" @click="router.push(`/requests/new?edit=${req.id}`)">
        <span class="material-symbols-outlined">edit</span>
        تعديل الطلب وإرساله تاني
      </button>
      <div class="flex flex-wrap justify-center gap-2">
        <button v-if="req.can.update && req.status === 'pending'" class="btn btn-ghost min-h-[44px]" @click="router.push(`/requests/new?edit=${req.id}`)">
          <span class="material-symbols-outlined">edit</span>تعديل
        </button>
        <button v-if="req.can.cancel" class="btn btn-ghost min-h-[44px] text-error" @click="cancel(req)">
          <span class="material-symbols-outlined">block</span>إلغاء الطلب
        </button>
        <button v-if="req.can.reverse" class="btn btn-ghost min-h-[44px] text-error" @click="reverse(req)">
          <span class="material-symbols-outlined">undo</span>إلغاء بقيد عكسي
        </button>
      </div>
    </div>

    <ExecuteDrawer v-model="executeOpen" :request="req" @done="(r) => { req = r; emit('changed', r) }" />
  </div>
</template>
