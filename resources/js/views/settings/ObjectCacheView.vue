<template>
  <div class="space-y-6">
    <!-- Breadcrumb & Top Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <router-link to="/settings" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
          تنظیمات
        </router-link>
        <span>/</span>
        <span class="text-slate-800 dark:text-slate-200 font-bold">پیکربندی و عیب‌یابی Object Cache</span>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchCacheInfo"
          :disabled="loading"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all disabled:opacity-50 cursor-pointer shadow-xs"
        >
          <Iconsax name="refresh" size="14" :class="{ 'animate-spin': loading }" />
          <span>بروزرسانی وضعیت</span>
        </button>

        <button
          @click="flushCache"
          :disabled="flushing"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 text-xs font-bold text-rose-700 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/60 transition-all disabled:opacity-50 cursor-pointer shadow-xs"
        >
          <Iconsax name="trash" size="14" />
          <span>{{ flushing ? 'در حال پاکسازی...' : 'پاکسازی کش سراسری' }}</span>
        </button>
      </div>
    </div>

    <!-- Header Banner with Accurate Real-time Hit Ratio -->
    <div class="bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-950 rounded-3xl p-6 sm:p-8 text-white shadow-xl border border-indigo-800/40 relative overflow-hidden">
      <div class="relative z-10 flex flex-col md:flex-row items-center md:items-start justify-between gap-6">
        <div class="flex items-center gap-4 text-center md:text-right">
          <div class="w-16 h-16 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-300 shrink-0 shadow-inner">
            <Iconsax name="activity" size="32" />
          </div>
          <div>
            <div class="flex items-center justify-center md:justify-start gap-2.5 flex-wrap">
              <h1 class="text-xl sm:text-2xl font-black text-white">مدیریت و وضعیت Object Cache</h1>
              <span
                class="px-2.5 py-0.5 rounded-full text-xs font-bold border flex items-center gap-1.5"
                :class="statusBadgeClasses"
              >
                <span class="w-2 h-2 rounded-full" :class="isObjectCacheConnected ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
                {{ statusText }}
              </span>
            </div>
            <p class="text-xs text-indigo-200/80 mt-1 max-w-2xl leading-relaxed">
              لایه کش اشیاء سرور برای تسریع پاسخ‌دهی داده‌ها، کوئری‌های تکراری ووکامرس و سگمنت‌های CRM.
            </p>
          </div>
        </div>

        <!-- Real-time Hit Ratio Widget (Correct Empty/Zero handling) -->
        <div class="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/10 text-center shrink-0 min-w-[170px]">
          <div class="text-[11px] text-indigo-200 font-semibold">نسبت موفقیت (Hit Ratio)</div>
          <div class="mt-1 font-black text-white" :class="hasHitRatioData ? 'text-2xl' : 'text-sm sm:text-base py-1'">
            <span v-if="hasHitRatioData">
              {{ toPersianDigits(cacheInfo?.telemetry?.hit_ratio_percent) }}٪
            </span>
            <span v-else class="text-indigo-200/90 font-bold">
              بدون داده
            </span>
          </div>
          <div class="text-[10px] text-indigo-200 mt-1 font-medium">
            {{ toPersianDigits(cacheInfo?.telemetry?.hits ?? 0) }} موفق / {{ toPersianDigits(cacheInfo?.telemetry?.misses ?? 0) }} ناموفق
          </div>
          <div v-if="!hasHitRatioData" class="text-[9px] text-indigo-300/70 mt-0.5">
            داده‌ای برای محاسبه ثبت نشده است
          </div>
        </div>
      </div>
    </div>

    <!-- Independent Cache Engines Status Row (Requirement 30) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- 1. File Cache Card (Always Functional & Standalone) -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm flex items-center justify-between">
        <div class="flex items-center gap-3.5">
          <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <Iconsax name="inventory" size="24" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="font-bold text-sm text-slate-900 dark:text-white">File Cache (دیسک محلی)</span>
              <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                فعال ✓
              </span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              {{ toPersianDigits(cacheInfo?.storage?.file_items_count ?? 0) }} آیتم ذخیره شده • حجم: {{ toPersianDigits(cacheInfo?.storage?.file_size_formatted ?? '0 KB') }}
            </div>
          </div>
        </div>
        <div class="text-[11px] text-slate-400 hidden sm:block text-left">
          مخزن پیش‌فرض امن
        </div>
      </div>

      <!-- 2. Object Cache Card (Memcached / Redis Status) -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm flex items-center justify-between">
        <div class="flex items-center gap-3.5">
          <div
            class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 border"
            :class="isObjectCacheConnected ? 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-200 text-emerald-600 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/60 border-rose-200 text-rose-600 dark:text-rose-400'"
          >
            <Iconsax :name="isObjectCacheConnected ? 'check' : 'close'" size="24" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="font-bold text-sm text-slate-900 dark:text-white">Object Cache (Memcached)</span>
              <span
                class="text-[11px] font-bold px-2 py-0.5 rounded-full border"
                :class="isObjectCacheConnected ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border-emerald-200' : 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 border-rose-200'"
              >
                {{ isObjectCacheConnected ? 'متصل ✓' : 'اتصال برقرار نیست ✕' }}
              </span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              نوع اتصال: {{ cacheInfo?.config?.memcached?.connection_type === 'socket' ? 'Unix Socket' : 'شبکه TCP' }} •
              پیکربندی: {{ activeConfigEndpoint }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Diagnostic Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
      <!-- Live Test Connection Card (Left 7 Cols) -->
      <div class="lg:col-span-7 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Iconsax name="activity" size="20" />
            </div>
            <div>
              <h2 class="text-sm font-bold text-slate-900 dark:text-white">تست زنده اتصال Object Cache</h2>
              <p class="text-xs text-slate-400 mt-0.5">ارسال درخواست PING / SET / GET / DELETE به سرور کش</p>
            </div>
          </div>
        </div>

        <div class="space-y-4 text-xs">
          <!-- Driver Selector -->
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5">انتخاب درایور جهت تست:</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
              <button
                type="button"
                @click="testForm.driver = 'file'"
                class="p-3 rounded-xl border text-center font-bold transition-all cursor-pointer"
                :class="testForm.driver === 'file' ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-500 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                File Cache
                <div class="text-[10px] font-normal text-slate-400 mt-0.5">محلی و پیش‌فرض</div>
              </button>

              <button
                type="button"
                @click="testForm.driver = 'memcached'"
                class="p-3 rounded-xl border text-center font-bold transition-all cursor-pointer"
                :class="testForm.driver === 'memcached' ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-500 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                Memcached
                <div class="text-[10px] font-normal mt-0.5" :class="hasAnyMemcachedExtension ? 'text-emerald-500' : 'text-slate-400'">
                  {{ hasAnyMemcachedExtension ? 'اکستنشن فعال' : 'نیازمند اکستنشن' }}
                </div>
              </button>

              <button
                type="button"
                @click="testForm.driver = 'redis'"
                class="p-3 rounded-xl border text-center font-bold transition-all cursor-pointer"
                :class="testForm.driver === 'redis' ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-500 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                Redis
                <div class="text-[10px] font-normal mt-0.5" :class="cacheInfo?.extensions?.redis ? 'text-emerald-500' : 'text-slate-400'">
                  {{ cacheInfo?.extensions?.redis ? 'اکستنشن فعال' : 'نیازمند اکستنشن' }}
                </div>
              </button>

              <button
                type="button"
                @click="testForm.driver = 'apcu'"
                class="p-3 rounded-xl border text-center font-bold transition-all cursor-pointer"
                :class="testForm.driver === 'apcu' ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-500 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                APCu
                <div class="text-[10px] font-normal mt-0.5" :class="cacheInfo?.extensions?.apcu ? 'text-emerald-500' : 'text-slate-400'">
                  {{ cacheInfo?.extensions?.apcu ? 'اکستنشن فعال' : 'نیازمند اکستنشن' }}
                </div>
              </button>
            </div>
          </div>

          <!-- Parameters for Memcached (TCP vs Unix Socket) -->
          <div v-if="testForm.driver === 'memcached'" class="space-y-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-2">نوع اتصال (Connection Type):</label>
              <div class="flex items-center gap-5 text-xs">
                <label class="flex items-center gap-1.5 cursor-pointer font-medium text-slate-700 dark:text-slate-300">
                  <input
                    type="radio"
                    v-model="testForm.connection_type"
                    value="tcp"
                    class="text-indigo-600 focus:ring-indigo-500"
                  />
                  <span>شبکه TCP (Host / Port)</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer font-medium text-slate-700 dark:text-slate-300">
                  <input
                    type="radio"
                    v-model="testForm.connection_type"
                    value="socket"
                    class="text-indigo-600 focus:ring-indigo-500"
                  />
                  <span>سوکت یونیکس (Unix Socket)</span>
                </label>
              </div>
            </div>

            <!-- TCP Inputs -->
            <div v-if="testForm.connection_type === 'tcp'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
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
                  placeholder="11211"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white dir-ltr text-right"
                />
              </div>
            </div>

            <!-- Unix Socket Inputs -->
            <div v-else class="pt-1 space-y-1.5">
              <label class="block font-semibold text-slate-700 dark:text-slate-300">مسیر فایل سوکت (Socket Path):</label>
              <input
                v-model="testForm.socket_path"
                type="text"
                placeholder="/memcached.sock"
                class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white dir-ltr text-right"
              />
              <p class="text-[11px] text-slate-400">
                در اتصال Unix Domain Socket، پورت صفر در نظر گرفته می‌شود و ارتباط مستقیماً از طریق فایل سوکت با سرور برقرار می‌گردد.
              </p>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="pt-2 flex items-center justify-between gap-3">
            <div class="text-[11px] text-slate-400 leading-tight">
              تست اتصال به‌صورت ایزوله انجام شده و به آمار Hit Ratio عمومی کاری ندارد.
            </div>
            <button
              type="button"
              @click="runTestConnection"
              :disabled="testing"
              class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold text-xs shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2 cursor-pointer shrink-0"
            >
              <div v-if="testing" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <Iconsax v-else name="activity" size="16" />
              <span>{{ testing ? 'در حال اجرای تست...' : 'تست اتصال' }}</span>
            </button>
          </div>

          <!-- Diagnostic Test Result Box -->
          <div
            v-if="testResult"
            class="p-4 rounded-2xl border transition-all"
            :class="testResult.success ? 'bg-emerald-50/70 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800' : 'bg-rose-50/70 dark:bg-rose-950/40 border-rose-300 dark:border-rose-800'"
          >
            <div class="flex items-start justify-between gap-3 mb-2.5">
              <div class="flex items-center gap-2 font-bold" :class="testResult.success ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300'">
                <span class="w-2.5 h-2.5 rounded-full" :class="testResult.success ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                <span>{{ testResult.success ? 'نتیجه تست: اتصال و عملیات موفق' : 'نتیجه تست: ناموفق / عدم اتصال' }}</span>
                <span
                  v-if="testResult.connection_status"
                  class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider"
                  :class="testResult.success ? 'bg-emerald-200 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200' : 'bg-rose-200 dark:bg-rose-900 text-rose-800 dark:text-rose-200'"
                >
                  {{ testResult.connection_status }}
                </span>
              </div>

              <div class="flex items-center gap-2">
                <span v-if="testResult.connection_type" class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-white/70 dark:bg-slate-900/70 text-slate-700 dark:text-slate-300 uppercase">
                  {{ testResult.connection_type }}
                </span>
                <span v-if="testResult.latency_ms > 0" class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-white/70 dark:bg-slate-900/70 text-slate-700 dark:text-slate-300" dir="ltr">
                  {{ toPersianDigits(testResult.latency_ms) }} ms
                </span>
              </div>
            </div>

            <!-- Error / Success explanation message -->
            <p class="text-xs mb-3 font-medium leading-relaxed" :class="testResult.success ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'">
              {{ testResult.message }}
            </p>

            <!-- Operational Steps Breakdown (Requirement 29) -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center text-[11px]">
              <div class="p-2 rounded-xl bg-white/80 dark:bg-slate-900/80 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">اکستنشن PHP</div>
                <div class="font-bold mt-0.5" :class="testResult.extension_installed ? 'text-emerald-600' : 'text-rose-600'">
                  {{ testResult.extension_installed ? 'تایید شد ✓' : 'عدم نصب ✕' }}
                </div>
              </div>

              <div class="p-2 rounded-xl bg-white/80 dark:bg-slate-900/80 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">اتصال (Connect)</div>
                <div class="font-bold mt-0.5" :class="testResult.connected ? 'text-emerald-600' : 'text-rose-600'">
                  {{ testResult.connected ? 'موفق ✓' : 'ناموفق ✕' }}
                </div>
              </div>

              <div class="p-2 rounded-xl bg-white/80 dark:bg-slate-900/80 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">نوشتن (Set)</div>
                <div class="font-bold mt-0.5" :class="testResult.write_ok ? 'text-emerald-600' : 'text-rose-600'">
                  {{ testResult.write_ok ? 'موفق ✓' : 'ناموفق ✕' }}
                </div>
              </div>

              <div class="p-2 rounded-xl bg-white/80 dark:bg-slate-900/80 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">خواندن (Get)</div>
                <div class="font-bold mt-0.5" :class="testResult.read_ok ? 'text-emerald-600' : 'text-rose-600'">
                  {{ testResult.read_ok ? 'موفق ✓' : 'ناموفق ✕' }}
                </div>
              </div>

              <div class="p-2 rounded-xl bg-white/80 dark:bg-slate-900/80 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">حذف (Delete)</div>
                <div class="font-bold mt-0.5" :class="testResult.delete_ok ? 'text-emerald-600' : 'text-rose-600'">
                  {{ testResult.delete_ok ? 'موفق ✓' : 'ناموفق ✕' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Environment & Server Diagnostics (Right 5 Cols) -->
      <div class="lg:col-span-5 space-y-4">
        <!-- Extensions State Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-4">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center">
              <Iconsax name="shield-tick" size="20" />
            </div>
            <div>
              <h2 class="text-sm font-bold text-slate-900 dark:text-white">وضعیت اکستنشن‌های PHP</h2>
              <p class="text-[11px] text-slate-400">شناسایی افزونه‌های فعال در محیط Runtime</p>
            </div>
          </div>

          <div class="space-y-2.5 text-xs">
            <!-- PHP Memcached -->
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-200">PHP Memcached (libmemcached)</span>
                <div class="text-[10px] text-slate-400">
                  {{ cacheInfo?.extension_versions?.memcached ? `نسخه ${cacheInfo.extension_versions.memcached}` : 'افزونه استاندارد Memcached' }}
                </div>
              </div>
              <span
                class="px-2 py-0.5 rounded-lg text-[10px] font-bold"
                :class="cacheInfo?.extensions?.memcached ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
              >
                {{ cacheInfo?.extensions?.memcached ? 'فعال ✓' : 'نصب نیست' }}
              </span>
            </div>

            <!-- PHP Memcache (Legacy) -->
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-200">PHP Memcache (افزونه جایگزین)</span>
                <div class="text-[10px] text-slate-400">
                  {{ cacheInfo?.extension_versions?.memcache ? `نسخه ${cacheInfo.extension_versions.memcache}` : 'افزونه قدیمی‌تر Memcache' }}
                </div>
              </div>
              <span
                class="px-2 py-0.5 rounded-lg text-[10px] font-bold"
                :class="cacheInfo?.extensions?.memcache ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
              >
                {{ cacheInfo?.extensions?.memcache ? 'فعال ✓' : 'نصب نیست' }}
              </span>
            </div>

            <!-- PHP Redis -->
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-200">PHP Redis Extension</span>
                <div class="text-[10px] text-slate-400">ساختار داده درون‌حافظه‌ای</div>
              </div>
              <span
                class="px-2 py-0.5 rounded-lg text-[10px] font-bold"
                :class="cacheInfo?.extensions?.redis ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
              >
                {{ cacheInfo?.extensions?.redis ? 'فعال ✓' : 'نصب نیست' }}
              </span>
            </div>

            <!-- PHP APCu -->
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-200">PHP APCu Extension</span>
                <div class="text-[10px] text-slate-400">کش مشترک فرآیندهای PHP</div>
              </div>
              <span
                class="px-2 py-0.5 rounded-lg text-[10px] font-bold"
                :class="cacheInfo?.extensions?.apcu ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'"
              >
                {{ cacheInfo?.extensions?.apcu ? 'فعال ✓' : 'نصب نیست' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Runtime Server Environment -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-3">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
              <Iconsax name="settings" size="20" />
            </div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">محیط اجرایی سیستم</h2>
          </div>

          <div class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-400">سیستم‌عامل سرور:</span>
              <span class="font-bold uppercase" dir="ltr">{{ cacheInfo?.system?.os_family || 'UNKNOWN' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
              <span class="text-slate-400">کاربر وب‌سرور (PHP User):</span>
              <span class="font-bold" dir="ltr">{{ cacheInfo?.system?.php_user || '—' }}</span>
            </div>
            <div class="flex justify-between py-1">
              <span class="text-slate-400">درایور فعال سیستم:</span>
              <span class="font-black text-indigo-600 dark:text-indigo-400 uppercase" dir="ltr">
                {{ cacheInfo?.active_driver || 'FILE' }}
              </span>
            </div>
          </div>
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
  connection_type: 'tcp',
  socket_path: '/memcached.sock',
  host: '127.0.0.1',
  port: 11211,
  password: '',
  database: 0,
});

const isObjectCacheConnected = computed(() => {
  return Boolean(cacheInfo.value?.drivers_status?.object_cache?.connected);
});

const hasAnyMemcachedExtension = computed(() => {
  return Boolean(cacheInfo.value?.extensions?.memcached || cacheInfo.value?.extensions?.memcache);
});

const hasHitRatioData = computed(() => {
  const telemetry = cacheInfo.value?.telemetry;
  if (!telemetry) return false;
  const total = (Number(telemetry.hits) || 0) + (Number(telemetry.misses) || 0);
  return total > 0 && telemetry.hit_ratio_percent !== null && telemetry.hit_ratio_percent !== undefined;
});

const activeConfigEndpoint = computed(() => {
  const memc = cacheInfo.value?.config?.memcached;
  if (!memc) return '127.0.0.1:11211';
  if (memc.connection_type === 'socket') {
    return memc.socket_path || '/memcached.sock';
  }
  return `${memc.host || '127.0.0.1'}:${memc.port || 11211}`;
});

const statusText = computed(() => {
  if (isObjectCacheConnected.value) return 'متصل و پایدار';
  return 'اتصال برقرار نیست';
});

const statusBadgeClasses = computed(() => {
  if (isObjectCacheConnected.value) {
    return 'bg-emerald-500/20 text-emerald-300 border-emerald-400/30';
  }
  return 'bg-rose-500/20 text-rose-300 border-rose-400/30';
});

async function fetchCacheInfo() {
  loading.value = true;
  try {
    const res = await apiClient.get('/system/cache');
    cacheInfo.value = res.data || {};
    if (cacheInfo.value?.config?.memcached) {
      testForm.connection_type = cacheInfo.value.config.memcached.connection_type || 'tcp';
      testForm.socket_path = cacheInfo.value.config.memcached.socket_path || '/memcached.sock';
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
      connection_type: testForm.connection_type,
      socket_path: testForm.socket_path,
      host: testForm.host,
      port: testForm.port,
      password: testForm.password || undefined,
      database: testForm.database,
    });
    testResult.value = res.data || {};
  } catch (err) {
    testResult.value = {
      success: false,
      driver: testForm.driver,
      connection_type: testForm.connection_type,
      connection_status: 'DISCONNECTED',
      message: err.message || 'خطا در برقراری ارتباط با سرور کش.',
      latency_ms: 0,
    };
  } finally {
    testing.value = false;
  }
}

async function flushCache() {
  if (!confirm('آیا از پاکسازی تمام داده‌های کش سامانه اطمینان دارید؟')) return;
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
