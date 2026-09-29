<template>
  <ErrorBoundary>
    <router-view />
  </ErrorBoundary>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useThemeStore } from '@/stores/theme';
import ErrorBoundary from '@/components/common/ErrorBoundary.vue';

const themeStore = useThemeStore();
const router = useRouter();

onMounted(() => {
  themeStore.init();

  window.addEventListener('auth:unauthorized', () => {
    // Only redirect if not already on the login page
    if (router.currentRoute.value.path !== '/login') {
      router.push('/login');
    }
  });
});
</script>
