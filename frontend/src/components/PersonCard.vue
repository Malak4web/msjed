<script setup>
import PersonAvatar from './PersonAvatar.vue'
import MoneyAmount from './MoneyAmount.vue'
import { money } from '@/utils/format'

/** كارت "الفلوس مع مين؟": الرصيد + استلم/صرف الشهر ده + كشف الحساب */
defineProps({
  person: { type: Object, required: true },
  compact: { type: Boolean, default: false },
})
defineEmits(['statement'])
</script>

<template>
  <article class="card flex flex-col gap-3 border border-line p-4">
    <header class="flex items-center gap-3">
      <PersonAvatar :name="person.name" :id="person.id" />
      <div class="min-w-0 flex-1">
        <h3 class="truncate text-label-lg font-bold text-on-surface">{{ person.name }}</h3>
        <p class="truncate text-body-sm text-on-surface-variant">
          {{ person.title || '—' }}
          <span v-if="person.has_login === false" class="mr-1 rounded-full bg-surface-container px-2 py-0.5 text-label-sm">بدون حساب دخول</span>
        </p>
      </div>
    </header>

    <div class="rounded-xl bg-surface-container-low p-3">
      <p class="text-label-md text-on-surface-variant">الرصيد اللي معاه</p>
      <MoneyAmount :value="person.balance" size="lg" :tone="Number(person.balance) < 0 ? 'out' : 'primary'" />
      <p v-if="Number(person.reserved) > 0" class="mt-1 flex flex-wrap items-center gap-x-1 text-body-sm text-pending-text">
        <span class="material-symbols-outlined text-[16px]">lock_clock</span>
        <span>محجوز لطلبات معتمدة:</span>
        <b class="tabular">{{ money(person.reserved) }}</b>
      </p>
    </div>

    <div v-if="!compact && person.month_in !== undefined" class="grid grid-cols-2 gap-2 text-center">
      <div class="rounded-lg border border-inflow-border/60 bg-inflow-bg/50 p-2">
        <p class="text-label-sm text-inflow-text">استلم الشهر ده</p>
        <MoneyAmount :value="person.month_in" size="sm" tone="in" />
      </div>
      <div class="rounded-lg border border-outflow-border/60 bg-outflow-bg/50 p-2">
        <p class="text-label-sm text-outflow-text">صرف الشهر ده</p>
        <MoneyAmount :value="person.month_out" size="sm" tone="out" />
      </div>
    </div>

    <button class="btn btn-outline w-full" @click="$emit('statement', person)">
      <span class="material-symbols-outlined">receipt_long</span>
      كشف الحساب
    </button>
  </article>
</template>
