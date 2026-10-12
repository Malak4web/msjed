<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api, { errorMessage } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import PushCard from '@/components/PushCard.vue'
import PageHeader from '@/components/PageHeader.vue'
import EmptyState from '@/components/EmptyState.vue'
import { ago } from '@/utils/format'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()

const items = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

const icons = {
  submitted: ['hourglass_top', 'bg-pending-bg text-pending-text'],
  approved: ['verified', 'bg-inflow-bg text-inflow-text'],
  rejected: ['cancel', 'bg-outflow-bg text-outflow-text'],
  returned: ['undo', 'bg-tertiary-fixed text-on-tertiary-fixed-variant'],
  transfer_pending: ['schedule', 'bg-pending-bg text-pending-text'],
  transfer_accepted: ['task_alt', 'bg-inflow-bg text-inflow-text'],
  transfer_rejected: ['cancel', 'bg-outflow-bg text-outflow-text'],
  transfer_cancelled: ['block', 'bg-surface-container text-on-surface-variant'],
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
onMounted(load)

async function open(n) {
  if (!n.read) {
    n.read = true
    api.post(`/notifications/${n.id}/read`).then(({ data }) => auth.user && (auth.user.unread_notifications = data.unread)).catch(() => {})
  }
  if (n.expense_request_id) router.push({ path: '/requests', query: { id: n.expense_request_id, status: n.status } })
  else if (n.transfer_id) router.push({ path: '/transfers' })
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

    <!-- إشعارات الموبايل: الحالة الحقيقية + تفعيل + إشعار تجريبي -->
    <PushCard class="mb-4" />

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
