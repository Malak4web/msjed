<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { NAV_ITEMS } from '@/layouts/nav'
import { initials } from '@/utils/format'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()

const items = computed(() => NAV_ITEMS.filter((i) => i.to !== '/' && (!i.permission || auth.can(i.permission))))

async function logout() {
  if (await ui.ask({ title: 'تسجيل الخروج؟', confirmText: 'خروج', icon: 'logout' })) {
    await auth.logout()
    router.replace({ name: 'login' })
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="card flex items-center gap-4 border border-line p-4">
      <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary text-headline-sm font-bold text-on-primary">{{ initials(auth.user?.name) }}</div>
      <div class="flex-1">
        <p class="text-headline-sm font-bold">{{ auth.user?.name }}</p>
        <p class="text-body-sm text-on-surface-variant">{{ auth.user?.role_label }} · <span dir="ltr">{{ auth.user?.phone }}</span></p>
      </div>
    </div>

    <nav class="card divide-y divide-line border border-line">
      <RouterLink to="/notifications" class="flex min-h-[60px] items-center gap-4 px-4 hover:bg-surface-container-low">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-tertiary-fixed text-on-tertiary-fixed-variant">
          <span class="material-symbols-outlined">notifications</span>
        </span>
        <span class="flex-1 text-label-lg font-bold">الإشعارات</span>
        <span v-if="auth.user?.unread_notifications" class="rounded-full bg-tertiary-container px-2 text-label-sm font-bold">{{ auth.user.unread_notifications }}</span>
        <span class="material-symbols-outlined text-on-surface-variant">chevron_left</span>
      </RouterLink>
      <RouterLink v-for="item in items" :key="item.to" :to="item.to" class="flex min-h-[60px] items-center gap-4 px-4 hover:bg-surface-container-low">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-secondary-container text-on-secondary-fixed-variant">
          <span class="material-symbols-outlined">{{ item.icon }}</span>
        </span>
        <span class="flex-1 text-label-lg font-bold">{{ item.label }}</span>
        <span class="material-symbols-outlined text-on-surface-variant">chevron_left</span>
      </RouterLink>
    </nav>

    <button class="btn btn-ghost w-full bg-surface-container-lowest text-error" @click="logout">
      <span class="material-symbols-outlined">logout</span>
      تسجيل الخروج
    </button>
  </div>
</template>
