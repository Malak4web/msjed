import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { handleUnauthorized } from './api/client'
import { useAuthStore } from './stores/auth'
import './fonts.css'
import './style.css'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)

handleUnauthorized(() => {
  const auth = useAuthStore(pinia)
  if (auth.token) {
    auth.clear()
    router.replace({ name: 'login' })
  }
})

app.mount('#app')
