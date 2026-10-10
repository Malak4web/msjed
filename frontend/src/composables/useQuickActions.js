import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { QUICK_ACTIONS } from '@/layouts/nav'

export function useQuickActions() {
  const auth = useAuthStore()
  const ui = useUiStore()
  const router = useRouter()

  const actions = computed(() => QUICK_ACTIONS.filter((a) => !a.permission || auth.can(a.permission)))

  function run(action) {
    ui.actionSheet = false
    if (action.key === 'income') ui.incomeDrawer = true
    else if (action.to) router.push(action.to)
  }

  return { actions, run }
}
