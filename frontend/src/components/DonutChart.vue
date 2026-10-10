<script setup>
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
import { money, num } from '@/utils/format'

ChartJS.register(ArcElement, Tooltip, Legend)

/** Donut بألوان الهوية — items: [{ name, total }] */
const props = defineProps({
  items: { type: Array, default: () => [] },
  centerLabel: { type: String, default: 'الإجمالي' },
})

const PALETTE = ['#1f6f50', '#c9a227', '#366851', '#8ad6b0', '#755b00', '#ecc246', '#196b4c', '#b8efd1', '#584400', '#6f7a73', '#a5f3cc']

const total = computed(() => props.items.reduce((s, i) => s + Number(i.total), 0))

const data = computed(() => ({
  labels: props.items.map((i) => i.name),
  datasets: [{ data: props.items.map((i) => Number(i.total)), backgroundColor: PALETTE, borderColor: '#ffffff', borderWidth: 2, hoverOffset: 6 }],
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: {
    legend: { display: false },
    tooltip: { rtl: true, bodyFont: { size: 14 }, callbacks: { label: (c) => ` ${c.label}: ${money(c.raw)}` } },
  },
}
</script>

<template>
  <div>
    <!-- مقاس ثابت عشان Chart.js ميقيسش الحاوية غلط -->
    <div class="relative mx-auto h-[220px] w-[220px]">
      <Doughnut :data="data" :options="options" role="img" :aria-label="`رسم دائري: ${centerLabel}`" />
      <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
        <span class="text-label-md text-on-surface-variant">{{ centerLabel }}</span>
        <span class="tabular text-headline-md font-bold">{{ num(total) }}</span>
        <span class="text-label-sm text-on-surface-variant">ج.م</span>
      </div>
    </div>
    <ul class="mt-4 space-y-2">
      <li v-for="(item, i) in items" :key="item.name" class="flex items-center gap-2 text-body-md">
        <span class="h-3.5 w-3.5 shrink-0 rounded" :style="{ background: PALETTE[i % PALETTE.length] }"></span>
        <span class="flex-1 truncate">{{ item.name }}</span>
        <span class="tabular font-bold">{{ money(item.total) }}</span>
        <span class="w-12 text-left text-body-sm text-on-surface-variant">{{ total ? num(Math.round((item.total / total) * 100)) : 0 }}٪</span>
      </li>
    </ul>
  </div>
</template>
