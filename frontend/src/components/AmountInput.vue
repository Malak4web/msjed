<script setup>
import { computed } from 'vue'
import { money } from '@/utils/format'

/** حقل مبلغ كبير ببادئة ج.م ثابتة + أزرار مبالغ سريعة اختيارية */
const model = defineModel({ type: [Number, String, null], default: null })
defineProps({
  label: { type: String, default: 'المبلغ' },
  id: { type: String, default: 'amount' },
  error: { type: String, default: '' },
  presets: { type: Array, default: () => [] },
  hint: { type: String, default: '' },
})

// يقبل الأرقام العربية ويحوّلها
function normalize(e) {
  const map = { '٠': 0, '١': 1, '٢': 2, '٣': 3, '٤': 4, '٥': 5, '٦': 6, '٧': 7, '٨': 8, '٩': 9 }
  const v = e.target.value.replace(/[٠-٩]/g, (d) => map[d]).replace(/[٫,]/g, '.').replace(/[^\d.]/g, '')
  model.value = v
  e.target.value = v
}

const preview = computed(() => (Number(model.value) > 0 ? money(model.value) : ''))
</script>

<template>
  <div>
    <label class="field-label" :for="id">{{ label }}</label>
    <div class="relative">
      <input
        :id="id"
        :value="model"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        placeholder="0"
        class="field tabular h-16 pl-16 text-headline-md font-bold"
        :class="{ 'field-error': error }"
        @input="normalize"
      />
      <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-label-lg font-bold text-ink-2">ج.م</span>
    </div>
    <p v-if="preview && !error" class="mt-1 text-body-sm text-on-surface-variant">{{ preview }}</p>
    <p v-if="hint && !error" class="mt-1 text-body-sm text-on-surface-variant">{{ hint }}</p>
    <p v-if="error" class="error-text"><span class="material-symbols-outlined text-[18px]">error</span>{{ error }}</p>
    <div v-if="presets.length" class="mt-2 flex flex-wrap gap-2">
      <button
        v-for="p in presets"
        :key="p"
        type="button"
        class="chip border-outline-variant bg-surface-container-lowest text-on-surface-variant hover:border-primary-container hover:text-primary"
        @click="model = String(p)"
      >
        {{ money(p) }}
      </button>
    </div>
  </div>
</template>
