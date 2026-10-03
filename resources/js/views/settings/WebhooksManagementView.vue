<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">وب‌هوک‌ها و سلامت ادغام</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          پایش بلادرنگ رویدادهای دریافتی از ووکامرس، اعتبارسنجی امضا، بررسی Idempotency و ابزار مدیریت خطا
        </p>
      </div>

      <div class="flex items-center gap-2">
        <select
          v-model="selectedStoreId"
          @change="onStoreChange"
          class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option :value="null">همه فروشگاه‌ها</option>
          <option v-for="store in stores" :key="store.id" :value="store.id">
            {{ store.name }}
          </option>
        </select>

        <RefreshButton
          @click="loadData"
          :loading="loading"
          label="به‌روزرسانی"
          title="بروزرسانی داده‌ها"
        />

        <router-link
          to="/settings/stores"
          class="flex items-center gap-1.5 px-3 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition-all"
        >
          <Iconsax name="shop" size="16" />
          <span>پیکربندی فروشگاه‌ها</span>
        </router-link>
      </div>
    </div>

    <!-- Health Metrics Banner -->
    <div v-if="health" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Webhook Health Card -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-400">وضعیت وب‌هوک</span>
          <span
            class="w-8 h-8 rounded-xl flex items-center justify-center"
            :class="getHealthBg(health.webhook_health)"
          >
            <Iconsax :name="health.webhook_health === 'healthy' ? 'check' : 'warning-2'" size="18" />
          </span>
        </div>
        <div class="mt-3">
          <div class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>{{ getHealthLabel(health.webhook_health) }}</span>
            <span
              class="text-[10px] font-bold px-2 py-0.5 rounded-full"
              :class="getHealthBadgeClass(health.webhook_health)"
            >
              {{ health.failed_webhooks_24h > 0 ? toPersianDigits(health.failed_webhooks_24h) + ' خطای اخیر' : 'بدون خطا' }}
            </span>
          </div>
          <div class="text-[11px] text-slate-400 mt-1">
            میانگین پردازش: {{ health.avg_processing_time_ms ? toPersianDigits(health.avg_processing_time_ms) + ' میلی‌ثانیه' : '—' }}
          </div>
        </div>
      </div>

      <!-- Total Events -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-400">رویدادهای دریافتی</span>
          <span class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
            <Iconsax name="activity" size="18" />
          </span>
        </div>
        <div class="mt-3">
          <div class="text-lg font-bold text-slate-900 dark:text-white">
            {{ formatNumber(health.total_webhooks) }}
          </div>
          <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-2">
            <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ formatNumber(health.processed_webhooks) }} موفق</span>
            <span class="text-slate-300">|</span>
            <span class="text-amber-600 dark:text-amber-400">{{ formatNumber(health.total_webhooks - health.processed_webhooks) }} سایر</span>
          </div>
        </div>
      </div>

      <!-- Last Webhook Time -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-400">آخرین وب‌هوک دریافتی</span>
          <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
            <Iconsax name="clock" size="18" />
          </span>
        </div>
        <div class="mt-3">
          <div class="text-sm font-semibold text-slate-900 dark:text-white truncate" :title="health.last_webhook_at">
            {{ health.last_webhook_at ? formatDateTime(health.last_webhook_at) : 'رویدادی ثبت نشده' }}
          </div>
          <div class="text-[11px] text-slate-400 mt-1">
            وضعیت اتصال ووکامرس:
            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ health.is_connected ? 'برقرار' : 'قطع' }}</span>
          </div>
        </div>
      </div>

      <!-- Last Reconciliation -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-400">آخرین تطبیق (Reconciliation)</span>
          <span class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
            <Iconsax name="refresh" size="18" />
          </span>
        </div>
        <div class="mt-3">
          <div class="text-sm font-semibold text-slate-900 dark:text-white truncate" :title="health.last_reconciliation_at">
            {{ health.last_reconciliation_at ? formatDateTime(health.last_reconciliation_at) : 'تاکنون اجرا نشده' }}
          </div>
          <div class="text-[11px] text-slate-400 mt-1">
            کل دفعات تطبیق: <span class="font-bold text-slate-700 dark:text-slate-300">{{ toPersianDigits(health.total_reconciliations) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
      <!-- Status Tabs -->
      <div class="flex items-center gap-1 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
        <button
          v-for="st in statusTabs"
          :key="st.key"
          @click="setStatusFilter(st.key)"
          class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-1.5"
          :class="filters.status === st.key ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-600/20' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
        >
          <span>{{ st.label }}</span>
        </button>
      </div>

      <!-- Search Input -->
      <div class="relative w-full md:w-72">
        <input
          v-model="filters.search"
          @input="debounceSearch"
          type="text"
          placeholder="جستجو در شناسه، رویداد یا ارسال..."
          class="w-full pr-9 pl-4 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        />
        <span class="absolute right-3 top-2.5 text-slate-400">
          <Iconsax name="search-normal" size="16" />
        </span>
      </div>
    </div>

    <!-- Webhook Logs Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
      <div v-if="loading && logs.length === 0" class="p-16 text-center">
        <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <div class="text-xs text-slate-400">در حال دریافت رویدادهای وب‌هوک...</div>
      </div>

      <div v-else-if="logs.length === 0" class="p-12 text-center">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
          <Iconsax name="receipt-2" size="24" />
        </div>
        <div class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">هیچ لاگ وب‌هوکی یافت نشد</div>
        <div class="text-xs text-slate-400">
          در صورت ارسال رویداد جدید از سوی ووکامرس، تاریخچه آن در این بخش ثبت خواهد شد.
        </div>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-right text-xs">
          <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th class="p-4">شناسه</th>
              <th class="p-4">وضعیت</th>
              <th class="p-4">رویداد (Event)</th>
              <th class="p-4">شناسه منبع (Resource ID)</th>
              <th class="p-4">شناسه تحویل (Delivery ID)</th>
              <th class="p-4">زمان پردازش</th>
              <th class="p-4">تاریخ ثبت</th>
              <th class="p-4 text-center">عملیات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="log in logs"
              :key="log.id"
              class="hover:bg-slate-50/60 dark:hover:bg-slate-850/50 transition-colors"
            >
              <!-- ID -->
              <td class="p-4 font-bold text-slate-500">
                #{{ toPersianDigits(log.id) }}
              </td>

              <!-- Status -->
              <td class="p-4">
                <span
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold"
                  :class="getStatusBadgeClass(log.status)"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(log.status)"></span>
                  <span>{{ getStatusLabel(log.status) }}</span>
                </span>
              </td>

              <!-- Event & Topic -->
              <td class="p-4">
                <div class="font-bold text-slate-900 dark:text-white dir-ltr text-right">{{ log.event }}</div>
                <div class="text-[10px] text-slate-400 dir-ltr text-right">{{ log.topic }}</div>
              </td>

              <!-- Resource ID -->
              <td class="p-4">
                <span v-if="log.resource_id" class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">
                  {{ toPersianDigits(log.resource_id) }}
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>

              <!-- Delivery ID -->
              <td class="p-4 text-slate-500 dir-ltr text-right">
                <span v-if="log.delivery_id" :title="log.delivery_id" class="truncate block max-w-[150px]">
                  {{ log.delivery_id }}
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>

              <!-- Processing Time -->
              <td class="p-4 text-slate-600 dark:text-slate-400">
                <span v-if="log.processing_time_ms !== null" :class="{ 'text-amber-600 font-bold': log.processing_time_ms > 500 }">
                  {{ toPersianDigits(log.processing_time_ms) }} میلی‌ثانیه
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>

              <!-- Created At -->
              <td class="p-4 text-slate-500 text-[11px]">
                {{ log.created_at ? formatDateTime(log.created_at) : '—' }}
              </td>

              <!-- Actions -->
              <td class="p-4 text-center">
                <div class="flex items-center justify-center gap-1">
                  <!-- View Details -->
                  <button
                    @click="viewLogDetails(log)"
                    class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    title="مشاهده جزئیات و داده‌های رویداد"
                  >
                    <Iconsax name="eye" size="16" />
                  </button>

                  <!-- Retry Button (for failed logs) -->
                  <button
                    v-if="log.status === 'failed' && authStore.hasPermission('webhooks.retry')"
                    @click="retryLog(log)"
                    :disabled="retryingId === log.id"
                    class="p-1.5 rounded-lg text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/60 transition-colors disabled:opacity-50"
                    title="تلاش مجدد برای پردازش"
                  >
                    <Iconsax name="refresh" size="16" :class="{ 'animate-spin': retryingId === log.id }" />
                  </button>

                  <!-- Ignore Button -->
                  <button
                    v-if="log.status === 'failed' && authStore.hasPermission('webhooks.manage')"
                    @click="ignoreLog(log)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    title="نادیده‌گرفتن رویداد"
                  >
                    <Iconsax name="close-circle" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="meta && meta.last_page > 1" class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div class="text-xs text-slate-400">
          نمایش صفحه {{ meta.current_page }} از {{ meta.last_page }} (مجموع {{ meta.total }} رویداد)
        </div>
        <div class="flex items-center gap-1">
          <button
            :disabled="meta.current_page <= 1"
            @click="changePage(meta.current_page - 1)"
            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-semibold disabled:opacity-40"
          >
            قبلی
          </button>
          <button
            :disabled="meta.current_page >= meta.last_page"
            @click="changePage(meta.current_page + 1)"
            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-semibold disabled:opacity-40"
          >
            بعدی
          </button>
        </div>
      </div>
    </div>

    <!-- Log Details Modal -->
    <div
      v-if="activeLog"
      class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-2xl max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
        <!-- Modal Header -->
        <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Iconsax name="receipt-2" size="18" />
            </span>
            <div>
              <h3 class="font-bold text-sm text-slate-900 dark:text-white">جزئیات وب‌هوک #{{ toPersianDigits(activeLog.id) }}</h3>
              <p class="text-[11px] text-slate-400 dir-ltr text-right">{{ activeLog.event }} | {{ activeLog.topic }}</p>
            </div>
          </div>
          <button @click="activeLog = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1">
            <Iconsax name="close-circle" size="20" />
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 overflow-y-auto space-y-4 text-xs">
          <!-- Status & Meta Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-3.5 bg-slate-50 dark:bg-slate-850 rounded-2xl border border-slate-100 dark:border-slate-800/80">
            <div>
              <div class="text-[10px] text-slate-400">وضعیت</div>
              <div class="font-bold mt-0.5" :class="getStatusTextColor(activeLog.status)">
                {{ getStatusLabel(activeLog.status) }}
              </div>
            </div>
            <div>
              <div class="text-[10px] text-slate-400">شناسه منبع</div>
              <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                {{ activeLog.resource_id ? toPersianDigits(activeLog.resource_id) : '—' }}
              </div>
            </div>
            <div>
              <div class="text-[10px] text-slate-400">تعداد تلاش</div>
              <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                {{ toPersianDigits(activeLog.attempt) }}
              </div>
            </div>
            <div>
              <div class="text-[10px] text-slate-400">زمان پردازش</div>
              <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                {{ activeLog.processing_time_ms ? toPersianDigits(activeLog.processing_time_ms) + ' میلی‌ثانیه' : '—' }}
              </div>
            </div>
          </div>

          <!-- Error notice if failed -->
          <div v-if="activeLog.error_message" class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-rose-700 dark:text-rose-300 space-y-1">
            <div class="font-bold flex items-center gap-1.5">
              <Iconsax name="warning-2" size="16" />
              <span>پیام خطا:</span>
            </div>
            <div class="text-[11px] leading-relaxed">{{ activeLog.error_message }}</div>
          </div>

          <!-- Delivery ID & IP -->
          <div class="space-y-1 text-slate-500 text-[11px] p-3 rounded-xl bg-slate-100/60 dark:bg-slate-950/40 border border-slate-200/50 dark:border-slate-800/60">
            <div><span class="text-slate-400">Delivery ID: </span>{{ activeLog.delivery_id || '—' }}</div>
            <div><span class="text-slate-400">IP Address: </span>{{ activeLog.ip_address || '—' }}</div>
            <div><span class="text-slate-400">Created At: </span>{{ activeLog.created_at }}</div>
            <div v-if="activeLog.processed_at"><span class="text-slate-400">Processed At: </span>{{ activeLog.processed_at }}</div>
          </div>

          <!-- Payload JSON Box -->
          <div>
            <div class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
              <span>داده دریافتی (Payload - اطلاعات حساس سانسور شده است):</span>
              <button
                @click="copyPayload(activeLog.payload)"
                class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline"
              >
                کپی داده‌ها
              </button>
            </div>
            <pre class="p-3.5 rounded-2xl bg-slate-950 text-emerald-400 text-[11px] overflow-x-auto max-h-60 dir-ltr text-left border border-slate-800">{{ JSON.stringify(activeLog.payload, null, 2) }}</pre>
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <button
              v-if="activeLog.status === 'failed' && authStore.hasPermission('webhooks.retry')"
              @click="retryLog(activeLog)"
              :disabled="retryingId === activeLog.id"
              class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs flex items-center gap-1.5 transition-all disabled:opacity-50"
            >
              <Iconsax name="refresh" size="16" :class="{ 'animate-spin': retryingId === activeLog.id }" />
              <span>تلاش مجدد</span>
            </button>

            <button
              v-if="activeLog.status === 'failed' && authStore.hasPermission('webhooks.manage')"
              @click="ignoreLog(activeLog)"
              class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition-all"
            >
              نادیده‌گرفتن
            </button>
          </div>

          <button
            @click="activeLog = null"
            class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200"
          >
            بستن
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import axios from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import { formatDateTime, formatNumber, toPersianDigits } from '@/utils/formatters';

const authStore = useAuthStore();

const stores = ref([]);
const selectedStoreId = ref(null);
const loading = ref(false);
const logs = ref([]);
const meta = ref(null);
const health = ref(null);
const retryingId = ref(null);
const activeLog = ref(null);

const filters = ref({
  status: 'all',
  search: '',
  page: 1,
});

const statusTabs = [
  { key: 'all', label: 'همه رویدادها' },
  { key: 'failed', label: 'خطاها (Failed)' },
  { key: 'processed', label: 'موفق (Processed)' },
  { key: 'duplicate', label: 'تکراری (Duplicate)' },
  { key: 'ignored', label: 'نادیده‌گرفته (Ignored)' },
  { key: 'processing', label: 'در حال پردازش' },
];

let searchTimeout = null;
const debounceSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    filters.value.page = 1;
    loadLogs();
  }, 350);
};

const setStatusFilter = (status) => {
  filters.value.status = status;
  filters.value.page = 1;
  loadLogs();
};

const changePage = (page) => {
  filters.value.page = page;
  loadLogs();
};

const onStoreChange = () => {
  filters.value.page = 1;
  loadData();
};

const loadStores = async () => {
  try {
    const res = await axios.get('/stores');
    stores.value = Array.isArray(res.data) ? res.data : (res.data?.data || res.data || []);
    if (!selectedStoreId.value && stores.value.length > 0) {
      selectedStoreId.value = stores.value[0].id;
    }
  } catch (e) {
    console.error('Error loading stores:', e);
  }
};

const loadLogs = async () => {
  loading.value = true;
  try {
    const params = {
      page: filters.value.page,
      status: filters.value.status,
      search: filters.value.search,
    };
    if (selectedStoreId.value) {
      params.store_id = selectedStoreId.value;
    }

    const res = await axios.get('/webhooks', { params });
    logs.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
    meta.value = res.meta || res.data?.meta || null;
  } catch (e) {
    console.error('Error loading webhooks:', e);
  } finally {
    loading.value = false;
  }
};

const loadHealth = async () => {
  if (!selectedStoreId.value) {
    health.value = null;
    return;
  }
  try {
    const res = await axios.get(`/stores/${selectedStoreId.value}/webhook-health`);
    health.value = res.data?.data || res.data || null;
  } catch (e) {
    console.error('Error loading health:', e);
  }
};

const loadData = async () => {
  await Promise.all([loadLogs(), loadHealth()]);
};

const viewLogDetails = (log) => {
  activeLog.value = log;
};

const retryLog = async (log) => {
  retryingId.value = log.id;
  try {
    await axios.post(`/webhooks/${log.id}/retry`);
    await loadLogs();
    if (activeLog.value && activeLog.value.id === log.id) {
      activeLog.value.status = 'processed';
    }
  } catch (e) {
    alert(e.response?.data?.message || 'خطا در تلاش مجدد وب‌هوک');
  } finally {
    retryingId.value = null;
  }
};

const ignoreLog = async (log) => {
  try {
    await axios.post(`/webhooks/${log.id}/ignore`);
    await loadLogs();
    if (activeLog.value && activeLog.value.id === log.id) {
      activeLog.value.status = 'ignored';
    }
  } catch (e) {
    alert(e.response?.data?.message || 'خطا در نادیده‌گرفتن وب‌هوک');
  }
};

const copyPayload = (payload) => {
  navigator.clipboard.writeText(JSON.stringify(payload, null, 2));
  alert('داده‌ها در کلیپ‌بورد کپی شد.');
};

// Styling Helpers
const getStatusLabel = (st) => {
  switch (st) {
    case 'processed': return 'موفق';
    case 'failed': return 'ناموفق';
    case 'duplicate': return 'تکراری';
    case 'ignored': return 'نادیده‌گرفته';
    case 'processing': return 'در حال پردازش';
    default: return st;
  }
};

const getStatusBadgeClass = (st) => {
  switch (st) {
    case 'processed': return 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
    case 'failed': return 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800';
    case 'duplicate': return 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800';
    case 'ignored': return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700';
    case 'processing': return 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 animate-pulse';
    default: return 'bg-slate-100 text-slate-600';
  }
};

const getStatusDotClass = (st) => {
  switch (st) {
    case 'processed': return 'bg-emerald-500';
    case 'failed': return 'bg-rose-500';
    case 'duplicate': return 'bg-amber-500';
    case 'ignored': return 'bg-slate-400';
    case 'processing': return 'bg-indigo-500 animate-ping';
    default: return 'bg-slate-400';
  }
};

const getStatusTextColor = (st) => {
  switch (st) {
    case 'processed': return 'text-emerald-600 dark:text-emerald-400';
    case 'failed': return 'text-rose-600 dark:text-rose-400';
    case 'duplicate': return 'text-amber-600 dark:text-amber-400';
    case 'ignored': return 'text-slate-400';
    default: return 'text-slate-600';
  }
};

const getHealthLabel = (h) => {
  switch (h) {
    case 'healthy': return 'کاملاً سالم';
    case 'warning': return 'هشدار خطا';
    case 'failing': return 'وضعیت بحرانی';
    default: return 'پایش نشده';
  }
};

const getHealthBadgeClass = (h) => {
  switch (h) {
    case 'healthy': return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
    case 'warning': return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
    case 'failing': return 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300';
    default: return 'bg-slate-100 text-slate-800';
  }
};

const getHealthBg = (h) => {
  switch (h) {
    case 'healthy': return 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400';
    case 'warning': return 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400';
    case 'failing': return 'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400';
    default: return 'bg-slate-100 text-slate-600';
  }
};

onMounted(async () => {
  await loadStores();
  await loadData();
});
</script>
