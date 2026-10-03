<template>
  <div class="space-y-6">
    <!-- Header Page Title -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">داشبورد مدیریت سامانه</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          خوش آمدید، <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ authStore.user?.full_name || authStore.user?.username }}</span> | سطح دسترسی: <span class="font-bold">{{ primaryRole?.display_name }}</span>
        </p>
      </div>

      <div class="flex items-center gap-2">
        <RefreshButton
          @click="refreshHealth"
          :loading="refreshing"
          label="بروزرسانی وضعیت"
          title="بروزرسانی وضعیت سلامت سیستم"
        />
      </div>
    </div>

    <!-- Active Store Context Banner -->
    <div
      v-if="storeContext.activeStore"
      class="bg-gradient-to-r from-indigo-50 via-white to-slate-50 dark:from-indigo-950/40 dark:via-slate-900 dark:to-slate-900 border border-indigo-100 dark:border-indigo-900/40 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
    >
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold shadow-md shadow-indigo-500/20 shrink-0">
          <Iconsax :name="storeContext.activeStore.icon || 'shop'" size="24" />
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="font-bold text-slate-900 dark:text-white text-base">{{ storeContext.activeStore.name }}</span>
            <span
              v-if="storeContext.activeStore.is_demo"
              class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300 font-bold border border-amber-200 dark:border-amber-800"
            >
              حالت دمو ایزوله
            </span>
            <span
              class="text-[10px] px-2 py-0.5 rounded-full font-bold"
              :class="storeContext.isConnected ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300'"
            >
              {{ storeContext.isConnected ? '● متصل' : '● خطای اتصال' }}
            </span>
          </div>
          <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400 mt-1">
            <span class="dir-ltr">{{ storeContext.activeStore.url }}</span>
            <span>•</span>
            <span>واحد پولی: <strong class="dir-ltr text-indigo-600 dark:text-indigo-400">{{ storeContext.activeStore.currency || 'IRR' }}</strong></span>
            <span>•</span>
            <span>منطقه زمانی: <strong class="dir-ltr text-slate-600 dark:text-slate-300">{{ storeContext.activeStore.timezone || 'Asia/Tehran' }}</strong></span>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <router-link
          to="/stores"
          class="px-3 py-1.5 rounded-xl border border-indigo-200 dark:border-indigo-800/80 bg-white dark:bg-slate-800 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors shadow-xs"
        >
          تنظیمات فروشگاه‌ها
        </router-link>
      </div>
    </div>

    <!-- System Health Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Database Card -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-400">پایگاه داده سامانه</span>
          <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
            <Iconsax name="check" size="18" />
          </span>
        </div>
        <div class="mt-3">
          <div class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>MariaDB</span>
            <span class="text-xs font-normal text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950 px-2 py-0.5 rounded-full">متصل</span>
          </div>
          <div class="text-[11px] text-slate-400 mt-1">نسخه: {{ healthData?.database?.version || '13.0.2-MariaDB' }}</div>
        </div>
      </div>

      <!-- PHP API Engine -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-400">موتور بک‌اند PHP</span>
          <span class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
            <Iconsax name="activity" size="18" />
          </span>
        </div>
        <div class="mt-3">
          <div class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>REST API</span>
          </div>
          <div class="text-[11px] text-slate-400 mt-1">PHP {{ healthData?.php_version || '8.4' }}</div>
        </div>
      </div>

      <!-- Authentication & Session -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-400">امنیت و نشست</span>
          <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
            <Iconsax name="shield-tick" size="18" />
          </span>
        </div>
        <div class="mt-3">
          <div class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>Server Session</span>
            <span class="text-xs font-normal text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950 px-2 py-0.5 rounded-full">HttpOnly</span>
          </div>
          <div class="text-[11px] text-slate-400 mt-1">محافظت CSRF و RateLimit</div>
        </div>
      </div>

      <!-- RBAC Foundation -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-400">سطوح دسترسی (RBAC)</span>
          <span class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
            <Iconsax name="users" size="18" />
          </span>
        </div>
        <div class="mt-3">
          <div class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>{{ toPersianDigits(authStore.permissions.length) }} مجوز فعال</span>
          </div>
          <div class="text-[11px] text-slate-400 mt-1">مدیریت دسترسی‌های کاربر جاری</div>
        </div>
      </div>
    </div>

    <!-- Architecture Principle Banner -->
    <div class="bg-gradient-to-br from-indigo-900/90 to-slate-900 rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden border border-indigo-800/40 shadow-xl">
      <div class="relative z-10 max-w-3xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-bold mb-4 border border-indigo-400/20">
          <span>معماری مستقل و امن</span>
        </div>
        <h2 class="text-xl sm:text-2xl font-black mb-3">تفکیک کامل سامانه مدیریتی و CRM از پایگاه داده وردپرس</h2>
        <p class="text-xs sm:text-sm text-indigo-100/90 leading-relaxed mb-6">
          این سامانه یک افزونه وردپرس نیست و تحت هیچ شرایطی به پایگاه داده وردپرس دسترسی مستقیم ندارد. ارتباط با ووکامرس منحصراً از طریق APIهای استاندارد و وب‌هوک‌ها به صورت ایزوله در لایه آداپتور بک‌اند صورت می‌گیرد. اطلاعات مشتریان و محصولات متعلق به ووکامرس بوده و داده‌های اختصاصی CRM (تسک‌ها، یادداشت‌ها، سگمنت‌ها) در دیتابیس مستقل سامانه ذخیره می‌گردند.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
          <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3 border border-white/10">
            <div class="font-bold text-indigo-300 mb-1">فرانت‌اند Vue 3 (SPA)</div>
            <div class="text-[11px] text-slate-200">فونت وزیرمتن و آیکون‌سکس محلی بدون نیاز به اینترنت</div>
          </div>
          <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3 border border-white/10">
            <div class="font-bold text-indigo-300 mb-1">بک‌اند لایه‌ای PHP</div>
            <div class="text-[11px] text-slate-200">کنترلرهای سبک، سرویس‌ها، مخازن و آداپتور ووکامرس</div>
          </div>
          <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3 border border-white/10">
            <div class="font-bold text-indigo-300 mb-1">ایزولاسیون کامل کلیدها</div>
            <div class="text-[11px] text-slate-200">کلیدها در مرورگر ارسال نمی‌شوند و در دیتابیس رمزنگاری شده‌اند</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Inventory Alerts & Overview Widget -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
            <Iconsax name="box" size="18" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">وضعیت انبار و کالاهای نیازمند توجه</h3>
            <p class="text-xs text-slate-400 mt-0.5">پایش بلادرنگ کالاهای ناموجود، دارای کمبود یا در وضعیت پیش‌خرید</p>
          </div>
        </div>

        <router-link
          to="/inventory"
          class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
        >
          <span>مشاهده داشبورد کامل انبار</span>
          <Iconsax name="arrow-left" size="14" />
        </router-link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
        <!-- Low Stock Items Card -->
        <div class="p-4 rounded-xl bg-amber-50/40 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 space-y-3">
          <div class="flex items-center justify-between text-xs font-bold text-amber-800 dark:text-amber-300">
            <span class="flex items-center gap-1.5">
              <span>⚠️ هشدارهای کمبود موجودی</span>
              <span v-if="lowStockItems.length > 0" class="px-1.5 py-0.2 rounded-full bg-amber-200/60 dark:bg-amber-800 text-[10px]">
                {{ toPersianDigits(lowStockItems.length) }} مورد
              </span>
            </span>
            <router-link to="/inventory?stock_status=low_stock" class="text-[11px] hover:underline">
              مشاهده همه ←
            </router-link>
          </div>

          <div v-if="loadingInventory" class="text-xs text-slate-400 py-4 text-center">
            در حال بارگذاری وضعیت انبار...
          </div>
          <div v-else-if="lowStockItems.length === 0" class="text-xs text-slate-400 py-3 text-center">
            در حال حاضر هیچ کالایی در آستانه کمبود موجودی قرار ندارد.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="item in lowStockItems"
              :key="item.product_id"
              class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-900 border border-amber-100 dark:border-amber-900/60 text-xs"
            >
              <div class="truncate max-w-[200px]">
                <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.product_name }}</div>
                <div class="text-[10px] text-slate-400 dir-ltr text-right">SKU: {{ item.sku || ('#' + item.product_id) }}</div>
              </div>
              <div class="flex items-center gap-2">
                <span class="font-bold text-amber-600">{{ formatNumber(item.stock_quantity) }} عدد</span>
                <router-link
                  :to="'/inventory?search=' + (item.sku || item.product_id)"
                  class="p-1 rounded bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300"
                  title="ویرایش موجودی"
                >
                  <Iconsax name="edit" size="13" />
                </router-link>
              </div>
            </div>
          </div>
        </div>

        <!-- Out of Stock Items Card -->
        <div class="p-4 rounded-xl bg-rose-50/40 dark:bg-rose-950/20 border border-rose-200/60 dark:border-rose-900/40 space-y-3">
          <div class="flex items-center justify-between text-xs font-bold text-rose-800 dark:text-rose-300">
            <span class="flex items-center gap-1.5">
              <span>🚫 کالاهای ناموجود در انبار</span>
              <span v-if="outOfStockItems.length > 0" class="px-1.5 py-0.2 rounded-full bg-rose-200/60 dark:bg-rose-800 text-[10px]">
                {{ toPersianDigits(outOfStockItems.length) }} مورد
              </span>
            </span>
            <router-link to="/inventory?stock_status=outofstock" class="text-[11px] hover:underline">
              مشاهده همه ←
            </router-link>
          </div>

          <div v-if="loadingInventory" class="text-xs text-slate-400 py-4 text-center">
            در حال بارگذاری وضعیت انبار...
          </div>
          <div v-else-if="outOfStockItems.length === 0" class="text-xs text-slate-400 py-3 text-center">
            هیچ کالایی در وضعیت ناموجود نیست.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="item in outOfStockItems"
              :key="item.product_id"
              class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-slate-900 border border-rose-100 dark:border-rose-900/60 text-xs"
            >
              <div class="truncate max-w-[200px]">
                <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.product_name }}</div>
                <div class="text-[10px] text-slate-400 dir-ltr text-right">SKU: {{ item.sku || ('#' + item.product_id) }}</div>
              </div>
              <div class="flex items-center gap-2">
                <span class="font-bold text-rose-600 text-[11px]">ناموجود</span>
                <router-link
                  :to="'/inventory?search=' + (item.sku || item.product_id)"
                  class="p-1 rounded bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300"
                  title="افزایش موجودی"
                >
                  <Iconsax name="edit" size="13" />
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Integration Health Widget -->
    <div v-if="integrationHealth" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
            <Iconsax name="activity" size="18" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">وضعیت یکپارچه‌سازی ووکامرس و وب‌هوک‌ها</h3>
            <p class="text-xs text-slate-400 mt-0.5">پایش بلادرنگ سلامت رویدادها، تطبیق وضعیت و اتصالات فعال</p>
          </div>
        </div>

        <router-link
          to="/settings/webhooks"
          class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
        >
          <span>مدیریت کامل رویدادها و تطبیق</span>
          <Iconsax name="arrow-left" size="14" />
        </router-link>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-1">
        <!-- Item 1: WooCommerce Connection -->
        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-100 dark:border-slate-800">
          <div class="text-[11px] text-slate-400">اتصال ووکامرس</div>
          <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>برقرار ({{ integrationHealth.store_name }})</span>
          </div>
        </div>

        <!-- Item 2: Webhook Status -->
        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-100 dark:border-slate-800">
          <div class="text-[11px] text-slate-400">سلامت وب‌هوک</div>
          <div class="text-xs font-bold mt-1 flex items-center gap-1.5" :class="integrationHealth.webhook_health === 'healthy' ? 'text-emerald-600' : 'text-amber-600'">
            <span class="w-2 h-2 rounded-full" :class="integrationHealth.webhook_health === 'healthy' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
            <span>{{ integrationHealth.webhook_health === 'healthy' ? 'کاملاً سالم' : 'نیازمند بررسی' }}</span>
          </div>
        </div>

        <!-- Item 3: Last Webhook -->
        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-100 dark:border-slate-800">
          <div class="text-[11px] text-slate-400">آخرین وب‌هوک دریافتی</div>
          <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-1 truncate" :title="integrationHealth.last_webhook_at">
            {{ integrationHealth.last_webhook_at ? formatDateTime(integrationHealth.last_webhook_at) : 'رویدادی ثبت نشده' }}
          </div>
        </div>

        <!-- Item 4: Last Reconciliation -->
        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-100 dark:border-slate-800">
          <div class="text-[11px] text-slate-400">آخرین تطبیق (Reconciliation)</div>
          <div class="text-xs font-semibold text-slate-800 dark:text-slate-200 mt-1 truncate" :title="integrationHealth.last_reconciliation_at">
            {{ integrationHealth.last_reconciliation_at ? formatDateTime(integrationHealth.last_reconciliation_at) : 'امروز' }}
          </div>
        </div>

        <!-- Item 5: Failed Webhooks -->
        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-100 dark:border-slate-800">
          <div class="text-[11px] text-slate-400">خطاهای ۲۴ ساعت گذشته</div>
          <div class="text-xs font-bold mt-1" :class="integrationHealth.failed_webhooks_24h > 0 ? 'text-rose-600 font-extrabold' : 'text-slate-700 dark:text-slate-300'">
            {{ toPersianDigits(integrationHealth.failed_webhooks_24h) }} خطا
          </div>
        </div>
      </div>
    </div>

    <!-- Active User Permissions Preview -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">مجوزهای فعال کاربر جاری در نشست امنیتی</h3>
          <p class="text-xs text-slate-400 mt-0.5">مجوزهای ریزدانه‌ای اعمال شده در سطح سرور (RBAC)</p>
        </div>
        <router-link
          to="/users"
          class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
        >
          مشاهده ماتریس کامل نقش‌ها →
        </router-link>
      </div>

      <div class="flex flex-wrap gap-2">
        <span
          v-for="perm in authStore.permissions"
          :key="perm"
          class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700/60"
        >
          {{ perm }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useStoreContext } from '@/stores/storeContext';
import { useNotificationStore } from '@/stores/notification';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import { formatNumber, formatDateTime, toPersianDigits } from '@/utils/formatters';

const authStore = useAuthStore();
const storeContext = useStoreContext();
const notification = useNotificationStore();

const healthData = ref(null);
const refreshing = ref(false);
const lowStockItems = ref([]);
const outOfStockItems = ref([]);
const loadingInventory = ref(false);
const integrationHealth = ref(null);

const primaryRole = computed(() => authStore.user?.roles?.[0]);

const fetchHealth = async () => {
  try {
    const res = await apiClient.get('/system/health');
    healthData.value = res.data;
  } catch (e) {
    //
  }
};

const fetchIntegrationHealth = async () => {
  const storeId = storeContext.activeStoreId;
  if (!storeId) return;

  try {
    const healthRes = await apiClient.get(`/stores/${storeId}/health`);
    integrationHealth.value = healthRes.data || null;
  } catch (e) {
    try {
      const fallbackRes = await apiClient.get(`/stores/${storeId}/webhook-health`);
      integrationHealth.value = fallbackRes.data?.data || null;
    } catch (err) {
      //
    }
  }
};

const fetchInventoryAlerts = async () => {
  if (!authStore.hasPermission('inventory.view')) return;
  loadingInventory.value = true;
  try {
    const [lowRes, outRes] = await Promise.all([
      apiClient.get('/inventory/low-stock?limit=4').catch(() => ({ data: [] })),
      apiClient.get('/inventory/out-of-stock?limit=4').catch(() => ({ data: [] }))
    ]);
    lowStockItems.value = Array.isArray(lowRes.data) ? lowRes.data : [];
    outOfStockItems.value = Array.isArray(outRes.data) ? outRes.data : [];
  } catch (e) {
    console.error('Failed to load inventory alerts', e);
  } finally {
    loadingInventory.value = false;
  }
};

const refreshHealth = async () => {
  refreshing.value = true;
  await Promise.all([fetchHealth(), fetchInventoryAlerts(), fetchIntegrationHealth()]);
  notification.success('اطلاعات پیشخوان و وضعیت انبار با موفقیت بروزرسانی شد.');
  refreshing.value = false;
};

onMounted(() => {
  fetchHealth();
  fetchInventoryAlerts();
  fetchIntegrationHealth();
});
</script>
