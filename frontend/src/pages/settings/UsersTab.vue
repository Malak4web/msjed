<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
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
const router = useRouter()

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
  { key: 'actions', label: 'الإجراءات', align: 'end' },
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

async function deleteUser(user) {
  if (user.id === auth.user?.id) {
    ui.error('لا يمكنك حذف الحساب المسجل به حالياً.')
    return
  }

  const ok = await ui.ask({
    title: `حذف حساب ${user.name}؟`,
    message: 'هل أنت متأكد من حذف هذا المستخدم؟ في حال عدم وجود معاملات مالية وسجلات مسجلة باسمه، سيتم حذفه نهائياً.',
    confirmText: 'حذف الحساب',
    icon: 'delete',
    danger: true,
  })

  if (!ok) return

  try {
    const { data } = await api.delete(`/users/${user.id}`)
    ui.success(data.message)
    if (drawer.value && editing.value?.id === user.id) {
      drawer.value = false
    }
    load()
  } catch (e) {
    ui.error(errorMessage(e))
  }
}

async function impersonateUser(user) {
  if (!user.is_active) {
    ui.error('لا يمكن تسجيل الدخول بحساب معطل. قم بتفعيل الحساب أولاً.')
    return
  }

  const ok = await ui.ask({
    title: `تسجيل الدخول بحساب ${user.name}؟`,
    message: `سيتم تحويل الجلسة إلى حساب (${user.name}) لتجربة النظام بصلاحياته ودوره (${user.role_label}). يمكنك العودة لحساب الإمام في أي وقت من الشريط العلوي.`,
    confirmText: 'تسجيل الدخول بالحساب',
    icon: 'switch_account',
  })

  if (!ok) return

  try {
    await auth.impersonate(user.id)
    ui.success(`تم تسجيل الدخول بنجاح بحساب ${user.name}`)
    drawer.value = false
    router.push('/')
  } catch (e) {
    ui.error(errorMessage(e))
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
        <div>
          <h2 class="text-headline-sm font-bold">المستخدمين ({{ users.length }})</h2>
          <p class="text-body-sm text-on-surface-variant">إدارة الحسابات، الصلاحيات، وإمكانية تسجيل الدخول بحساب أي مستخدم لإمام المسجد.</p>
        </div>
        <button class="btn btn-primary" @click="openForm()"><span class="material-symbols-outlined">person_add</span>إضافة مستخدم</button>
      </div>

      <DataTable :columns="columns" :rows="users" :loading="loading" clickable @row-click="openForm">
        <template #cell-name="{ row }">
          <span class="flex items-center gap-3">
            <PersonAvatar :name="row.name" :id="row.id" size="sm" />
            <div>
              <b class="text-on-surface">{{ row.name }}</b>
              <span v-if="row.id === auth.user?.id" class="mr-2 inline-block rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-bold text-primary">حسابك الحالي</span>
            </div>
          </span>
        </template>
        <template #cell-phone="{ row }"><span dir="ltr">{{ row.phone }}</span></template>
        <template #cell-role="{ row }">{{ row.role_label }}</template>
        <template #cell-custodian="{ row }">{{ row.custodian?.name || '—' }}</template>
        <template #cell-status="{ row }"><StatusBadge :status="row.is_active ? 'active' : 'inactive'" /></template>
        <template #cell-last="{ row }"><span class="text-body-sm text-on-surface-variant">{{ row.last_login_at ? ago(row.last_login_at) : 'لسه مدخلش' }}</span></template>
        
        <!-- إجراءات الصف -->
        <template #cell-actions="{ row }">
          <div class="flex items-center justify-end gap-1.5" @click.stop>
            <!-- زر تسجيل الدخول بالحساب لإمام المسجد -->
            <button
              v-if="auth.isImam && row.id !== auth.user?.id"
              class="inline-flex h-9 items-center gap-1 rounded-lg border border-amber-600/30 bg-amber-50 px-2.5 text-label-sm font-bold text-amber-900 transition-colors hover:border-amber-600 hover:bg-amber-100 active:scale-95"
              title="تسجيل الدخول بهذا الحساب"
              @click="impersonateUser(row)"
            >
              <span class="material-symbols-outlined text-[18px]">switch_account</span>
              <span class="hidden xl:inline">دخول بالحساب</span>
            </button>

            <!-- زر التعديل -->
            <button
              class="flex h-9 w-9 items-center justify-center rounded-lg text-on-surface-variant transition-colors hover:bg-surface-container active:scale-95"
              title="تعديل المستخدم"
              @click="openForm(row)"
            >
              <span class="material-symbols-outlined text-[20px]">edit</span>
            </button>

            <!-- زر الحذف -->
            <button
              v-if="row.id !== auth.user?.id"
              class="flex h-9 w-9 items-center justify-center rounded-lg text-error transition-colors hover:bg-error-container/30 active:scale-95"
              title="حذف المستخدم"
              @click="deleteUser(row)"
            >
              <span class="material-symbols-outlined text-[20px]">delete</span>
            </button>
          </div>
        </template>

        <!-- كارت الموبايل -->
        <template #card="{ row }">
          <div class="flex items-center gap-3">
            <PersonAvatar :name="row.name" :id="row.id" />
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <p class="font-bold">{{ row.name }}</p>
                <span v-if="row.id === auth.user?.id" class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">حسابك</span>
              </div>
              <p class="text-body-sm text-on-surface-variant">{{ row.role_label }} · <span dir="ltr">{{ row.phone }}</span></p>
              <p v-if="row.custodian" class="text-label-sm text-tertiary">عهدة: {{ row.custodian.name }}</p>
            </div>
            <StatusBadge :status="row.is_active ? 'active' : 'inactive'" />
          </div>

          <div class="mt-3 flex items-center justify-end gap-2 border-t border-line/60 pt-2.5" @click.stop>
            <button
              v-if="auth.isImam && row.id !== auth.user?.id"
              class="inline-flex h-8 items-center gap-1 rounded-lg border border-amber-600/30 bg-amber-50 px-2.5 text-label-sm font-bold text-amber-900 active:bg-amber-100"
              @click="impersonateUser(row)"
            >
              <span class="material-symbols-outlined text-[16px]">switch_account</span>
              <span>دخول بالحساب</span>
            </button>
            <button
              class="inline-flex h-8 items-center gap-1 rounded-lg bg-surface-container px-2.5 text-label-sm font-bold text-on-surface active:bg-surface-container-high"
              @click="openForm(row)"
            >
              <span class="material-symbols-outlined text-[16px]">edit</span>
              <span>تعديل</span>
            </button>
            <button
              v-if="row.id !== auth.user?.id"
              class="inline-flex h-8 items-center gap-1 rounded-lg border border-error/20 bg-error-container/20 px-2.5 text-label-sm font-bold text-error active:bg-error-container/40"
              @click="deleteUser(row)"
            >
              <span class="material-symbols-outlined text-[16px]">delete</span>
              <span>حذف</span>
            </button>
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

    <!-- درج إضافة وتعديل المستخدم -->
    <AppDrawer v-model="drawer" :title="editing ? `تعديل ${editing.name}` : 'إضافة مستخدم'" subtitle="المستخدم بيدخل برقم الموبايل وكلمة المرور">
      <div class="space-y-5">
        <!-- كارت محاكاة الحساب للإمام -->
        <div v-if="editing && auth.isImam && editing.id !== auth.user?.id" class="rounded-xl border border-amber-300 bg-amber-50 p-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="flex items-center gap-1.5 font-bold text-amber-950">
                <span class="material-symbols-outlined text-[20px] text-amber-700">switch_account</span>
                تسجيل الدخول بحساب {{ editing.name }}
              </p>
              <p class="text-body-sm text-amber-900/80">التبديل الفوري لتجربة واستخدام النظام بصلاحيات ودور هذا الحساب.</p>
            </div>
            <button
              type="button"
              class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-amber-600 px-3.5 py-2 text-label-md font-bold text-white shadow-sm transition-all hover:bg-amber-700 active:scale-95"
              @click="impersonateUser(editing)"
            >
              <span class="material-symbols-outlined text-[18px]">login</span>
              <span>دخول بالحساب</span>
            </button>
          </div>
        </div>

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
            <label class="field-label" for="u-pass">
              كلمة المرور
              <span v-if="editing" class="text-body-sm font-normal text-on-surface-variant">(اتركها فارغة للإبقاء على الحالية، أو اكتب كلمة جديدة لإعادة التعيين)</span>
            </label>
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
      </div>

      <template #footer>
        <div class="flex items-center gap-3">
          <button class="btn btn-primary btn-lg flex-1" :disabled="saving" @click="save">
            <span class="material-symbols-outlined">check</span>
            {{ editing ? 'حفظ التعديلات' : 'إضافة المستخدم' }}
          </button>
          <button
            v-if="editing && editing.id !== auth.user?.id"
            type="button"
            class="btn btn-lg border border-error/30 text-error hover:bg-error-container/20"
            title="حذف الحساب نهائياً"
            @click="deleteUser(editing)"
          >
            <span class="material-symbols-outlined text-[20px]">delete</span>
            <span>حذف</span>
          </button>
        </div>
      </template>
    </AppDrawer>
  </div>
</template>
