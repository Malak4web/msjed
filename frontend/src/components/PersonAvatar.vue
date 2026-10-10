<script setup>
import { computed } from 'vue'
import { initials } from '@/utils/format'

const props = defineProps({
  name: { type: String, default: '' },
  id: { type: Number, default: 0 },
  size: { type: String, default: 'md' },
})

// نفس ألوان الأفاتار في الديزاين، بتتوزع حسب الشخص
const palettes = [
  'bg-secondary-container text-on-secondary-fixed-variant',
  'bg-primary-container text-on-primary',
  'bg-tertiary-container text-on-surface',
  'bg-primary-fixed text-on-primary-fixed-variant',
]
const palette = computed(() => palettes[props.id % palettes.length])
const sizeClass = computed(() => ({ sm: 'h-9 w-9 text-label-md', md: 'h-11 w-11 text-headline-sm', lg: 'h-14 w-14 text-headline-md' })[props.size])
</script>

<template>
  <div class="flex shrink-0 items-center justify-center rounded-xl font-bold shadow-sm" :class="[palette, sizeClass]" aria-hidden="true">
    {{ initials(name) }}
  </div>
</template>
