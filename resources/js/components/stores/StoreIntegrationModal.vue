<template>
  <div
    v-if="modelValue && store"
    class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
  >
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
      <!-- Modal Header -->
      <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-lg">
            {{ store.name.charAt(0) }}
          </div>
          <div>
            <h3 class="font-extrabold text-base text-slate-900 dark:text-white flex items-center gap-2">
              <span>مدیریت وب‌هوک و تطبیق — {{ store.name }}</span>
              <span
                class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                :class="store.status === 'active' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950' : 'bg-rose-50 text-rose-600 dark:bg-rose-950'"
              >
                {{ store.status === 'active' ? 'متصل' : 'قطع ارتباط' }}
              </span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ store.url }}</p>
          </div>
        </div>

        <button @click="close" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
          <Iconsax name="close-circle" size="20" />
        </button>
      </div>

      <!-- Tabs Header -->
      <div class="px-5 pt-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2 overflow-x-auto bg-slate-50/50 dark:bg-slate-850/40">
        <button
          v-for="t in tabs"
          :key="t.key"
          @click="activeTab = t.key"
          class="px-4 py-2 text-xs font-bold transition-all border-b-2 flex items-center gap-2 shrink-0"
          :class="activeTab === t.key ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'"
        >
          <Iconsax :name="t.icon" size="16" />
          <span>{{ t.label }}</span>
        </button>
      </div>

      <!-- Tab Content Area -->
      <div class="p-6 overflow-y-auto space-y-5 flex-1 text-xs">
        <!-- ================= TAB 1: INTEGRATION HEALTH ================= -->
        <div v-if="activeTab === 'health'" class="space-y-5">
          <div v-if="loadingHealth" class="py-12 text-center text-slate-400">
            <div class="w-7 h-7 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
            <span>در حال بررسی سلامت ادغام...</span>
          </div>

          <div v-else-if="health" class="space-y-4">
            <!-- Metrics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
              <!-- WooCommerce Connection -->
              <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/80 dark:border-slate-800">
                <div class="text-[11px] text-slate-400">اتصال ووکامرس</div>
                <div class="text-base font-bold text-slate-900 dark:text-white mt-1 flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full" :class="health.is_connected ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                  <span>{{ health.is_connected ? 'برقرار و سالم' : 'خطای اتصال' }}</span>
                </div>
                <div class="text-[10px] text-slate-400 mt-1 dir-ltr text-right">
                  WC {{ health.woocommerce_version || '—' }} | WP {{ health.wordpress_version || '—' }}
                </div>
              </div>

              <!-- Webhook Health Status -->
              <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/80 dark:border-slate-800">
                <div class="text-[11px] text-slate-400">سلامت وب‌هوک (Webhook Health)</div>
                <div class="text-base font-bold mt-1 flex items-center gap-2" :class="getHealthTextColor(health.webhook_health)">
                  <span>{{ getHealthLabel(health.webhook_health) }}</span>
                  <span class="text-[10px] px-2 py-0.5 rounded-full font-bold bg-white dark:bg-slate-900 border">
                    {{ health.failed_webhooks_24h > 0 ? toPersianDigits(health.failed_webhooks_24h) + ' خطای ۲۴ ساعت گذشته' : 'عالی' }}
                  </span>
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                  میانگین تاخیر پردازش: {{ health.avg_processing_time_ms ? toPersianDigits(health.avg_processing_time_ms) + ' میلی‌ثانیه' : '—' }}
                </div>
              </div>

              <!-- Last Events -->
              <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/80 dark:border-slate-800">
                <div class="text-[11px] text-slate-400">آخرین رویداد دریافت شده</div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white mt-1 truncate" :title="health.last_webhook_at">
                  {{ health.last_webhook_at ? formatDateTime(health.last_webhook_at) : 'رویدادی ثبت نشده' }}
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                  کل رویدادهای موفق: <span class="font-bold text-emerald-600">{{ formatNumber(health.processed_webhooks) }}</span>
                </div>
              </div>
            </div>

            <!-- Principle Reminder -->
            <div class="p-4 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-200/50 dark:border-indigo-900/30 flex items-start gap-3">
              <span class="text-indigo-600 mt-0.5 shrink-0">
                <Iconsax name="shield-tick" size="18" />
              </span>
              <div class="text-slate-600 dark:text-slate-300 leading-relaxed text-[11px]">
                <strong class="text-slate-900 dark:text-white">اصل حاکم بر معماری: </strong>
                فروشگاه ووکامرس همواره منبع نهایی حقیقت (Source of Truth) است. دریافت وب‌هوک به معنای کپی‌سازی داده نیست؛ بلکه جهت پاکسازی هوشمند کش، بروزرسانی بلادرنگ پیشخوان، ایجاد فعالیت‌های تعاملی و ارسال اعلان‌ها به کار می‌رود.
              </div>
            </div>
          </div>
        </div>

        <!-- ================= TAB 2: MANUAL RECONCILIATION ================= -->
        <div v-if="activeTab === 'reconcile'" class="space-y-6">
          <div class="bg-slate-50 dark:bg-slate-850 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 space-y-4">
            <div>
              <h4 class="font-bold text-sm text-slate-900 dark:text-white">ابزار تطبیق داده‌ها (Reconciliation Tool)</h4>
              <p class="text-slate-400 text-[11px] mt-0.5">
                مقایسه وضعیت کش محلی سامانه با سرور ووکامرس، پاکسازی کش‌های منقضی و پایش سلامت ادغام بدون ایجاد بار اضافی روی سرور
              </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Entity Type -->
              <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1.5">بخش هدف برای تطبیق:</label>
                <select
                  v-model="reconcileForm.entity_type"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-slate-800 dark:text-slate-200"
                >
                  <option value="all">تمام بخش‌ها (سفارش‌ها، محصولات، انبار، مشتریان)</option>
                  <option value="orders">تنها سفارش‌ها (Orders)</option>
                  <option value="products">محصولات و موجودی انبار (Products & Inventory)</option>
                  <option value="customers">مشتریان و سگمنت‌ها (Customers & Segments)</option>
                </select>
              </div>

              <!-- Date Range -->
              <div>
                <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1.5">بازه زمانی بررسی:</label>
                <select
                  v-model="reconcileForm.date_range"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-slate-800 dark:text-slate-200"
                >
                  <option value="24h">۲۴ ساعت گذشته (توصیه شده)</option>
                  <option value="7d">۷ روز گذشته</option>
                  <option value="30d">۳۰ روز گذشته</option>
                  <option value="all">تمام داده‌ها</option>
                </select>
              </div>
            </div>

            <!-- Submit Reconcile Button -->
            <div class="flex items-center justify-between pt-2">
              <div class="text-[11px] text-slate-400">
                فرآیند تطبیق به صورت دسته‌ای (Batching) با حداکثر ۲۵ آیتم در هر درخواست اجرا می‌گردد.
              </div>
              <button
                @click="startReconciliation"
                :disabled="reconciling"
                class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-indigo-600/20 disabled:opacity-50 transition-all"
              >
                <Iconsax name="refresh" size="16" :class="{ 'animate-spin': reconciling }" />
                <span>{{ reconciling ? 'در حال اجرای تطبیق...' : 'شروع عملیات تطبیق' }}</span>
              </button>
            </div>

            <!-- Last Reconcile Result Box -->
            <div v-if="lastReconcileResult" class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-2">
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-800 dark:text-slate-200">نتیجه آخرین تطبیق:</span>
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                  :class="lastReconcileResult.status === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'"
                >
                  {{ lastReconcileResult.status === 'completed' ? 'موفقیت‌آمیز' : 'جزئی یا دارای خطا' }}
                </span>
              </div>
              <div class="text-[11px] text-slate-500 space-y-1">
                <div>مجموع آیتم‌های بررسی‌شده: <strong>{{ toPersianDigits(lastReconcileResult.processed) }}</strong></div>
                <div v-if="lastReconcileResult.details?.orders?.inspected">
                  سفارش‌ها: {{ toPersianDigits(lastReconcileResult.details.orders.inspected) }} مورد بررسی و کش‌ها نوسازی شد.
                </div>
                <div v-if="lastReconcileResult.details?.products?.inspected">
                  محصولات و موجودی: {{ toPersianDigits(lastReconcileResult.details.products.inspected) }} مورد بررسی شد.
                </div>
              </div>
            </div>
          </div>

          <!-- Sync History Table -->
          <div class="space-y-3">
            <h4 class="font-bold text-sm text-slate-900 dark:text-white">تاریخچه تطبیق و همگام‌سازی (Sync Logs)</h4>

            <div v-if="loadingSyncLogs" class="py-8 text-center text-slate-400">
              در حال دریافت تاریخچه...
            </div>

            <div v-else-if="syncLogs.length === 0" class="py-6 text-center text-slate-400 bg-slate-50 dark:bg-slate-850 rounded-xl">
              هنوز عملیات تطبیقی برای این فروشگاه ثبت نشده است.
            </div>

            <div v-else class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
              <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-850 text-slate-400 border-b border-slate-100 dark:border-slate-800">
                  <tr>
                    <th class="p-3">بخش</th>
                    <th class="p-3">وضعیت</th>
                    <th class="p-3">بررسی‌شده</th>
                    <th class="p-3">خطاها</th>
                    <th class="p-3">زمان شروع</th>
                    <th class="p-3">پایان</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="sl in syncLogs" :key="sl.id" class="hover:bg-slate-50/50">
                    <td class="p-3 font-bold dir-ltr text-right">{{ sl.entity_type }}</td>
                    <td class="p-3">
                      <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                        :class="sl.status === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
                      >
                        {{ sl.status }}
                      </span>
                    </td>
                    <td class="p-3 font-semibold">{{ formatNumber(sl.processed) }}</td>
                    <td class="p-3 font-semibold text-rose-600">{{ formatNumber(sl.failed) }}</td>
                    <td class="p-3 text-slate-500">{{ sl.started_at ? formatDateTime(sl.started_at) : '—' }}</td>
                    <td class="p-3 text-slate-500">{{ sl.completed_at ? formatDateTime(sl.completed_at) : '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ================= TAB 3: WOOCOMMERCE REMOTE WEBHOOKS ================= -->
        <div v-if="activeTab === 'webhooks'" class="space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-bold text-sm text-slate-900 dark:text-white">وب‌هوک‌های ثبت‌شده در ووکامرس</h4>
              <p class="text-slate-400 text-[11px] mt-0.5">فهرست وب‌هوک‌های تعریف‌شده روی REST API فروشگاه</p>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="autoSetupWebhooks"
                :disabled="settingUp"
                class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-indigo-600/20 disabled:opacity-50"
              >
                <Iconsax name="add-circle" size="16" />
                <span>{{ settingUp ? 'در حال ثبت...' : 'ثبت خودکار وب‌هوک‌های استاندارد' }}</span>
              </button>
            </div>
          </div>

          <div v-if="loadingWebhooks" class="py-10 text-center text-slate-400">
            در حال بارگذاری وب‌هوک‌ها از ووکامرس...
          </div>

          <div v-else-if="remoteWebhooks.length === 0" class="p-8 text-center bg-slate-50 dark:bg-slate-850 rounded-2xl border border-slate-200/80 dark:border-slate-800 space-y-3">
            <div class="text-sm font-bold text-slate-800 dark:text-slate-200">هنوز هیچ وب‌هوکی در این فروشگاه ثبت نشده است.</div>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
              با کلیک بر روی دکمه «ثبت خودکار وب‌هوک‌های استاندارد»، رویدادهای ایجاد و بروزرسانی سفارش‌ها، محصولات و مشتریان به صورت خودکار در ووکامرس تعریف می‌شوند.
            </p>
            <button
              @click="autoSetupWebhooks"
              :disabled="settingUp"
              class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs inline-flex items-center gap-2"
            >
              <span>راه‌اندازی فوری وب‌هوک‌ها</span>
            </button>
          </div>

          <div v-else class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
            <table class="w-full text-right text-xs">
              <thead class="bg-slate-50 dark:bg-slate-850 text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                  <th class="p-3">موضوع (Topic)</th>
                  <th class="p-3">نام</th>
                  <th class="p-3">وضعیت</th>
                  <th class="p-3">آدرس دریافت (Delivery URL)</th>
                  <th class="p-3">خطاهای ارسال</th>
                  <th class="p-3 text-center">عملیات</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="wh in remoteWebhooks" :key="wh.id" class="hover:bg-slate-50/50">
                  <td class="p-3 font-bold text-indigo-600">{{ wh.topic }}</td>
                  <td class="p-3 text-slate-700 dark:text-slate-300 font-semibold">{{ wh.name }}</td>
                  <td class="p-3">
                    <span
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                      :class="wh.status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'"
                    >
                      {{ wh.status }}
                    </span>
                  </td>
                  <td class="p-3 text-[10px] text-slate-400 max-w-[180px] truncate dir-ltr text-right" :title="wh.delivery_url">
                    {{ wh.delivery_url }}
                  </td>
                  <td class="p-3" :class="wh.failure_count > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'">
                    {{ toPersianDigits(wh.failure_count) }}
                  </td>
                  <td class="p-3 text-center">
                    <button
                      @click="deleteRemoteWebhook(wh.id)"
                      class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50"
                      title="حذف وب‌هوک"
                    >
                      <Iconsax name="trash" size="15" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ================= TAB 4: STORE WEBHOOK LOGS ================= -->
        <div v-if="activeTab === 'logs'" class="space-y-4">
          <div class="flex items-center justify-between">
            <h4 class="font-bold text-sm text-slate-900 dark:text-white">رویدادهای دریافتی اخیر از این فروشگاه</h4>
            <router-link
              to="/settings/webhooks"
              class="text-xs text-indigo-600 dark:text-indigo-400 font-bold hover:underline"
            >
              مشاهده کارتابل کامل وب‌هوک‌ها ←
            </router-link>
          </div>

          <div v-if="loadingStoreLogs" class="py-10 text-center text-slate-400">
            در حال بارگذاری لاگ‌ها...
          </div>

          <div v-else-if="storeLogs.length === 0" class="py-8 text-center text-slate-400 bg-slate-50 dark:bg-slate-850 rounded-xl">
            هیچ رویدادی تاکنون از این فروشگاه دریافت نشده است.
          </div>

          <div v-else class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
            <table class="w-full text-right text-xs">
              <thead class="bg-slate-50 dark:bg-slate-850 text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                  <th class="p-3">رویداد</th>
                  <th class="p-3">وضعیت</th>
                  <th class="p-3">شناسه منبع</th>
                  <th class="p-3">زمان پردازش</th>
                  <th class="p-3">تاریخ ثبت</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="l in storeLogs" :key="l.id" class="hover:bg-slate-50/50">
                  <td class="p-3 font-bold text-slate-800 dark:text-slate-200">{{ l.event }}</td>
                  <td class="p-3">
                    <span
                      class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                      :class="getStatusBadgeClass(l.status)"
                    >
                      {{ l.status }}
                    </span>
                  </td>
                  <td class="p-3 text-slate-500 font-medium">{{ l.resource_id ? '#' + toPersianDigits(l.resource_id) : '—' }}</td>
                  <td class="p-3 text-slate-400">{{ l.processing_time_ms ? toPersianDigits(l.processing_time_ms) + ' میلی‌ثانیه' : '—' }}</td>
                  <td class="p-3 text-slate-400 text-[10px]">{{ l.created_at ? formatDateTime(l.created_at) : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end">
        <button
          @click="close"
          class="px-5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-200"
        >
          بستن
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import { formatDateTime, formatNumber, toPersianDigits } from '@/utils/formatters';

const props = defineProps({
  modelValue: Boolean,
  store: Object,
});

const emit = defineEmits(['update:modelValue']);

const activeTab = ref('health');
const tabs = [
  { key: 'health', label: 'سلامت ادغام', icon: 'shield-tick' },
  { key: 'reconcile', label: 'تطبیق و یکسان‌سازی', icon: 'refresh' },
  { key: 'webhooks', label: 'وب‌هوک‌های ووکامرس', icon: 'send-2' },
  { key: 'logs', label: 'لاگ رویدادها', icon: 'receipt-2' },
];

const health = ref(null);
const loadingHealth = ref(false);

const reconcileForm = ref({
  entity_type: 'all',
  date_range: '24h',
});
const reconciling = ref(false);
const lastReconcileResult = ref(null);

const syncLogs = ref([]);
const loadingSyncLogs = ref(false);

const remoteWebhooks = ref([]);
const loadingWebhooks = ref(false);
const settingUp = ref(false);

const storeLogs = ref([]);
const loadingStoreLogs = ref(false);

const close = () => {
  emit('update:modelValue', false);
};

const loadHealth = async () => {
  if (!props.store) return;
  loadingHealth.value = true;
  try {
    const res = await axios.get(`/stores/${props.store.id}/webhook-health`);
    health.value = res.data?.data || res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loadingHealth.value = false;
  }
};

const startReconciliation = async () => {
  if (!props.store) return;
  reconciling.value = true;
  try {
    const res = await axios.post(`/stores/${props.store.id}/reconcile`, reconcileForm.value);
    lastReconcileResult.value = res.data?.data || res.data;
    await Promise.all([loadHealth(), loadSyncLogs()]);
  } catch (e) {
    alert(e.message || e.response?.data?.message || 'خطا در اجرای فرآیند تطبیق');
  } finally {
    reconciling.value = false;
  }
};

const loadSyncLogs = async () => {
  if (!props.store) return;
  loadingSyncLogs.value = true;
  try {
    const res = await axios.get(`/stores/${props.store.id}/sync-logs?per_page=10`);
    syncLogs.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
  } catch (e) {
    console.error(e);
  } finally {
    loadingSyncLogs.value = false;
  }
};

const loadRemoteWebhooks = async () => {
  if (!props.store) return;
  loadingWebhooks.value = true;
  try {
    const res = await axios.get(`/stores/${props.store.id}/webhooks`);
    remoteWebhooks.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
  } catch (e) {
    console.error(e);
  } finally {
    loadingWebhooks.value = false;
  }
};

const autoSetupWebhooks = async () => {
  if (!props.store) return;
  settingUp.value = true;
  try {
    const res = await axios.post(`/stores/${props.store.id}/webhooks/auto-setup`);
    const count = res.data?.created_count ?? res.data?.data?.created_count ?? 0;
    alert(`${count} وب‌هوک استاندارد با موفقیت روی فروشگاه ثبت شد.`);
    await loadRemoteWebhooks();
  } catch (e) {
    alert(e.message || e.response?.data?.message || 'خطا در ثبت خودکار وب‌هوک‌ها');
  } finally {
    settingUp.value = false;
  }
};

const deleteRemoteWebhook = async (webhookId) => {
  if (!confirm('آیا از حذف این وب‌هوک از روی ووکامرس اطمینان دارید؟')) return;
  try {
    await axios.delete(`/stores/${props.store.id}/webhooks/${webhookId}`);
    await loadRemoteWebhooks();
  } catch (e) {
    alert(e.message || e.response?.data?.message || 'خطا در حذف وب‌هوک');
  }
};

const loadStoreLogs = async () => {
  if (!props.store) return;
  loadingStoreLogs.value = true;
  try {
    const res = await axios.get(`/webhooks?store_id=${props.store.id}&per_page=15`);
    storeLogs.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
  } catch (e) {
    console.error(e);
  } finally {
    loadingStoreLogs.value = false;
  }
};

const getHealthLabel = (h) => {
  switch (h) {
    case 'healthy': return 'کاملاً سالم';
    case 'warning': return 'هشدار خطا';
    case 'failing': return 'وضعیت بحرانی';
    default: return 'نامشخص';
  }
};

const getHealthTextColor = (h) => {
  switch (h) {
    case 'healthy': return 'text-emerald-600 dark:text-emerald-400';
    case 'warning': return 'text-amber-600 dark:text-amber-400';
    case 'failing': return 'text-rose-600 dark:text-rose-400';
    default: return 'text-slate-500';
  }
};

const getStatusBadgeClass = (st) => {
  switch (st) {
    case 'processed': return 'bg-emerald-50 text-emerald-600';
    case 'failed': return 'bg-rose-50 text-rose-600';
    case 'duplicate': return 'bg-amber-50 text-amber-600';
    default: return 'bg-slate-100 text-slate-500';
  }
};

watch(
  () => props.modelValue,
  (open) => {
    if (open && props.store) {
      loadHealth();
      loadSyncLogs();
      loadRemoteWebhooks();
      loadStoreLogs();
    }
  }
);
</script>
