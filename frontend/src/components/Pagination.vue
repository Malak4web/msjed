<script setup>
import { num } from '@/utils/format'

defineProps({ meta: { type: Object, default: null } })
defineEmits(['page'])
</script>

<template>
  <div v-if="meta && meta.last_page > 1" class="no-print flex items-center justify-between gap-3 border-t border-line px-4 py-3">
    <p class="text-body-sm text-on-surface-variant">صفحة {{ num(meta.current_page) }} من {{ num(meta.last_page) }} — {{ num(meta.total) }} عملية</p>
    <div class="flex gap-2">
      <button class="btn btn-ghost min-h-[44px] bg-surface-container px-3" :disabled="meta.current_page <= 1" @click="$emit('page', meta.current_page - 1)">
        <span class="material-symbols-outlined">chevron_right</span>
        السابق
      </button>
      <button class="btn btn-ghost min-h-[44px] bg-surface-container px-3" :disabled="meta.current_page >= meta.last_page" @click="$emit('page', meta.current_page + 1)">
        التالي
        <span class="material-symbols-outlined">chevron_left</span>
      </button>
    </div>
  </div>
</template>
