<template>
  <div class="space-y-6">
    <!-- 1. Header Bar: Welcome, Live Clock & Jalali Date, Store Selector, Period Filter -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-5 sm:p-6 shadow-sm">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
        <!-- User Greeting & Live Date/Time -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
          <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-violet-500 text-white flex items-center justify-center font-black text-xl shadow-md shadow-indigo-500/20 shrink-0">
            <Iconsax name="shop" size="24" />
          </div>

          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                خوش آمدید، {{ userDisplayName }}
              </h1>
              <span
                v-if="storeContext.activeStore?.is_demo"
                class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 font-bold border border-amber-200 dark:border-amber-800"
              >
                حالت دمو
              </span>
            </div>

            <!-- Live Clock & Jalali Date -->
            <div class="flex items-center gap-3 mt-1.5 text-slate-500 dark:text-slate-400">
              <!-- Live Time (Large) -->
              <div class="flex items-center gap-1.5 font-bold text-slate-800 dark:text-slate-200 text-sm sm:text-base dir-ltr">
                <Iconsax name="clock" size="16" class="text-indigo-500" />
                <span>{{ liveTimeString }}</span>
              </div>

              <span class="text-slate-300 dark:text-slate-700">•</span>

              <!-- Jalali Full Date -->
              <div class="text-xs sm:text-sm font-medium">
                {{ liveDateString }}
              </div>
            </div>
          </div>
        </div>

        <!-- Actions: Store Selector & Period Filter & Refresh -->
        <div class="flex flex-wrap items-center gap-2.5">
          <!-- Time Range Selector -->
          <div class="flex items-center bg-slate-100 dark:bg-slate-800/80 p-1 rounded-2xl border border-slate-200/60 dark:border-slate-700/60 text-xs font-semibold">
            <button
              v-for="p in periodOptions"
              :key="p.key"
              @click="changePeriod(p.key)"
              class="px-2.5 sm:px-3 py-1.5 rounded-xl transition-all"
              :class="selectedPeriod === p.key ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 font-bold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
            >
              {{ p.label }}
            </button>
          </div>

          <!-- Refresh Button -->
          <RefreshButton
            @click="refreshDashboard"
            :loading="refreshing"
            label="بروزرسانی"
            title="بروزرسانی آمار زنده داشبورد"
          />
        </div>
      </div>

      <!-- Custom Date Range Picker (shown when 'custom' is active) -->
      <transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div
          v-if="selectedPeriod === 'custom'"
          class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center gap-3 text-xs"
        >
          <span class="font-bold text-slate-700 dark:text-slate-300">انتخاب بازه دلخواه:</span>
          <div class="flex items-center gap-2">
            <label class="text-slate-400">از تاریخ:</label>
            <input
              type="date"
              v-model="customAfter"
              class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-850 text-slate-800 dark:text-slate-200 text-xs"
            />
          </div>
          <div class="flex items-center gap-2">
            <label class="text-slate-400">تا تاریخ:</label>
            <input
              type="date"
              v-model="customBefore"
              class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-850 text-slate-800 dark:text-slate-200 text-xs"
            />
          </div>
          <button
            @click="applyCustomRange"
            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-colors"
          >
            اعمال بازه
          </button>
        </div>
      </transition>
    </div>

    <!-- Store Disconnected Warning Banner (if inactive) -->
    <div
      v-if="dashboardData && !dashboardData.store?.connected && !storeContext.activeStore?.is_demo"
      class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
    >
      <div class="flex items-center gap-3">
        <span class="text-amber-600 dark:text-amber-400 text-xl">⚠️</span>
        <div class="text-xs text-amber-900 dark:text-amber-200">
          <div class="font-bold">فروشگاه «{{ storeContext.currentStoreName }}» متصل نیست یا در حالت غیرفعال قرار دارد.</div>
          <div class="text-amber-700/80 dark:text-amber-400/80 mt-0.5">جهت دریافت آمار واقعی و ارتباط زنده با ووکامرس، وضعیت اتصال فروشگاه را در بخش تنظیمات بررسی فرمایید.</div>
        </div>
      </div>
      <router-link
        to="/stores"
        class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shrink-0 text-center transition-colors shadow-xs"
      >
        تنظیمات فروشگاه
      </router-link>
    </div>

    <!-- Error State for Dashboard API -->
    <div
      v-if="errorStats"
      class="p-5 rounded-2xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/40 flex items-center justify-between"
    >
      <div class="flex items-center gap-3">
        <Iconsax name="close-circle" size="20" class="text-rose-500" />
        <span class="text-xs font-bold text-rose-700 dark:text-rose-300">{{ errorStats }}</span>
      </div>
      <button
        @click="fetchDashboardData(true)"
        class="text-xs font-bold text-rose-600 dark:text-rose-400 hover:underline"
      >
        تلاش مجدد
      </button>
    </div>

    <!-- 2. Primary KPI Cards Grid (Real WooCommerce Data) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- KPI 1: Total Sales -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm flex flex-col justify-between transition-all hover:shadow-md">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">فروش کل</span>
          <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shadow-xs">
            <Iconsax name="money-recive" size="20" />
          </div>
        </div>

        <div class="mt-3">
          <div v-if="loadingStats" class="h-8 bg-slate-100 dark:bg-slate-800 rounded animate-pulse w-3/4"></div>
          <div v-else class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
            {{ formatCurrency(kpis.total_sales, currencySymbol) }}
          </div>

          <!-- Comparison Indicator (shown only if real comparison is present) -->
          <div class="flex items-center gap-2 mt-2 text-[11px]">
            <span
              v-if="kpis.sales_change_percent !== null"
              class="font-bold flex items-center gap-1 px-1.5 py-0.5 rounded-md"
              :class="kpis.sales_change_percent >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-600'"
            >
              <span>{{ kpis.sales_change_percent >= 0 ? '↑' : '↓' }}</span>
              <span>{{ formatPercent(Math.abs(kpis.sales_change_percent), 1) }}</span>
            </span>
            <span class="text-slate-400">
              {{ kpis.sales_change_percent !== null ? 'نسبت به دوره قبل' : 'بر اساس سفارش‌های موفق' }}
            </span>
          </div>
        </div>
      </div>

      <!-- KPI 2: Total Orders -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm flex flex-col justify-between transition-all hover:shadow-md">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">تعداد سفارش‌ها</span>
          <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shadow-xs">
            <Iconsax name="bag-2" size="20" />
          </div>
        </div>

        <div class="mt-3">
          <div v-if="loadingStats" class="h-8 bg-slate-100 dark:bg-slate-800 rounded animate-pulse w-1/2"></div>
          <div v-else class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-baseline gap-1.5">
            <span>{{ formatNumber(kpis.orders_count) }}</span>
            <span class="text-xs font-normal text-slate-400">سفارش</span>
          </div>

          <div class="flex items-center gap-2 mt-2 text-[11px]">
            <span
              v-if="kpis.orders_change_percent !== null"
              class="font-bold flex items-center gap-1 px-1.5 py-0.5 rounded-md"
              :class="kpis.orders_change_percent >= 0 ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-600'"
            >
              <span>{{ kpis.orders_change_percent >= 0 ? '↑' : '↓' }}</span>
              <span>{{ formatPercent(Math.abs(kpis.orders_change_percent), 1) }}</span>
            </span>
            <span class="text-slate-400">
              {{ kpis.completed_orders > 0 ? `${formatNumber(kpis.completed_orders)} سفارش تکمیل‌شده` : 'ثبت‌شده در ووکامرس' }}
            </span>
          </div>
        </div>
      </div>

      <!-- KPI 3: Total Customers -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm flex flex-col justify-between transition-all hover:shadow-md">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">کل مشتریان</span>
          <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center shadow-xs">
            <Iconsax name="user" size="20" />
          </div>
        </div>

        <div class="mt-3">
          <div v-if="loadingStats" class="h-8 bg-slate-100 dark:bg-slate-800 rounded animate-pulse w-1/2"></div>
          <div v-else class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-baseline gap-1.5">
            <span>{{ formatNumber(kpis.customers_count) }}</span>
            <span class="text-xs font-normal text-slate-400">کاربر</span>
          </div>

          <div class="flex items-center justify-between mt-2 text-[11px] text-slate-400">
            <span>مشتریان همگام‌شده</span>
            <router-link to="/customers" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
              مشاهده لیست
            </router-link>
          </div>
        </div>
      </div>

      <!-- KPI 4: Total Products -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm flex flex-col justify-between transition-all hover:shadow-md">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">تنوع محصولات</span>
          <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shadow-xs">
            <Iconsax name="box" size="20" />
          </div>
        </div>

        <div class="mt-3">
          <div v-if="loadingStats" class="h-8 bg-slate-100 dark:bg-slate-800 rounded animate-pulse w-1/2"></div>
          <div v-else class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-baseline gap-1.5">
            <span>{{ formatNumber(kpis.products_count) }}</span>
            <span class="text-xs font-normal text-slate-400">قلم کالا</span>
          </div>

          <div class="flex items-center justify-between mt-2 text-[11px]">
            <span
              class="font-semibold"
              :class="kpis.low_stock_count > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'"
            >
              {{ kpis.low_stock_count > 0 ? `${formatNumber(kpis.low_stock_count)} کالا کم‌موجودی` : 'موجودی پایدار' }}
            </span>
            <router-link to="/products" class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
              فروشگاه
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Secondary KPI Summary Pills (Pending, Processing, Completed) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <!-- Pending Orders -->
      <div class="p-3.5 rounded-2xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 flex items-center justify-between">
        <div>
          <div class="text-[11px] font-semibold text-amber-800 dark:text-amber-300">در انتظار پرداخت / بررسی</div>
          <div class="text-lg font-black text-amber-700 dark:text-amber-400 mt-0.5">
            {{ formatNumber(kpis.pending_orders) }}
          </div>
        </div>
        <router-link to="/orders?status=pending" class="text-xs font-bold text-amber-700 dark:text-amber-400 hover:underline">
          بررسی ←
        </router-link>
      </div>

      <!-- Processing Orders -->
      <div class="p-3.5 rounded-2xl bg-blue-50/60 dark:bg-blue-950/20 border border-blue-200/60 dark:border-blue-900/40 flex items-center justify-between">
        <div>
          <div class="text-[11px] font-semibold text-blue-800 dark:text-blue-300">در حال پردازش و بسته‌بندی</div>
          <div class="text-lg font-black text-blue-700 dark:text-blue-400 mt-0.5">
            {{ formatNumber(kpis.processing_orders) }}
          </div>
        </div>
        <router-link to="/orders?status=processing" class="text-xs font-bold text-blue-700 dark:text-blue-400 hover:underline">
          مشاهده ←
        </router-link>
      </div>

      <!-- Completed Orders -->
      <div class="p-3.5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/40 flex items-center justify-between">
        <div>
          <div class="text-[11px] font-semibold text-emerald-800 dark:text-emerald-300">سفارش‌های تکمیل‌شده</div>
          <div class="text-lg font-black text-emerald-700 dark:text-emerald-400 mt-0.5">
            {{ formatNumber(kpis.completed_orders) }}
          </div>
        </div>
        <router-link to="/orders?status=completed" class="text-xs font-bold text-emerald-700 dark:text-emerald-400 hover:underline">
          مشاهده ←
        </router-link>
      </div>

      <!-- Inventory Low Stock Alert -->
      <div class="p-3.5 rounded-2xl bg-rose-50/60 dark:bg-rose-950/20 border border-rose-200/60 dark:border-rose-900/40 flex items-center justify-between">
        <div>
          <div class="text-[11px] font-semibold text-rose-800 dark:text-rose-300">کالاهای نیازمند تأمین</div>
          <div class="text-lg font-black text-rose-700 dark:text-rose-400 mt-0.5">
            {{ formatNumber(kpis.low_stock_count) }}
          </div>
        </div>
        <router-link to="/inventory" class="text-xs font-bold text-rose-700 dark:text-rose-400 hover:underline">
          انبار ←
        </router-link>
      </div>
    </div>

    <!-- 3. Sales Trend Chart & Orders Status Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
      <!-- Sales Chart (8 Cols) -->
      <div class="lg:col-span-8">
        <SalesChart
          :data="salesChartData"
          :currency-symbol="currencySymbol"
          :period-label="periodLabel"
          :loading="loadingStats"
        />
      </div>

      <!-- Orders Status Breakdown (4 Cols) -->
      <div class="lg:col-span-4">
        <OrdersStatusChart
          :statuses="orderStatuses"
          :loading="loadingStats"
        />
      </div>
    </div>

    <!-- 4. Low Stock Products & Recent/Top Customers Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
      <!-- Low Stock Widget (6 Cols) -->
      <div class="lg:col-span-6">
        <LowStockWidget
          :items="lowStockProducts"
          :currency-symbol="currencySymbol"
          :loading="loadingStats"
        />
      </div>

      <!-- Customers Widget (6 Cols) -->
      <div class="lg:col-span-6">
        <CustomersWidget
          :recent-customers="recentCustomers"
          :top-customers="topCustomers"
          :currency-symbol="currencySymbol"
          :loading="loadingStats"
        />
      </div>
    </div>

    <!-- 5. Recent Activities Feed Widget (Full Width) -->
    <RecentActivitiesWidget
      :activities="recentActivities"
      :loading="loadingStats"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useStoreContext } from '@/stores/storeContext';
import { useNotificationStore } from '@/stores/notification';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import RefreshButton from '@/components/ui/RefreshButton.vue';
import SalesChart from '@/components/dashboard/SalesChart.vue';
import OrdersStatusChart from '@/components/dashboard/OrdersStatusChart.vue';
import CustomersWidget from '@/components/dashboard/CustomersWidget.vue';
import LowStockWidget from '@/components/dashboard/LowStockWidget.vue';
import RecentActivitiesWidget from '@/components/dashboard/RecentActivitiesWidget.vue';
import {
  formatNumber,
  formatCurrency,
  formatPercent,
  formatFullJalaliDayDate,
  formatLiveClock,
} from '@/utils/formatters';

const authStore = useAuthStore();
const storeContext = useStoreContext();
const notification = useNotificationStore();

// Live Date & Time
const currentTime = ref(new Date());
let clockInterval = null;

const storeTimezone = computed(() => {
  return storeContext.activeStore?.timezone || 'Asia/Tehran';
});

const liveTimeString = computed(() => {
  return formatLiveClock(currentTime.value, storeTimezone.value);
});

const liveDateString = computed(() => {
  return formatFullJalaliDayDate(currentTime.value, storeTimezone.value);
});

const userDisplayName = computed(() => {
  return authStore.user?.full_name || authStore.user?.username || 'مدیر گرامی';
});

// Period Filter Options
const selectedPeriod = ref('last_30_days');
const customAfter = ref('');
const customBefore = ref('');

const periodOptions = [
  { key: 'today', label: 'امروز' },
  { key: 'last_7_days', label: '۷ روز اخیر' },
  { key: 'last_30_days', label: '۳۰ روز اخیر' },
  { key: 'last_90_days', label: '۹۰ روز اخیر' },
  { key: 'custom', label: 'بازه دلخواه' },
];

const periodLabel = computed(() => {
  const opt = periodOptions.find(o => o.key === selectedPeriod.value);
  return opt ? opt.label : '';
});

// Dashboard Data State
const dashboardData = ref(null);
const loadingStats = ref(false);
const refreshing = ref(false);
const errorStats = ref(null);

const currencySymbol = computed(() => {
  return dashboardData.value?.store?.currency_symbol || storeContext.currencySymbol || 'تومان';
});

const kpis = computed(() => {
  return dashboardData.value?.kpis || {
    total_sales: 0,
    net_sales: 0,
    orders_count: 0,
    customers_count: 0,
    products_count: 0,
    pending_orders: 0,
    processing_orders: 0,
    completed_orders: 0,
    low_stock_count: 0,
    sales_change_percent: null,
    orders_change_percent: null,
  };
});

const salesChartData = computed(() => dashboardData.value?.sales_chart || []);
const orderStatuses = computed(() => dashboardData.value?.order_statuses || []);
const recentCustomers = computed(() => dashboardData.value?.recent_customers || []);
const topCustomers = computed(() => dashboardData.value?.top_customers || []);
const lowStockProducts = computed(() => dashboardData.value?.low_stock_products || []);
const recentActivities = computed(() => dashboardData.value?.recent_activities || []);

const fetchDashboardData = async (forceRefresh = false) => {
  const storeId = storeContext.activeStoreId;
  if (!storeId) {
    return;
  }

  loadingStats.value = true;
  errorStats.value = null;

  try {
    const params = {
      period: selectedPeriod.value,
    };
    if (selectedPeriod.value === 'custom' && customAfter.value) {
      params.after = customAfter.value;
      params.before = customBefore.value || customAfter.value;
    }
    if (forceRefresh) {
      params.refresh = 'true';
    }

    const res = await apiClient.get('/dashboard/stats', { params });
    dashboardData.value = res.data || res;
  } catch (err) {
    console.error('Failed to load dashboard statistics', err);
    errorStats.value = err?.message || 'خطا در بارگذاری آمار داشبورد فروشگاه.';
  } finally {
    loadingStats.value = false;
  }
};

const changePeriod = (key) => {
  selectedPeriod.value = key;
  if (key !== 'custom') {
    fetchDashboardData();
  }
};

const applyCustomRange = () => {
  if (!customAfter.value) {
    notification.warning('لطفاً تاریخ شروع بازه را انتخاب نمایید.');
    return;
  }
  fetchDashboardData();
};

const refreshDashboard = async () => {
  refreshing.value = true;
  await fetchDashboardData(true);
  notification.success('اطلاعات داشبورد با موفقیت بروزرسانی شد.');
  refreshing.value = false;
};

// Watch for store changes
watch(() => storeContext.activeStoreId, (newId, oldId) => {
  if (newId && newId !== oldId) {
    fetchDashboardData(true);
  }
});

const onStoreChanged = () => {
  fetchDashboardData(true);
};

onMounted(() => {
  // Start 1-second ticker for live clock
  clockInterval = setInterval(() => {
    currentTime.value = new Date();
  }, 1000);

  window.addEventListener('store:changed', onStoreChanged);

  // Initialize data
  if (storeContext.activeStoreId) {
    fetchDashboardData();
  } else {
    // If stores are not yet loaded, wait for storeContext
    storeContext.fetchStores().then(() => {
      if (storeContext.activeStoreId) {
        fetchDashboardData();
      }
    });
  }
});

onUnmounted(() => {
  if (clockInterval) {
    clearInterval(clockInterval);
  }
  window.removeEventListener('store:changed', onStoreChanged);
});
</script>
