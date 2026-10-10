<script setup>
import { ref } from 'vue'
import { monthStart, monthEnd, today } from '@/utils/format'

/** اختيار فترة: أزرار سريعة + من/إلى. v-model = { from, to } */
const model = defineModel({ type: Object, required: true })
defineProps({ allowAll: { type: Boolean, default: true } })

const yearStart = () => `${new Date().getFullYear()}-01-01`
const presets = [
  { label: 'الشهر ده', from: () => monthStart(), to: () => today() },
  { label: 'الشهر اللي فات', from: () => monthStart(-1), to: () => monthEnd(-1) },
  { label: 'آخر 3 شهور', from: () => monthStart(-2), to: () => today() },
  { label: 'آخر 6 شهور', from: () => monthStart(-5), to: () => today() },
  { label: 'السنة دي', from: yearStart, to: () => today() },
  { label: 'كل الفترات', from: () => '', to: () => today(), all: true },
]
const active = ref(presets.findIndex((p) => p.from() === model.value.from && p.to() === model.value.to))
const showDates = ref(false)

function pick(i) {
  active.value = i
  model.value = { from: presets[i].from(), to: presets[i].to() }
}
function custom(key, value) {
  active.value = -1
  model.value = { ...model.value, [key]: value }
}
</script>

<template>
  <!-- موبايل: صف كبسولات بيتسحب (زي شهور كشف الحساب في Stitch)، والتواريخ عند الطلب -->
  <div class="no-print space-y-3 md:card md:border md:border-line md:p-4">
    <div class="scrollbar-none -mx-space-sm flex gap-2 overflow-x-auto px-space-sm md:mx-0 md:px-0" role="tablist">
      <button
        v-for="(p, i) in presets.filter((x) => allowAll || !x.all)"
        :key="p.label"
        role="tab"
        :aria-selected="active === i"
        class="flex min-h-[44px] shrink-0 items-center gap-1.5 rounded-full px-4 text-label-md transition-colors md:min-h-[40px]"
        :class="active === i ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-highest text-on-surface-variant hover:bg-surface-container-high'"
        @click="pick(i); showDates = false"
      >
        <span v-if="active === i" class="material-symbols-outlined text-[18px]">check_circle</span>
        {{ p.label }}
      </button>
      <button
        role="tab"
        :aria-selected="active === -1"
        class="flex min-h-[44px] shrink-0 items-center gap-1 rounded-full px-4 text-label-md md:hidden"
        :class="active === -1 || showDates ? 'bg-primary text-on-primary' : 'bg-surface-container-highest text-on-surface-variant'"
        @click="showDates = !showDates"
      >
        <span class="material-symbols-outlined text-[18px]">calendar_month</span>
        تاريخ مخصص
      </button>
    </div>
    <div class="grid-cols-2 gap-3 sm:max-w-md md:grid" :class="showDates ? 'grid' : 'hidden'">
      <div>
        <label class="mb-1 block text-label-md text-on-surface-variant" for="pf-from">من</label>
        <input id="pf-from" :value="model.from" type="date" class="field min-h-[44px]" @change="custom('from', $event.target.value)" />
      </div>
      <div>
        <label class="mb-1 block text-label-md text-on-surface-variant" for="pf-to">إلى</label>
        <input id="pf-to" :value="model.to" type="date" class="field min-h-[44px]" @change="custom('to', $event.target.value)" />
      </div>
    </div>
  </div>
</template>
