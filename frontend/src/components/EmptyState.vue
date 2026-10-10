<script setup>
defineProps({
  icon: { type: String, default: 'inbox' },
  title: { type: String, required: true },
  text: { type: String, default: '' },
})
</script>

<template>
  <div class="flex flex-col items-center px-6 py-12 text-center">
    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-surface-container text-primary-container">
      <span class="material-symbols-outlined text-[34px]">{{ icon }}</span>
    </div>
    <h3 class="text-headline-sm font-bold text-on-surface">{{ title }}</h3>
    <p v-if="text" class="mt-1 max-w-sm text-body-md text-on-surface-variant">{{ text }}</p>
    <div v-if="$slots.default" class="mt-5"><slot /></div>
  </div>
</template>
