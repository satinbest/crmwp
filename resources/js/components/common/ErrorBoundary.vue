<template>
  <div v-if="hasError" class="min-h-[400px] flex items-center justify-center p-6">
    <div class="max-w-lg w-full bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-900/60 rounded-3xl p-6 shadow-xl text-center space-y-4">
      <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-2xl border border-rose-100 dark:border-rose-900/50">
        ⚠️
      </div>
      <div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white">خطایی در پردازش این بخش رخ داده است</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          رابط کاربری با خطای غیرمنتظره‌ای مواجه شد. می‌توانید صفحه را مجدداً بارگذاری نمایید.
        </p>
      </div>

      <!-- Development / Error Details -->
      <div v-if="errorDetails" class="text-right p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-rose-600 dark:text-rose-400 overflow-x-auto max-h-40">
        <div class="font-bold text-[11px] text-slate-500 mb-1 font-sans">پیام خطا:</div>
        <div class="font-mono dir-ltr text-left">{{ errorDetails }}</div>
      </div>

      <div class="flex items-center justify-center gap-3 pt-2">
        <button
          @click="retry"
          class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md shadow-indigo-500/20 transition-all flex items-center gap-1.5"
        >
          <span>تلاش مجدد</span>
          <span>↺</span>
        </button>
        <button
          @click="goHome"
          class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-medium transition-all"
        >
          صفحه اصلی / ورود
        </button>
      </div>
    </div>
  </div>
  <slot v-else />
</template>

<script setup>
import { ref, onErrorCaptured } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const hasError = ref(false);
const errorDetails = ref('');

onErrorCaptured((err, instance, info) => {
  console.error('ErrorBoundary captured error:', err, info);
  hasError.value = true;
  errorDetails.value = (err && (err.message || String(err))) || 'Unknown runtime error';
  return false; // Prevent error from propagating further up
});

const retry = () => {
  hasError.value = false;
  errorDetails.value = '';
  window.location.reload();
};

const goHome = () => {
  hasError.value = false;
  errorDetails.value = '';
  router.push('/login');
};
</script>
