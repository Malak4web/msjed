<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js'
import { monthShort, money, num } from '@/utils/format'

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)
ChartJS.defaults.font.family = 'Cairo, system-ui, sans-serif'
ChartJS.defaults.color = '#3f4943'

/** الإيرادات مقابل المصروفات — rows: [{ month, income, expense }] */
const props = defineProps({ rows: { type: Array, default: () => [] } })

const data = computed(() => ({
  labels: props.rows.map((r) => monthShort(r.month)),
  datasets: [
    { label: 'الإيرادات', data: props.rows.map((r) => Number(r.income)), backgroundColor: '#16A34A', borderRadius: 6, maxBarThickness: 28 },
    { label: 'المصروفات', data: props.rows.map((r) => Number(r.expense)), backgroundColor: '#DC2626', borderRadius: 6, maxBarThickness: 28 },
  ],
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  locale: 'ar-EG',
  plugins: {
    legend: { position: 'top', align: 'end', rtl: true, labels: { usePointStyle: true, pointStyle: 'rectRounded', font: { size: 14, weight: '600' } } },
    tooltip: { rtl: true, titleFont: { size: 14 }, bodyFont: { size: 14 }, callbacks: { label: (c) => ` ${c.dataset.label}: ${money(c.raw)}` } },
  },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 13, weight: '600' } } },
    y: { position: 'right', grid: { color: '#EAEBE4' }, ticks: { font: { size: 12 }, callback: (v) => num(v) } },
  },
}
</script>

<template>
  <div class="h-72">
    <Bar :data="data" :options="options" aria-label="رسم بياني للإيرادات مقابل المصروفات" role="img" />
  </div>
</template>
