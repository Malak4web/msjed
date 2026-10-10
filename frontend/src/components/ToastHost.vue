<script setup>
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4" aria-live="polite">
    <TransitionGroup
      enter-from-class="opacity-0 -translate-y-3"
      enter-active-class="transition duration-200"
      leave-to-class="opacity-0"
      leave-active-class="transition duration-200"
    >
      <div
        v-for="t in ui.toasts"
        :key="t.id"
        role="status"
        class="pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border p-4 shadow-modal"
        :class="t.type === 'error' ? 'border-outflow-border bg-outflow-bg text-outflow-text' : 'border-inflow-border bg-inflow-bg text-inflow-text'"
        @click="ui.dismiss(t.id)"
      >
        <span class="material-symbols-outlined icon-fill text-[24px]">{{ t.type === 'error' ? 'error' : 'check_circle' }}</span>
        <p class="flex-1 text-body-md font-bold leading-relaxed">{{ t.message }}</p>
      </div>
    </TransitionGroup>
  </div>
</template>
