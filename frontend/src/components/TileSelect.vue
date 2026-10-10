<script setup>
/** اختيار بمربعات كبيرة بدل قائمة منسدلة — أسهل لغير التقنيين */
const model = defineModel({ type: [Number, String, null], default: null })

defineProps({
  options: { type: Array, required: true }, // [{ id, name, icon?, hint? }]
  columns: { type: String, default: 'grid-cols-2 sm:grid-cols-3' },
  error: { type: String, default: '' },
  label: { type: String, default: '' },
})
</script>

<template>
  <fieldset>
    <legend v-if="label" class="field-label">{{ label }}</legend>
    <div class="grid gap-2" :class="columns" role="radiogroup">
      <button
        v-for="opt in options"
        :key="opt.id"
        type="button"
        role="radio"
        :aria-checked="model === opt.id"
        class="relative flex min-h-[64px] flex-col items-center justify-center gap-1 rounded-xl border-2 p-2 text-center transition-all active:scale-95"
        :class="
          model === opt.id
            ? 'border-primary-container bg-secondary-container/60 text-primary shadow-sm'
            : 'border-outline-variant/70 bg-surface-container-lowest text-on-surface-variant hover:border-primary-fixed-dim'
        "
        @click="model = opt.id"
      >
        <span v-if="opt.icon" class="material-symbols-outlined text-[26px]" :class="{ 'icon-fill': model === opt.id }">{{ opt.icon }}</span>
        <span class="text-label-md font-bold leading-tight">{{ opt.name }}</span>
        <span v-if="opt.hint" class="text-label-sm text-on-surface-variant">{{ opt.hint }}</span>
        <span v-if="model === opt.id" class="material-symbols-outlined icon-fill absolute left-1.5 top-1.5 text-[18px] text-primary-container">check_circle</span>
      </button>
    </div>
    <p v-if="error" class="error-text"><span class="material-symbols-outlined text-[18px]">error</span>{{ error }}</p>
  </fieldset>
</template>
