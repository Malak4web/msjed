/** عناصر القائمة الجانبية — permission: لو محتاجة صلاحية */
export const NAV_ITEMS = [
  { to: '/', name: 'dashboard', label: 'الرئيسية', icon: 'dashboard' },
  { to: '/incomes', name: 'incomes', label: 'الإيرادات', icon: 'payments' },
  { to: '/requests', name: 'requests', label: 'طلبات الصرف', icon: 'assignment_late', badge: 'pending' },
  { to: '/balances', name: 'balances', label: 'الأرصدة مع الأشخاص', icon: 'account_balance_wallet' },
  { to: '/transfers', name: 'transfers', label: 'تسليم فلوس', icon: 'swap_horiz' },
  { to: '/recurring', name: 'recurring', label: 'المصروفات الثابتة', icon: 'event_repeat' },
  { to: '/reports', name: 'reports', label: 'التقارير', icon: 'monitoring', permission: 'reports.view' },
  { to: '/settings', name: 'settings', label: 'المستخدمين والإعدادات', icon: 'settings', permission: 'settings.manage' },
]

/**
 * الإجراءات السريعة (زرار + في الموبايل وأزرار الداشبورد).
 * الألوان والنصوص من شاشة Stitch "إجراء عملية مالية سريعة".
 */
export const QUICK_ACTIONS = [
  {
    key: 'income',
    label: 'تسجيل إيراد',
    title: 'تسجيل إيراد جديد وتوريده للعهدة',
    hint: 'فلوس دخلت المسجد',
    subtitle: 'سند قبض وإثبات تحصيل',
    description: 'إثبات استلام كاش أو إنستاباي أو تبرع مصلين وإيداعه في عهدة أحد الأشخاص.',
    badge: 'يضاف للرصيد فورًا',
    cta: 'بدء تسجيل الإيراد',
    icon: 'add_card',
    permission: 'incomes.create',
    tone: 'bg-inflow-bg text-inflow-text',
    sheet: { icon: 'bg-primary-container text-on-primary', title: 'text-primary', sub: 'text-secondary', badge: 'bg-secondary-fixed text-on-secondary-fixed-variant', cta: 'text-primary' },
  },
  {
    key: 'request',
    label: 'طلب صرف',
    title: 'تقديم طلب صرف جديد',
    hint: 'هيتعرض على الإمام',
    subtitle: 'مستلزمات، فواتير، وصيانة',
    description: 'طلب لشراء مستلزمات أو سداد فاتورة، بيتبعت تلقائيًا للإمام عشان يوافق قبل الصرف.',
    badge: 'يتطلب موافقة الإمام',
    cta: 'إنشاء طلب صرف',
    icon: 'request_quote',
    permission: 'expenses.create',
    to: '/requests/new',
    tone: 'bg-pending-bg text-pending-text',
    sheet: { icon: 'bg-tertiary-container text-on-tertiary', title: 'text-tertiary', sub: 'text-tertiary', badge: 'bg-tertiary-fixed text-on-tertiary-fixed-variant', cta: 'text-tertiary' },
  },
  {
    key: 'transfer',
    label: 'تسليم فلوس لشخص',
    title: 'تسليم ونقل عهدة بين شخصين',
    hint: 'من عهدة لعهدة',
    subtitle: 'مناقلة نقدية داخلية',
    description: 'تحويل مبلغ من عهدة شخص لشخص تاني، والرصيد بيتحدّث عند الاتنين في نفس اللحظة.',
    badge: 'مطابقة فورية للعهد',
    cta: 'توثيق تسليم العهدة',
    icon: 'swap_horiz',
    permission: 'transfers.create',
    to: '/transfers',
    tone: 'bg-secondary-container text-on-secondary-fixed-variant',
    sheet: { icon: 'bg-secondary text-on-secondary', title: 'text-on-surface', sub: 'text-secondary', badge: 'bg-secondary-fixed-dim text-on-secondary-fixed', cta: 'text-secondary' },
  },
]
