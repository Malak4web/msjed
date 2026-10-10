<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import UsersTab from './UsersTab.vue'
import CustodiansTab from './CustodiansTab.vue'
import LookupTab from './LookupTab.vue'
import MosqueTab from './MosqueTab.vue'

const route = useRoute()
const router = useRouter()

const tabs = [
  { key: 'users', label: 'المستخدمين والصلاحيات', icon: 'manage_accounts' },
  { key: 'custodians', label: 'حاملي العُهد', icon: 'groups' },
  { key: 'income-sources', label: 'مصادر الإيراد', icon: 'volunteer_activism' },
  { key: 'payment-methods', label: 'طرق الدفع', icon: 'credit_card' },
  { key: 'expense-categories', label: 'بنود الصرف', icon: 'category' },
  { key: 'mosque', label: 'بيانات المسجد والنظام', icon: 'mosque' },
]

const active = ref(tabs.some((t) => t.key === route.query.tab) ? route.query.tab : 'users')
watch(active, (tab) => router.replace({ query: { tab } }))

const lookupConf = {
  'income-sources': { title: 'مصادر الإيراد', singular: 'مصدر' },
  'payment-methods': { title: 'طرق الدفع', singular: 'طريقة دفع' },
  'expense-categories': { title: 'بنود الصرف', singular: 'بند', withType: true },
}
</script>

<template>
  <div>
    <PageHeader title="المستخدمين والإعدادات" subtitle="مين يدخل النظام، ومين معاه عهدة، وقوائم الاختيارات" />

    <div class="scrollbar-none -mx-space-sm mb-5 flex gap-2 overflow-x-auto px-space-sm md:mx-0 md:px-0" role="tablist">
      <button
        v-for="t in tabs"
        :key="t.key"
        role="tab"
        :aria-selected="active === t.key"
        class="flex shrink-0 items-center gap-1.5 rounded-full px-4 py-2.5 text-label-md transition-colors"
        :class="active === t.key ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-highest text-on-surface-variant hover:bg-surface-container-high'"
        @click="active = t.key"
      >
        <span class="material-symbols-outlined text-[18px]">{{ t.icon }}</span>
        {{ t.label }}
      </button>
    </div>

    <UsersTab v-if="active === 'users'" />
    <CustodiansTab v-else-if="active === 'custodians'" />
    <MosqueTab v-else-if="active === 'mosque'" />
    <LookupTab v-else :key="active" :type="active" v-bind="lookupConf[active]" />
  </div>
</template>
