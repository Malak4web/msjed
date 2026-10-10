<script setup>
import { computed } from 'vue'
import { num } from '@/utils/format'

/**
 * مبلغ كبير وواضح + "ج.م". tone: plain | in | out | primary
 * size: sm | md | lg | kpi
 */
const props = defineProps({
  value: { type: [Number, String], default: 0 },
  tone: { type: String, default: 'plain' },
  size: { type: String, default: 'md' },
  sign: { type: Boolean, default: false },
})

const toneClass = computed(
  () =>
    ({
      plain: 'text-on-surface',
      in: 'text-inflow-text',
      out: 'text-error',
      primary: 'text-primary',
      gold: 'text-tertiary',
    })[props.tone],
)

const sizes = {
  sm: ['text-label-lg', 'text-label-sm'],
  md: ['text-headline-md font-bold', 'text-label-sm'],
  lg: ['text-headline-lg', 'text-label-md'],
  kpi: ['text-kpi-number tracking-tight', 'text-label-lg'],
}

const text = computed(() => {
  const n = Number(props.value || 0)
  const prefix = props.sign ? (n > 0 ? '+' : n < 0 ? '−' : '') : n < 0 ? '−' : ''
  return prefix + num(Math.abs(n))
})
</script>

<template>
  <span class="tabular inline-flex items-baseline gap-1 whitespace-nowrap" :class="toneClass">
    <span :class="[sizes[size][0], size === 'kpi' ? 'font-extrabold' : 'font-bold']">{{ text }}</span>
    <span class="font-bold" :class="sizes[size][1]">ج.م</span>
  </span>
</template>
