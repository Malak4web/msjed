<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})

// الشاشات الداخلية على الموبايل: العنوان ظاهر في الهيدر فوق، فمش هنكرره
const route = useRoute()
const inner = computed(() => !route.meta.tab)
</script>

<template>
  <div class="mb-4 flex flex-wrap items-center justify-between gap-3 md:mb-5">
    <div class="min-w-0" :class="{ 'max-md:hidden': inner && !subtitle }">
      <div class="flex items-center gap-2" :class="{ 'max-md:hidden': inner }">
        <div class="h-6 w-2.5 rounded-full bg-primary-container"></div>
        <h1 class="text-headline-lg-mobile text-on-surface md:text-headline-lg">{{ title }}</h1>
        <slot name="badge" />
      </div>
      <p v-if="subtitle" class="mt-1 text-body-sm text-on-surface-variant md:pr-4 md:text-body-md" :class="{ 'pr-4': !inner }">{{ subtitle }}</p>
    </div>
    <!-- على الموبايل: الأزرار كبسولات في صف بيتسحب أفقي -->
    <div
      v-if="$slots.actions"
      class="no-print scrollbar-none flex items-center gap-2 max-md:-mx-space-sm max-md:w-[calc(100%+1.5rem)] max-md:overflow-x-auto max-md:px-space-sm max-md:[&_.btn]:min-h-[42px] max-md:[&_.btn]:shrink-0 max-md:[&_.btn]:rounded-full max-md:[&_.btn]:px-4 max-md:[&_.btn]:text-label-md md:flex-wrap"
    >
      <slot name="actions" />
    </div>
  </div>
</template>
