<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage, fieldErrors } from '@/api/client'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const phone = ref('')
const password = ref('')
const rememberMe = ref(true)
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  if (!phone.value || !password.value) {
    error.value = 'اكتب رقم الموبايل وكلمة المرور.'
    return
  }
  loading.value = true
  try {
    const digits = phone.value.replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/\D/g, '')
    await auth.login(digits, password.value, rememberMe.value)
    router.replace(route.query.next || '/')
  } catch (e) {
    error.value = fieldErrors(e).phone || errorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-surface lg:flex-row">
    <!-- الجانب الزخرفي (ديسكتوب) -->
    <section class="relative hidden flex-1 overflow-hidden bg-forest lg:flex lg:flex-col lg:justify-between lg:p-12">
      <div
        class="absolute inset-0 opacity-[0.07]"
        style="background-image: radial-gradient(circle at 25% 25%, #c9a227 2px, transparent 2px), radial-gradient(circle at 75% 75%, #c9a227 2px, transparent 2px); background-size: 48px 48px"
      ></div>
      <div class="relative flex items-center gap-3 text-on-primary">
        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary-container">
          <span class="material-symbols-outlined text-[32px]">mosque</span>
        </div>
        <div>
          <p class="text-headline-md font-bold">مسجد خالد بن الوليد</p>
          <p class="text-body-sm text-on-primary-container">نظام العهد والمالية</p>
        </div>
      </div>
      <div class="relative max-w-lg text-on-primary">
        <p class="text-display-md font-bold leading-snug">أمانة بيت مال المسجد<br /><span class="text-gold">بوضوح وسهولة</span></p>
        <ul class="mt-6 space-y-3 text-body-lg text-white/85">
          <li class="flex items-center gap-3"><span class="material-symbols-outlined text-gold">check_circle</span>تعرف كل شخص معاه كام بالظبط</li>
          <li class="flex items-center gap-3"><span class="material-symbols-outlined text-gold">check_circle</span>مفيش صرف من غير موافقة الإمام</li>
          <li class="flex items-center gap-3"><span class="material-symbols-outlined text-gold">check_circle</span>كل جنيه متسجل بفاتورته</li>
        </ul>
      </div>
      <p class="relative text-body-sm text-white/60">﴿إِنَّمَا يَعْمُرُ مَسَاجِدَ اللَّهِ مَنْ آمَنَ بِاللَّهِ وَالْيَوْمِ الْآخِرِ﴾</p>
    </section>

    <!-- فورم الدخول -->
    <section class="flex flex-1 items-center justify-center px-4 py-10 lg:max-w-xl">
      <div class="w-full max-w-md">
        <div class="mb-8 flex flex-col items-center text-center lg:hidden">
          <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-xl bg-primary-container text-on-primary shadow-fab">
            <span class="material-symbols-outlined text-[36px]">mosque</span>
          </div>
          <p class="text-headline-md font-bold">مسجد خالد بن الوليد</p>
          <p class="text-body-sm text-on-surface-variant">نظام العهد والمالية</p>
        </div>

        <div class="card border border-line p-6 sm:p-8">
          <h1 class="text-headline-lg text-on-surface">أهلاً بيك 👋</h1>
          <p class="mb-6 mt-1 text-body-md text-on-surface-variant">ادخل برقم موبايلك وكلمة المرور</p>

          <form class="space-y-5" @submit.prevent="submit">
            <div>
              <label class="field-label" for="phone">رقم الموبايل</label>
              <div class="relative">
                <input
                  id="phone"
                  v-model="phone"
                  type="tel"
                  inputmode="tel"
                  autocomplete="username"
                  dir="ltr"
                  placeholder="01xxxxxxxxx"
                  class="field h-14 pl-12 text-left text-headline-sm tracking-wider"
                  :class="{ 'field-error': error }"
                />
                <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">smartphone</span>
              </div>
            </div>

            <div>
              <label class="field-label" for="password">كلمة المرور</label>
              <div class="relative">
                <input
                  id="password"
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  class="field h-14 pl-12"
                  :class="{ 'field-error': error }"
                />
                <button
                  type="button"
                  class="absolute left-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container"
                  :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                  @click="showPassword = !showPassword"
                >
                  <span class="material-symbols-outlined">{{ showPassword ? 'visibility_off' : 'visibility' }}</span>
                </button>
              </div>
            </div>

            <div class="flex items-center justify-between py-1">
              <label class="flex items-center gap-2.5 cursor-pointer select-none text-body-md text-on-surface hover:text-primary transition-colors">
                <input
                  v-model="rememberMe"
                  type="checkbox"
                  class="h-5 w-5 rounded border-line text-primary focus:ring-primary accent-forest cursor-pointer"
                />
                <span>تذكر تسجيل الدخول (البقاء مسجلاً)</span>
              </label>
            </div>

            <p v-if="error" class="flex items-start gap-2 rounded-lg border border-outflow-border bg-outflow-bg p-3 text-body-md font-semibold text-outflow-text" role="alert">
              <span class="material-symbols-outlined">error</span>{{ error }}
            </p>

            <button type="submit" class="btn btn-primary btn-lg w-full text-headline-sm" :disabled="loading">
              <span class="material-symbols-outlined" :class="{ 'animate-spin': loading }">{{ loading ? 'progress_activity' : 'login' }}</span>
              {{ loading ? 'جاري الدخول...' : 'دخول' }}
            </button>
          </form>
        </div>

        <p class="mt-6 text-center text-body-sm text-on-surface-variant">نسيت كلمة المرور؟ كلّم إمام المسجد أو المحاسب يغيّرها لك.</p>
      </div>
    </section>
  </div>
</template>
