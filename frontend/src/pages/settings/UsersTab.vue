<script setup>
import { ref, reactive, onMounted } from 'vue'
import api, { errorMessage, fieldErrors } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { useUiStore } from '@/stores/ui'
import DataTable from '@/components/DataTable.vue'
import AppDrawer from '@/components/AppDrawer.vue'
import TileSelect from '@/components/TileSelect.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import PersonAvatar from '@/components/PersonAvatar.vue'
import { ago } from '@/utils/format'

const auth = useAuthStore()
const meta = useMetaStore()
const ui = useUiStore()

const users = ref([])
const loading = ref(true)
const drawer = ref(false)
const editing = ref(null)
const errors = ref({})
const saving = ref(false)
const form = reactive({ name: '', phone: '', password: '', role: 'treasurer', custodian_id: '', is_active: true })

const matrix = ref({})
const permissionLabels = ref({})
const roleLabels = ref({})
const savingMatrix = ref(false)

const roleOptions = [
  { id: 'imam', name: 'إمام المسجد', icon: 'mosque' },
  { id: 'treasurer', name: 'أمين الصندوق', icon: 'account_balance_wallet' },
  { id: 'accountant', name: 'المحاسب', icon: 'calculate' },
]

const columns = [
  { key: 'name', label: 'الاسم' },
  { key: 'phone', label: 'الموبايل' },
  { key: 'role', label: 'الدور' },
  { key: 'custodian', label: 'مربوط بعهدة' },
  { key: 'status', label: 'الحالة' },
  { key: 'last', label: 'آخر دخول' },
]

async function load() {
  loading.value = true
  try {
    const [u, p] = await Promise.all([api.get('/users'), api.get('/permissions')])
    users.value = u.data.data
    matrix.value = Object.fromEntries(Object.keys(p.data.roles).map((r) => [r, [...(p.data.matrix[r] || [])]]))
    permissionLabels.value = p.data.permissions
    roleLabels.value = p.data.roles
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(load)

function openForm(user = null) {
  editing.value = user
  errors.value = {}
  Object.assign(form, user
    ? { name: user.name, phone: user.phone, password: '', role: user.role, custodian_id: user.custodian_id || '', is_active: user.is_active }
    : { name: '', phone: '', password: '', role: 'treasurer', custodian_id: '', is_active: true })
  drawer.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const body = { ...form, custodian_id: form.custodian_id || null }
    const { data } = editing.value ? await api.put(`/users/${editing.value.id}`, body) : await api.post('/users', body)
    ui.success(data.message)
    drawer.value = false
    load()
  } catch (e) {
    errors.value = fieldErrors(e)
    ui.error(errorMessage(e))
  } finally {
    saving.value = false
  }
}

function toggle(role, perm) {
  const list = matrix.value[role]
  const i = list.indexOf(perm)
  i >= 0 ? list.splice(i, 1) : list.push(perm)
}

async function saveMatrix() {
  savingMatrix.value = true
  try {
    const { data } = await api.put('/permissions', { matrix: matrix.value })
    ui.success(data.message)
    await auth.fetchMe()
    load()
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    savingMatrix.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <section>
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-headline-sm font-bold">المستخدمين ({{ users.length }})</h2>
        <button class="btn btn-primary" @click="openForm()"><span class="material-symbols-outlined">person_add</span>إضافة مستخدم</button>
      </div>
      <DataTable :columns="columns" :rows="users" :loading="loading" clickable @row-click="openForm">
        <template #cell-name="{ row }">
          <span class="flex items-center gap-3"><PersonAvatar :name="row.name" :id="row.id" size="sm" /><b>{{ row.name }}</b></span>
        </template>
        <template #cell-phone="{ row }"><span dir="ltr">{{ row.phone }}</span></template>
        <template #cell-role="{ row }">{{ row.role_label }}</template>
        <template #cell-custodian="{ row }">{{ row.custodian?.name || '—' }}</template>
        <template #cell-status="{ row }"><StatusBadge :status="row.is_active ? 'active' : 'inactive'" /></template>
        <template #cell-last="{ row }"><span class="text-body-sm text-on-surface-variant">{{ row.last_login_at ? ago(row.last_login_at) : 'لسه مدخلش' }}</span></template>
        <template #card="{ row }">
          <div class="flex items-center gap-3">
            <PersonAvatar :name="row.name" :id="row.id" />
            <div class="flex-1">
              <p class="font-bold">{{ row.name }}</p>
              <p class="text-body-sm text-on-surface-variant">{{ row.role_label }} · <span dir="ltr">{{ row.phone }}</span></p>
            </div>
            <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
          </div>
        </template>
      </DataTable>
    </section>

    <!-- جدول الصلاحيات -->
    <section>
      <h2 class="mb-1 text-headline-sm font-bold">جدول الصلاحيات</h2>
      <p class="mb-3 text-body-sm text-on-surface-variant">علّم على اللي كل دور يقدر يعمله. الموافقة على الصرف ثابتة للإمام بس ومتتغيرش.</p>
      <div class="card overflow-x-auto border border-line">
        <table class="w-full min-w-[560px] text-right">
          <thead class="bg-[#F6F8F6]">
            <tr>
              <th class="px-4 py-3 text-label-md font-bold">الصلاحية</th>
              <th v-for="(label, role) in roleLabels" :key="role" class="px-4 py-3 text-center text-label-md font-bold">{{ label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr class="h-[56px] border-t border-line bg-tertiary-fixed/30">
              <td class="px-4 font-bold">
                <span class="flex items-center gap-2"><span class="material-symbols-outlined text-tertiary">lock</span>الموافقة / الرفض / الإرجاع للتعديل</span>
              </td>
              <td v-for="(label, role) in roleLabels" :key="role" class="text-center">
                <span class="material-symbols-outlined icon-fill" :class="role === 'imam' ? 'text-primary-container' : 'text-outline-variant'">{{ role === 'imam' ? 'check_circle' : 'cancel' }}</span>
              </td>
            </tr>
            <tr v-for="(label, perm) in permissionLabels" :key="perm" class="h-[56px] border-t border-line">
              <td class="px-4">{{ label }}</td>
              <td v-for="(rl, role) in roleLabels" :key="role" class="text-center">
                <label class="inline-flex h-12 w-12 cursor-pointer items-center justify-center rounded-lg hover:bg-surface-container-low">
                  <input
                    type="checkbox"
                    class="h-6 w-6 accent-[#1f6f50]"
                    :checked="matrix[role]?.includes(perm)"
                    :disabled="role === 'imam' && perm === 'settings.manage'"
                    :aria-label="`${label} — ${rl}`"
                    @change="toggle(role, perm)"
                  />
                </label>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <button class="btn btn-primary mt-3" :disabled="savingMatrix" @click="saveMatrix"><span class="material-symbols-outlined">save</span>حفظ الصلاحيات</button>
    </section>

    <AppDrawer v-model="drawer" :title="editing ? `تعديل ${editing.name}` : 'إضافة مستخدم'" subtitle="المستخدم بيدخل برقم الموبايل وكلمة المرور">
      <form class="space-y-5" @submit.prevent="save">
        <div>
          <label class="field-label" for="u-name">الاسم</label>
          <input id="u-name" v-model="form.name" class="field" :class="{ 'field-error': errors.name }" />
          <p v-if="errors.name" class="error-text">{{ errors.name }}</p>
        </div>
        <div>
          <label class="field-label" for="u-phone">رقم الموبايل</label>
          <input id="u-phone" v-model="form.phone" type="tel" dir="ltr" class="field text-left" :class="{ 'field-error': errors.phone }" />
          <p v-if="errors.phone" class="error-text">{{ errors.phone }}</p>
        </div>
        <div>
          <label class="field-label" for="u-pass">كلمة المرور <span v-if="editing" class="text-body-sm font-normal text-on-surface-variant">(سيبها فاضية لو مش هتغيّرها)</span></label>
          <input id="u-pass" v-model="form.password" type="text" autocomplete="new-password" class="field" :class="{ 'field-error': errors.password }" placeholder="8 حروف أو أرقام على الأقل" />
          <p v-if="errors.password" class="error-text">{{ errors.password }}</p>
        </div>
        <TileSelect v-model="form.role" label="الدور" :options="roleOptions" columns="grid-cols-3" :error="errors.role" />
        <div>
          <label class="field-label" for="u-c">مربوط بعهدة مين؟</label>
          <select id="u-c" v-model="form.custodian_id" class="field" :class="{ 'field-error': errors.custodian_id }">
            <option value="">— مش مربوط —</option>
            <option v-for="c in meta.custodians" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <p v-if="errors.custodian_id" class="error-text">{{ errors.custodian_id }}</p>
          <p v-else class="mt-1 text-body-sm text-on-surface-variant">عشان لما يسجّل إيراد، يتعلّم تلقائيًا إن الفلوس معاه.</p>
        </div>
        <label class="flex min-h-[56px] cursor-pointer items-center justify-between rounded-xl border border-line bg-surface-container-lowest px-4">
          <span class="font-bold">الحساب نشط</span>
          <input v-model="form.is_active" type="checkbox" class="h-6 w-6 accent-[#1f6f50]" />
        </label>
      </form>
      <template #footer>
        <button class="btn btn-primary btn-lg w-full" :disabled="saving" @click="save"><span class="material-symbols-outlined">check</span>حفظ</button>
      </template>
    </AppDrawer>
  </div>
</template>
