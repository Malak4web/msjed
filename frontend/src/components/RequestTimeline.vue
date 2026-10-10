<script setup>
import { computed } from 'vue'
import { ago, money } from '@/utils/format'

/**
 * مسار الحوكمة: الأحداث اللي حصلت + الخطوة الجاية (زي الديزاين: ✓ / ⏳ / رقم).
 */
const props = defineProps({
  request: { type: Object, required: true },
})

const icons = {
  created: 'edit_note',
  approved: 'verified',
  auto_approved: 'bolt',
  rejected: 'cancel',
  returned: 'undo',
  resubmitted: 'redo',
  executed: 'task_alt',
  cancelled: 'block',
  reversed: 'history',
}
const tones = {
  rejected: 'bg-error text-on-error',
  cancelled: 'bg-outline text-white',
  reversed: 'bg-error text-on-error',
  returned: 'bg-tertiary-container text-on-surface',
}

const next = computed(() => {
  const s = props.request.status
  if (s === 'pending') return [{ label: 'قرار فضيلة الإمام واعتماد الصرف', hint: 'قيد المراجعة حالياً', current: true }, { label: 'الشراء ورفع الفاتورة النهائية', hint: 'بعد الموافقة' }]
  if (s === 'returned') return [{ label: 'تعديل الطلب من صاحبه', hint: 'وبعدين يرجع للإمام', current: true }]
  if (s === 'approved') return [{ label: 'الشراء ورفع الفاتورة النهائية', hint: 'المبلغ محجوز لحد التنفيذ', current: true }]
  return []
})
</script>

<template>
  <ol class="flex flex-col gap-3 pr-1">
    <li v-for="(e, i) in request.timeline" :key="i" class="flex items-start gap-2.5">
      <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full" :class="tones[e.action] || 'bg-primary text-on-primary'">
        <span class="material-symbols-outlined text-[16px]">{{ icons[e.action] || 'check' }}</span>
      </span>
      <div class="min-w-0">
        <p class="text-label-md font-bold text-on-surface">{{ e.label }}</p>
        <p class="text-body-sm text-on-surface-variant">
          <template v-if="e.by">بواسطة {{ e.by }} — </template>{{ ago(e.at) }}
        </p>
        <p v-if="e.note" class="mt-1 rounded-lg bg-surface-container-low px-3 py-1.5 text-body-sm text-on-surface">“{{ e.note }}”</p>
        <p v-if="e.meta?.actual_amount" class="mt-1 text-body-sm text-on-surface-variant">
          المبلغ الفعلي {{ money(e.meta.actual_amount) }} من المعتمد {{ money(e.meta.approved_amount) }}
        </p>
      </div>
    </li>
    <li v-for="(n, i) in next" :key="'n' + i" class="flex items-start gap-2.5" :class="{ 'opacity-60': !n.current }">
      <span
        class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12px] font-bold"
        :class="n.current ? 'animate-pulse bg-tertiary text-on-tertiary' : 'bg-surface-container-high text-on-surface-variant'"
      >
        <span v-if="n.current" class="material-symbols-outlined text-[16px]">hourglass_top</span>
        <template v-else>{{ request.timeline.length + i + 1 }}</template>
      </span>
      <div>
        <p class="text-label-md font-bold" :class="n.current ? 'text-tertiary' : 'text-on-surface'">{{ n.label }}</p>
        <p class="text-body-sm text-on-surface-variant">{{ n.hint }}</p>
      </div>
    </li>
  </ol>
</template>
