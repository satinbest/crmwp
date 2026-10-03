<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">مدیریت فروشگاه‌های ووکامرس</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          اتصال امن، پایش وضعیت، سنجش قابلیت‌ها (Capability Detection) و مدیریت فروشگاه‌های متصل
        </p>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <button
          v-if="authStore.hasPermission('stores.manage')"
          @click="openAddModal"
          class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all"
        >
          <Iconsax name="shop" size="18" />
          <span>افزودن فروشگاه جدید</span>
        </button>

        <RefreshButton
          @click="loadStores"
          :loading="loading"
          label="به‌روزرسانی"
          title="بروزرسانی فهرست فروشگاه‌ها"
        />
      </div>
    </div>

    <!-- Security & Isolation Banner -->
    <div class="bg-gradient-to-r from-indigo-900/10 via-indigo-600/5 to-transparent border border-indigo-200/60 dark:border-indigo-800/40 rounded-2xl p-4 flex items-start gap-3.5">
      <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-sm shadow-indigo-500/30">
        <Iconsax name="shield-tick" size="20" />
      </div>
      <div class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">
        <span class="font-bold text-slate-900 dark:text-white">امنیت و مرز ارتباطی: </span>
        مرورگر هرگز مستقیماً با ووکامرس ارتباط برقرار نمی‌کند. کلیدهای دسترسی (Consumer Key و Consumer Secret) با الگوریتم <span class="font-bold text-indigo-600 dark:text-indigo-400">AES-256-CBC</span> در دیتابیس سامانه رمزنگاری شده و در هیچ لاگ یا پاسخ عادی API درج نمی‌شوند.
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && stores.length === 0" class="p-16 text-center">
      <div class="w-9 h-9 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <div class="text-xs text-slate-400 font-medium">در حال دریافت لیست فروشگاه‌ها و وضعیت اتصال...</div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="stores.length === 0"
      class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-12 text-center shadow-sm"
    >
      <div class="w-16 h-16 rounded-3xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4">
        <Iconsax name="shop" size="32" />
      </div>
      <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">هیچ فروشگاهی ثبت نشده است</h3>
      <p class="text-xs text-slate-400 max-w-md mx-auto leading-relaxed mb-6">
        برای شروع ارتباط و همگام‌سازی، اولین فروشگاه ووکامرس خود را با وارد کردن آدرس و کلیدهای REST API اضافه نمایید.
      </p>
      <button
        v-if="authStore.hasPermission('stores.manage')"
        @click="openAddModal"
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/25 transition-all"
      >
        <Iconsax name="shop" size="18" />
        <span>شروع فرآیند اتصال فروشگاه</span>
      </button>
    </div>

    <!-- Stores Grid -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <div
        v-for="store in stores"
        :key="store.id"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between"
      >
        <div>
          <!-- Card Top Bar -->
          <div class="flex items-start justify-between gap-3 mb-4">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xl shadow-inner">
                {{ store.name.charAt(0) }}
              </div>
              <div>
                <h3 class="font-extrabold text-base text-slate-900 dark:text-white">{{ store.name }}</h3>
                <a
                  :href="store.url"
                  target="_blank"
                  class="text-xs text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors flex items-center gap-1 mt-0.5"
                >
                  <span>{{ store.url }}</span>
                </a>
              </div>
            </div>

            <!-- Status Badge -->
            <div class="flex flex-col items-end gap-1">
              <span
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold"
                :class="getStatusBadgeClass(store.status)"
              >
                <span class="w-2 h-2 rounded-full" :class="getStatusDotClass(store.status)"></span>
                <span>{{ getStatusLabel(store.status) }}</span>
              </span>
              <span v-if="store.hpos_enabled" class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded-full border border-emerald-500/20">
                HPOS فعال
              </span>
              <span v-else class="text-[10px] text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">
                ذخیره‌سازی سنتی (Legacy)
              </span>
            </div>
          </div>

          <!-- Store Meta Specs -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 my-4 p-3.5 bg-slate-50 dark:bg-slate-850 rounded-2xl border border-slate-100 dark:border-slate-800/80 text-xs">
            <div>
              <div class="text-[10px] text-slate-400">ووکامرس</div>
              <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                {{ store.woocommerce_version || store.wc_version || '—' }}
              </div>
            </div>

            <div>
              <div class="text-[10px] text-slate-400">وردپرس</div>
              <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                {{ store.wordpress_version || store.wp_version || '—' }}
              </div>
            </div>

            <div>
              <div class="text-[10px] text-slate-400">واحد پول</div>
              <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                {{ store.currency || 'IRR' }}
              </div>
            </div>

            <div>
              <div class="text-[10px] text-slate-400">منطقه زمانی</div>
              <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5 truncate">
                {{ store.timezone || 'Asia/Tehran' }}
              </div>
            </div>
          </div>

          <!-- Credentials Preview (Masked) -->
          <div class="space-y-1.5 text-xs text-slate-500 bg-slate-100/60 dark:bg-slate-950/40 p-3 rounded-xl border border-slate-200/50 dark:border-slate-800/60 mb-4">
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Consumer Key:</span>
              <span class="font-semibold">{{ store.consumer_key_masked }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Consumer Secret:</span>
              <span class="font-semibold tracking-widest text-slate-400">{{ store.consumer_secret_masked }}</span>
            </div>
          </div>

          <!-- Error notice if status is error -->
          <div v-if="store.last_error" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-xl text-xs text-rose-700 dark:text-rose-300 mb-4 leading-relaxed">
            <span class="font-bold">خطای اتصال: </span>
            {{ store.last_error }}
          </div>
        </div>

        <!-- Card Footer Actions -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
          <div class="text-[11px] text-slate-400">
            آخرین بررسی: <span class="font-medium text-slate-600 dark:text-slate-300">{{ store.last_connection_check ? formatDateTime(store.last_connection_check) : '—' }}</span>
          </div>

          <div class="flex items-center gap-1.5 flex-wrap justify-end">
            <!-- Health Summary Button -->
            <button
              @click="openHealthModal(store)"
              class="flex items-center gap-1 px-2.5 py-1.5 bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 rounded-lg text-xs font-semibold transition-colors"
              title="خلاصه وضعیت سلامت فروشگاه"
            >
              <Iconsax name="shield-tick" size="14" />
              <span>سلامت</span>
            </button>

            <!-- Webhook & Reconcile Button -->
            <button
              @click="openIntegrationModal(store)"
              class="flex items-center gap-1 px-2.5 py-1.5 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 rounded-lg text-xs font-semibold transition-colors"
              title="مدیریت وب‌هوک‌ها، لاگ‌ها و تطبیق داده‌ها"
            >
              <Iconsax name="activity" size="14" />
              <span>وب‌هوک و تطبیق</span>
            </button>

            <!-- View Capabilities Button -->
            <button
              @click="openCapabilities(store)"
              class="px-2.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-semibold transition-colors"
            >
              قابلیت‌ها
            </button>

            <!-- Test Connection Button -->
            <button
              v-if="authStore.hasPermission('stores.manage')"
              @click="testStoreConnection(store)"
              :disabled="testingStoreId === store.id"
              class="flex items-center gap-1 px-3 py-1.5 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 rounded-lg text-xs font-semibold transition-colors disabled:opacity-50"
            >
              <Iconsax name="refresh" size="14" :class="{ 'animate-spin': testingStoreId === store.id }" />
              <span>تست اتصال</span>
            </button>

            <!-- Toggle Status (Soft Disable / Enable) -->
            <button
              v-if="authStore.hasPermission('stores.manage')"
              @click="toggleStoreStatus(store)"
              :disabled="togglingStoreId === store.id"
              class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-colors disabled:opacity-50"
              :class="store.status === 'active' ? 'border-amber-200 dark:border-amber-800/80 text-amber-700 dark:text-amber-300 bg-amber-50/50 dark:bg-amber-950/40 hover:bg-amber-100' : 'border-emerald-200 dark:border-emerald-800/80 text-emerald-700 dark:text-emerald-300 bg-emerald-50/50 dark:bg-emerald-950/40 hover:bg-emerald-100'"
              :title="store.status === 'active' ? 'غیرفعال‌سازی موقت فروشگاه' : 'فعال‌سازی مجدد فروشگاه'"
            >
              {{ store.status === 'active' ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}
            </button>

            <!-- Edit Button -->
            <button
              v-if="authStore.hasPermission('stores.manage')"
              @click="openEditModal(store)"
              class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              title="ویرایش مشخصات"
            >
              <Iconsax name="settings" size="16" />
            </button>

            <!-- Delete Button -->
            <button
              v-if="authStore.hasPermission('stores.manage')"
              @click="confirmDelete(store)"
              class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
              title="حذف فروشگاه"
            >
              <Iconsax name="close" size="16" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= ADD STORE MULTI-STEP MODAL ================= -->
    <div
      v-if="showAddModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
      @click.self="closeAddModal"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-xl w-full overflow-hidden transition-all">
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div>
            <h3 class="font-extrabold text-base text-slate-900 dark:text-white">اتصال فروشگاه ووکامرس جدید</h3>
            <p class="text-xs text-slate-400 mt-0.5">فرآیند مرحله‌به‌مرحله اتصال امن به REST API</p>
          </div>
          <button @click="closeAddModal" class="p-1.5 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <!-- Step Indicator -->
        <div class="px-6 pt-5 pb-3">
          <div class="flex items-center justify-between relative">
            <div class="absolute top-1/2 left-0 right-0 -translate-y-1/2 h-0.5 bg-slate-200 dark:bg-slate-800 -z-0"></div>
            <div
              v-for="s in [1, 2, 3, 4]"
              :key="s"
              class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold relative z-10 transition-all"
              :class="getStepCircleClass(s)"
            >
              {{ s }}
            </div>
          </div>
          <div class="flex justify-between text-[11px] font-medium text-slate-400 mt-2 px-1">
            <span>آدرس سایت</span>
            <span>کلیدهای API</span>
            <span>تست اتصال</span>
            <span>تأیید و ذخیره</span>
          </div>
        </div>

        <!-- Step Content Area -->
        <div class="p-6">
          <!-- STEP 1: Name & URL -->
          <div v-if="currentStep === 1" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">نام دلخواه فروشگاه</label>
              <input
                v-model="addForm.name"
                type="text"
                placeholder="مثال: فروشگاه اصلی تهران"
                class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">آدرس وب‌سایت فروشگاه (URL)</label>
              <input
                v-model="addForm.url"
                type="text"
                placeholder="https://myshop.com"
                class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
              />
              <div class="text-[11px] text-slate-400 mt-1 leading-relaxed">
                لطفاً آدرس ریشه سایت را وارد کنید. از وارد کردن مسیرهای اضافی نظیر <code class="text-rose-500">/wp-admin</code> یا <code class="text-rose-500">/wp-json</code> خودداری نمایید.
              </div>
            </div>
          </div>

          <!-- STEP 2: Consumer Key & Secret -->
          <div v-else-if="currentStep === 2" class="space-y-4">
            <div class="p-3.5 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200/60 dark:border-indigo-800/40 rounded-xl text-xs text-indigo-950 dark:text-indigo-200 leading-relaxed">
              <span class="font-bold">راهنمای ساخت کلید در ووکامرس:</span>
              در پیشخوان وردپرس به مسیر <strong>ووکامرس &gt; پیکربندی &gt; پیشرفته &gt; REST API</strong> مراجعه کرده و یک کلید با دسترسی <strong>خواندن/نوشتن (Read/Write)</strong> ایجاد کنید.
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Consumer Key</label>
              <input
                v-model="addForm.consumer_key"
                type="text"
                placeholder="ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Consumer Secret</label>
              <input
                v-model="addForm.consumer_secret"
                type="password"
                placeholder="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <!-- STEP 3: Live Testing -->
          <div v-else-if="currentStep === 3" class="space-y-4 text-center py-4">
            <div v-if="testingConnection" class="space-y-3">
              <div class="w-12 h-12 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
              <div class="text-sm font-bold text-slate-900 dark:text-white">در حال آزمون ارتباط با ووکامرس...</div>
              <div class="text-xs text-slate-400">بررسی REST API، احراز هویت، و سنجش قابلیت‌های فروشگاه</div>
            </div>

            <div v-else-if="testError" class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 rounded-2xl text-right">
              <div class="text-xs font-bold text-rose-700 dark:text-rose-300 mb-1">خطا در برقراری اتصال با فروشگاه</div>
              <p class="text-xs text-rose-600 dark:text-rose-400 leading-relaxed">{{ testError }}</p>
              <div class="mt-3 text-[11px] text-slate-500">
                لطفاً از صحت آدرس دامنه، فعال بودن ووکامرس و درستی کلیدها اطمینان حاصل نمایید.
              </div>
            </div>
          </div>

          <!-- STEP 4: Success Result & Capabilities Preview -->
          <div v-else-if="currentStep === 4" class="space-y-4">
            <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/60 rounded-2xl flex items-center gap-3">
              <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                <Iconsax name="check" size="18" />
              </span>
              <div>
                <div class="text-xs font-bold text-emerald-800 dark:text-emerald-200">اتصال با موفقیت برقرار و تأیید شد!</div>
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5">مشخصات محیطی ووکامرس با موفقیت دریافت گردید.</div>
              </div>
            </div>

            <div class="bg-slate-50 dark:bg-slate-850 rounded-2xl p-4 border border-slate-200/60 dark:border-slate-800/80 space-y-2 text-xs">
              <div class="flex justify-between py-1 border-b border-slate-200/40 dark:border-slate-800">
                <span class="text-slate-400">نسخه ووکامرس:</span>
                <span class="font-bold">{{ connectionResult?.woocommerce_version }}</span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-200/40 dark:border-slate-800">
                <span class="text-slate-400">نسخه وردپرس:</span>
                <span class="font-bold">{{ connectionResult?.wordpress_version }}</span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-200/40 dark:border-slate-800">
                <span class="text-slate-400">وضعیت جداول پرسرعت سفارش‌ها (HPOS):</span>
                <span class="font-bold" :class="connectionResult?.hpos_enabled ? 'text-emerald-600' : 'text-slate-500'">
                  {{ connectionResult?.hpos_enabled ? 'فعال (HPOS Enabled)' : 'غیرفعال (Legacy Posts Storage)' }}
                </span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-200/40 dark:border-slate-800">
                <span class="text-slate-400">واحد پول فروشگاه:</span>
                <span class="font-bold">{{ connectionResult?.currency }} ({{ connectionResult?.currency_symbol }})</span>
              </div>
              <div class="flex justify-between py-1">
                <span class="text-slate-400">منطقه زمانی:</span>
                <span class="font-bold">{{ connectionResult?.timezone }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer Navigation -->
        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-850 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <button
            v-if="currentStep > 1 && currentStep < 4"
            @click="currentStep--"
            class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            مرحله قبل
          </button>
          <div v-else></div>

          <div class="flex items-center gap-2">
            <button
              v-if="currentStep === 1"
              @click="goToStep2"
              class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-semibold hover:bg-indigo-500"
            >
              مرحله بعد: کلیدهای دسترسی
            </button>

            <button
              v-else-if="currentStep === 2"
              @click="runStep3Test"
              class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-semibold hover:bg-indigo-500"
            >
              تست اتصال زنده
            </button>

            <button
              v-else-if="currentStep === 3 && testError"
              @click="runStep3Test"
              class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-semibold hover:bg-indigo-500"
            >
              تلاش مجدد تست
            </button>

            <button
              v-else-if="currentStep === 4"
              @click="finalizeAddStore"
              :disabled="saving"
              class="flex items-center gap-2 px-6 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/20 disabled:opacity-50"
            >
              <span v-if="saving" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>ذخیره و فعال‌سازی فروشگاه</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= EDIT STORE MODAL ================= -->
    <div
      v-if="editingStore"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
      @click.self="editingStore = null"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <h3 class="font-extrabold text-base text-slate-900 dark:text-white">ویرایش مشخصات فروشگاه</h3>
          <button @click="editingStore = null" class="p-1.5 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <form @submit.prevent="saveEditStore" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">نام فروشگاه</label>
            <input
              v-model="editForm.name"
              type="text"
              required
              class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">آدرس سایت (URL)</label>
            <input
              v-model="editForm.url"
              type="text"
              required
              class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 mb-2">جایگزینی کلیدها (اختیاری)</div>
            <p class="text-[11px] text-slate-400 mb-3">تنها در صورت نیاز به تغییر یا بازسازی کلیدهای REST API فیلدهای زیر را پر کنید:</p>

            <div class="space-y-3">
              <input
                v-model="editForm.consumer_key"
                type="text"
                placeholder="Consumer Key جدید (خالی بگذارید تا تغییر نکند)"
                class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
              />
              <input
                v-model="editForm.consumer_secret"
                type="password"
                placeholder="Consumer Secret جدید (خالی بگذارید تا تغییر نکند)"
                class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <div class="pt-4 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="editingStore = null"
              class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-semibold"
            >
              انصراف
            </button>
            <button
              type="submit"
              :disabled="savingEdit"
              class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold disabled:opacity-50"
            >
              {{ savingEdit ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= CAPABILITIES MODAL ================= -->
    <div
      v-if="viewingCapabilitiesStore"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
      @click.self="viewingCapabilitiesStore = null"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-2xl w-full max-h-[85vh] overflow-y-auto">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md z-10">
          <div>
            <h3 class="font-extrabold text-base text-slate-900 dark:text-white">قابلیت‌های شناسایی‌شده ووکامرس</h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ viewingCapabilitiesStore.name }}</p>
          </div>
          <button @click="viewingCapabilitiesStore = null" class="p-1.5 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <div class="p-6 space-y-5">
          <!-- Capabilities Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800 flex items-center justify-between">
              <div>
                <div class="font-bold text-slate-800 dark:text-slate-200">وضعیت REST API</div>
                <div class="text-[11px] text-slate-400">wc/v3 Endpoint</div>
              </div>
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950 text-emerald-600">فعال و در دسترس</span>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800 flex items-center justify-between">
              <div>
                <div class="font-bold text-slate-800 dark:text-slate-200">مدیریت وب‌هوک‌ها (Webhooks)</div>
                <div class="text-[11px] text-slate-400">همگام‌سازی بلادرنگ</div>
              </div>
              <span
                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                :class="currentCaps?.webhooks_supported ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-600' : 'bg-slate-100 text-slate-500'"
              >
                {{ currentCaps?.webhooks_supported ? 'پشتیبانی می‌شود' : 'غیرفعال' }}
              </span>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800 flex items-center justify-between">
              <div>
                <div class="font-bold text-slate-800 dark:text-slate-200">معماری سفارش‌ها (HPOS)</div>
                <div class="text-[11px] text-slate-400">High-Performance Order Storage</div>
              </div>
              <span
                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                :class="currentCaps?.hpos_enabled ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-600' : 'bg-slate-100 text-slate-500'"
              >
                {{ currentCaps?.hpos_enabled ? 'جداول اختصاصی فعال' : 'سنتی (wp_posts)' }}
              </span>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800 flex items-center justify-between">
              <div>
                <div class="font-bold text-slate-800 dark:text-slate-200">استرداد وجه سفارش (Refunds)</div>
                <div class="text-[11px] text-slate-400">ثبت استرداد از طریق API</div>
              </div>
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950 text-emerald-600">پشتیبانی می‌شود</span>
            </div>
          </div>

          <!-- Dynamic Order Statuses Section -->
          <div>
            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 mb-2">وضعیت‌های سفارش موجود در فروشگاه (عدم فرض وضعیت‌های ثابت)</div>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="st in (currentCaps?.custom_order_statuses || [])"
                :key="st.slug"
                class="px-3 py-1 rounded-xl text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60 flex items-center gap-1.5"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                <span>{{ st.name || st.slug }}</span>
                <span class="text-[10px] text-slate-400 dir-ltr">({{ st.slug }})</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= DELETE CONFIRMATION DIALOG ================= -->
    <div
      v-if="deletingStore"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
      @click.self="deletingStore = null"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-lg w-full p-6 text-center">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto mb-4">
          <Iconsax name="close" size="28" />
        </div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">حذف یا غیرفعال‌سازی فروشگاه</h3>
        <p class="text-xs text-slate-500 leading-relaxed mb-4">
          آیا از حذف اتصال فروشگاه «<span class="font-bold text-slate-800 dark:text-slate-200">{{ deletingStore.name }}</span>» از این سامانه مطمئن هستید؟
        </p>

        <!-- CRM Records Count Warning -->
        <div v-if="loadingCrmCounts" class="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl text-xs text-slate-400 mb-4">
          در حال بررسی داده‌های CRM وابسته به این فروشگاه...
        </div>
        <div
          v-else-if="crmCounts && crmCounts.total > 0"
          class="p-3.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-xl text-xs text-rose-800 dark:text-rose-200 text-right mb-4 space-y-1.5"
        >
          <div class="font-bold flex items-center gap-1.5">
            <span>⚠️ هشدار وجود داده‌های CRM:</span>
          </div>
          <p class="leading-relaxed">
            این فروشگاه دارای <strong class="font-bold text-rose-600 dark:text-rose-300">{{ crmCounts.total }}</strong> رکورد ثبت‌شده در پایگاه داده سامانه است:
          </p>
          <div class="grid grid-cols-2 gap-1 text-[11px] pt-1">
            <span>• وظایف (Tasks): {{ crmCounts.tasks }}</span>
            <span>• برچسب‌ها (Tags): {{ crmCounts.tags }}</span>
            <span>• سگمنت‌ها (Segments): {{ crmCounts.segments }}</span>
            <span>• فعالیت‌ها (Activities): {{ crmCounts.activities }}</span>
            <span>• یادداشت‌ها: {{ crmCounts.customer_notes }}</span>
            <span>• لاگ‌های وب‌هوک و سینک: {{ crmCounts.webhook_logs + crmCounts.sync_logs }}</span>
          </div>
          <p class="pt-1 text-[11px] text-rose-700 dark:text-rose-300 font-medium">
            توصیه می‌شود به جای حذف دائم، فروشگاه را موقتاً غیرفعال نمایید.
          </p>
        </div>

        <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/60 rounded-xl text-xs text-amber-800 dark:text-amber-200 text-right mb-6 leading-relaxed">
          <span class="font-bold">یادآوری مهم: </span>
          حذف فروشگاه داده‌های ووکامرس اصلی را پاک نمی‌کند، اما دسترسی پنل قطع شده و طبق تنظیمات دیتابیس داده‌های محلی پاک خواهند شد.
        </div>

        <div class="flex items-center justify-center gap-2 flex-wrap">
          <button
            @click="deletingStore = null"
            class="px-4 py-2.5 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold"
          >
            انصراف
          </button>

          <!-- Soft Disable Option -->
          <button
            @click="softDisableFromDelete"
            class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-500/20"
          >
            غیرفعال‌سازی موقت (Soft Disable)
          </button>

          <!-- Hard Delete -->
          <button
            @click="executeDelete"
            :disabled="executingDelete"
            class="px-4 py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-md shadow-rose-600/20 disabled:opacity-50"
          >
            {{ executingDelete ? 'در حال حذف...' : 'حذف قطعی' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ================= STORE HEALTH SUMMARY MODAL ================= -->
    <div
      v-if="viewingHealthStore"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
      @click.self="viewingHealthStore = null"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-xl w-full max-h-[85vh] overflow-y-auto">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md z-10">
          <div>
            <h3 class="font-extrabold text-base text-slate-900 dark:text-white">خلاصه وضعیت سلامت فروشگاه</h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ viewingHealthStore.name }}</p>
          </div>
          <button @click="viewingHealthStore = null" class="p-1.5 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div v-if="loadingHealth" class="py-12 text-center text-xs text-slate-400">
            در حال دریافت گزارش سلامت فروشگاه...
          </div>
          <div v-else-if="healthSummary" class="space-y-4">
            <!-- Connection Status Pill -->
            <div class="p-4 rounded-2xl border flex items-center justify-between"
              :class="healthSummary.connection?.api_available ? 'bg-emerald-50/50 dark:bg-emerald-950/40 border-emerald-200/60 dark:border-emerald-800/60' : 'bg-rose-50/50 dark:bg-rose-950/40 border-rose-200/60 dark:border-rose-800/60'">
              <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full" :class="healthSummary.connection?.api_available ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                <div>
                  <div class="text-xs font-bold text-slate-800 dark:text-slate-200">وضعیت اتصال WooCommerce REST API</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400">
                    {{ healthSummary.connection?.api_available ? 'ارتباط پایدار و فعال' : 'خطای ارتباط با API' }}
                  </div>
                </div>
              </div>
              <span class="text-xs font-bold" :class="healthSummary.connection?.api_available ? 'text-emerald-600' : 'text-rose-600'">
                {{ healthSummary.connection?.status }}
              </span>
            </div>

            <!-- Health Grid -->
            <div class="grid grid-cols-2 gap-3 text-xs">
              <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">وضعیت وب‌هوک‌ها</div>
                <div class="font-bold text-slate-800 dark:text-slate-200 mt-1 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full" :class="healthSummary.webhooks?.healthy ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                  <span>{{ healthSummary.webhooks?.healthy ? 'سالم و فعال' : 'نیازمند بررسی' }}</span>
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                  ناموفق ۲۴ ساعت اخیر: {{ toPersianDigits(healthSummary.webhooks?.failed_last_24h || 0) }}
                </div>
              </div>

              <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">وضعیت همگام‌سازی (Sync)</div>
                <div class="font-bold text-slate-800 dark:text-slate-200 mt-1 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full" :class="healthSummary.sync?.healthy ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                  <span>{{ healthSummary.sync?.healthy ? 'همگام و منظم' : 'خطا در آخرین سینک' }}</span>
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                  ناموفق ۲۴ ساعت اخیر: {{ toPersianDigits(healthSummary.sync?.failed_last_24h || 0) }}
                </div>
              </div>

              <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">آخرین وب‌هوک دریافتی</div>
                <div class="font-semibold text-slate-800 dark:text-slate-200 mt-1 text-[11px] truncate">
                  {{ healthSummary.webhooks?.last_delivery ? formatDateTime(healthSummary.webhooks?.last_delivery) : '—' }}
                </div>
              </div>

              <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800">
                <div class="text-slate-400 text-[10px]">آخرین تطبیق موفق</div>
                <div class="font-semibold text-slate-800 dark:text-slate-200 mt-1 text-[11px] truncate">
                  {{ healthSummary.sync?.last_sync ? formatDateTime(healthSummary.sync?.last_sync) : '—' }}
                </div>
              </div>
            </div>

            <!-- Error Notice if Last Error Exists -->
            <div v-if="healthSummary.connection?.last_error" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-xl text-xs text-rose-700 dark:text-rose-300 leading-relaxed">
              <span class="font-bold">آخرین خطای ثبت شده: </span>
              {{ healthSummary.connection.last_error }}
            </div>
          </div>
        </div>

        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-850 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end">
          <button
            @click="viewingHealthStore = null"
            class="px-4 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold"
          >
            بستن
          </button>
        </div>
      </div>
    </div>

    <!-- Store Integration (Webhooks, Reconcile, Health) Modal -->
    <StoreIntegrationModal
      v-model="showIntegrationModal"
      :store="selectedIntegrationStore"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useNotificationStore } from '@/stores/notification';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import StoreIntegrationModal from '@/components/stores/StoreIntegrationModal.vue';
import { formatDateTime, toPersianDigits } from '@/utils/formatters';

const authStore = useAuthStore();
const notification = useNotificationStore();

const stores = ref([]);
const loading = ref(true);
const testingStoreId = ref(null);

// Store Integration Modal State
const showIntegrationModal = ref(false);
const selectedIntegrationStore = ref(null);

const openIntegrationModal = (store) => {
  selectedIntegrationStore.value = store;
  showIntegrationModal.value = true;
};

// Add Modal multi-step state
const showAddModal = ref(false);
const currentStep = ref(1);
const addForm = ref({
  name: '',
  url: '',
  consumer_key: '',
  consumer_secret: '',
});
const testingConnection = ref(false);
const testError = ref(null);
const connectionResult = ref(null);
const saving = ref(false);

// Edit Modal state
const editingStore = ref(null);
const editForm = ref({
  name: '',
  url: '',
  consumer_key: '',
  consumer_secret: '',
});
const savingEdit = ref(false);

// Capabilities Modal state
const viewingCapabilitiesStore = ref(null);
const currentCaps = ref(null);

// Delete state
const deletingStore = ref(null);
const executingDelete = ref(false);

const loadStores = async () => {
  loading.value = true;
  try {
    const res = await apiClient.get('/stores');
    stores.value = res.data;
  } catch (e) {
    stores.value = [];
  } finally {
    loading.value = false;
  }
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'active':
      return 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20';
    case 'error':
      return 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-500/20';
    default:
      return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';
  }
};

const getStatusDotClass = (status) => {
  switch (status) {
    case 'active': return 'bg-emerald-500';
    case 'error': return 'bg-rose-500';
    default: return 'bg-slate-400';
  }
};

const getStatusLabel = (status) => {
  switch (status) {
    case 'active': return 'متصل و فعال';
    case 'error': return 'خطای اتصال';
    default: return 'غیرفعال';
  }
};

const getStepCircleClass = (step) => {
  if (currentStep.value === step) {
    return 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 ring-4 ring-indigo-500/20';
  } else if (currentStep.value > step) {
    return 'bg-emerald-600 text-white';
  } else {
    return 'bg-slate-100 dark:bg-slate-800 text-slate-400';
  }
};

// Add Store Flow
const openAddModal = () => {
  showAddModal.value = true;
  currentStep.value = 1;
  addForm.value = { name: '', url: '', consumer_key: '', consumer_secret: '' };
  testError.value = null;
  connectionResult.value = null;
};

const closeAddModal = () => {
  showAddModal.value = false;
};

const goToStep2 = () => {
  if (!addForm.value.name.trim()) {
    notification.warning('لطفاً نام فروشگاه را وارد نمایید.');
    return;
  }
  if (!addForm.value.url.trim()) {
    notification.warning('لطفاً آدرس وب‌سایت فروشگاه را وارد نمایید.');
    return;
  }
  currentStep.value = 2;
};

const runStep3Test = async () => {
  if (!addForm.value.consumer_key.trim() || !addForm.value.consumer_secret.trim()) {
    notification.warning('لطفاً Consumer Key و Consumer Secret را وارد نمایید.');
    return;
  }

  currentStep.value = 3;
  testingConnection.value = true;
  testError.value = null;

  try {
    const res = await apiClient.post('/stores/test-connection', {
      url: addForm.value.url,
      consumer_key: addForm.value.consumer_key,
      consumer_secret: addForm.value.consumer_secret,
    });
    connectionResult.value = res.data;
    currentStep.value = 4;
    notification.success('ارتباط با ووکامرس تأیید گردید.');
  } catch (err) {
    testError.value = err.message || 'خطا در برقراری ارتباط با ووکامرس.';
  } finally {
    testingConnection.value = false;
  }
};

const finalizeAddStore = async () => {
  saving.value = true;
  try {
    await apiClient.post('/stores', {
      name: addForm.value.name,
      url: addForm.value.url,
      consumer_key: addForm.value.consumer_key,
      consumer_secret: addForm.value.consumer_secret,
    });
    notification.success('فروشگاه با موفقیت متصل و ذخیره شد.');
    closeAddModal();
    loadStores();
  } catch (err) {
    notification.error(err.message || 'خطا در ثبت فروشگاه');
  } finally {
    saving.value = false;
  }
};

// Test Existing Store
const testStoreConnection = async (store) => {
  testingStoreId.value = store.id;
  try {
    const res = await apiClient.post(`/stores/${store.id}/test`);
    notification.success(`اتصال فروشگاه «${store.name}» با موفقیت تأیید شد.`);
    loadStores();
  } catch (err) {
    notification.error(err.message || 'خطا در تست اتصال');
    loadStores();
  } finally {
    testingStoreId.value = null;
  }
};

// View Capabilities
const openCapabilities = async (store) => {
  viewingCapabilitiesStore.value = store;
  currentCaps.value = store.capabilities || null;

  try {
    const res = await apiClient.get(`/stores/${store.id}/capabilities`);
    currentCaps.value = res.data;
  } catch (e) {
    //
  }
};

// Edit Store
const openEditModal = (store) => {
  editingStore.value = store;
  editForm.value = {
    name: store.name,
    url: store.url,
    consumer_key: '',
    consumer_secret: '',
  };
};

const saveEditStore = async () => {
  savingEdit.value = true;
  try {
    const payload = {
      name: editForm.value.name,
      url: editForm.value.url,
    };
    if (editForm.value.consumer_key.trim()) {
      payload.consumer_key = editForm.value.consumer_key.trim();
    }
    if (editForm.value.consumer_secret.trim()) {
      payload.consumer_secret = editForm.value.consumer_secret.trim();
    }

    await apiClient.patch(`/stores/${editingStore.value.id}`, payload);
    notification.success('اطلاعات فروشگاه با موفقیت بروزرسانی شد.');
    editingStore.value = null;
    loadStores();
  } catch (err) {
    notification.error(err.message || 'خطا در بروزرسانی مشخصات فروشگاه');
  } finally {
    savingEdit.value = false;
  }
};

// Health Summary state
const viewingHealthStore = ref(null);
const loadingHealth = ref(false);
const healthSummary = ref(null);
const togglingStoreId = ref(null);

// Delete state additions
const crmCounts = ref(null);
const loadingCrmCounts = ref(false);

const openHealthModal = async (store) => {
  viewingHealthStore.value = store;
  loadingHealth.value = true;
  healthSummary.value = null;
  try {
    const res = await apiClient.get(`/stores/${store.id}/health`);
    healthSummary.value = res.data;
  } catch (err) {
    notification.error(err.message || 'خطا در دریافت وضعیت سلامت فروشگاه');
  } finally {
    loadingHealth.value = false;
  }
};

const toggleStoreStatus = async (store) => {
  togglingStoreId.value = store.id;
  try {
    const res = await apiClient.post(`/stores/${store.id}/toggle-status`);
    const newStatus = res.data?.status === 'active' ? 'فعال' : 'غیرفعال';
    notification.success(`وضعیت فروشگاه «${store.name}» به ${newStatus} تغییر یافت.`);
    await loadStores();
  } catch (err) {
    notification.error(err.message || 'خطا در تغییر وضعیت فروشگاه');
  } finally {
    togglingStoreId.value = null;
  }
};

// Delete Store
const confirmDelete = async (store) => {
  deletingStore.value = store;
  crmCounts.value = null;
  loadingCrmCounts.value = true;
  try {
    const res = await apiClient.get(`/stores/${store.id}/crm-counts`);
    crmCounts.value = res.data;
  } catch (err) {
    crmCounts.value = null;
  } finally {
    loadingCrmCounts.value = false;
  }
};

const softDisableFromDelete = async () => {
  if (!deletingStore.value) return;
  const store = deletingStore.value;
  deletingStore.value = null;
  await toggleStoreStatus(store);
};

const executeDelete = async () => {
  executingDelete.value = true;
  try {
    await apiClient.delete(`/stores/${deletingStore.value.id}`);
    notification.success('فروشگاه با موفقیت حذف شد.');
    deletingStore.value = null;
    loadStores();
  } catch (err) {
    notification.error(err.message || 'خطا در حذف فروشگاه');
  } finally {
    executingDelete.value = false;
  }
};

onMounted(() => {
  loadStores();
});
</script>
