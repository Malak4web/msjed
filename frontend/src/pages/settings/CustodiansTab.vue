<script setup>
import { ref, reactive, onMounted } from 'vue'
import api, { errorMessage, fieldErrors } from '@/api/client'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import DataTable from '@/components/DataTable.vue'
import AppDrawer from '@/components/AppDrawer.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import PersonAvatar from '@/components/PersonAvatar.vue'
import MoneyAmount from '@/components/MoneyAmount.vue'

const meta = useMetaStore()
const ui = useUiStore()

const rows = ref([])
const loading = ref(true)
const drawer = ref(false)
const editing = ref(null)
const errors = ref({})
const saving = ref(false)
const form = reactive({ name: '', title: '', phone: '', is_active: true })

const columns = [
  { key: 'name', label: 'الاسم' },
  { key: 'title', label: 'الصفة' },
  { key: 'login', label: 'حساب دخول' },
  { key: 'status', label: 'الحالة' },
  { key: 'balance', label: 'الرصيد', align: 'end' },
]

async function load() {
  loading.value = true
  try {
    rows.value = (await api.get('/custodians', { params: { all: 1 } })).data.data
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(load)

function openForm(row = null) {
  editing.value = row
  errors.value = {}
  Object.assign(form, row ? { name: row.name, title: row.title || '', phone: row.phone || '', is_active: row.is_active } : { name: '', title: '', phone: '', is_active: true })
  drawer.value = true
}

async function save() {
  saving.value = true
  try {
    const { data } = editing.value ? await api.put(`/custodians/${editing.value.id}`, form) : await api.post('/custodians', form)
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
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <div>
        <h2 class="text-headline-sm font-bold">الأشخاص حاملي العُهد</h2>
        <p class="text-body-sm text-on-surface-variant">أي حد ممكن يكون معاه فلوس المسجد — حتى لو ملوش حساب دخول (زي عامل المسجد).</p>
      </div>
      <button class="btn btn-primary" @click="openForm()"><span class="material-symbols-outlined">person_add</span>إضافة شخص</button>
    </div>
    <DataTable :columns="columns" :rows="rows" :loading="loading" clickable @row-click="openForm">
      <template #cell-name="{ row }"><span class="flex items-center gap-3"><PersonAvatar :name="row.name" :id="row.id" size="sm" /><b>{{ row.name }}</b></span></template>
      <template #cell-title="{ row }">{{ row.title || '—' }}</template>
      <template #cell-login="{ row }">{{ row.has_login ? 'عنده حساب' : 'بدون حساب' }}</template>
      <template #cell-status="{ row }"><StatusBadge :status="row.is_active ? 'active' : 'inactive'" /></template>
      <template #cell-balance="{ row }"><MoneyAmount :value="row.balance" size="sm" tone="primary" /></template>
      <template #card="{ row }">
        <div class="flex items-center gap-3">
          <PersonAvatar :name="row.name" :id="row.id" />
          <div class="flex-1">
            <p class="font-bold">{{ row.name }}</p>
            <p class="text-body-sm text-on-surface-variant">{{ row.title || '—' }} · {{ row.has_login ? 'عنده حساب' : 'بدون حساب' }}</p>
          </div>
          <MoneyAmount :value="row.balance" size="sm" tone="primary" />
        </div>
      </template>
    </DataTable>

    <AppDrawer v-model="drawer" :title="editing ? `تعديل ${editing.name}` : 'إضافة شخص حامل عهدة'">
      <form class="space-y-5" @submit.prevent="save">
        <div>
          <label class="field-label" for="c-name">الاسم</label>
          <input id="c-name" v-model="form.name" class="field" :class="{ 'field-error': errors.name }" placeholder="مثلاً: عم سيد" />
          <p v-if="errors.name" class="error-text">{{ errors.name }}</p>
        </div>
        <div>
          <label class="field-label" for="c-title">الصفة</label>
          <input id="c-title" v-model="form.title" class="field" placeholder="مثلاً: عامل المسجد" />
        </div>
        <div>
          <label class="field-label" for="c-phone">الموبايل <span class="text-body-sm font-normal text-on-surface-variant">(اختياري)</span></label>
          <input id="c-phone" v-model="form.phone" type="tel" dir="ltr" class="field text-left" />
        </div>
        <label class="flex min-h-[56px] cursor-pointer items-center justify-between rounded-xl border border-line bg-surface-container-lowest px-4">
          <span class="font-bold">نشط</span>
          <input v-model="form.is_active" type="checkbox" class="h-6 w-6 accent-[#1f6f50]" />
        </label>
        <p class="text-body-sm text-on-surface-variant">ملحوظة: مينفعش توقف شخص معاه فلوس — سلّم رصيده لحد تاني الأول.</p>
      </form>
      <template #footer>
        <button class="btn btn-primary btn-lg w-full" :disabled="saving" @click="save"><span class="material-symbols-outlined">check</span>حفظ</button>
      </template>
    </AppDrawer>
  </section>
</template>
