<script setup>
import { computed } from 'vue'

/** شارة حالة بلون + أيقونة + نص (مش لون بس) */
const props = defineProps({
  status: { type: String, required: true },
  label: { type: String, default: '' },
})

const map = {
  pending: ['bg-pending-bg text-pending-text border-pending-border', 'hourglass_top', 'بانتظار الإمام'],
  approved: ['bg-secondary-container text-on-secondary-fixed-variant border-secondary-fixed-dim', 'verified', 'معتمد – بانتظار التنفيذ'],
  executed: ['bg-inflow-bg text-inflow-text border-inflow-border', 'task_alt', 'تم التنفيذ'],
  rejected: ['bg-outflow-bg text-outflow-text border-outflow-border', 'cancel', 'مرفوض'],
  returned: ['bg-tertiary-fixed text-on-tertiary-fixed-variant border-tertiary-fixed-dim', 'undo', 'مُرجَع للتعديل'],
  cancelled: ['bg-surface-container text-on-surface-variant border-outline-variant', 'block', 'مُلغى'],
  reversed: ['bg-outflow-bg text-outflow-text border-outflow-border', 'history', 'ملغى بقيد عكسي'],
  completed: ['bg-inflow-bg text-inflow-text border-inflow-border', 'task_alt', 'تم الاستلام'],
  transfer_pending: ['bg-pending-bg text-pending-text border-pending-border', 'schedule', 'بانتظار الاستلام'],
  due: ['bg-pending-bg text-pending-text border-pending-border', 'schedule', 'مستحق'],
  overdue: ['bg-outflow-bg text-outflow-text border-outflow-border', 'warning', 'متأخر'],
  paid: ['bg-inflow-bg text-inflow-text border-inflow-border', 'check_circle', 'مدفوع'],
  income: ['bg-inflow-bg text-inflow-text border-inflow-border', 'south_west', 'وارد'],
  expense: ['bg-outflow-bg text-outflow-text border-outflow-border', 'north_east', 'منصرف'],
  active: ['bg-inflow-bg text-inflow-text border-inflow-border', 'check_circle', 'نشط'],
  inactive: ['bg-surface-container text-on-surface-variant border-outline-variant', 'pause_circle', 'متوقف'],
}

const conf = computed(() => map[props.status] || map.cancelled)
</script>

<template>
  <span class="chip shrink-0 whitespace-nowrap" :class="conf[0]">
    <span class="material-symbols-outlined text-[16px]">{{ conf[1] }}</span>
    {{ label || conf[2] }}
  </span>
</template>
