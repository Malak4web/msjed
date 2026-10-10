<script setup>
/**
 * جدول بصفوف 60px، النصوص يمين والأرقام شمال. على الموبايل بيتحول لكروت (slot: card).
 * columns: [{ key, label, align?: 'end', class? }]
 */
defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  rowKey: { type: String, default: 'id' },
  rowClass: { type: Function, default: () => '' },
  clickable: { type: Boolean, default: false },
})
defineEmits(['row-click'])
</script>

<template>
  <div class="card overflow-hidden border border-line">
    <!-- ديسكتوب/تابلت -->
    <div class="hidden overflow-x-auto md:block">
      <table class="w-full text-right">
        <thead class="bg-[#F6F8F6]">
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              scope="col"
              class="whitespace-nowrap px-4 py-3 text-label-md font-bold text-ink"
              :class="[col.align === 'end' ? 'text-left' : '', col.class]"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="i in 5" :key="'s' + i" class="border-t border-line">
              <td v-for="col in columns" :key="col.key" class="px-4 py-4"><div class="skeleton h-5 w-full max-w-[140px]"></div></td>
            </tr>
          </template>
          <tr
            v-for="row in rows"
            v-else
            :key="row[rowKey]"
            class="h-[60px] border-t border-line transition-colors hover:bg-[#FAF9F5]"
            :class="[rowClass(row), clickable ? 'cursor-pointer' : '']"
            @click="$emit('row-click', row)"
          >
            <td
              v-for="col in columns"
              :key="col.key"
              class="px-4 py-2 text-body-md"
              :class="[col.align === 'end' ? 'text-left tabular' : '', col.class]"
            >
              <slot :name="'cell-' + col.key" :row="row">{{ row[col.key] }}</slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- موبايل -->
    <div class="divide-y divide-line md:hidden">
      <template v-if="loading">
        <div v-for="i in 4" :key="'m' + i" class="space-y-2 p-4">
          <div class="skeleton h-5 w-1/2"></div>
          <div class="skeleton h-4 w-1/3"></div>
        </div>
      </template>
      <div
        v-for="row in rows"
        v-else
        :key="row[rowKey]"
        class="p-4"
        :class="[rowClass(row), clickable ? 'cursor-pointer active:bg-surface-container-low' : '']"
        @click="$emit('row-click', row)"
      >
        <slot name="card" :row="row" />
      </div>
    </div>

    <slot v-if="!loading && !rows.length" name="empty" />
    <slot name="footer" />
  </div>
</template>
