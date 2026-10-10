<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'

/**
 * إرفاق صورة/ملف. camera = زرار كاميرا كبير (على الموبايل بيفتح الكاميرا الخلفية مباشرة).
 */
const model = defineModel({ type: [File, null], default: null })
const props = defineProps({
  label: { type: String, default: 'إرفاق صورة' },
  camera: { type: Boolean, default: false },
  accept: { type: String, default: 'image/*,application/pdf' },
  error: { type: String, default: '' },
})

const cameraInput = ref(null)
const fileInput = ref(null)
const previewUrl = ref(null)

const isImage = computed(() => model.value?.type?.startsWith('image/'))

function pick(e) {
  const file = e.target.files?.[0] || null
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : null
  model.value = file
  e.target.value = ''
}

function clear() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = null
  model.value = null
}

onBeforeUnmount(() => previewUrl.value && URL.revokeObjectURL(previewUrl.value))
</script>

<template>
  <div>
    <span class="field-label">{{ label }}</span>
    <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="pick" />
    <input ref="fileInput" type="file" :accept="props.accept" class="hidden" @change="pick" />

    <div v-if="model" class="flex items-center gap-3 rounded-xl border border-line bg-surface-container-lowest p-2">
      <img v-if="isImage && previewUrl" :src="previewUrl" alt="" class="h-16 w-16 rounded-lg object-cover" />
      <div v-else class="flex h-16 w-16 items-center justify-center rounded-lg bg-surface-container text-primary">
        <span class="material-symbols-outlined text-[30px]">description</span>
      </div>
      <div class="min-w-0 flex-1">
        <p class="truncate text-label-md font-bold">{{ model.name }}</p>
        <p class="text-body-sm text-on-surface-variant">{{ (model.size / 1024 / 1024).toFixed(1) }} ميجا</p>
      </div>
      <button type="button" class="btn btn-ghost min-h-[44px] px-3 text-error" @click="clear">
        <span class="material-symbols-outlined">delete</span>
        شيل
      </button>
    </div>

    <div v-else class="grid gap-2" :class="camera ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1'">
      <button
        v-if="camera"
        type="button"
        class="flex min-h-[88px] flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-primary-container bg-secondary-container/40 text-primary transition active:scale-98"
        @click="cameraInput.click()"
      >
        <span class="material-symbols-outlined icon-fill text-[36px]">photo_camera</span>
        <span class="text-label-lg font-bold">صوّر الفاتورة</span>
      </button>
      <button
        type="button"
        class="flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-outline-variant bg-surface-container-lowest text-on-surface-variant transition hover:border-primary-container hover:text-primary"
        :class="camera ? 'min-h-[88px] flex-col' : 'min-h-[56px]'"
        @click="fileInput.click()"
      >
        <span class="material-symbols-outlined text-[28px]">attach_file</span>
        <span class="text-label-lg font-bold">{{ camera ? 'اختار من الجهاز' : 'اختار صورة أو ملف' }}</span>
      </button>
    </div>
    <p v-if="error" class="error-text"><span class="material-symbols-outlined text-[18px]">error</span>{{ error }}</p>
  </div>
</template>
