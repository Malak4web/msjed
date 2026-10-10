<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { errorMessage } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import PageHeader from '@/components/PageHeader.vue'
import PersonCard from '@/components/PersonCard.vue'
import MoneyAmount from '@/components/MoneyAmount.vue'

const ui = useUiStore()
const route = useRoute()
const router = useRouter()
const people = ref([])
const loading = ref(true)

const total = computed(() => people.value.reduce((s, p) => s + Number(p.balance), 0))
const reserved = computed(() => people.value.reduce((s, p) => s + Number(p.reserved), 0))

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/custodians')
    people.value = data.data
  } catch (e) {
    ui.error(errorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(() => ui.dataVersion, load)

function statement(person) {
  router.push(`/balances/${person.id}`)
}
</script>

<template>
  <div>
    <PageHeader title="الأرصدة مع الأشخاص" subtitle="فلوس المسجد متقسّمة مع مين، وكل واحد معاه كام" />

    <section class="mobile-scroller mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
      <div class="card relative overflow-hidden border border-line p-4">
        <div class="absolute inset-x-0 top-0 h-[3px] bg-gold"></div>
        <p class="text-label-md text-on-surface-variant">إجمالي فلوس المسجد</p>
        <MoneyAmount :value="total" size="lg" tone="primary" />
      </div>
      <div class="card border border-line p-4">
        <p class="text-label-md text-on-surface-variant">محجوز لطلبات معتمدة</p>
        <MoneyAmount :value="reserved" size="lg" tone="gold" />
      </div>
      <div class="card border border-line p-4">
        <p class="text-label-md text-on-surface-variant">متاح للصرف</p>
        <MoneyAmount :value="total - reserved" size="lg" tone="in" />
      </div>
    </section>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <template v-if="loading">
        <div v-for="i in 4" :key="i" class="card space-y-3 p-4">
          <div class="skeleton h-11 w-2/3"></div>
          <div class="skeleton h-20"></div>
        </div>
      </template>
      <PersonCard
        v-for="p in people"
        v-else
        :key="p.id"
        :person="p"
        :class="{ 'ring-2 ring-gold': Number(route.query.person) === p.id }"
        @statement="statement"
      />
    </div>
  </div>
</template>
