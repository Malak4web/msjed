<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePush } from '@/composables/usePush'
import { useUiStore } from '@/stores/ui'

/**
 * حالة إشعارات الموبايل + تفعيل + إشعار تجريبي.
 * compact: نسخة صغيرة للرئيسية بتظهر بس لو الإشعارات مش مفعّلة.
 */
const props = defineProps({ compact: { type: Boolean, default: false } })

const push = usePush()
const ui = useUiStore()
const results = ref(null)

onMounted(() => push.refresh())

const view = computed(() => {
  const s = push.status.value
  return (
    {
      on: { icon: 'notifications', tone: 'bg-inflow-bg text-inflow-text', title: 'الإشعارات شغالة على الجهاز ده', text: 'هيوصلك تنبيه على الموبايل حتى لو التطبيق مقفول.' },
      off: { icon: 'notifications', tone: 'bg-secondary-container text-on-secondary-fixed-variant', title: 'فعّل إشعارات الموبايل', text: 'عشان يوصلك تنبيه أول ما يتعمل طلب صرف أو الإمام يرد عليه.' },
      denied: { icon: 'block', tone: 'bg-outflow-bg text-outflow-text', title: 'الإشعارات مقفولة للموقع', text: push.MESSAGES.denied },
      insecure: { icon: 'lock', tone: 'bg-pending-bg text-pending-text', title: 'الإشعارات محتاجة رابط آمن (https)', text: push.MESSAGES.insecure },
      'ios-install': { icon: 'smartphone', tone: 'bg-pending-bg text-pending-text', title: 'ثبّت التطبيق الأول', text: push.MESSAGES['ios-install'] },
      unsupported: { icon: 'warning', tone: 'bg-outflow-bg text-outflow-text', title: 'المتصفح مش بيدعم الإشعارات', text: push.MESSAGES.unsupported },
      checking: { icon: 'progress_activity', tone: 'bg-surface-container text-on-surface-variant', title: 'بنتأكد من حالة الإشعارات...', text: '' },
    }[s] || {}
  )
})

// في الرئيسية: نظهر بس لو فيه حاجة المستخدم يقدر يعملها
const visible = computed(() => !props.compact || ['off', 'ios-install', 'denied'].includes(push.status.value))

async function enable() {
  const error = await push.enable()
  if (error) return ui.error(error)
  ui.success('تمام! الإشعارات اتفعّلت. جرّب "إشعار تجريبي" تتأكد.')
}

async function disable() {
  await push.disable()
  results.value = null
  ui.success('الإشعارات اتوقفت على الجهاز ده.')
}

async function test() {
  const r = await push.test()
  results.value = r
  r.ok ? ui.success(r.message) : ui.error(r.message)
}
</script>

<template>
  <section v-if="visible" class="card border border-line p-4" :class="{ '!p-3': compact }">
    <div class="flex items-start gap-3">
      <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" :class="[view.tone, { '!h-10 !w-10': compact }]">
        <span class="material-symbols-outlined icon-fill" :class="{ 'animate-spin': push.status.value === 'checking' }">{{ view.icon }}</span>
      </span>
      <div class="min-w-0 flex-1">
        <p class="font-bold">{{ view.title }}</p>
        <p v-if="view.text" class="text-body-sm leading-relaxed text-on-surface-variant">{{ view.text }}</p>
      </div>
      <button v-if="compact && push.status.value === 'off'" class="btn btn-primary min-h-[44px] shrink-0 px-4" :disabled="push.busy.value" @click="enable">تفعيل</button>
    </div>

    <div v-if="!compact && ['on', 'off'].includes(push.status.value)" class="mt-3 grid grid-cols-2 gap-2">
      <button v-if="push.status.value === 'off'" class="btn btn-primary col-span-2" :disabled="push.busy.value" @click="enable">
        <span class="material-symbols-outlined">notifications</span>تفعيل الإشعارات على الجهاز ده
      </button>
      <template v-else>
        <button class="btn btn-primary" :disabled="push.busy.value" @click="test">
          <span class="material-symbols-outlined" :class="{ 'animate-spin': push.busy.value }">{{ push.busy.value ? 'progress_activity' : 'send' }}</span>إشعار تجريبي
        </button>
        <button class="btn btn-ghost bg-surface-container" :disabled="push.busy.value" @click="disable">إيقاف</button>
      </template>
    </div>

    <ul v-if="results?.results?.length" class="mt-3 space-y-1.5 rounded-xl bg-surface-container-low p-3 text-body-sm">
      <li v-for="(r, i) in results.results" :key="i" class="flex items-start gap-2">
        <span class="material-symbols-outlined icon-fill text-[18px]" :class="r.ok ? 'text-inflow' : 'text-error'">{{ r.ok ? 'check_circle' : 'error' }}</span>
        <span><b>{{ r.device }}:</b> {{ r.ok ? 'اتبعت بنجاح' : r.reason }}</span>
      </li>
    </ul>
  </section>
</template>
