<script setup>
import PersonAvatar from './PersonAvatar.vue'
import MoneyAmount from './MoneyAmount.vue'
import StatusBadge from './StatusBadge.vue'
import { ago } from '@/utils/format'

/**
 * كارت طلب صرف بنفس شكل ديزاين "اعتماد الإمام": اسم وصفة، البند، المبلغ بالأحمر،
 * وزراير موافقة/رفض كبيرة لو المستخدم يقدر يقرر.
 */
defineProps({
  request: { type: Object, required: true },
  available: { type: [String, Number], default: null },
  active: { type: Boolean, default: false },
  busy: { type: String, default: null },
})
defineEmits(['open', 'approve', 'reject'])
</script>

<template>
  <article
    class="relative flex cursor-pointer flex-col overflow-hidden rounded-2xl bg-surface-container-lowest p-4 transition-all"
    :class="active ? 'shadow-md ring-2 ring-primary-container' : 'shadow-sm hover:shadow-md'"
    @click="$emit('open', request)"
  >
    <div v-if="request.status === 'pending'" class="absolute inset-x-0 top-0 h-1.5 bg-tertiary-container"></div>

    <div class="flex items-start justify-between gap-3 pt-1">
      <div class="flex min-w-0 items-center gap-3">
        <PersonAvatar :name="request.custodian.name" :id="request.custodian.id" />
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-label-lg font-bold text-on-surface">{{ request.custodian.name }}</span>
            <span v-if="request.custodian.title" class="rounded-full bg-surface-container px-2 py-0.5 text-label-sm font-bold text-on-surface-variant">{{ request.custodian.title }}</span>
          </div>
          <span class="text-body-sm text-on-surface-variant">
            {{ ago(request.created_at) }} • {{ request.category?.name }}
            <template v-if="request.requester && request.requester.name !== request.custodian.name"> • طلبه {{ request.requester.name }}</template>
          </span>
        </div>
      </div>
      <div class="flex shrink-0 flex-col items-end">
        <span class="text-label-sm text-on-surface-variant">المبلغ</span>
        <MoneyAmount :value="request.amount" size="md" tone="out" />
      </div>
    </div>

    <div class="mt-2.5 flex items-center gap-2 rounded-lg bg-surface-container-low p-2.5">
      <span class="material-symbols-outlined text-[18px] text-tertiary">{{ request.category?.icon || 'shopping_cart' }}</span>
      <span class="text-body-md font-bold text-on-surface">{{ request.description }}</span>
    </div>

    <p v-if="available !== null && request.status === 'pending'" class="mt-2 flex items-center gap-1 text-body-sm font-bold" :class="Number(available) >= Number(request.amount) ? 'text-on-secondary-fixed-variant' : 'text-error'">
      <span class="material-symbols-outlined text-[16px]">{{ Number(available) >= Number(request.amount) ? 'check_circle' : 'warning' }}</span>
      <template v-if="Number(available) >= Number(request.amount)">الرصيد المتاح مع {{ request.custodian.name }} يغطي المبلغ</template>
      <template v-else>الرصيد المتاح مش كفاية!</template>
    </p>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 pt-1">
      <span v-if="request.status === 'pending'" class="flex items-center gap-1 text-label-sm font-bold text-tertiary">
        <span class="h-2 w-2 rounded-full bg-tertiary"></span>
        بانتظار قرار الإمام
      </span>
      <StatusBadge v-else :status="request.is_reversed ? 'reversed' : request.status" />

      <div v-if="request.can?.decide" class="flex items-center gap-2" @click.stop>
        <button class="flex min-h-[44px] items-center gap-1 rounded-xl bg-primary px-4 text-label-md font-bold text-on-primary shadow-sm transition-transform active:scale-95" :disabled="!!busy" @click="$emit('approve', request)">
          <span class="material-symbols-outlined text-[18px]">check</span>
          موافقة
        </button>
        <button class="flex min-h-[44px] items-center gap-1 rounded-xl bg-surface-container-high px-4 text-label-md font-bold text-error transition-transform active:scale-95" :disabled="!!busy" @click="$emit('reject', request)">
          <span class="material-symbols-outlined text-[18px]">close</span>
          رفض
        </button>
      </div>
      <span v-else-if="request.attachments_count" class="flex items-center gap-1 text-body-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-[16px]">attach_file</span>{{ request.attachments_count }} مرفق
      </span>
    </div>
  </article>
</template>
