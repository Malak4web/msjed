import { createRouter, createWebHistory, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue'), meta: { guest: true, title: 'تسجيل الدخول' } },
  {
    path: '/',
    component: () => import('@/layouts/AppShell.vue'),
    children: [
      // tab: شاشة رئيسية في الشريط السفلي (هيدر المسجد). غير كده: شاشة داخلية بزرار رجوع لـ back
      { path: '', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue'), meta: { title: 'الرئيسية', tab: true } },
      { path: 'requests', name: 'requests', component: () => import('@/pages/requests/RequestsPage.vue'), meta: { title: 'طلبات الصرف', tab: true } },
      { path: 'balances', name: 'balances', component: () => import('@/pages/BalancesPage.vue'), meta: { title: 'الأرصدة مع الأشخاص', tab: true } },
      { path: 'more', name: 'more', component: () => import('@/pages/MorePage.vue'), meta: { title: 'المزيد', tab: true } },
      { path: 'incomes', name: 'incomes', component: () => import('@/pages/IncomesPage.vue'), meta: { title: 'الإيرادات', back: '/more' } },
      { path: 'requests/new', name: 'request-new', component: () => import('@/pages/requests/RequestNewPage.vue'), meta: { title: 'طلب صرف جديد', permission: 'expenses.create', back: '/requests', hideTabBar: true } },
      { path: 'transfers', name: 'transfers', component: () => import('@/pages/TransfersPage.vue'), meta: { title: 'تسليم فلوس', back: '/more' } },
      { path: 'balances/:id(\\d+)', name: 'statement', component: () => import('@/pages/StatementPage.vue'), meta: { title: 'كشف الحساب', back: '/balances' } },
      { path: 'recurring', name: 'recurring', component: () => import('@/pages/RecurringPage.vue'), meta: { title: 'المصروفات الثابتة', back: '/more' } },
      { path: 'reports', name: 'reports', component: () => import('@/pages/ReportsPage.vue'), meta: { title: 'التقارير', permission: 'reports.view', back: '/more' } },
      { path: 'settings', name: 'settings', component: () => import('@/pages/settings/SettingsPage.vue'), meta: { title: 'الإعدادات', permission: 'settings.manage', back: '/more' } },
      { path: 'notifications', name: 'notifications', component: () => import('@/pages/NotificationsPage.vue'), meta: { title: 'الإشعارات', back: '/' } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  // Electron (file://) محتاج hash: VITE_ROUTER_MODE=hash
  history: import.meta.env.VITE_ROUTER_MODE === 'hash' ? createWebHashHistory() : createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.loaded) await auth.fetchMe()

  if (!to.meta.guest && !auth.isLoggedIn) return { name: 'login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} }
  if (to.meta.guest && auth.isLoggedIn) return { name: 'dashboard' }
  if (to.meta.permission && !auth.can(to.meta.permission)) return { name: 'dashboard' }
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} — حسابات المسجد` : 'حسابات مسجد خالد بن الوليد'
})

export default router
