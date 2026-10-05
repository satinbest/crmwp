<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col justify-between transition-all">
    <!-- Card Header -->
    <div class="flex items-center justify-between gap-3 mb-4">
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shadow-sm shadow-blue-500/50"></span>
        <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">تفکیک وضعیت سفارش‌ها</h3>
      </div>
      <router-link
        to="/orders"
        class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
      >
        <span>مشاهده سفارش‌ها</span>
        <Iconsax name="arrow-left" size="14" />
      </router-link>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="h-64 flex flex-col justify-center space-y-4 animate-pulse">
      <div class="w-full h-4 bg-slate-100 dark:bg-slate-800 rounded-full"></div>
      <div class="grid grid-cols-2 gap-3 pt-4">
        <div v-for="i in 4" :key="i" class="p-3 bg-slate-100 dark:bg-slate-800 rounded-xl h-14"></div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="statuses.length === 0 || totalCount === 0"
      class="h-64 flex flex-col items-center justify-center text-center p-6 bg-slate-50/50 dark:bg-slate-850/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-800"
    >
      <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-500 flex items-center justify-center mb-3">
        <Iconsax name="box" size="24" />
      </div>
      <h4 class="text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-300">سفارشی ثبت نشده</h4>
      <p class="text-xs text-slate-400 mt-1">سفارشی برای تحلیل وضعیت‌ها یافت نشد.</p>
    </div>

    <!-- Chart Content -->
    <div v-else class="space-y-5">
      <!-- Proportional Segment Bar -->
      <div class="space-y-1.5">
        <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 font-semibold px-1">
          <span>توزیع وضعیت‌ها</span>
          <span>مجموع: {{ formatNumber(totalCount) }} سفارش</span>
        </div>

        <div class="h-3 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden flex shadow-inner">
          <div
            v-for="st in activeStatuses"
            :key="st.status"
            class="h-full transition-all duration-500 first:rounded-r-full last:rounded-l-full relative group cursor-pointer"
            :class="getSegmentBg(st.color)"
            :style="{ width: `${Math.max(st.percentage, 2)}%` }"
            :title="`${st.label}: ${formatNumber(st.count)} (${formatPercent(st.percentage)})`"
          ></div>
        </div>
      </div>

      <!-- Grid of Status Badges & Metrics -->
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-2">
        <div
          v-for="st in activeStatuses"
          :key="st.status"
          class="p-3 rounded-xl border transition-all hover:scale-[1.02]"
          :class="getStatusCardClass(st.color)"
        >
          <div class="flex items-center justify-between gap-1 mb-1">
            <span class="text-xs font-bold truncate">{{ st.label }}</span>
            <span class="w-2 h-2 rounded-full shrink-0" :class="getStatusDotClass(st.color)"></span>
          </div>

          <div class="flex items-baseline justify-between mt-2">
            <span class="text-base sm:text-lg font-black">{{ formatNumber(st.count) }}</span>
            <span class="text-[11px] font-semibold opacity-80">{{ formatPercent(st.percentage) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import { formatNumber, formatPercent } from '@/utils/formatters';

const props = defineProps({
  statuses: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const totalCount = computed(() => {
  return props.statuses.reduce((sum, item) => sum + (Number(item.count) || 0), 0);
});

const activeStatuses = computed(() => {
  return props.statuses.filter(s => Number(s.count) > 0 || ['completed', 'processing', 'pending'].includes(s.status));
});

const getSegmentBg = (color) => {
  switch (color) {
    case 'emerald': return 'bg-emerald-500 hover:bg-emerald-600';
    case 'blue': return 'bg-blue-500 hover:bg-blue-600';
    case 'amber': return 'bg-amber-500 hover:bg-amber-600';
    case 'rose': return 'bg-rose-500 hover:bg-rose-600';
    case 'slate': return 'bg-slate-400 hover:bg-slate-500';
    default: return 'bg-indigo-500 hover:bg-indigo-600';
  }
};

const getStatusDotClass = (color) => {
  switch (color) {
    case 'emerald': return 'bg-emerald-500';
    case 'blue': return 'bg-blue-500';
    case 'amber': return 'bg-amber-500';
    case 'rose': return 'bg-rose-500';
    case 'slate': return 'bg-slate-400';
    default: return 'bg-indigo-500';
  }
};

const getStatusCardClass = (color) => {
  switch (color) {
    case 'emerald':
      return 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200/60 dark:border-emerald-900/40 text-emerald-900 dark:text-emerald-300';
    case 'blue':
      return 'bg-blue-50/50 dark:bg-blue-950/20 border-blue-200/60 dark:border-blue-900/40 text-blue-900 dark:text-blue-300';
    case 'amber':
      return 'bg-amber-50/50 dark:bg-amber-950/20 border-amber-200/60 dark:border-amber-900/40 text-amber-900 dark:text-amber-300';
    case 'rose':
      return 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-200/60 dark:border-rose-900/40 text-rose-900 dark:text-rose-300';
    case 'slate':
      return 'bg-slate-50/50 dark:bg-slate-850/40 border-slate-200/60 dark:border-slate-800/40 text-slate-800 dark:text-slate-300';
    default:
      return 'bg-indigo-50/50 dark:bg-indigo-950/20 border-indigo-200/60 dark:border-indigo-900/40 text-indigo-900 dark:text-indigo-300';
  }
};
</script>
