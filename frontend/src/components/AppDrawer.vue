<script setup>
import { watch, onBeforeUnmount } from 'vue'

/** درج جانبي (من الشمال في RTL) — على الموبايل بيبقى ملء الشاشة */
const open = defineModel({ type: Boolean, default: false })
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  width: { type: String, default: 'sm:max-w-xl' },
})

function onKey(e) {
  if (e.key === 'Escape') open.value = false
}
watch(open, (v) => {
  document.body.style.overflow = v ? 'hidden' : ''
  v ? window.addEventListener('keydown', onKey) : window.removeEventListener('keydown', onKey)
})
onBeforeUnmount(() => {
  document.body.style.overflow = ''
  window.removeEventListener('keydown', onKey)
})
</script>

<template>
  <Teleport to="body">
    <Transition enter-from-class="opacity-0" enter-active-class="transition duration-200" leave-to-class="opacity-0" leave-active-class="transition duration-200">
      <div v-if="open" class="fixed inset-0 z-[70] bg-forest/40 backdrop-blur-sm" @click="open = false"></div>
    </Transition>
    <!-- موبايل: Bottom sheet طالع من تحت — تابلت وأكبر: درج جانبي -->
    <Transition
      enter-from-class="max-sm:translate-y-full sm:-translate-x-full"
      enter-active-class="transition duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
      leave-to-class="max-sm:translate-y-full sm:-translate-x-full"
      leave-active-class="transition duration-200 ease-in"
    >
      <aside
        v-if="open"
        class="fixed z-[71] flex w-full flex-col overflow-hidden bg-surface shadow-modal max-sm:inset-x-0 max-sm:bottom-0 max-sm:max-h-[94dvh] max-sm:rounded-t-[1.75rem] sm:inset-y-0 sm:left-0 sm:rounded-r-xl"
        :class="width"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
      >
        <div class="flex justify-center bg-surface-container-lowest pt-2.5 sm:hidden" aria-hidden="true">
          <span class="h-1.5 w-11 rounded-full bg-outline-variant"></span>
        </div>
        <header class="flex items-center gap-3 border-b border-line bg-surface-container-lowest px-4 pb-3 pt-1.5 sm:px-6 sm:pt-[max(0.75rem,env(safe-area-inset-top))]">
          <div class="flex-1">
            <h2 class="text-headline-md font-bold text-on-surface">{{ title }}</h2>
            <p v-if="subtitle" class="text-body-sm text-on-surface-variant">{{ subtitle }}</p>
          </div>
          <button class="flex h-12 w-12 items-center justify-center rounded-full hover:bg-surface-container" aria-label="إغلاق" @click="open = false">
            <span class="material-symbols-outlined text-[26px]">close</span>
          </button>
        </header>
        <div class="flex-1 overflow-y-auto px-4 py-5 sm:px-6">
          <slot />
        </div>
        <footer v-if="$slots.footer" class="pb-safe border-t border-line bg-surface-container-lowest px-4 py-3 sm:px-6">
          <slot name="footer" />
        </footer>
      </aside>
    </Transition>
  </Teleport>
</template>
