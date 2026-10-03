<template>
  <div class="space-y-6">
    <!-- Breadcrumb & Back -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <router-link to="/settings" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
          تنظیمات
        </router-link>
        <span>/</span>
        <span class="text-slate-800 dark:text-slate-200 font-bold">درباره برنامه</span>
      </div>

      <button
        @click="loadAboutData"
        :disabled="loading"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all disabled:opacity-50"
      >
        <Iconsax name="refresh" size="14" :class="{ 'animate-spin': loading }" />
        <span>بروزرسانی اطلاعات</span>
      </button>
    </div>

    <!-- Header Hero Banner -->
    <div class="relative overflow-hidden bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-950 rounded-3xl p-6 sm:p-8 text-white shadow-xl border border-indigo-800/40">
      <!-- Background subtle decorative shapes -->
      <div class="absolute -top-24 -left-24 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-violet-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row items-center md:items-start gap-6 text-center md:text-right">
        <!-- App Icon -->
        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-gradient-to-tr from-indigo-500 via-indigo-600 to-violet-500 p-0.5 shadow-2xl shadow-indigo-500/30 shrink-0 flex items-center justify-center">
          <div class="w-full h-full rounded-[14px] bg-indigo-950/40 backdrop-blur-sm flex items-center justify-center text-white">
            <Iconsax name="shop" size="44" />
          </div>
        </div>

        <!-- App Details -->
        <div class="flex-1 min-w-0 space-y-2.5">
          <div class="flex flex-wrap items-center justify-center md:justify-start gap-2.5">
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
              {{ aboutData?.app?.name || 'CRMWP' }}
            </h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
              نسخه {{ aboutData?.app?.version || '1.0.0' }}
            </span>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
              {{ aboutData?.app?.environment === 'production' ? 'محیط عملیاتی (Production)' : 'محیط محلی (Local)' }}
            </span>
          </div>

          <p class="text-sm text-indigo-100/90 font-medium">
            {{ aboutData?.app?.title || 'سامانه مدیریت یکپارچه ووکامرس و مشتریان (CRM)' }}
          </p>

          <p class="text-xs text-indigo-200/70 leading-relaxed max-w-3xl">
            {{ aboutData?.app?.description || 'این برنامه یک سامانه مستقل برای مدیریت فروشگاه WooCommerce، مشتریان، سفارش‌ها، محصولات و عملیات CRM است.' }}
          </p>
        </div>
      </div>
    </div>

    <!-- Main Grid: Developer & Store Connection -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <!-- Developer & Brand Card -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between space-y-4">
        <div>
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                <Iconsax name="shield-tick" size="20" />
              </div>
              <h2 class="text-sm font-bold text-slate-900 dark:text-white">توسعه‌دهنده</h2>
            </div>
            <span class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 px-2 py-0.5 rounded-lg">
              ناشر رسمی
            </span>
          </div>

          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <span class="text-slate-500 dark:text-slate-400">نام توسعه‌دهنده / تیم</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ aboutData?.developer?.name || 'تیم توسعه CRMWP' }}</span>
            </div>

            <div v-if="aboutData?.developer?.website" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <span class="text-slate-500 dark:text-slate-400">وب‌سایت</span>
              <a
                :href="aboutData.developer.website"
                target="_blank"
                rel="noopener noreferrer"
                class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                dir="ltr"
              >
                {{ formatUrl(aboutData.developer.website) }}
              </a>
            </div>

            <div v-if="aboutData?.developer?.email" class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <span class="text-slate-500 dark:text-slate-400">ایمیل ارتباطی</span>
              <a
                :href="'mailto:' + aboutData.developer.email"
                class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                dir="ltr"
              >
                {{ aboutData.developer.email }}
              </a>
            </div>
          </div>
        </div>

        <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800/60">
          طراحی و توسعه داده شده بر اساس الگوهای مدرن معماری نرم‌افزار و استانداردهای بومی
        </div>
      </div>

      <!-- Store Connection Card -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm flex flex-col justify-between space-y-4">
        <div>
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2.5">
              <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                <Iconsax name="shop" size="20" />
              </div>
              <h2 class="text-sm font-bold text-slate-900 dark:text-white">اتصال به فروشگاه</h2>
            </div>
            
            <!-- Connection Status Badge -->
            <div
              class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold"
              :class="isStoreConnected ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-300 dark:border-rose-800'"
            >
              <span class="w-2 h-2 rounded-full" :class="isStoreConnected ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
              <span>{{ isStoreConnected ? 'متصل' : 'قطع ارتباط' }}</span>
            </div>
          </div>

          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <span class="text-slate-500 dark:text-slate-400">نام فروشگاه</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ aboutData?.store?.name || 'فروشگاه آنلاین' }}</span>
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <span class="text-slate-500 dark:text-slate-400">آدرس فروشگاه</span>
              <a
                v-if="aboutData?.store?.url"
                :href="aboutData.store.url"
                target="_blank"
                rel="noopener noreferrer"
                class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                dir="ltr"
              >
                {{ formatUrl(aboutData.store.url) }}
              </a>
              <span v-else class="text-slate-400">-</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
                <div class="text-[10px] text-slate-400">نسخه WooCommerce</div>
                <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5" dir="ltr">
                  {{ aboutData?.store?.woocommerce_version || '11.1.2' }}
                </div>
              </div>

              <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
                <div class="text-[10px] text-slate-400">نسخه WordPress</div>
                <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5" dir="ltr">
                  {{ aboutData?.store?.wordpress_version || '7.1.2' }}
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
              <div class="flex items-center gap-1.5">
                <span class="text-slate-500 dark:text-slate-400">وضعیت HPOS</span>
                <span class="text-[10px] text-slate-400">(Order Storage)</span>
              </div>
              <span
                class="px-2 py-0.5 rounded-lg text-[11px] font-bold"
                :class="aboutData?.store?.hpos_enabled ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'"
              >
                {{ aboutData?.store?.hpos_enabled ? 'فعال (جداول اختصاصی)' : 'غیرفعال (جداول سنتی پست‌ها)' }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800/60">
          <span>واحد پول: <strong class="text-slate-700 dark:text-slate-300 font-semibold">{{ aboutData?.store?.currency === 'IRT' ? 'تومان (IRT)' : (aboutData?.store?.currency || 'تومان') }}</strong></span>
          <span>منطقه زمانی: <strong class="text-slate-700 dark:text-slate-300 font-semibold" dir="ltr">{{ aboutData?.store?.timezone || 'Asia/Tehran' }}</strong></span>
        </div>
      </div>
    </div>

    <!-- Technologies Used Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-5">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center">
            <Iconsax name="bulk" size="20" />
          </div>
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">تکنولوژی‌های استفاده‌شده</h2>
            <p class="text-xs text-slate-400 mt-0.5">زیرساخت‌های فنی، کتابخانه‌ها و موتورهای هسته سامانه</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- Frontend -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/30 border border-slate-200/60 dark:border-slate-700/40 space-y-3">
          <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
            <Iconsax name="dashboard" size="16" />
            <span>بخش Frontend</span>
          </div>
          <div class="space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">Vue.js 3</span>
              <span class="text-[10px] text-slate-400">Composition API</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">Vite</span>
              <span class="text-[10px] text-slate-400">Build Tool</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">Tailwind CSS</span>
              <span class="text-[10px] text-slate-400">Utility Styling</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">Vazirmatn</span>
              <span class="text-[10px] text-slate-400">فونت محلی</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">Iconsax</span>
              <span class="text-[10px] text-slate-400">آیکون‌های SVG محلی</span>
            </div>
          </div>
        </div>

        <!-- Backend -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/30 border border-slate-200/60 dark:border-slate-700/40 space-y-3">
          <div class="text-xs font-bold text-violet-600 dark:text-violet-400 flex items-center gap-1.5">
            <Iconsax name="activity" size="16" />
            <span>بخش Backend</span>
          </div>
          <div class="space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">PHP 8.4</span>
              <span class="text-[10px] text-slate-400">معماری لایه‌ای تمیز</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">REST API</span>
              <span class="text-[10px] text-slate-400">JSON API v1</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">Object Cache</span>
              <span class="text-[10px] text-slate-400">Multi-Driver Cache</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">Security Guard</span>
              <span class="text-[10px] text-slate-400">AES-256-GCM</span>
            </div>
          </div>
        </div>

        <!-- Database -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/30 border border-slate-200/60 dark:border-slate-700/40 space-y-3">
          <div class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
            <Iconsax name="inventory" size="16" />
            <span>پایگاه داده (Database)</span>
          </div>
          <div class="space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">MariaDB / MySQL</span>
              <span class="text-[10px] text-slate-400">ACID Relational</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">موتور ذخیره‌سازی</span>
              <span class="text-[10px] text-slate-400">InnoDB (utf8mb4)</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">مهاجرت‌ها (Migrations)</span>
              <span class="text-[10px] text-slate-400">خودکار و ماژولار</span>
            </div>
          </div>
        </div>

        <!-- Integration -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/30 border border-slate-200/60 dark:border-slate-700/40 space-y-3">
          <div class="text-xs font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
            <Iconsax name="shop" size="16" />
            <span>یکپارچگی (Integration)</span>
          </div>
          <div class="space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">WooCommerce REST API</span>
              <span class="text-[10px] text-slate-400">v3 Endpoints</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">موتور Webhooks</span>
              <span class="text-[10px] text-slate-400">دریافت بلادرنگ</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-200">HPOS Ready</span>
              <span class="text-[10px] text-slate-400">جداول اختصاصی سفارش</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Architecture & Flow Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-4">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
          <Iconsax name="segments" size="20" />
        </div>
        <div>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">معماری و جریان عملکرد سامانه</h2>
          <p class="text-xs text-slate-400 mt-0.5">نحوه ارتباط ماژول‌های برنامه با فروشگاه و پایگاه‌داده</p>
        </div>
      </div>

      <div class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40 space-y-4">
        <!-- Visual Flow Diagram -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-center">
          <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col items-center justify-center space-y-1">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Iconsax name="dashboard" size="18" />
            </div>
            <div class="text-xs font-bold text-slate-900 dark:text-white mt-1">پنل کاربر (CRM Frontend)</div>
            <div class="text-[11px] text-slate-400">رابط کاربری تک‌صفحه‌ای با Vue 3 و Tailwind</div>
          </div>

          <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col items-center justify-center space-y-1">
            <div class="w-8 h-8 rounded-lg bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center">
              <Iconsax name="activity" size="18" />
            </div>
            <div class="text-xs font-bold text-slate-900 dark:text-white mt-1">سرویس‌های مرکزی (PHP Backend)</div>
            <div class="text-[11px] text-slate-400">لایه Adapter، کش هوشمند و اعتبارسنجی امنیتی</div>
          </div>

          <div class="p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col items-center justify-center space-y-1">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <Iconsax name="shop" size="18" />
            </div>
            <div class="text-xs font-bold text-slate-900 dark:text-white mt-1">فروشگاه WooCommerce</div>
            <div class="text-[11px] text-slate-400">همگام‌سازی دوطرفه سفارش‌ها و محصولات</div>
          </div>
        </div>

        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed text-justify">
          در این سامانه، کلیه عملیات‌های CRM شامل ایجاد برچسب‌ها، سگمنت‌های هوشمند مشتریان، یادداشت‌ها، وظایف و ثبت لاگ‌های رخداد مستقیماً در پایگاه‌داده محلی ذخیره می‌شوند؛ در حالی که داده‌های سفارش‌ها، کالاها و مشتریان با فروشگاه ووکامرس از طریق REST API امن و وب‌هوک‌های بلادرنگ هماهنگ می‌مانند.
        </p>
      </div>
    </div>

    <!-- Runtime & Offline Independence Card -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <!-- Runtime Environment -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-3.5">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center">
            <Iconsax name="settings" size="20" />
          </div>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">محیط اجرایی (Runtime)</h2>
        </div>

        <div class="space-y-2.5 text-xs">
          <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
            <span class="text-slate-500 dark:text-slate-400">نسخه PHP سرور</span>
            <span class="font-bold text-slate-900 dark:text-white" dir="ltr">{{ aboutData?.runtime?.php_version || '8.4.x' }}</span>
          </div>

          <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
            <span class="text-slate-500 dark:text-slate-400">موتور پایگاه‌داده</span>
            <span class="font-bold text-slate-900 dark:text-white" dir="ltr">{{ aboutData?.runtime?.database?.version || 'MariaDB' }}</span>
          </div>

          <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
            <span class="text-slate-500 dark:text-slate-400">وضعیت کش حافظه</span>
            <span class="font-bold text-emerald-600 dark:text-emerald-400">فعال (درایور {{ aboutData?.runtime?.cache?.driver || 'File' }})</span>
          </div>
        </div>
      </div>

      <!-- Independence & UI Specs -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-3.5">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
            <Iconsax name="shield-tick" size="20" />
          </div>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">اجرای مستقل و رابط کاربری</h2>
        </div>

        <div class="p-3.5 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-200/60 dark:border-indigo-800/40">
          <div class="text-xs font-bold text-indigo-900 dark:text-indigo-300 mb-1">اجرای کاملاً مستقل</div>
          <p class="text-[11px] text-indigo-800/80 dark:text-indigo-200/80 leading-relaxed">
            این برنامه برای اجرای رابط کاربری خود به سرویس‌های خارجی یا CDN وابسته نیست و به شکل کاملاً محلی و امن بارگذاری می‌شود.
          </p>
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs">
          <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
            <div class="text-[10px] text-slate-400">تایپوگرافی</div>
            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">Vazirmatn محلی</div>
          </div>
          <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
            <div class="text-[10px] text-slate-400">پوسته و تم</div>
            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">روز (Light) و شب (Dark)</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer Copyright -->
    <div class="text-center py-4 text-xs text-slate-400 border-t border-slate-200/60 dark:border-slate-800/60">
      <div>
        © {{ currentYear }} {{ aboutData?.copyright?.holder || 'CRMWP' }}. کلیه حقوق محفوظ است.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';

const loading = ref(false);
const aboutData = ref(null);

const isStoreConnected = computed(() => {
  return aboutData.value?.store?.status === 'active' || aboutData.value?.store?.connection_status === 'connected';
});

const currentYear = computed(() => {
  return aboutData.value?.copyright?.year || new Date().getFullYear();
});

function formatUrl(url) {
  if (!url) return '';
  return url.replace(/^https?:\/\//, '').replace(/\/$/, '');
}

async function loadAboutData() {
  loading.value = true;
  try {
    const res = await apiClient.get('/system/about');
    aboutData.value = res.data?.data || res.data || {};
  } catch (err) {
    console.error('Failed to load about data:', err);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadAboutData();
});
</script>
