<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col justify-between transition-all">
    <!-- Header with Tabs -->
    <div class="flex items-center justify-between gap-3 mb-4">
      <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl text-xs font-bold">
        <button
          @click="activeTab = 'recent'"
          class="px-3 py-1.5 rounded-lg transition-all"
          :class="activeTab === 'recent' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
        >
          مشتریان جدید
        </button>
        <button
          @click="activeTab = 'top'"
          class="px-3 py-1.5 rounded-lg transition-all"
          :class="activeTab === 'top' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
        >
          مشتریان برتر
        </button>
      </div>

      <router-link
        to="/customers"
        class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
      >
        <span>لیست مشتریان</span>
        <Iconsax name="arrow-left" size="14" />
      </router-link>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="space-y-3 py-2 animate-pulse">
      <div v-for="i in 4" :key="i" class="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-850 rounded-xl">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full bg-slate-200 dark:bg-slate-700"></div>
          <div class="space-y-1.5">
            <div class="w-24 h-3 bg-slate-200 dark:bg-slate-700 rounded"></div>
            <div class="w-32 h-2.5 bg-slate-200 dark:bg-slate-700 rounded"></div>
          </div>
        </div>
        <div class="w-16 h-3 bg-slate-200 dark:bg-slate-700 rounded"></div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="displayCustomers.length === 0"
      class="h-56 flex flex-col items-center justify-center text-center p-6 bg-slate-50/50 dark:bg-slate-850/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-800"
    >
      <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-500 flex items-center justify-center mb-3">
        <Iconsax name="user" size="24" />
      </div>
      <h4 class="text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-300">مشتری ثبت نشده است</h4>
      <p class="text-xs text-slate-400 mt-1">هنوز داده‌ای از مشتریان در فروشگاه همگام نشده است.</p>
    </div>

    <!-- Customers List -->
    <div v-else class="space-y-2.5">
      <div
        v-for="c in displayCustomers"
        :key="c.id"
        class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors border border-transparent hover:border-slate-100 dark:hover:border-slate-800"
      >
        <div class="flex items-center gap-3 min-w-0">
          <!-- Avatar / Initial -->
          <div
            v-if="c.avatar_url && !c.avatar_url.includes('gravatar.com')"
            class="w-9 h-9 rounded-full overflow-hidden border border-slate-200 dark:border-slate-700 shrink-0"
          >
            <img :src="c.avatar_url" alt="" class="w-full h-full object-cover" />
          </div>
          <div
            v-else
            class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-violet-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs"
          >
            {{ getCustomerInitial(c) }}
          </div>

          <div class="min-w-0">
            <router-link
              :to="'/customers/' + c.id"
              class="text-xs font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors truncate block"
            >
              {{ getCustomerName(c) }}
            </router-link>
            <div class="text-[11px] text-slate-400 truncate dir-ltr text-right">
              {{ c.email || c.phone || 'بدون ایمیل' }}
            </div>
          </div>
        </div>

        <!-- Stats: Orders & Total Spent -->
        <div class="text-left shrink-0 pl-1">
          <div class="text-xs font-black text-slate-800 dark:text-slate-100">
            {{ formatCurrency(c.total_spent || 0, currencySymbol) }}
          </div>
          <div class="text-[10px] text-slate-400">
            {{ formatNumber(c.orders_count || 0) }} سفارش
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import { formatNumber, formatCurrency } from '@/utils/formatters';

const props = defineProps({
  recentCustomers: {
    type: Array,
    default: () => [],
  },
  topCustomers: {
    type: Array,
    default: () => [],
  },
  currencySymbol: {
    type: String,
    default: 'تومان',
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const activeTab = ref('recent');

const displayCustomers = computed(() => {
  return activeTab.value === 'top' ? props.topCustomers : props.recentCustomers;
});

const getCustomerName = (c) => {
  const fullName = `${c.first_name || ''} ${c.last_name || ''}`.trim();
  return fullName || c.username || c.email || `مشتری #${c.id}`;
};

const getCustomerInitial = (c) => {
  const name = getCustomerName(c);
  return name ? name.charAt(0) : 'م';
};
</script>
