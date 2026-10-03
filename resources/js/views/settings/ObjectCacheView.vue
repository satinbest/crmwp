<template>
  <div class="space-y-6">
    <!-- Breadcrumb & Back -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <router-link to="/settings" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
          تنظیمات
        </router-link>
        <span>/</span>
        <span class="text-slate-800 dark:text-slate-200 font-bold">اتصال و عیب‌یابی Object Cache</span>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchCacheInfo"
          :disabled="loading"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all disabled:opacity-50 cursor-pointer"
        >
          <Iconsax name="refresh" size="14" :class="{ 'animate-spin': loading }" />
          <span>بروزرسانی وضعیت</span>
        </button>

        <button
          @click="flushCache"
          :disabled="flushing"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-xs font-bold text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-all disabled:opacity-50 cursor-pointer"
        >
          <Iconsax name="trash" size="14" />
          <span>{{ flushing ? 'در حال پاکسازی...' : 'پاکسازی کش سراسری' }}</span>
        </button>
      </div>
    </div>

    <!-- Header Banner -->
    <div class="bg-gradient-to-br from-indigo-900 via-slate-900 to-slate-950 rounded-3xl p-6 sm:p-8 text-white shadow-xl border border-indigo-800/40 relative overflow-hidden">
      <div class="relative z-10 flex flex-col md:flex-row items-center md:items-start justify-between gap-6">
        <div class="flex items-center gap-4 text-center md:text-right">
          <div class="w-16 h-16 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300 shrink-0">
            <Iconsax name="flash" size="32" />
          </div>
          <div>
            <div class="flex items-center justify-center md:justify-start gap-2.5 flex-wrap">
              <h1 class="text-xl sm:text-2xl font-extrabold text-white">اتصال به Object Cache</h1>
              <span
                class="px-2.5 py-0.5 rounded-full text-xs font-bold border flex items-center gap-1.5"
                :class="statusBadgeClasses"
              >
                <span class="w-2 h-2 rounded-full" :class="cacheInfo?.is_available ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
                {{ statusText }}
              </span>
            </div>
            <p class="text-xs text-indigo-200/80 mt-1 max-w-2xl leading-relaxed">
              لایه کش اشیاء سرور برای تسریع بارگذاری کالاها، سفارش‌ها، سگمنت‌های CRM و کاهش بار کوئری‌های تکراری ووکامرس.
            </p>
          </div>
        </div>

        <!-- Real-time Hit Ratio Stat -->
        <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/10 text-center shrink-0 min-w-[150px]">
          <div class="text-[11px] text-indigo-200">نسبت موفقیت (Hit Ratio)</div>
          <div class="text-2xl font-extrabold text-white mt-0.5">
            {{ toPersianDigits(cacheInfo?.telemetry?.hit_ratio_percent ?? 0) }}٪
          </div>
          <div class="text-[10px] text-indigo-300 mt-0.5">
            {{ toPersianDigits(cacheInfo?.telemetry?.hits ?? 0) }} موفق / {{ toPersianDigits(cacheInfo?.telemetry?.misses ?? 0) }} ناموفق
          </div>
        </div>
      </div>
    </div>

    <!-- Telemetry Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="text-xs text-slate-400">درایور فعال (Active Driver)</div>
        <div class="text-base font-extrabold text-slate-900 dark:text-white mt-1 uppercase" dir="ltr">
          {{ cacheInfo?.active_driver || 'FILE' }}
        </div>
        <div class="text-[11px] text-indigo-600 dark:text-indigo-400 mt-1 font-medium">
          {{ getDriverTitle(cacheInfo?.active_driver) }}
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="text-xs text-slate-400">درایور ترجیحی کانفیگ</div>
        <div class="text-base font-extrabold text-slate-900 dark:text-white mt-1 uppercase" dir="ltr">
          {{ cacheInfo?.configured_driver || 'FILE' }}
        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
          تنظیم شده در .env
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="text-xs text-slate-400">تعداد آیتم‌های ذخیره شده</div>
        <div class="text-base font-extrabold text-slate-900 dark:text-white mt-1">
          {{ toPersianDigits(cacheInfo?.storage?.file_items_count ?? 0) }} کلید
        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
          حجم تقریبی: {{ toPersianDigits(cacheInfo?.storage?.file_size_formatted ?? '0 KB') }}
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm">
        <div class="text-xs text-slate-400">مکانیسم جایگزین (Fallback)</div>
        <div class="text-base font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">
          آماده و خودکار
        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
          سوییچ بدون قطعی به دیسک محلی
        </div>
      </div>
    </div>

    <!-- Main Section: Live Test Connection & Diagnostics -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
      <!-- Test Connection Form -->
      <div class="lg:col-span-7 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Iconsax name="activity" size="20" />
            </div>
            <div>
              <h2 class="text-sm font-bold text-slate-900 dark:text-white">تست زنده اتصال و عملیات کش</h2>
              <p class="text-xs text-slate-400 mt-0.5">ارسال درخواست PING / SET / GET / DELETE به سرور کش</p>
            </div>
          </div>
        </div>

        <div class="space-y-4 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5">انتخاب درایور جهت تست:</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <button
                type="button"
                @click="testForm.driver = 'file'"
                class="p-3 rounded-xl border text-center font-bold transition-all cursor-pointer"
                :class="testForm.driver === 'file' ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                File Cache
                <div class="text-[10px] font-normal text-slate-400 mt-0.5">محلی و پیش‌فرض</div>
              </button>

              <button
                type="button"
                @click="testForm.driver = 'memcached'"
                class="p-3 rounded-xl border text-center font-bold transition-all cursor-pointer"
                :class="testForm.driver === 'memcached' ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                Memcached
                <div class="text-[10px] font-normal mt-0.5" :class="cacheInfo?.extensions?.memcached ? 'text-emerald-500' : 'text-slate-400'">
                  {{ cacheInfo?.extensions?.memcached ? 'اکستنشن نصب است' : 'نیاز به اکستنشن' }}
                </div>
              </button>

              <button
                type="button"
                @click="testForm.driver = 'redis'"
                class="p-3 rounded-xl border text-center font-bold transition-all cursor-pointer"
                :class="testForm.driver === 'redis' ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                Redis
                <div class="text-[10px] font-normal mt-0.5" :class="cacheInfo?.extensions?.redis ? 'text-emerald-500' : 'text-slate-400'">
                  {{ cacheInfo?.extensions?.redis ? 'اکستنشن نصب است' : 'نیاز به اکستنشن' }}
                </div>
              </button>

              <button
                type="button"
                @click="testForm.driver = 'apcu'"
                class="p-3 rounded-xl border text-center font-bold transition-all cursor-pointer"
                :class="testForm.driver === 'apcu' ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                APCu
                <div class="text-[10px] font-normal mt-0.5" :class="cacheInfo?.extensions?.apcu ? 'text-emerald-500' : 'text-slate-400'">
                  {{ cacheInfo?.extensions?.apcu ? 'اکستنشن فعال' : 'نیاز به اکستنشن' }}
                </div>
              </button>
            </div>
          </div>

          <!-- Connection parameters for network drivers -->
          <div v-if="testForm.driver === 'memcached' || testForm.driver === 'redis'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">آدرس سرور (Host):</label>
              <input
                v-model="testForm.host"
                type="text"
                placeholder="127.0.0.1"
                class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white dir-ltr text-right"
              />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">پورت اتصال (Port):</label>
              <input
                v-model.number="testForm.port"
                type="number"
                :placeholder="testForm.driver === 'memcached' ? '11211' : '6379'"
                class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white dir-ltr text-right"
              />
            </div>
            <div v-if="testForm.driver === 'redis'" class="sm:col-span-2">
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">کلمه عبور Redis (اختیاری):</label>
              <input
                v-model="testForm.password"
                type="password"
                placeholder="در صورت وجود احراز هویت..."
                class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white"
              />
            </div>
          </div>

          <div class="pt-2 flex items-center justify-between">
            <div class="text-[11px] text-slate-400">
              تست اتصال بدون ایجاد تداخل با داده‌های واقعی کاربران اجرا می‌شود.
            </div>
            <button
              type="button"
              @click="runTestConnection"
              :disabled="testing"
              class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold text-xs shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2 cursor-pointer"
            >
              <div v-if="testing" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <Iconsax v-else name="activity" size="16" />
              <span>{{ testing ? 'در حال اجرای تست...' : 'تست اتصال' }}</span>
            </button>
          </div>

          <!-- Test Result Box -->
          <div v-if="testResult" class="p-4 rounded-2xl border transition-all" :class="testResult.success ? 'bg-emerald-50/70 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800' : 'bg-rose-50/70 dark:bg-rose-950/40 border-rose-300 dark:border-rose-800'">
            <div class="flex items-start justify-between gap-3 mb-3">
              <div class="flex items-center gap-2 font-bold" :class="testResult.success ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300'">
                <span class="w-2.5 h-2.5 rounded-full" :class="testResult.success ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                <span>{{ testResult.success ? 'نتیجه تست: اتصال موفق و پایدار' : 'نتیجه تست: خطا در ارتباط' }}</span>
              </div>
              <span v-if="testResult.latency_ms > 0" class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-white/60 dark:bg-slate-900/60 text-slate-700 dark:text-slate-300" dir="ltr">
                {{ testResult.latency_ms }} ms
              </span>
            </div>

            <p class="text-xs mb-3" :class="testResult.success ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'">
              {{ testResult.message }}
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-[11px]">
              <div class="p-2 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">نصب اکستنشن</div>
                <div class="font-bold mt-0.5" :class="testResult.extension_installed ? 'text-emerald-600' : 'text-rose-600'">
                  {{ testResult.extension_installed ? 'تایید شد' : 'عدم نصب' }}
                </div>
              </div>

              <div class="p-2 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">نوشتن (Write)</div>
                <div class="font-bold mt-0.5" :class="testResult.write_ok ? 'text-emerald-600' : 'text-rose-600'">
                  {{ testResult.write_ok ? 'تایید شد (OK)' : 'خطا' }}
                </div>
              </div>

              <div class="p-2 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">خواندن (Read)</div>
                <div class="font-bold mt-0.5" :class="testResult.read_ok ? 'text-emerald-600' : 'text-rose-600'">
                  {{ testResult.read_ok ? 'تایید شد (OK)' : 'خطا' }}
                </div>
              </div>

              <div class="p-2 rounded-xl bg-white/70 dark:bg-slate-900/70 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">حذف کلید (Delete)</div>
                <div class="font-bold mt-0.5" :class="testResult.delete_ok ? 'text-emerald-600' : 'text-rose-600'">
                  {{ testResult.delete_ok ? 'تایید شد (OK)' : 'خطا' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Server Support & Architectural Specifications -->
      <div class="lg:col-span-5 space-y-5">
        <!-- Extensions Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-4">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center">
              <Iconsax name="shield-tick" size="20" />
            </div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">وضعیت اکستنشن‌های سرور</h2>
          </div>

          <div class="space-y-2.5 text-xs">
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-200">PHP Memcached Extension</span>
                <div class="text-[10px] text-slate-400">سرویس حافظه توزیع‌شده با کارایی بالا</div>
              </div>
              <span
                class="px-2 py-0.5 rounded-lg text-[10px] font-bold"
                :class="cacheInfo?.extensions?.memcached ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
              >
                {{ cacheInfo?.extensions?.memcached ? 'فعال روی سرور' : 'غیرفعال' }}
              </span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-200">PHP Redis Extension</span>
                <div class="text-[10px] text-slate-400">ساختار داده در حافظه و کش سریع</div>
              </div>
              <span
                class="px-2 py-0.5 rounded-lg text-[10px] font-bold"
                :class="cacheInfo?.extensions?.redis ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
              >
                {{ cacheInfo?.extensions?.redis ? 'فعال روی سرور' : 'غیرفعال' }}
              </span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-200">PHP APCu Extension</span>
                <div class="text-[10px] text-slate-400">کش مشترک حافظه محلی فرآیندهای PHP</div>
              </div>
              <span
                class="px-2 py-0.5 rounded-lg text-[10px] font-bold"
                :class="cacheInfo?.extensions?.apcu ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
              >
                {{ cacheInfo?.extensions?.apcu ? 'فعال روی سرور' : 'غیرفعال' }}
              </span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-200">مخزن دیسک محلی (File Driver)</span>
                <div class="text-[10px] text-slate-400">ذخیره‌سازی بهینه روی فایل با قفل EX</div>
              </div>
              <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300">
                همیشه آماده (Fallback)
              </span>
            </div>
          </div>
        </div>

        <!-- Multi-store Scoping Note -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-3">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
              <Iconsax name="segments" size="20" />
            </div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">تفکیک کلیدهای Multi-store</h2>
          </div>

          <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed text-justify">
            کلیه کلیدهای کش با الگوی <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-bold">store:{store_id}:{resource}:{hash}</code> ایجاد می‌شوند تا تداخل داده‌ای میان فروشگاه‌های مختلف ناممکن باشد. با دریافت هر وب‌هوک سفارش یا محصول، کلیدهای مربوطه به صورت خودکار ابطال (Invalidate) می‌گردند.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import { toPersianDigits } from '@/utils/formatters';

const loading = ref(true);
const flushing = ref(false);
const testing = ref(false);
const cacheInfo = ref(null);
const testResult = ref(null);

const testForm = reactive({
  driver: 'file',
  host: '127.0.0.1',
  port: 11211,
  password: '',
  database: 0,
});

const statusText = computed(() => {
  if (cacheInfo.value?.is_available) return 'متصل و فعال';
  if (cacheInfo.value?.status === 'disabled') return 'غیرفعال';
  return 'قطع ارتباط';
});

const statusBadgeClasses = computed(() => {
  if (cacheInfo.value?.is_available) {
    return 'bg-emerald-500/20 text-emerald-300 border-emerald-400/30';
  }
  return 'bg-rose-500/20 text-rose-300 border-rose-400/30';
});

function getDriverTitle(driver) {
  switch (driver) {
    case 'memcached': return 'سرور کش حافظه Memcached';
    case 'redis': return 'سرور داده Redis In-Memory';
    case 'apcu': return 'کش مشترک حافظه APCu';
    case 'file': return 'کش بهینه فایل (دیسک پرسرعت)';
    default: return 'سرویس کش';
  }
}

async function fetchCacheInfo() {
  loading.value = true;
  try {
    const res = await apiClient.get('/system/cache');
    cacheInfo.value = res.data || {};
    if (cacheInfo.value?.config?.memcached) {
      testForm.host = cacheInfo.value.config.memcached.host || '127.0.0.1';
      testForm.port = cacheInfo.value.config.memcached.port || 11211;
    }
  } catch (err) {
    console.error('Failed to fetch cache info:', err);
  } finally {
    loading.value = false;
  }
}

async function runTestConnection() {
  testing.value = true;
  testResult.value = null;
  try {
    const res = await apiClient.post('/system/cache/test', {
      driver: testForm.driver,
      host: testForm.host,
      port: testForm.port,
      password: testForm.password || undefined,
      database: testForm.database,
    });
    testResult.value = res.data || {};
  } catch (err) {
    testResult.value = {
      success: false,
      message: err.message || 'خطا در برقراری ارتباط با سرور کش.',
      latency_ms: 0,
    };
  } finally {
    testing.value = false;
  }
}

async function flushCache() {
  if (!confirm('آیا از پاکسازی تمام کلیدها و اشیاء کش سامانه اطمینان دارید؟')) return;
  flushing.value = true;
  try {
    const res = await apiClient.post('/system/cache/flush');
    alert(res.data?.message || 'کش با موفقیت پاکسازی شد.');
    await fetchCacheInfo();
  } catch (err) {
    alert(err.message || 'خطا در پاکسازی کش');
  } finally {
    flushing.value = false;
  }
}

onMounted(() => {
  fetchCacheInfo();
});
</script>
