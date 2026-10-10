<script setup>
import { ref, reactive, onMounted } from 'vue'
import api, { errorMessage, fieldErrors } from '@/api/client'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import AppDrawer from '@/components/AppDrawer.vue'
import TileSelect from '@/components/TileSelect.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import EmptyState from '@/components/EmptyState.vue'

/** إدارة قائمة بسيطة: مصادر الإيراد / طرق الدفع / بنود الصرف */
const props = defineProps({
  type: { type: String, required: true },
  title: { type: String, required: true },
  singular: { type: String, required: true },
  withType: { type: Boolean, default: false },
})

const meta = useMetaStore()
const ui = useUiStore()

const items = ref([])
const loading = ref(true)
const drawer = ref(false)
const editing = ref(null)
const errors = ref({})
const saving = ref(false)
const form = reactive({ name: '', icon: '', type: 'variable', is_active: true })

const icons = ['payments', 'volunteer_activism', 'favorite', 'menu_book', 'storefront', 'account_balance', 'bolt', 'smartphone', 'receipt', 'water_drop', 'wifi', 'badge', 'campaign', 'engineering', 'cleaning_services', 'build', 'chair', 'shopping_cart', 'lightbulb', 'local_shipping', 'school', 'more_horiz']
const typeOptions = [
  { id: 'fixed', name: 'ثابت (شهري)', icon: 'event_repeat' },
  { id: 'variable', name: 'غير ثابت', icon: 'shuffle' },
]

async function load() {
  loading.value = true
  try {
    items.value = (await api.get(`/lookups/${props.type}`)).data.data
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(load)

function openForm(item = null) {
  editing.value = item
  errors.value = {}
  Object.assign(form, item ? { name: item.name, icon: item.icon || '', type: item.type || 'variable', is_active: item.is_active } : { name: '', icon: '', type: 'variable', is_active: true })
  drawer.value = true
}

async function save() {
  saving.value = true
  try {
    const body = { ...form }
    if (!props.withType) delete body.type
    const { data } = editing.value ? await api.put(`/lookups/${props.type}/${editing.value.id}`, body) : await api.post(`/lookups/${props.type}`, body)
    ui.success(data.message)
    drawer.value = false
    meta.load(true)
    load()
  } catch (e) {
    errors.value = fieldErrors(e)
    ui.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section>
    <div class="mb-3 flex items-center justify-between">
      <h2 class="text-headline-sm font-bold">{{ title }}</h2>
      <button class="btn btn-primary" @click="openForm()"><span class="material-symbols-outlined">add</span>إضافة {{ singular }}</button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 6" :key="i" class="skeleton h-[72px]"></div>
    </div>
    <div v-else-if="items.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <button
        v-for="item in items"
        :key="item.id"
        class="card flex min-h-[72px] items-center gap-3 border border-line p-3 text-right transition hover:border-primary-fixed-dim"
        :class="{ 'opacity-60': !item.is_active }"
        @click="openForm(item)"
      >
        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-secondary-container text-on-secondary-fixed-variant">
          <span class="material-symbols-outlined">{{ item.icon || 'label' }}</span>
        </span>
        <span class="flex-1">
          <span class="block font-bold">{{ item.name }}</span>
          <span v-if="withType" class="text-body-sm text-on-surface-variant">{{ item.type === 'fixed' ? 'ثابت شهري' : 'غير ثابت' }}</span>
        </span>
        <StatusBadge v-if="!item.is_active" status="inactive" />
        <span class="material-symbols-outlined text-on-surface-variant">edit</span>
      </button>
    </div>
    <div v-else class="card border border-line"><EmptyState :title="`مفيش ${title} لسه`" /></div>

    <AppDrawer v-model="drawer" :title="editing ? `تعديل ${editing.name}` : `إضافة ${singular}`">
      <form class="space-y-5" @submit.prevent="save">
        <div>
          <label class="field-label" for="l-name">الاسم</label>
          <input id="l-name" v-model="form.name" class="field" :class="{ 'field-error': errors.name }" />
          <p v-if="errors.name" class="error-text">{{ errors.name }}</p>
        </div>
        <TileSelect v-if="withType" v-model="form.type" label="النوع" :options="typeOptions" columns="grid-cols-2" :error="errors.type" />
        <fieldset>
          <legend class="field-label">الأيقونة</legend>
          <div class="grid grid-cols-6 gap-2 sm:grid-cols-8">
            <button
              v-for="ic in icons"
              :key="ic"
              type="button"
              class="flex h-12 items-center justify-center rounded-lg border-2"
              :class="form.icon === ic ? 'border-primary-container bg-secondary-container text-primary' : 'border-transparent bg-surface-container-low text-on-surface-variant'"
              :aria-label="ic"
              @click="form.icon = ic"
            >
              <span class="material-symbols-outlined">{{ ic }}</span>
            </button>
          </div>
        </fieldset>
        <label class="flex min-h-[56px] cursor-pointer items-center justify-between rounded-xl border border-line bg-surface-container-lowest px-4">
          <span class="font-bold">نشط (يظهر في الاختيارات)</span>
          <input v-model="form.is_active" type="checkbox" class="h-6 w-6 accent-[#1f6f50]" />
        </label>
        <p class="text-body-sm text-on-surface-variant">مفيش حذف عشان العمليات القديمة مربوطة بيه — لو مش محتاجه وقّفه بس.</p>
      </form>
      <template #footer>
        <button class="btn btn-primary btn-lg w-full" :disabled="saving" @click="save"><span class="material-symbols-outlined">check</span>حفظ</button>
      </template>
    </AppDrawer>
  </section>
</template>
