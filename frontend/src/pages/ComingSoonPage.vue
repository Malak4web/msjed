<script setup>
import { useRoute } from 'vue-router'
import PageHeader from '@/components/PageHeader.vue'
import EmptyState from '@/components/EmptyState.vue'

const route = useRoute()
const phaseNames = { 2: 'التانية', 3: 'التالتة', 4: 'الرابعة' }
</script>

<template>
  <div>
    <PageHeader :title="route.meta.title" />
    <div class="card border border-line">
      <EmptyState icon="construction" title="الشاشة دي تحت التنفيذ" :text="`هتكون جاهزة في المرحلة ${phaseNames[route.meta.phase] || 'الجاية'} إن شاء الله.`">
        <RouterLink to="/" class="btn btn-outline">الرجوع للرئيسية</RouterLink>
      </EmptyState>
    </div>
  </div>
</template>
