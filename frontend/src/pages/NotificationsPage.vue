<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api, { errorMessage } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { usePush } from '@/composables/usePush'
import PageHeader from '@/components/PageHeader.vue'
import EmptyState from '@/components/EmptyState.vue'
import { ago } from '@/utils/format'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()
const push = usePush()

const items = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

const icons = {
  submitted: ['hourglass_top', 'bg-pending-bg text-pending-text'],
  approved: ['verified', 'bg-inflow-bg text-inflow-text'],
  rejected: ['cancel', 'bg-outflow-bg text-outflow-text'],
  returned: ['undo', 'bg-tertiary-fixed text-on-tertiary-fixed-variant'],
}

async function load(more = false) {
  loading.value = true
  try {
    const { data } = await api.get('/notifications', { params: { page: page.value } })
    items.value = more ? [...items.value, ...data.data] : data.data
    lastPage.value = data.meta.last_page
    if (auth.user) auth.user.unread_notifications = data.unread
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(() => {
  load()
  push.refresh()
})

async function open(n) {
  if (!n.read) {
    n.read = true
    api.post(`/notifications/${n.id}/read`).then(({ data }) => auth.user && (auth.user.unread_notifications = data.unread)).catch(() => {})
  }
  if (n.expense_request_id) router.push({ path: '/requests', query: { id: n.expense_request_id, status: n.status } })
}

async function readAll() {
  try {
    await api.post('/notifications/read-all')
    items.value.forEach((n) => (n.read = true))
    if (auth.user) auth.user.unread_notifications = 0
  } catch (e) {
    ui.error(errorMessage(e))
  }
}

async function togglePush() {
  if (push.enabled.value) {
    await push.disable()
    ui.success('الإشعارات اتوقفت على الجهاز ده.')
    return
  }
  const error = await push.enable()
  error ? ui.error(error) : ui.success('تمام! هيوصلك إشعار على الجهاز ده أول ما يحصل جديد.')
}

function more() {
  page.value++
  load(true)
}
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <PageHeader title="الإشعارات" subtitle="طلبات جديدة، وموافقات، ورفض">
      <template #actions>
        <button v-if="items.some((n) => !n.read)" class="btn btn-ghost bg-surface-container-lowest" @click="readAll">
          <span class="material-symbols-outlined">check</span>علّم الكل كمقروء
        </button>
      </template>
    </PageHeader>

    <!-- تفعيل Push -->
    <div v-if="push.supported" class="card mb-4 flex items-center gap-3 border border-line p-4">
      <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" :class="push.enabled.value ? 'bg-inflow-bg text-inflow-text' : 'bg-secondary-container text-on-secondary-fixed-variant'">
        <span class="material-symbols-outlined icon-fill">notifications</span>
      </span>
      <div class="flex-1">
        <p class="font-bold">{{ push.enabled.value ? 'الإشعارات شغالة على الجهاز ده' : 'فعّل الإشعارات على الجهاز ده' }}</p>
        <p class="text-body-sm text-on-surface-variant">يوصلك تنبيه على الموبايل حتى لو التطبيق مقفول.</p>
      </div>
      <button class="btn" :class="push.enabled.value ? 'btn-ghost bg-surface-container' : 'btn-primary'" :disabled="push.busy.value" @click="togglePush">
        {{ push.enabled.value ? 'إيقاف' : 'تفعيل' }}
      </button>
    </div>

    <div v-if="loading && !items.length" class="space-y-2"><div v-for="i in 4" :key="i" class="skeleton h-20"></div></div>
    <ul v-else-if="items.length" class="card divide-y divide-line overflow-hidden border border-line">
      <li v-for="n in items" :key="n.id">
        <button class="flex w-full items-start gap-3 p-4 text-right transition hover:bg-surface-container-low" :class="{ 'bg-secondary-container/20': !n.read }" @click="open(n)">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="(icons[n.event] || icons.submitted)[1]">
            <span class="material-symbols-outlined">{{ (icons[n.event] || icons.submitted)[0] }}</span>
          </span>
          <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2">
              <span class="font-bold" :class="n.read ? 'text-on-surface' : 'text-primary'">{{ n.title }}</span>
              <span v-if="!n.read" class="h-2.5 w-2.5 rounded-full bg-tertiary-container" aria-label="جديد"></span>
            </span>
            <span class="block text-body-md text-on-surface">{{ n.body }}</span>
            <span v-if="n.reason" class="mt-1 block rounded-lg bg-surface-container-low px-3 py-1.5 text-body-sm">السبب: {{ n.reason }}</span>
            <span class="mt-1 block text-body-sm text-on-surface-variant">{{ ago(n.created_at) }}</span>
          </span>
          <span class="material-symbols-outlined self-center text-on-surface-variant">chevron_left</span>
        </button>
      </li>
    </ul>
    <div v-else class="card"><EmptyState icon="notifications" title="مفيش إشعارات" text="لما حد يطلب صرف أو الإمام يرد على طلبك، هتلاقيه هنا." /></div>

    <button v-if="page < lastPage" class="btn btn-ghost mt-3 w-full bg-surface-container-lowest" :disabled="loading" @click="more">عرض أقدم</button>
  </div>
</template>
