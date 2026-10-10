<script setup>
const open = defineModel({ type: Boolean, default: false })
defineProps({
  title: { type: String, default: '' },
  // overNav: الشريط السفلي فاضل ظاهر فوق الشيت (زي تصميم Stitch) فنسيب له مكان
  overNav: { type: Boolean, default: false },
})
</script>

<template>
  <Teleport to="body">
    <Transition enter-from-class="opacity-0" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-200">
      <div v-if="open" class="fixed inset-0 z-[80] bg-on-surface/60 backdrop-blur-sm" @click="open = false"></div>
    </Transition>
    <Transition
      enter-from-class="translate-y-full"
      enter-active-class="transition duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
      leave-to-class="translate-y-full"
      leave-active-class="transition duration-200 ease-in"
    >
      <div
        v-if="open"
        class="fixed inset-x-0 bottom-0 z-[81] mx-auto max-h-[88dvh] max-w-lg overflow-y-auto overscroll-contain rounded-t-[1.75rem] bg-surface-container-lowest shadow-modal"
        :class="overNav ? 'pb-[calc(5.5rem+env(safe-area-inset-bottom))]' : 'pb-safe'"
        role="dialog"
        aria-modal="true"
        :aria-label="title || 'قائمة'"
      >
        <div class="sticky top-0 z-10 flex justify-center bg-surface-container-lowest pb-1 pt-3"><span class="h-1.5 w-12 rounded-full bg-outline-variant"></span></div>
        <h2 v-if="title" class="px-5 pb-2 pt-2 text-headline-sm font-bold">{{ title }}</h2>
        <div class="px-4 pb-6">
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
