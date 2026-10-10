<script setup>
import { ref, watch, nextTick } from 'vue'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()
const reason = ref('')
const error = ref('')
const input = ref(null)

watch(
  () => ui.confirm,
  async (c) => {
    reason.value = ''
    error.value = ''
    if (c?.reason) {
      await nextTick()
      input.value?.focus()
    }
  },
)

function submit() {
  if (ui.confirm.reason) {
    if (reason.value.trim().length < 3) {
      error.value = 'اكتب السبب عشان يتسجل.'
      return
    }
    ui.closeConfirm(reason.value.trim())
  } else {
    ui.closeConfirm(true)
  }
}
</script>

<template>
  <Transition enter-from-class="opacity-0" enter-active-class="transition duration-150" leave-to-class="opacity-0" leave-active-class="transition duration-150">
    <div v-if="ui.confirm" class="fixed inset-0 z-[90] flex items-end justify-center bg-forest/40 p-4 backdrop-blur-sm sm:items-center" @click.self="ui.closeConfirm(false)">
      <div class="w-full max-w-md rounded-xl bg-surface-container-lowest p-6 shadow-modal" role="alertdialog" aria-modal="true">
        <div class="mb-4 flex items-start gap-3">
          <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
            :class="ui.confirm.danger ? 'bg-error-container text-error' : 'bg-secondary-container text-primary-container'"
          >
            <span class="material-symbols-outlined icon-fill text-[26px]">{{ ui.confirm.icon || (ui.confirm.danger ? 'warning' : 'help') }}</span>
          </div>
          <div>
            <h2 class="text-headline-sm font-bold text-on-surface">{{ ui.confirm.title }}</h2>
            <p v-if="ui.confirm.message" class="mt-1 text-body-md text-on-surface-variant">{{ ui.confirm.message }}</p>
          </div>
        </div>

        <div v-if="ui.confirm.reason" class="mb-4">
          <label class="field-label" for="confirm-reason">{{ ui.confirm.reasonLabel || 'السبب' }}</label>
          <textarea
            id="confirm-reason"
            ref="input"
            v-model="reason"
            rows="3"
            class="field py-3"
            :class="{ 'field-error': error }"
            :placeholder="ui.confirm.reasonPlaceholder || 'اكتب السبب هنا...'"
          ></textarea>
          <p v-if="error" class="error-text"><span class="material-symbols-outlined text-[18px]">error</span>{{ error }}</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <button class="btn" :class="ui.confirm.danger ? 'btn-danger' : 'btn-primary'" @click="submit">
            {{ ui.confirm.confirmText || 'تأكيد' }}
          </button>
          <button class="btn btn-ghost bg-surface-container" @click="ui.closeConfirm(false)">رجوع</button>
        </div>
      </div>
    </div>
  </Transition>
</template>
