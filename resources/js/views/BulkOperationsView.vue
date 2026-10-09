<template>
  <div class="space-y-8" dir="rtl">
    <!-- Header & Store Info -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-slate-200/60 dark:border-slate-800/60">
      <div>
        <div class="flex items-center gap-3">
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center shadow-lg shadow-indigo-500/25">
            <Iconsax name="bulk" size="24" />
          </div>
          <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-2.5">
              <span>مرکز عملیات گروهی</span>
              <span class="text-xs px-2.5 py-0.5 rounded-full font-bold bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
                WooCommerce Bulk Edit
              </span>
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              ویرایش سریع تعداد زیادی محصول با انتخاب هوشمند و پیش‌نمایش قبل از اجرا
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3 flex-wrap">
        <!-- Store Context Badge -->
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300">
          <Iconsax name="shop" size="16" class="text-indigo-600 dark:text-indigo-400" />
          <span>فروشگاه فعال:</span>
          <strong class="text-slate-900 dark:text-slate-100">{{ currentStoreName }}</strong>
        </div>

        <button
          @click="openPriceRestoreModal"
          type="button"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-violet-600 hover:bg-violet-700 active:bg-violet-800 text-white font-bold text-xs transition-colors shadow-xs cursor-pointer"
          title="بازگردانی امن قیمت‌ها از نسخه پشتیبان JSON"
        >
          <Iconsax name="document-upload" size="16" />
          <span>بازیابی قیمت‌ها از JSON</span>
        </button>

        <RefreshButton
          @click="handleGlobalRefresh"
          :loading="loadingHistory || loadingMeta"
          label="تازه‌سازی داده‌ها"
          title="تازه‌سازی اطلاعات و تاریخچه"
        />
      </div>
    </div>

    <!-- Quick Actions Shortcuts (دسترسی سریع) -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white rounded-3xl p-5 shadow-xl shadow-indigo-950/20 relative overflow-hidden">
      <!-- Glow effect -->
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-violet-500/20 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-1 space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <span class="flex h-2 w-2 relative">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <h2 class="text-sm font-bold text-slate-100">عملیات پرتکرار و سریع (Quick Actions)</h2>
          </div>
          <span class="text-[11px] text-slate-300">انتخاب مستقیم سناریوهای متداول برای اعمال گروهی</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
          <button
            v-for="qa in quickActions"
            :key="qa.key"
            @click="applyQuickAction(qa)"
            class="flex flex-col items-center justify-center p-3 rounded-2xl bg-white/10 hover:bg-white/20 active:bg-white/25 border border-white/10 hover:border-white/20 transition-all text-center group cursor-pointer"
          >
            <div class="w-8 h-8 rounded-xl bg-white/10 group-hover:scale-110 flex items-center justify-center transition-transform mb-1.5" :class="qa.color">
              <Iconsax :name="qa.icon" size="18" />
            </div>
            <span class="text-[11px] font-bold text-slate-100 group-hover:text-white">{{ qa.title }}</span>
            <span class="text-[9px] text-slate-300 mt-0.5">{{ qa.badge }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Saved Operations / Presets Bar -->
    <div v-if="presets.length > 0" class="bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/50 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
      <div class="flex items-center gap-2.5 flex-wrap">
        <div class="w-7 h-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0">
          <Iconsax name="flash" size="16" />
        </div>
        <div>
          <span class="font-bold text-slate-800 dark:text-slate-200">الگوهای ذخیره‌شده (Presets):</span>
          <span class="text-slate-500 dark:text-slate-400 mr-1.5 text-[11px]">اجرای سریع سناریوهای روزانه با یک کلیک</span>
        </div>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <div
          v-for="p in presets"
          :key="p.id"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-indigo-200/80 dark:border-indigo-800/80 shadow-xs"
        >
          <button
            @click="loadPreset(p)"
            class="font-bold text-indigo-700 dark:text-indigo-300 hover:text-indigo-900 dark:hover:text-indigo-100 transition-colors flex items-center gap-1.5"
            :title="p.description || p.title"
          >
            <span>{{ p.title }}</span>
          </button>
          <button
            @click.stop="deletePreset(p.id)"
            class="text-slate-400 hover:text-rose-600 transition-colors p-0.5 rounded"
            title="حذف الگو"
          >
            <Iconsax name="close" size="14" />
          </button>
        </div>
      </div>
    </div>

    <!-- MAIN WIZARD CONTAINER -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl shadow-sm overflow-hidden">
      <!-- Wizard Stepper Header -->
      <div class="p-4 sm:p-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-850/40">
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
          <!-- Step 1 -->
          <button
            @click="goToStep(1)"
            :disabled="isProcessing"
            class="flex items-center gap-2 p-2 rounded-xl transition-all text-right"
            :class="step === 1 ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/20' : (step > 1 ? 'text-emerald-700 dark:text-emerald-400 hover:bg-slate-100 dark:hover:bg-slate-800' : 'text-slate-400 opacity-60')"
          >
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] shrink-0" :class="step === 1 ? 'bg-white text-indigo-600' : (step > 1 ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-500')">
              <Iconsax v-if="step > 1" name="check" size="14" />
              <span v-else>۱</span>
            </span>
            <span class="truncate">۱. انتخاب محصولات</span>
          </button>

          <!-- Step 2 -->
          <button
            @click="goToStep(2)"
            :disabled="isProcessing || step < 2"
            class="flex items-center gap-2 p-2 rounded-xl transition-all text-right"
            :class="step === 2 ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/20' : (step > 2 ? 'text-emerald-700 dark:text-emerald-400 hover:bg-slate-100 dark:hover:bg-slate-800' : 'text-slate-400 opacity-60')"
          >
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] shrink-0" :class="step === 2 ? 'bg-white text-indigo-600' : (step > 2 ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-500')">
              <Iconsax v-if="step > 2" name="check" size="14" />
              <span v-else>۲</span>
            </span>
            <span class="truncate">۲. انتخاب عملیات</span>
          </button>

          <!-- Step 3 -->
          <button
            @click="goToStep(3)"
            :disabled="isProcessing || step < 3"
            class="flex items-center gap-2 p-2 rounded-xl transition-all text-right"
            :class="step === 3 ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/20' : (step > 3 ? 'text-emerald-700 dark:text-emerald-400 hover:bg-slate-100 dark:hover:bg-slate-800' : 'text-slate-400 opacity-60')"
          >
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] shrink-0" :class="step === 3 ? 'bg-white text-indigo-600' : (step > 3 ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-500')">
              <Iconsax v-if="step > 3" name="check" size="14" />
              <span v-else>۳</span>
            </span>
            <span class="truncate">۳. پیش‌نمایش تغییرات</span>
          </button>

          <!-- Step 4 -->
          <button
            @click="goToStep(4)"
            :disabled="isProcessing || step < 4"
            class="flex items-center gap-2 p-2 rounded-xl transition-all text-right"
            :class="step === 4 ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-600/20' : (step > 4 ? 'text-emerald-700 dark:text-emerald-400 hover:bg-slate-100 dark:hover:bg-slate-800' : 'text-slate-400 opacity-60')"
          >
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] shrink-0" :class="step === 4 ? 'bg-white text-indigo-600' : (step > 4 ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700' : 'bg-slate-200 dark:bg-slate-800 text-slate-500')">
              <Iconsax v-if="step > 4" name="check" size="14" />
              <span v-else>۴</span>
            </span>
            <span class="truncate">۴. تأیید و اجرا</span>
          </button>

          <!-- Step 5 -->
          <div
            class="flex items-center gap-2 p-2 rounded-xl text-right col-span-2 sm:col-span-1"
            :class="step === 5 ? 'bg-emerald-600 text-white font-bold shadow-md shadow-emerald-600/20' : 'text-slate-400 opacity-60'"
          >
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-[11px] shrink-0" :class="step === 5 ? 'bg-white text-emerald-600' : 'bg-slate-200 dark:bg-slate-800 text-slate-500'">
              ۵
            </span>
            <span class="truncate">۵. گزارش نتیجه</span>
          </div>
        </div>
      </div>

      <!-- WIZARD STEP BODIES -->
      <div class="p-6">
        <!-- ==================== STEP 1: TARGET SELECTION ==================== -->
        <div v-if="step === 1" class="space-y-6">
          <!-- Selection Mode Tabs -->
          <div class="flex items-center justify-between flex-wrap gap-3 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1 rounded-2xl text-xs">
              <button
                @click="setSelectionMode('filter')"
                class="px-3.5 py-1.5 rounded-xl font-bold transition-all"
                :class="selectionMode === 'filter' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
              >
                🔍 فیلتر پیشرفته محصولات
              </button>
              <button
                @click="setSelectionMode('manual')"
                class="px-3.5 py-1.5 rounded-xl font-bold transition-all"
                :class="selectionMode === 'manual' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
              >
                📋 انتخاب دستی کالاها
                <span v-if="manualSelectedIds.length > 0" class="mr-1 px-1.5 py-0.5 rounded-full bg-indigo-600 text-white text-[10px]">
                  {{ toPersianDigits(manualSelectedIds.length) }}
                </span>
              </button>
              <button
                @click="setSelectionMode('all')"
                class="px-3.5 py-1.5 rounded-xl font-bold transition-all"
                :class="selectionMode === 'all' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
              >
                🌐 همه محصولات فروشگاه
              </button>
            </div>

            <!-- Target Scope (Parent / Variations / Both) -->
            <div class="flex items-center gap-2 bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50 p-1 rounded-2xl text-xs">
              <span class="text-indigo-700 dark:text-indigo-300 font-bold px-2 text-[11px]">دامنه هدف:</span>
              <label
                class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl cursor-pointer transition-colors"
                :class="targetScope === 'parent' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 font-bold shadow-xs' : 'text-slate-600 dark:text-slate-400'"
              >
                <input type="radio" v-model="targetScope" value="parent" class="hidden" @change="evaluateTargetNow" />
                <span>محصولات اصلی</span>
              </label>
              <label
                class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl cursor-pointer transition-colors"
                :class="targetScope === 'variations' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 font-bold shadow-xs' : 'text-slate-600 dark:text-slate-400'"
              >
                <input type="radio" v-model="targetScope" value="variations" class="hidden" @change="evaluateTargetNow" />
                <span>فقط متغیرها</span>
              </label>
              <label
                class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl cursor-pointer transition-colors"
                :class="targetScope === 'both' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 font-bold shadow-xs' : 'text-slate-600 dark:text-slate-400'"
              >
                <input type="radio" v-model="targetScope" value="both" class="hidden" @change="evaluateTargetNow" />
                <span>محصولات + متغیرها</span>
              </label>
            </div>
          </div>

          <!-- Dynamic Filters Grid (when mode is 'filter') -->
          <div v-if="selectionMode === 'filter'" class="p-5 rounded-3xl bg-slate-50/80 dark:bg-slate-850/60 border border-slate-200/80 dark:border-slate-800 space-y-4 text-xs">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                <Iconsax name="filter" size="16" class="text-indigo-600" />
                <span>ترکیب هوشمند فیلترها (همه شروط با هم AND می‌شوند):</span>
              </span>
              <button
                @click="resetFilters"
                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-[11px] underline"
              >
                پاکسازی فیلترها
              </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
              <!-- Multi-Category Filter -->
              <div class="space-y-1 relative sm:col-span-2">
                <div class="flex items-center justify-between">
                  <label class="block text-slate-500 font-medium">دسته‌بندی‌های هدف (چند انتخابی):</label>
                  <span v-if="filters.categories.length > 0" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400">
                    {{ toPersianDigits(filters.categories.length) }} دسته انتخاب شده
                  </span>
                </div>

                <!-- Custom Multi-Select Trigger -->
                <div
                  @click="categoryDropdownOpen = !categoryDropdownOpen"
                  class="w-full min-h-[38px] py-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 cursor-pointer flex items-center justify-between gap-2 focus:ring-2 focus:ring-indigo-500"
                >
                  <div class="flex items-center gap-1.5 flex-wrap flex-1 overflow-hidden">
                    <span v-if="filters.categories.length === 0" class="text-slate-400 text-xs">
                      همه دسته‌ها (کلیک برای انتخاب چندگانه...)
                    </span>
                    <span
                      v-for="cId in filters.categories.slice(0, 3)"
                      :key="cId"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/80 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-[11px] font-medium"
                    >
                      <span>{{ getCategoryName(cId) }}</span>
                      <button
                        type="button"
                        @click.stop="removeCategory(cId)"
                        class="hover:text-rose-600 p-0.5 rounded"
                      >
                        <Iconsax name="close" size="10" />
                      </button>
                    </span>
                    <span v-if="filters.categories.length > 3" class="text-[10px] font-bold text-indigo-600 bg-indigo-100 dark:bg-indigo-900 px-1.5 py-0.5 rounded-md">
                      +{{ toPersianDigits(filters.categories.length - 3) }} دیگر
                    </span>
                  </div>
                  <Iconsax name="arrow-down-1" size="14" class="text-slate-400 shrink-0 transition-transform" :class="{ 'rotate-180': categoryDropdownOpen }" />
                </div>

                <!-- Multi-Select Dropdown Menu -->
                <div
                  v-if="categoryDropdownOpen"
                  class="absolute z-30 top-full mt-1.5 right-0 left-0 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xl p-3 space-y-2.5 max-h-72 overflow-hidden flex flex-col text-xs"
                >
                  <!-- Search Bar -->
                  <div class="relative">
                    <input
                      v-model="categorySearchQuery"
                      type="text"
                      placeholder="جست‌وجوی نام دسته‌بندی..."
                      class="w-full py-1.5 pr-8 pl-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                    <Iconsax name="search-normal" size="14" class="absolute right-2.5 top-2.5 text-slate-400" />
                  </div>

                  <!-- Quick Controls: Select All Matching & Clear -->
                  <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2 text-[11px]">
                    <button
                      type="button"
                      @click="selectAllFilteredCategories"
                      class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold"
                    >
                      انتخاب همه موارد این لیست ({{ toPersianDigits(filteredCategories.length) }})
                    </button>
                    <button
                      type="button"
                      @click="clearCategorySelection"
                      class="text-rose-500 hover:underline font-medium"
                    >
                      پاک کردن همه
                    </button>
                  </div>

                  <!-- Categories List with Checkboxes -->
                  <div class="overflow-y-auto space-y-1 flex-1 pr-1">
                    <label
                      v-for="cat in filteredCategories"
                      :key="cat.id"
                      class="flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer transition-colors"
                      :class="filters.categories.includes(cat.id) ? 'bg-indigo-50/60 dark:bg-indigo-950/40 font-bold' : ''"
                    >
                      <div class="flex items-center gap-2">
                        <input
                          type="checkbox"
                          :checked="filters.categories.includes(cat.id)"
                          @change="toggleCategory(cat.id)"
                          class="rounded text-indigo-600 focus:ring-0 w-3.5 h-3.5"
                        />
                        <span class="text-slate-800 dark:text-slate-200">{{ cat.name }}</span>
                      </div>
                      <span class="text-[10px] text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded-full">
                        {{ toPersianDigits(cat.count || 0) }} کالا
                      </span>
                    </label>
                    <div v-if="filteredCategories.length === 0" class="p-3 text-center text-slate-400 text-xs">
                      هیچ دسته‌ای با این نام یافت نشد.
                    </div>
                  </div>

                  <!-- Close Button -->
                  <div class="pt-2 border-t border-slate-100 dark:border-slate-800 text-left">
                    <button
                      type="button"
                      @click="categoryDropdownOpen = false"
                      class="px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-lg text-slate-700 dark:text-slate-300 font-bold text-[11px]"
                    >
                      بستن
                    </button>
                  </div>
                </div>
              </div>

              <!-- Product Type Filter -->
              <div class="space-y-1">
                <label class="block text-slate-500 font-medium">نوع محصول (Product Type):</label>
                <select
                  v-model="filters.type"
                  @change="evaluateTargetNow"
                  class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="all">همه نوع‌ها</option>
                  <option value="simple">محصول ساده (Simple)</option>
                  <option value="variable">محصول متغیر (Variable)</option>
                  <option value="grouped">محصول گروهی (Grouped)</option>
                  <option value="external">محصول خارجی / افیلیت (External)</option>
                </select>
              </div>

              <!-- Stock Status Filter -->
              <div class="space-y-1">
                <label class="block text-slate-500 font-medium">وضعیت انبار:</label>
                <select
                  v-model="filters.stock_status"
                  @change="evaluateTargetNow"
                  class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="all">همه وضعیت‌های موجودی</option>
                  <option value="instock">موجود در انبار (In Stock)</option>
                  <option value="outofstock">ناموجود (Out of Stock)</option>
                  <option value="onbackorder">در پیش‌خرید (On Backorder)</option>
                </select>
              </div>

              <!-- Status Filter -->
              <div class="space-y-1">
                <label class="block text-slate-500 font-medium">وضعیت انتشار:</label>
                <select
                  v-model="filters.status"
                  @change="evaluateTargetNow"
                  class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="all">همه وضعیت‌های انتشار</option>
                  <option value="publish">منتشر شده (Published)</option>
                  <option value="draft">پیش‌نویس (Draft)</option>
                  <option value="pending">در انتظار بررسی (Pending)</option>
                  <option value="private">خصوصی (Private)</option>
                </select>
              </div>

              <!-- Price Range: Min Price -->
              <div class="space-y-1">
                <label class="block text-slate-500 font-medium">حداقل قیمت (تومان):</label>
                <input
                  v-model="filters.min_price"
                  @change="evaluateTargetNow"
                  type="number"
                  placeholder="مثلاً 500000"
                  class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <!-- Price Range: Max Price -->
              <div class="space-y-1">
                <label class="block text-slate-500 font-medium">حداکثر قیمت (تومان):</label>
                <input
                  v-model="filters.max_price"
                  @change="evaluateTargetNow"
                  type="number"
                  placeholder="مثلاً 10000000"
                  class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <!-- SKU Filter -->
              <div class="space-y-1">
                <label class="block text-slate-500 font-medium">شناسه SKU (تکی یا چندگانه):</label>
                <input
                  v-model="filters.sku"
                  @change="evaluateTargetNow"
                  type="text"
                  placeholder="SKU-1001, SKU-1002"
                  class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <!-- Title Search on real WooCommerce -->
              <div class="space-y-1">
                <label class="block text-slate-500 font-medium">جستجوی نام محصول:</label>
                <div class="relative">
                  <input
                    v-model="filters.search"
                    @input="debouncedEvaluate"
                    type="text"
                    placeholder="جستجو در عنوان کالا..."
                    class="w-full py-2 pl-8 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                  <Iconsax name="search" size="14" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
                </div>
              </div>
            </div>
          </div>

          <!-- Manual Selection Table (when mode is 'manual') -->
          <div v-if="selectionMode === 'manual'" class="space-y-3">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-700 dark:text-slate-200">
                کالاهای مورد نظر را با تیک زدن انتخاب فرمایید:
              </span>
              <div class="flex items-center gap-2">
                <input
                  v-model="manualSearch"
                  @input="fetchManualProducts"
                  placeholder="جستجو برای انتخاب دستی..."
                  class="py-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs bg-slate-50 dark:bg-slate-800"
                />
                <button
                  @click="toggleSelectAllManual"
                  class="py-1.5 px-3 rounded-xl border border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400 font-bold hover:bg-indigo-50 dark:hover:bg-indigo-950"
                >
                  {{ allManualSelectedOnPage ? 'لغو انتخاب این صفحه' : 'انتخاب همه این صفحه' }}
                </button>
              </div>
            </div>

            <!-- Manual products list table -->
            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden max-h-80 overflow-y-auto">
              <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-850 border-b border-slate-200 dark:border-slate-800 text-slate-500 sticky top-0">
                  <tr>
                    <th class="p-3 w-10 text-center">
                      <input
                        type="checkbox"
                        :checked="allManualSelectedOnPage"
                        @change="toggleSelectAllManual"
                        class="rounded text-indigo-600 focus:ring-0 cursor-pointer"
                      />
                    </th>
                    <th class="p-3">شناسه</th>
                    <th class="p-3">عنوان محصول</th>
                    <th class="p-3">نوع</th>
                    <th class="p-3">قیمت فعلی</th>
                    <th class="p-3">موجودی</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr
                    v-for="p in manualProductList"
                    :key="p.id"
                    @click="toggleManualId(p.id)"
                    class="hover:bg-slate-50/60 dark:hover:bg-slate-850/40 cursor-pointer transition-colors"
                    :class="manualSelectedIds.includes(p.id) ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''"
                  >
                    <td class="p-3 text-center" @click.stop>
                      <input
                        type="checkbox"
                        :value="p.id"
                        v-model="manualSelectedIds"
                        @change="evaluateTargetNow"
                        class="rounded text-indigo-600 focus:ring-0 cursor-pointer"
                      />
                    </td>
                    <td class="p-3 text-slate-400 font-medium">#{{ toPersianDigits(p.id) }}</td>
                    <td class="p-3 font-medium text-slate-800 dark:text-slate-100">{{ p.name }}</td>
                    <td class="p-3">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="p.type === 'variable' ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400' : 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400'">
                        {{ p.type === 'variable' ? 'متغیر' : 'ساده' }}
                      </span>
                    </td>
                    <td class="p-3 font-semibold text-slate-700 dark:text-slate-200">
                      {{ p.price ? formatPrice(p.price) : 'بدون قیمت' }}
                    </td>
                    <td class="p-3">
                      <span class="text-[11px]" :class="p.stock_status === 'instock' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-rose-500'">
                        {{ p.stock_status === 'instock' ? (p.stock_quantity !== null ? `${toPersianDigits(p.stock_quantity)} عدد` : 'موجود') : 'ناموجود' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- LIVE TARGET EVALUATION RESULTS CARD -->
          <div class="p-5 rounded-3xl bg-gradient-to-br from-indigo-50/80 via-white to-slate-50/60 dark:from-indigo-950/30 dark:via-slate-900 dark:to-slate-850/40 border border-indigo-200/80 dark:border-indigo-900/50 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <div class="flex items-center gap-2">
                  <span v-if="evaluatingTarget" class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></span>
                  <Iconsax v-else name="tick-circle" size="20" class="text-indigo-600 dark:text-indigo-400" />
                  <h3 class="text-base font-black text-slate-800 dark:text-slate-100">
                    <span v-if="targetData">
                      {{ toPersianDigits(targetData.affected_count || 0) }} رکورد مطابق این شرایط پیدا شد.
                    </span>
                    <span v-else>در حال ارزیابی محصولات...</span>
                  </h3>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                  ارزیابی دقیق روی سرور ووکامرس بر اساس تفکیک کالای اصلی و تنوع‌ها
                </p>
              </div>

              <button
                @click="evaluateTargetNow"
                :disabled="evaluatingTarget"
                class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-xl border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 bg-white dark:bg-slate-900 text-xs font-bold hover:bg-indigo-50 dark:hover:bg-indigo-950 transition-colors"
              >
                <Iconsax name="refresh" size="14" :class="{ 'animate-spin': evaluatingTarget }" />
                <span>محاسبه مجدد</span>
              </button>
            </div>

            <!-- Breakdown Stat Pills -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800">
                <span class="text-slate-400 text-[11px]">مجموع رکوردهای والد:</span>
                <div class="text-lg font-black text-slate-800 dark:text-slate-100 mt-0.5">
                  {{ toPersianDigits(targetData?.total_parents ?? 0) }}
                </div>
              </div>

              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800">
                <span class="text-blue-600 dark:text-blue-400 text-[11px] font-bold">محصول ساده (Simple):</span>
                <div class="text-lg font-black text-blue-700 dark:text-blue-300 mt-0.5">
                  {{ toPersianDigits(targetData?.simple_count ?? 0) }}
                </div>
              </div>

              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800">
                <span class="text-purple-600 dark:text-purple-400 text-[11px] font-bold">محصول متغیر (Variable):</span>
                <div class="text-lg font-black text-purple-700 dark:text-purple-300 mt-0.5">
                  {{ toPersianDigits(targetData?.variable_count ?? 0) }}
                </div>
              </div>

              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800">
                <span class="text-amber-600 dark:text-amber-400 text-[11px] font-bold">تنوع‌ها (Variations):</span>
                <div class="text-lg font-black text-amber-700 dark:text-amber-300 mt-0.5">
                  {{ toPersianDigits(targetData?.variation_count ?? 0) }}
                </div>
              </div>
            </div>

            <!-- Matched Products Sample Preview (Collapsible) -->
            <div v-if="targetData?.sample_products?.length > 0" class="pt-2 border-t border-indigo-100/60 dark:border-indigo-950">
              <div class="flex items-center justify-between text-xs mb-2">
                <span class="font-bold text-slate-700 dark:text-slate-300">نمونه محصولات انتخاب‌شده ({{ toPersianDigits(targetData.sample_products.length) }} مورد):</span>
                <button
                  @click="showSampleList = !showSampleList"
                  class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-bold text-[11px]"
                >
                  <span>{{ showSampleList ? 'بستن نمونه‌ها' : 'مشاهده نمونه‌ها' }}</span>
                  <Iconsax :name="showSampleList ? 'arrow-up' : 'arrow-down'" size="14" />
                </button>
              </div>

              <div v-if="showSampleList" class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden max-h-56 overflow-y-auto">
                <table class="w-full text-right text-xs">
                  <thead class="bg-slate-50 dark:bg-slate-850 text-slate-500">
                    <tr>
                      <th class="p-2.5">شناسه</th>
                      <th class="p-2.5">نام کالا</th>
                      <th class="p-2.5">نوع</th>
                      <th class="p-2.5">قیمت عادی</th>
                      <th class="p-2.5">موجودی</th>
                      <th class="p-2.5">دسته‌بندی</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="sp in targetData.sample_products" :key="sp.id">
                      <td class="p-2.5 text-slate-400 font-medium">#{{ toPersianDigits(sp.id) }}</td>
                      <td class="p-2.5 font-medium text-slate-800 dark:text-slate-200 max-w-[200px] truncate">{{ sp.name }}</td>
                      <td class="p-2.5">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="sp.is_variable ? 'bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300'">
                          {{ sp.is_variable ? 'متغیر' : 'ساده' }}
                        </span>
                      </td>
                      <td class="p-2.5 font-semibold text-slate-700 dark:text-slate-200">
                        {{ sp.regular_price ? formatPrice(sp.regular_price) : (sp.price ? formatPrice(sp.price) : '-') }}
                      </td>
                      <td class="p-2.5">
                        <span :class="sp.stock_status === 'instock' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-rose-500'">
                          {{ sp.stock_status === 'instock' ? (sp.stock_quantity !== null ? `${toPersianDigits(sp.stock_quantity)} عدد` : 'موجود') : 'ناموجود' }}
                        </span>
                      </td>
                      <td class="p-2.5 text-slate-500 text-[11px] truncate max-w-[150px]">
                        {{ sp.categories?.join(', ') || '-' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Bottom Action Buttons for Step 1 -->
          <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
            <span class="text-xs text-slate-400">
              مرحله ۱ از ۴: انتخاب کالاهای هدف
            </span>
            <button
              @click="goToStep(2)"
              :disabled="!canProceedStep1 || evaluatingTarget"
              class="py-3 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold transition-all shadow-lg shadow-indigo-600/25 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 cursor-pointer"
            >
              <span>مرحله بعد: تعیین نوع عملیات</span>
              <Iconsax name="arrow-left" size="16" />
            </button>
          </div>
        </div>

        <!-- ==================== STEP 2: OPERATION & VALUE ==================== -->
        <div v-if="step === 2" class="space-y-6">
          <div class="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div>
              <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">چه تغییری می‌خواهید اعمال کنید؟</h3>
              <p class="text-xs text-slate-400 mt-0.5">عملیات مورد نظر و مقادیر جدید را به دقت وارد فرمایید</p>
            </div>

            <!-- Target Summary Badge -->
            <div class="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 text-xs font-bold text-indigo-700 dark:text-indigo-300">
              کالاهای هدف: {{ toPersianDigits(targetData?.affected_count ?? 0) }} رکورد
            </div>
          </div>

          <!-- Operation Category Selector (Price, Inventory, General, Metadata) -->
          <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1.5 rounded-2xl text-xs flex-wrap">
            <button
              v-for="cat in opCategories"
              :key="cat.key"
              @click="activeOpCategory = cat.key"
              class="px-4 py-2 rounded-xl font-bold transition-all flex items-center gap-2"
              :class="activeOpCategory === cat.key ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
            >
              <Iconsax :name="cat.icon" size="16" />
              <span>{{ cat.title }}</span>
            </button>
          </div>

          <!-- Operation Actions Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div
              v-for="act in currentCategoryActions"
              :key="act.key"
              @click="selectAction(act.key)"
              class="p-4 rounded-2xl border transition-all cursor-pointer flex flex-col justify-between"
              :class="selectedActionType === act.key ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 ring-2 ring-indigo-500/20' : 'border-slate-200/80 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900'"
            >
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <span class="font-bold text-xs text-slate-800 dark:text-slate-100">{{ act.name }}</span>
                  <div
                    class="w-4 h-4 rounded-full border flex items-center justify-center"
                    :class="selectedActionType === act.key ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300 dark:border-slate-600'"
                  >
                    <div v-if="selectedActionType === act.key" class="w-1.5 h-1.5 rounded-full bg-white"></div>
                  </div>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">{{ act.description }}</p>
              </div>

              <div v-if="act.badge" class="mt-3">
                <span class="text-[10px] px-2 py-0.5 rounded-md font-bold" :class="act.badgeClass || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'">
                  {{ act.badge }}
                </span>
              </div>
            </div>
          </div>

          <!-- Dynamic Value Parameters Form based on selectedActionType -->
          <div v-if="selectedActionType" class="p-6 rounded-3xl bg-slate-50/80 dark:bg-slate-850/60 border border-slate-200/80 dark:border-slate-800 space-y-4 text-xs">
            <div class="flex items-center gap-2 pb-2 border-b border-slate-200/60 dark:border-slate-700/60">
              <Iconsax name="edit" size="18" class="text-indigo-600 dark:text-indigo-400" />
              <h4 class="font-bold text-slate-800 dark:text-slate-100">
                مقدار و پارامترهای عملیات: «{{ currentActionDefinition?.name }}»
              </h4>
            </div>

            <!-- Value Input (Percent or Amount or Stock) -->
            <div v-if="needsValueInput" class="max-w-md space-y-1.5">
              <label class="block font-bold text-slate-700 dark:text-slate-200">
                {{ valueInputLabel }}
              </label>
              <div class="relative">
                <input
                  v-model.number="actionParams.value"
                  type="number"
                  :min="selectedActionType.includes('percent') ? 0.1 : 0"
                  :max="selectedActionType === 'decrease_price_percent' ? 100 : null"
                  :step="selectedActionType.includes('percent') ? 'any' : '1'"
                  :placeholder="valueInputPlaceholder"
                  class="w-full py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-bold"
                />
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">
                  {{ valueInputUnit }}
                </span>
              </div>
              <p v-if="selectedActionType === 'increase_price_percent'" class="text-[11px] text-slate-400">
                مثال: با وارد کردن عدد ۱۰، قیمت یک کالای ۱۰,۰۰۰,۰۰۰ تومانی به ۱۱,۰۰۰,۰۰۰ تومان تغییر خواهد کرد.
              </p>
            </div>

            <!-- Stock Status select -->
            <div v-if="selectedActionType === 'set_stock_status'" class="max-w-md space-y-1.5">
              <label class="block font-bold text-slate-700 dark:text-slate-200">وضعیت انبار را انتخاب کنید:</label>
              <select
                v-model="actionParams.status"
                class="w-full py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="instock">موجود در انبار (In Stock)</option>
                <option value="outofstock">ناموجود (Out of Stock)</option>
                <option value="onbackorder">در پیش‌خرید (On Backorder)</option>
              </select>
            </div>

            <!-- Manage Stock Toggle -->
            <div v-if="selectedActionType === 'set_manage_stock'" class="max-w-md space-y-2">
              <label class="block font-bold text-slate-700 dark:text-slate-200">مدیریت موجودی در سطح انبار:</label>
              <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="actionParams.manage_stock" :value="true" class="text-indigo-600 focus:ring-0" />
                  <span>فعال (Enable)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="actionParams.manage_stock" :value="false" class="text-indigo-600 focus:ring-0" />
                  <span>غیرفعال (Disable)</span>
                </label>
              </div>
            </div>

            <!-- Status action -->
            <div v-if="selectedActionType === 'set_status'" class="max-w-md space-y-1.5">
              <label class="block font-bold text-slate-700 dark:text-slate-200">وضعیت انتشار جدید:</label>
              <select
                v-model="actionParams.status"
                class="w-full py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="publish">منتشر شده (Publish)</option>
                <option value="draft">پیش‌نویس (Draft)</option>
                <option value="pending">در انتظار بررسی (Pending)</option>
                <option value="private">خصوصی (Private)</option>
              </select>
            </div>

            <!-- Catalog Visibility -->
            <div v-if="selectedActionType === 'set_catalog_visibility'" class="max-w-md space-y-1.5">
              <label class="block font-bold text-slate-700 dark:text-slate-200">نمایش در کاتالوگ:</label>
              <select
                v-model="actionParams.visibility"
                class="w-full py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="visible">فروشگاه و نتایج جستجو (Visible)</option>
                <option value="catalog">فقط در کاتالوگ فروشگاه (Catalog)</option>
                <option value="search">فقط در نتایج جستجو (Search)</option>
                <option value="hidden">مخفی از همه جا (Hidden)</option>
              </select>
            </div>

            <!-- SKU Edit -->
            <div v-if="selectedActionType === 'set_sku'" class="space-y-3 max-w-md">
              <div class="space-y-1.5">
                <label class="block font-bold text-slate-700 dark:text-slate-200">حالت ویرایش SKU:</label>
                <select
                  v-model="actionParams.mode"
                  class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-xs"
                >
                  <option value="set">تنظیم شناسه مشخص (Set Exact)</option>
                  <option value="prefix">افزودن پیشوند به SKU موجود (Add Prefix)</option>
                  <option value="suffix">افزودن پسوند به SKU موجود (Add Suffix)</option>
                </select>
              </div>
              <div class="space-y-1.5">
                <label class="block font-bold text-slate-700 dark:text-slate-200">مقدار SKU:</label>
                <input
                  v-model="actionParams.value"
                  type="text"
                  placeholder="مثلاً PROD-"
                  class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-xs"
                />
              </div>
            </div>

            <!-- Custom Metadata Fields with Caution Warning -->
            <div v-if="selectedActionType === 'set_metadata'" class="space-y-3">
              <div class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-200 flex items-start gap-2.5">
                <Iconsax name="warning" size="20" class="shrink-0 mt-0.5 text-amber-600" />
                <div class="space-y-1 text-xs">
                  <strong class="font-bold">هشدار مهم در خصوص متادیتا:</strong>
                  <p class="leading-relaxed">
                    این بخش منحصراً برای فیلدهای متادیتای سفارشی (Custom Meta) طراحی شده است. برای فیلدهای استاندارد ووکامرس (مانند قیمت، موجودی، SKU و دسته‌بندی) حتماً از تب‌های رسمی مربوطه استفاده نمایید تا ساختار داده‌ها در ووکامرس دچار ناهماهنگی نشود.
                  </p>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="space-y-1">
                  <label class="block font-bold text-slate-700 dark:text-slate-200">نام کلید متادیتا (Meta Key):</label>
                  <input
                    v-model="actionParams.key"
                    type="text"
                    placeholder="مثلاً _custom_vendor_code"
                    class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-xs font-medium"
                  />
                </div>
                <div class="space-y-1">
                  <label class="block font-bold text-slate-700 dark:text-slate-200">عملیات متادیتا:</label>
                  <select
                    v-model="actionParams.action"
                    class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-xs"
                  >
                    <option value="set">تنظیم مقدار (Set)</option>
                    <option value="delete">حذف کلید (Delete)</option>
                  </select>
                </div>
                <div v-if="actionParams.action === 'set'" class="space-y-1">
                  <label class="block font-bold text-slate-700 dark:text-slate-200">مقدار متادیتا (Value):</label>
                  <input
                    v-model="actionParams.value"
                    type="text"
                    placeholder="مقدار مورد نظر..."
                    class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-xs"
                  />
                </div>
              </div>
            </div>

            <!-- Save as Preset Checkbox -->
            <div class="pt-3 border-t border-slate-200/60 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="saveAsPreset" class="rounded text-indigo-600 focus:ring-0" />
                <span class="font-bold text-slate-700 dark:text-slate-300">
                  ذخیره این تنظیمات به عنوان الگوی آماده (Preset) برای عملیات‌های بعدی
                </span>
              </label>

              <div v-if="saveAsPreset" class="flex items-center gap-2 w-full sm:w-auto">
                <input
                  v-model="presetTitle"
                  type="text"
                  placeholder="عنوان الگو (مثلاً افزایش قیمت لپ‌تاپ‌ها ۱۰٪)"
                  class="py-1.5 px-3 rounded-xl border border-indigo-200 dark:border-indigo-800 text-xs bg-white dark:bg-slate-900 w-full sm:w-72"
                />
              </div>
            </div>
          </div>

          <!-- Bottom Action Buttons for Step 2 -->
          <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
            <button
              @click="step = 1"
              class="py-2.5 px-5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            >
              ← بازگشت به انتخاب محصولات
            </button>

            <button
              @click="loadPreviewAndGoStep3"
              :disabled="!canProceedStep2 || loadingPreview"
              class="py-3 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold transition-all shadow-lg shadow-indigo-600/25 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 cursor-pointer"
            >
              <span v-if="loadingPreview" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>مشاهده پیش‌نمایش و ارزیابی تغییرات</span>
              <Iconsax name="arrow-left" size="16" />
            </button>
          </div>
        </div>

        <!-- ==================== STEP 3: DIFF PREVIEW ==================== -->
        <div v-if="step === 3" class="space-y-6">
          <div class="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div>
              <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">پیش‌نمایش دقیق تغییرات</h3>
              <p class="text-xs text-slate-400 mt-0.5">قبل از هرگونه تغییر در ووکامرس، مقادیر قبلی و جدید را مقایسه کنید</p>
            </div>

            <!-- Affected Count Badge -->
            <div class="px-3.5 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-xs font-black text-emerald-800 dark:text-emerald-200">
              تعداد موارد تحت تاثیر: {{ toPersianDigits(previewData?.affected_count ?? 0) }}
            </div>
          </div>

          <!-- Breakdown Summary Bar -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/80 dark:border-slate-800">
              <span class="text-slate-400 text-[11px]">کل موارد هدف:</span>
              <div class="text-lg font-black text-slate-800 dark:text-slate-100 mt-0.5">
                {{ toPersianDigits(previewData?.affected_count ?? 0) }}
              </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-200/60 dark:border-blue-900/40">
              <span class="text-blue-700 dark:text-blue-300 text-[11px] font-bold">محصولات ساده (Simple):</span>
              <div class="text-lg font-black text-blue-800 dark:text-blue-200 mt-0.5">
                {{ toPersianDigits(previewData?.breakdown?.simple ?? previewData?.simple_count ?? 0) }}
              </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-purple-50/60 dark:bg-purple-950/30 border border-purple-200/60 dark:border-purple-900/40">
              <span class="text-purple-700 dark:text-purple-300 text-[11px] font-bold">محصولات متغیر (Variable):</span>
              <div class="text-lg font-black text-purple-800 dark:text-purple-200 mt-0.5">
                {{ toPersianDigits(previewData?.breakdown?.variable ?? previewData?.variable_count ?? 0) }}
              </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-900/40">
              <span class="text-amber-700 dark:text-amber-300 text-[11px] font-bold">تنوع‌ها (Variations):</span>
              <div class="text-lg font-black text-amber-800 dark:text-amber-200 mt-0.5">
                {{ toPersianDigits(previewData?.breakdown?.variation ?? previewData?.variation_count ?? 0) }}
              </div>
            </div>
          </div>

          <!-- Warnings & Notices Box -->
          <div v-if="previewData?.warnings?.length > 0" class="p-4 rounded-2xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 space-y-2">
            <div class="flex items-center gap-2 text-amber-800 dark:text-amber-200 font-bold text-xs">
              <Iconsax name="warning" size="18" class="text-amber-600" />
              <span>نکات و هشدارهای مهم قبل از اجرا:</span>
            </div>
            <ul class="list-disc list-inside text-xs text-amber-700 dark:text-amber-300 space-y-1">
              <li v-for="(w, idx) in previewData.warnings" :key="idx">{{ w }}</li>
            </ul>
          </div>

          <!-- Diff Sample Table -->
          <div class="space-y-2 text-xs">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-700 dark:text-slate-200">
                نمونه تغییرات روی کالاها (قبل و بعد):
              </span>
              <span class="text-[11px] text-slate-400">
                نمایش {{ toPersianDigits(previewData?.sample?.length ?? 0) }} مورد
              </span>
            </div>

            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
              <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-850 border-b border-slate-200 dark:border-slate-800 text-slate-500">
                  <tr>
                    <th class="p-3">شناسه</th>
                    <th class="p-3">عنوان محصول / تنوع</th>
                    <th class="p-3">نوع ساختار</th>
                    <th class="p-3 text-left">مقدار فعلی</th>
                    <th class="p-3 text-left">مقدار جدید پس از اجرا</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="it in previewData?.sample" :key="it.entity_id" class="hover:bg-slate-50/50 dark:hover:bg-slate-850/40">
                    <td class="p-3 text-slate-400 font-medium">#{{ toPersianDigits(it.entity_id) }}</td>
                    <td class="p-3 font-medium text-slate-800 dark:text-slate-100 max-w-[220px] truncate">
                      {{ it.name }}
                    </td>
                    <td class="p-3">
                      <span v-if="it.is_variation" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                        متغیر (#{{ toPersianDigits(it.parent_id) }})
                      </span>
                      <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                        محصول اصلی
                      </span>
                    </td>
                    <td class="p-3 text-left font-medium text-slate-500 line-through">
                      {{ formatDiffValue(it.old_value) }}
                    </td>
                    <td class="p-3 text-left font-bold text-emerald-600 dark:text-emerald-400">
                      {{ formatDiffValue(it.new_value) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Bottom Action Buttons for Step 3 -->
          <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
            <button
              @click="step = 2"
              class="py-2.5 px-5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            >
              ← بازگشت و تغییر مقادیر
            </button>

            <button
              @click="step = 4"
              class="py-3 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold transition-all shadow-lg shadow-indigo-600/25 flex items-center gap-2 cursor-pointer"
            >
              <span>مرحله بعد: تایید نهایی و اجرا</span>
              <Iconsax name="arrow-left" size="16" />
            </button>
          </div>
        </div>

        <!-- ==================== STEP 4: CONFIRMATION & REAL-TIME EXECUTION ==================== -->
        <div v-if="step === 4" class="space-y-6">
          <!-- Before Execution: Safety Confirmation Box -->
          <div v-if="!isProcessing && !operationFinished" class="space-y-6">
            <div class="p-6 rounded-3xl bg-rose-50/80 dark:bg-rose-950/30 border border-rose-200/80 dark:border-rose-900/60 space-y-4">
              <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                  <Iconsax name="warning" size="22" />
                </div>
                <div class="space-y-1">
                  <h4 class="font-black text-sm text-rose-900 dark:text-rose-100">تأیید نهایی جهت اعمال تغییرات در ووکامرس</h4>
                  <p class="text-xs text-rose-700 dark:text-rose-300 leading-relaxed">
                    شما در حال اعمال تغییرات بر روی <strong>{{ toPersianDigits(previewData?.affected_count ?? 0) }}</strong> مورد در فروشگاه <strong>«{{ currentStoreName }}»</strong> هستید.
                    این تغییرات مستقیماً از طریق Official WooCommerce REST API در پایگاه داده فروشگاه درج می‌گردد.
                  </p>
                </div>
              </div>

              <!-- Permission Warning if user lacks bulk.execute -->
              <div v-if="!canExecuteBulk" class="p-3 rounded-xl bg-amber-100/60 dark:bg-amber-950/60 border border-amber-300 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs">
                ⚠️ شما فقط دسترسی مشاهده (View) دارید و مجوز اجرای عملیات گروهی (bulk.execute) برای حساب کاربری شما فعال نیست.
              </div>

              <label class="flex items-center gap-3 pt-3 border-t border-rose-200/60 dark:border-rose-900/60 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="userConfirmedExecution"
                  :disabled="!canExecuteBulk"
                  class="rounded text-rose-600 focus:ring-0 w-4 h-4 cursor-pointer"
                />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-100">
                  پیش‌نمایش را بررسی کرده و اطمینان دارم که این تغییرات باید مستقیماً در ووکامرس ثبت شوند.
                </span>
              </label>
            </div>

            <!-- Price Backup Safety Notice & Download -->
            <div v-if="activeOpCategory === 'price'" class="p-5 rounded-3xl bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200/80 dark:border-indigo-900/60 text-xs space-y-3">
              <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2 font-bold text-indigo-900 dark:text-indigo-200">
                  <Iconsax name="shield-tick" size="20" class="text-indigo-600 dark:text-indigo-400" />
                  <span>نسخه پشتیبان JSON از قیمت‌ها (Price Safety Backup)</span>
                </div>
                <span class="text-[11px] px-2.5 py-0.5 rounded-full font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">
                  الزامی پیش از اجرا
                </span>
              </div>
              <p class="text-slate-600 dark:text-slate-300 leading-relaxed text-xs">
                به منظور تضمین ایمنی و امکان بازگردانی دقیق، هم‌زمان با تایید عملیات، یک نسخه پشتیبان کامل با فرمت استاندارد JSON از قیمت‌های فعلی تمام کالاهای هدف تهیه شده و در اختیارتان قرار می‌گیرد.
              </p>
              <div v-if="currentActiveOp?.payload?.price_backup_uid" class="pt-2.5 flex items-center justify-between border-t border-indigo-200/60 dark:border-indigo-800/60 flex-wrap gap-2">
                <span class="text-slate-500 font-mono text-[11px]">فایل: {{ currentActiveOp.payload.price_backup_filename }}</span>
                <a
                  :href="`/api/v1/bulk-operations/price-backup/${currentActiveOp.payload.price_backup_uid}/download`"
                  target="_blank"
                  class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs inline-flex items-center gap-1.5 transition-colors shadow-xs"
                >
                  <Iconsax name="document-download" size="16" />
                  <span>دانلود فایل پشتیبان قیمت (JSON)</span>
                </a>
              </div>
            </div>

            <!-- Execution Actions -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
              <button
                @click="step = 3"
                class="py-2.5 px-5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              >
                ← لغو و بازگشت به پیش‌نمایش
              </button>

              <button
                @click="startExecutionLoop"
                :disabled="!userConfirmedExecution || !canExecuteBulk || isProcessing"
                class="py-3 px-8 rounded-2xl bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white text-xs font-black transition-all shadow-xl shadow-rose-600/30 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 cursor-pointer"
              >
                <span>تأیید و اجرای عملیات</span>
                <Iconsax name="flash" size="16" />
              </button>
            </div>
          </div>

          <!-- While Processing / Real-Time Progress -->
          <div v-if="isProcessing" class="space-y-6">
            <div class="p-6 rounded-3xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200/80 dark:border-slate-800 space-y-5">
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100">
                  <span class="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></span>
                  <span>در حال پردازش دسته‌ای در ووکامرس (Batch Processing)...</span>
                </div>
                <span class="text-base font-black text-indigo-600 dark:text-indigo-400">
                  {{ toPersianDigits(progressData.percent) }}٪
                </span>
              </div>

              <!-- Animated Progress Bar -->
              <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-4 overflow-hidden p-0.5">
                <div
                  class="bg-gradient-to-r from-indigo-600 to-violet-500 h-full rounded-full transition-all duration-300 relative shadow-sm"
                  :style="{ width: `${progressData.percent}%` }"
                >
                  <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                </div>
              </div>

              <!-- Real-time Stats Grid -->
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800">
                  <div class="text-slate-400 text-[11px]">مجموع کل</div>
                  <div class="text-lg font-black text-slate-800 dark:text-slate-100 mt-1">
                    {{ toPersianDigits(progressData.total) }}
                  </div>
                </div>
                <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900">
                  <div class="text-emerald-600 dark:text-emerald-400 text-[11px] font-bold">موفق</div>
                  <div class="text-lg font-black text-emerald-700 dark:text-emerald-300 mt-1">
                    {{ toPersianDigits(progressData.succeeded) }}
                  </div>
                </div>
                <div class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900">
                  <div class="text-amber-600 dark:text-amber-400 text-[11px] font-bold">رد شده (Skip)</div>
                  <div class="text-lg font-black text-amber-700 dark:text-amber-300 mt-1">
                    {{ toPersianDigits(progressData.skipped) }}
                  </div>
                </div>
                <div class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900">
                  <div class="text-rose-600 dark:text-rose-400 text-[11px] font-bold">ناموفق (خطا)</div>
                  <div class="text-lg font-black text-rose-700 dark:text-rose-300 mt-1">
                    {{ toPersianDigits(progressData.failed) }}
                  </div>
                </div>
              </div>

              <!-- Cancel Button during execution -->
              <div class="flex items-center justify-between pt-2">
                <span class="text-xs text-slate-400">
                  پردازش شده: {{ toPersianDigits(progressData.processed) }} از {{ toPersianDigits(progressData.total) }}
                </span>
                <button
                  @click="cancelActiveOperation"
                  class="py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-colors"
                >
                  توقف و لغو باقی‌مانده
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ==================== STEP 5: FINAL REPORT & PARTIAL FAILURES ==================== -->
        <div v-if="step === 5" class="space-y-6">
          <!-- Status Banner -->
          <div
            class="p-6 rounded-3xl border flex items-start gap-4"
            :class="finalReportClass"
          >
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" :class="finalReportIconBg">
              <Iconsax :name="finalReportIcon" size="28" />
            </div>
            <div class="space-y-1">
              <h3 class="text-base font-black">{{ finalReportTitle }}</h3>
              <p class="text-xs leading-relaxed opacity-90">{{ finalReportDescription }}</p>
            </div>
          </div>

          <!-- Final Counters Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
              <span class="text-slate-400 text-[11px]">مجموع آیتم‌ها:</span>
              <div class="text-xl font-black text-slate-800 dark:text-slate-100 mt-1">{{ toPersianDigits(progressData.total) }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900">
              <span class="text-emerald-700 dark:text-emerald-300 text-[11px] font-bold">موفقیت‌آمیز:</span>
              <div class="text-xl font-black text-emerald-800 dark:text-emerald-200 mt-1">{{ toPersianDigits(progressData.succeeded) }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900">
              <span class="text-amber-700 dark:text-amber-300 text-[11px] font-bold">رد شده:</span>
              <div class="text-xl font-black text-amber-800 dark:text-amber-200 mt-1">{{ toPersianDigits(progressData.skipped) }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900">
              <span class="text-rose-700 dark:text-rose-300 text-[11px] font-bold">ناموفق:</span>
              <div class="text-xl font-black text-rose-800 dark:text-rose-200 mt-1">{{ toPersianDigits(progressData.failed) }}</div>
            </div>
          </div>

          <!-- Price Backup & Verification Section -->
          <div v-if="currentActiveOpPriceBackupUid || hasVerificationData" class="p-5 rounded-3xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900 space-y-4 text-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <Iconsax name="shield-tick" size="22" class="text-indigo-600 dark:text-indigo-400 shrink-0" />
                <div>
                  <h4 class="font-bold text-slate-800 dark:text-slate-100">
                    گزارش تأیید واقعی قیمت‌ها در ووکامرس و نسخه پشتیبان JSON
                  </h4>
                  <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    قیمت‌ها پس از تغییر، مستقیماً از ووکامرس خوانده شده و با مقادیر مورد انتظار تطبیق داده شدند.
                  </p>
                </div>
              </div>

              <a
                v-if="currentActiveOpPriceBackupUid"
                :href="`/api/bulk-operations/price-backup/${currentActiveOpPriceBackupUid}/download`"
                target="_blank"
                download
                class="py-2 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold transition-all shadow-sm flex items-center gap-2 cursor-pointer shrink-0"
              >
                <Iconsax name="document-download" size="16" />
                <span>دانلود نسخه پشتیبان JSON قیمت‌ها</span>
              </a>
            </div>

            <!-- Verification Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-emerald-200 dark:border-emerald-900">
                <span class="text-emerald-700 dark:text-emerald-300 font-bold text-[11px]">قیمت‌های تأیید شده (مطابق):</span>
                <div class="text-lg font-black text-emerald-800 dark:text-emerald-200 mt-0.5">
                  {{ toPersianDigits(verifiedItemsCount) }}
                </div>
              </div>
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-amber-200 dark:border-amber-900">
                <span class="text-amber-700 dark:text-amber-300 font-bold text-[11px]">مغایرت قیمت (Discrepancy):</span>
                <div class="text-lg font-black text-amber-800 dark:text-amber-200 mt-0.5">
                  {{ toPersianDigits(discrepancyItemsCount) }}
                </div>
              </div>
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <span class="text-slate-600 dark:text-slate-400 font-bold text-[11px]">نیازمند بررسی / خطای تأیید:</span>
                <div class="text-lg font-black text-slate-800 dark:text-slate-200 mt-0.5">
                  {{ toPersianDigits(failedVerificationCount) }}
                </div>
              </div>
            </div>

            <!-- Verification Items Table -->
            <div v-if="verificationItemsList.length > 0" class="border border-indigo-200 dark:border-indigo-900/60 rounded-2xl overflow-hidden max-h-64 overflow-y-auto bg-white dark:bg-slate-900">
              <table class="w-full text-right text-xs">
                <thead class="bg-indigo-50/50 dark:bg-indigo-950/40 text-slate-500 sticky top-0">
                  <tr>
                    <th class="p-2.5">شناسه محصول</th>
                    <th class="p-2.5">قیمت مورد انتظار</th>
                    <th class="p-2.5">قیمت خوانده‌شده از ووکامرس</th>
                    <th class="p-2.5">وضعیت تأیید</th>
                    <th class="p-2.5">یادداشت</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="it in verificationItemsList" :key="it.id">
                    <td class="p-2.5 font-medium text-slate-400">#{{ toPersianDigits(it.entity_id) }}</td>
                    <td class="p-2.5 text-slate-800 dark:text-slate-200 font-mono">{{ formatVerifiedPrice(it.expected_price) }}</td>
                    <td class="p-2.5 text-slate-800 dark:text-slate-200 font-mono">{{ formatVerifiedPrice(it.verified_price) }}</td>
                    <td class="p-2.5">
                      <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                        :class="it.verification_status === 'verified' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : (it.verification_status === 'discrepancy' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300')"
                      >
                        {{ it.verification_status === 'verified' ? 'تأیید شده' : (it.verification_status === 'discrepancy' ? 'مغایرت' : 'تأیید نشده') }}
                      </span>
                    </td>
                    <td class="p-2.5 text-slate-500">{{ it.verification_notes || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Partial Failures Inspector & Retry Action -->
          <div v-if="failedItemsList.length > 0" class="p-5 rounded-3xl bg-rose-50/60 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900 space-y-4 text-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <Iconsax name="close-circle" size="20" class="text-rose-600" />
                <h4 class="font-bold text-slate-800 dark:text-slate-100">
                  موارد ناموفق ({{ toPersianDigits(failedItemsList.length) }} مورد دارای خطا در ووکامرس):
                </h4>
              </div>

              <button
                @click="retryFailedItemsNow"
                :disabled="retryingFailed"
                class="py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white font-bold transition-all shadow-md shadow-rose-600/20 flex items-center gap-2 cursor-pointer disabled:opacity-50"
              >
                <span v-if="retryingFailed" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <Iconsax v-else name="refresh" size="14" />
                <span>تلاش مجدد موارد ناموفق (Retry)</span>
              </button>
            </div>

            <!-- Failed items table -->
            <div class="border border-rose-200 dark:border-rose-900/60 rounded-2xl overflow-hidden max-h-60 overflow-y-auto bg-white dark:bg-slate-900">
              <table class="w-full text-right text-xs">
                <thead class="bg-rose-50/50 dark:bg-rose-950/40 text-slate-500">
                  <tr>
                    <th class="p-2.5">شناسه رکورد</th>
                    <th class="p-2.5">کد خطا</th>
                    <th class="p-2.5">توضیحات خطا در ووکامرس</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="it in failedItemsList" :key="it.id">
                    <td class="p-2.5 font-medium text-slate-400">#{{ toPersianDigits(it.entity_id) }}</td>
                    <td class="p-2.5 font-medium text-rose-600">{{ it.error_code || 'ERROR' }}</td>
                    <td class="p-2.5 text-slate-700 dark:text-slate-300">{{ it.error_message || 'خطا در ارتباط با ووکامرس' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Bottom Action Buttons for Step 5 -->
          <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
            <button
              @click="openDetails(currentActiveOp)"
              v-if="currentActiveOp"
              class="py-2.5 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-1.5"
            >
              <Iconsax name="search" size="16" />
              <span>مشاهده لاگ کامل و JSON</span>
            </button>

            <button
              @click="resetWizard"
              class="py-3 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold transition-all shadow-lg shadow-indigo-600/25 flex items-center gap-2 cursor-pointer"
            >
              <span>شروع عملیات گروهی جدید</span>
              <Iconsax name="refresh" size="16" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== HISTORY SECTION (تاریخچه عملیات گروهی) ==================== -->
    <div class="space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <Iconsax name="clock" size="20" class="text-indigo-600 dark:text-indigo-400" />
            <span>تاریخچه عملیات‌های گروهی</span>
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">مشاهده سوابق، لاگ تغییرات، کاربر مجری و وضعیت اجرای قبلی</p>
        </div>

        <!-- History Filters -->
        <div class="flex items-center gap-2 text-xs flex-wrap">
          <select
            v-model="historyFilters.status"
            @change="fetchOperations"
            class="py-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
          >
            <option value="all">همه وضعیت‌ها</option>
            <option value="completed">تکمیل شده</option>
            <option value="partial">تکمیل جزئی</option>
            <option value="processing">در حال پردازش</option>
            <option value="cancelled">لغو شده</option>
            <option value="failed">ناموفق</option>
          </select>

          <select
            v-model="historyFilters.entity"
            @change="fetchOperations"
            class="py-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
          >
            <option value="all">همه موجودیت‌ها</option>
            <option value="products">محصولات</option>
            <option value="orders">سفارش‌ها</option>
            <option value="customers">مشتریان</option>
          </select>
        </div>
      </div>

      <!-- History Table Card -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl shadow-sm overflow-hidden">
        <!-- Skeleton -->
        <div v-if="loadingHistory" class="p-8 space-y-4">
          <div v-for="i in 3" :key="i" class="flex items-center gap-4 animate-pulse">
            <div class="w-10 h-10 bg-slate-200 dark:bg-slate-800 rounded-xl shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="w-1/4 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div>
              <div class="w-1/3 h-2 bg-slate-100 dark:bg-slate-850 rounded"></div>
            </div>
            <div class="w-20 h-6 bg-slate-200 dark:bg-slate-800 rounded-full"></div>
          </div>
        </div>

        <!-- Empty -->
        <div v-else-if="operations.length === 0" class="p-10 text-center space-y-2">
          <Iconsax name="bulk" size="32" class="text-slate-300 dark:text-slate-600 mx-auto" />
          <h4 class="font-bold text-sm text-slate-700 dark:text-slate-300">سابقه‌ای ثبت نشده است</h4>
          <p class="text-xs text-slate-400">تاکنون هیچ عملیاتی با این فیلترها اجرا نشده است.</p>
        </div>

        <!-- History Table -->
        <div v-else class="overflow-x-auto">
          <table class="w-full text-right text-xs">
            <thead class="bg-slate-50 dark:bg-slate-850 border-b border-slate-100 dark:border-slate-800 text-slate-500">
              <tr>
                <th class="p-3.5">شناسه</th>
                <th class="p-3.5">نوع عملیات</th>
                <th class="p-3.5">موجودیت</th>
                <th class="p-3.5">کاربر مجری</th>
                <th class="p-3.5 text-center">پیشرفت</th>
                <th class="p-3.5 text-center">وضعیت</th>
                <th class="p-3.5">تاریخ ثبت</th>
                <th class="p-3.5 text-left">عملیات</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr
                v-for="op in operations"
                :key="op.id"
                @click="openDetails(op)"
                class="hover:bg-slate-50/60 dark:hover:bg-slate-850/40 cursor-pointer transition-colors"
              >
                <td class="p-3.5 text-slate-400 font-bold">#{{ toPersianDigits(op.id) }}</td>
                <td class="p-3.5 font-bold text-slate-800 dark:text-slate-100">
                  {{ formatActionName(op.action_type || op.type) }}
                </td>
                <td class="p-3.5">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold" :class="getEntityBadgeClass(op.entity_type)">
                    {{ getEntityLabel(op.entity_type) }}
                  </span>
                </td>
                <td class="p-3.5 text-slate-500">{{ op.user_name || 'مدیر' }}</td>
                <td class="p-3.5 text-center">
                  <div class="inline-flex flex-col items-center gap-1 w-24">
                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                      <div class="bg-indigo-600 h-full rounded-full transition-all" :style="{ width: `${op.percent}%` }"></div>
                    </div>
                    <span class="text-[10px] text-slate-400">
                      {{ toPersianDigits(op.processed_items) }} / {{ toPersianDigits(op.total_items) }}
                    </span>
                  </div>
                </td>
                <td class="p-3.5 text-center">
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold inline-flex items-center gap-1" :class="getStatusBadgeClass(op.status)">
                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(op.status)"></span>
                    {{ getStatusLabel(op.status) }}
                  </span>
                </td>
                <td class="p-3.5 text-slate-400 text-[11px]">
                  {{ op.created_at ? formatDateTime(op.created_at) : '-' }}
                </td>
                <td class="p-3.5 text-left">
                  <button
                    @click.stop="openDetails(op)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-800 transition-colors"
                    title="مشاهده جزئیات کامل"
                  >
                    <Iconsax name="search" size="16" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- History Pagination -->
        <div v-if="historyMeta.total_pages > 1" class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
          <span class="text-slate-400">صفحه {{ toPersianDigits(historyMeta.current_page) }} از {{ toPersianDigits(historyMeta.total_pages) }}</span>
          <div class="flex items-center gap-2">
            <button
              :disabled="historyMeta.current_page <= 1"
              @click="changeHistoryPage(historyMeta.current_page - 1)"
              class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 disabled:opacity-40"
            >
              قبلی
            </button>
            <button
              :disabled="historyMeta.current_page >= historyMeta.total_pages"
              @click="changeHistoryPage(historyMeta.current_page + 1)"
              class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 disabled:opacity-40"
            >
              بعدی
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- DETAIL DRAWER / MODAL -->
    <div v-if="selectedOpModal" class="fixed inset-0 z-modal-layer overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-850/50">
          <div class="flex items-center gap-3">
            <span class="text-xs font-bold text-slate-400">#{{ toPersianDigits(selectedOpModal.id) }}</span>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" :class="getEntityBadgeClass(selectedOpModal.entity_type)">
              {{ getEntityLabel(selectedOpModal.entity_type) }}
            </span>
            <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">
              {{ formatActionName(selectedOpModal.action_type || selectedOpModal.type) }}
            </h3>
          </div>
          <button
            @click="selectedOpModal = null"
            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <!-- Content -->
        <div class="p-6 overflow-y-auto space-y-6 text-xs flex-1">
          <!-- Stats Cards -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/80 dark:border-slate-800">
              <span class="text-slate-400 text-[11px]">وضعیت کلی</span>
              <div class="mt-1 font-bold flex items-center gap-1.5" :class="getStatusTextColor(selectedOpModal.status)">
                <span class="w-2 h-2 rounded-full" :class="getStatusDotClass(selectedOpModal.status)"></span>
                <span>{{ getStatusLabel(selectedOpModal.status) }}</span>
              </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/60 dark:border-emerald-900/40">
              <span class="text-emerald-700 dark:text-emerald-300 text-[11px]">موفق</span>
              <div class="text-lg font-black text-emerald-800 dark:text-emerald-200 mt-0.5">{{ formatNumber(selectedOpModal.success_items) }}</div>
            </div>
            <div class="p-3.5 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-900/40">
              <span class="text-amber-700 dark:text-amber-300 text-[11px]">رد شده (Skip)</span>
              <div class="text-lg font-black text-amber-800 dark:text-amber-200 mt-0.5">{{ formatNumber(selectedOpModal.skipped_items) }}</div>
            </div>
            <div class="p-3.5 rounded-2xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200/60 dark:border-rose-900/40">
              <span class="text-rose-700 dark:text-rose-300 text-[11px]">ناموفق</span>
              <div class="text-lg font-black text-rose-800 dark:text-rose-200 mt-0.5">{{ formatNumber(selectedOpModal.failed_items) }}</div>
            </div>
          </div>

          <!-- Metadata -->
          <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div><span class="text-slate-400">کاربر مجری:</span> <strong class="text-slate-800 dark:text-slate-100 mr-2">{{ selectedOpModal.user_name }}</strong></div>
            <div><span class="text-slate-400">فروشگاه:</span> <strong class="text-slate-800 dark:text-slate-100 mr-2">{{ selectedOpModal.store_name }}</strong></div>
            <div><span class="text-slate-400">شروع:</span> <span class="text-slate-600 dark:text-slate-300 mr-2">{{ selectedOpModal.started_at ? formatDateTime(selectedOpModal.started_at) : '-' }}</span></div>
            <div><span class="text-slate-400">پایان:</span> <span class="text-slate-600 dark:text-slate-300 mr-2">{{ selectedOpModal.completed_at ? formatDateTime(selectedOpModal.completed_at) : '-' }}</span></div>
          </div>

          <!-- JSON Payload -->
          <div class="space-y-1.5">
            <h4 class="font-bold text-slate-700 dark:text-slate-200">پارامترهای فنی عملیات:</h4>
            <div class="p-3 rounded-xl bg-slate-900 text-slate-200 text-[11px] overflow-x-auto font-medium" dir="ltr">
              {{ JSON.stringify(selectedOpModal.payload, null, 2) }}
            </div>
          </div>

          <!-- Items list if present -->
          <div v-if="selectedOpModal.items?.length > 0" class="space-y-2">
            <h4 class="font-bold text-slate-700 dark:text-slate-200">جزئیات آیتم‌ها و خطاهای ثبت‌شده:</h4>
            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden max-h-56 overflow-y-auto">
              <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-850 sticky top-0">
                  <tr>
                    <th class="p-2.5">شناسه</th>
                    <th class="p-2.5">وضعیت</th>
                    <th class="p-2.5">پیام خطا / توضیحات</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="it in selectedOpModal.items" :key="it.id">
                    <td class="p-2.5 font-medium text-slate-400">#{{ toPersianDigits(it.entity_id) }}</td>
                    <td class="p-2.5">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="it.status === 'completed' ? 'bg-emerald-50 text-emerald-700' : (it.status === 'skipped' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700')">
                        {{ getStatusLabel(it.status) }}
                      </span>
                    </td>
                    <td class="p-2.5 text-slate-600 dark:text-slate-300">
                      {{ it.error_message || '-' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-850/50">
          <button
            v-if="selectedOpModal.failed_items > 0 && selectedOpModal.status !== 'processing'"
            @click="retryFailedItemsFromModal(selectedOpModal.id)"
            class="py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition-colors"
          >
            تلاش مجدد موارد ناموفق این عملیات
          </button>
          <div v-else></div>

          <button
            @click="selectedOpModal = null"
            class="py-2 px-5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            بستن
          </button>
        </div>
      </div>
    </div>

    <!-- ==================== JSON PRICE RESTORE MODAL ==================== -->
    <div
      v-if="showPriceRestoreModal"
      class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto"
      @click.self="closePriceRestoreModal"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden text-xs">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-violet-600 text-white flex items-center justify-center shadow-md shadow-violet-600/20">
              <Iconsax name="document-upload" size="20" />
            </div>
            <div>
              <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">
                بازگردانی امن قیمت‌ها از نسخه پشتیبان JSON
              </h3>
              <p class="text-[11px] text-slate-400">
                بارگذاری فایل Backup، پیش‌نمایش تغییرات و تطبیق واقعی با قیمت‌های زنده ووکامرس
              </p>
            </div>
          </div>
          <button
            @click="closePriceRestoreModal"
            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto space-y-6 flex-1">
          <!-- Step 1: Upload File & Parse -->
          <div v-if="restoreStep === 1" class="space-y-4">
            <div class="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900 text-indigo-900 dark:text-indigo-200 space-y-1">
              <strong class="font-bold block">دستورالعمل بازگردانی:</strong>
              <p class="text-[11px] leading-relaxed">
                فایل JSON تهیه‌شده پیش از هر عملیات تغییر قیمت را انتخاب کنید. سیستم صحت ساختار، فروشگاه مربوطه و هش امنیتی (Checksum) فایل را اعتبارسنجی می‌کند و پیش از اعمال هرگونه تغییری در ووکامرس، تفاوت‌ها را به شما نمایش می‌دهد.
              </p>
            </div>

            <!-- File Upload Zone -->
            <div class="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-3xl p-8 text-center space-y-3 hover:border-violet-500 transition-colors bg-slate-50/50 dark:bg-slate-850/50">
              <input
                type="file"
                ref="restoreFileInput"
                accept=".json"
                class="hidden"
                @change="handleRestoreFileUpload"
              />
              <div class="w-12 h-12 rounded-2xl bg-violet-100 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center mx-auto">
                <Iconsax name="document-upload" size="24" />
              </div>
              <div>
                <p class="font-bold text-slate-700 dark:text-slate-200">
                  {{ restoreFileName || 'انتخاب فایل نسخه پشتیبان JSON' }}
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5">فرمت قابل قبول: crm-price-backup-*.json</p>
              </div>
              <button
                type="button"
                @click="$refs.restoreFileInput.click()"
                class="py-2 px-5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-bold text-xs transition-colors cursor-pointer shadow-sm"
              >
                انتخاب فایل از دستگاه
              </button>
            </div>

            <!-- Or Paste Raw JSON -->
            <div class="space-y-1.5">
              <label class="block font-bold text-slate-600 dark:text-slate-300">یا متن JSON نسخه پشتیبان را وارد نمایید:</label>
              <textarea
                v-model="restoreRawJson"
                rows="4"
                placeholder='{"schema_version": "1.0", "store_id": 1, ...}'
                class="w-full p-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-mono text-xs focus:ring-2 focus:ring-violet-500"
                dir="ltr"
              ></textarea>
            </div>
          </div>

          <!-- Step 2: Live Preview & Discrepancies Diff -->
          <div v-if="restoreStep === 2 && restorePreviewData" class="space-y-4">
            <!-- Metadata Bar -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-3 text-[11px]">
              <div><span class="text-slate-400">شناسه فروشگاه:</span> <strong class="text-slate-800 dark:text-slate-100 mr-1">{{ restorePreviewData.metadata?.store_id }}</strong></div>
              <div><span class="text-slate-400">نسخه ساختار:</span> <strong class="text-slate-800 dark:text-slate-100 mr-1">{{ restorePreviewData.metadata?.schema_version }}</strong></div>
              <div><span class="text-slate-400">تاریخ بک‌آپ:</span> <span class="text-slate-600 dark:text-slate-300 mr-1">{{ restorePreviewData.metadata?.created_at_utc }}</span></div>
              <div><span class="text-slate-400">وضعیت صحت هش:</span> <span class="font-bold text-emerald-600 mr-1">تأیید شده</span></div>
            </div>

            <!-- Stats Counters -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <span class="text-slate-400 text-[11px]">کل اقلام فایل:</span>
                <div class="text-lg font-black text-slate-800 dark:text-slate-100 mt-0.5">{{ toPersianDigits(restorePreviewData.total_items) }}</div>
              </div>
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-emerald-200 dark:border-emerald-900">
                <span class="text-emerald-700 dark:text-emerald-300 font-bold text-[11px]">بدون تغییر (تطابق کامل):</span>
                <div class="text-lg font-black text-emerald-800 dark:text-emerald-200 mt-0.5">{{ toPersianDigits(restorePreviewData.matching_count) }}</div>
              </div>
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-amber-200 dark:border-amber-900">
                <span class="text-amber-700 dark:text-amber-300 font-bold text-[11px]">دارای مغایرت (تغییر جدید):</span>
                <div class="text-lg font-black text-amber-800 dark:text-amber-200 mt-0.5">{{ toPersianDigits(restorePreviewData.discrepancy_count) }}</div>
              </div>
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-900">
                <span class="text-rose-700 dark:text-rose-300 font-bold text-[11px]">یافت‌نشده در ووکامرس:</span>
                <div class="text-lg font-black text-rose-800 dark:text-rose-200 mt-0.5">{{ toPersianDigits(restorePreviewData.not_found_count) }}</div>
              </div>
            </div>

            <!-- Discrepancy Warning & Options -->
            <div v-if="restorePreviewData.discrepancy_count > 0" class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-200 space-y-2">
              <div class="flex items-center gap-2">
                <Iconsax name="warning-2" size="20" class="text-amber-600 shrink-0" />
                <strong class="font-bold">هشدار: برخی کالاها پس از ایجاد این نسخه پشتیبان، تغییر قیمت جدید داشته‌اند!</strong>
              </div>
              <p class="text-[11px] leading-relaxed">
                قیمت فعلی زنده در ووکامرس با قیمتی که پس از عملیات مورد انتظار بوده یکسان نیست. برای جلوگیری از بازنویسی اشتباهی، می‌توانید فقط اقلام بدون مغایرت را بازیابی نمایید:
              </p>
              <label class="flex items-center gap-2 cursor-pointer font-bold text-xs pt-1">
                <input type="checkbox" v-model="restoreSkipDiscrepancies" class="rounded text-violet-600 focus:ring-0" />
                <span>فقط بازگردانی اقلام بدون مغایرت (عدم دستکاری کالاهای با تغییر قیمت جدید)</span>
              </label>
            </div>

            <!-- Preview Diff Table -->
            <div class="space-y-1.5">
              <h4 class="font-bold text-slate-700 dark:text-slate-200">پیش‌نمایش تغییرات قیمت (Diff):</h4>
              <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden max-h-60 overflow-y-auto">
                <table class="w-full text-right text-xs">
                  <thead class="bg-slate-50 dark:bg-slate-850 sticky top-0 text-slate-500">
                    <tr>
                      <th class="p-2.5">شناسه</th>
                      <th class="p-2.5">نوع</th>
                      <th class="p-2.5">قیمت عادی فعلی</th>
                      <th class="p-2.5">قیمت عادی بک‌آپ</th>
                      <th class="p-2.5">قیمت حراج فعلی</th>
                      <th class="p-2.5">قیمت حراج بک‌آپ</th>
                      <th class="p-2.5">وضعیت</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="it in restorePreviewData.items" :key="it.product_id">
                      <td class="p-2.5 font-medium text-slate-400">#{{ toPersianDigits(it.product_id) }}</td>
                      <td class="p-2.5 text-slate-500">{{ it.product_type }}</td>
                      <td class="p-2.5 font-mono">{{ formatVerifiedPrice(it.current_regular_price) }}</td>
                      <td class="p-2.5 font-mono text-violet-600 font-bold">{{ formatVerifiedPrice(it.backup_regular_price) }}</td>
                      <td class="p-2.5 font-mono">{{ formatVerifiedPrice(it.current_sale_price) }}</td>
                      <td class="p-2.5 font-mono text-violet-600">{{ formatVerifiedPrice(it.backup_sale_price) }}</td>
                      <td class="p-2.5">
                        <span
                          class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                          :class="it.has_discrepancy ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'"
                        >
                          {{ it.has_discrepancy ? 'دارای مغایرت' : 'مطابق' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Confirmation Checkbox -->
            <div class="p-3.5 rounded-2xl bg-violet-50/70 dark:bg-violet-950/30 border border-violet-200 dark:border-violet-900/60">
              <label class="flex items-center gap-2 cursor-pointer font-bold text-xs text-violet-900 dark:text-violet-200">
                <input type="checkbox" v-model="restoreConfirmed" class="rounded text-violet-600 focus:ring-0" />
                <span>تأیید می‌کنم که قیمت‌ها در ووکامرس بر اساس این نسخه پشتیبان بازنویسی شوند و تأیید مجدد انجام گیرد.</span>
              </label>
            </div>
          </div>

          <!-- Step 3: Execution Result -->
          <div v-if="restoreStep === 3 && restoreResult" class="space-y-4">
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900 text-emerald-900 dark:text-emerald-200 space-y-1">
              <strong class="font-bold flex items-center gap-2">
                <Iconsax name="tick-circle" size="20" class="text-emerald-600" />
                <span>عملیات بازگردانی قیمت‌ها با موفقیت در ووکامرس پایان یافت</span>
              </strong>
              <p class="text-[11px] leading-relaxed">
                قیمت‌ها به نسخه پشتیبان بازگردانده شده و مستقیماً از ووکامرس مجدداً خوانده و تأیید شدند.
              </p>
            </div>

            <!-- Results Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <span class="text-slate-400 text-[11px]">کل اقلام هدف:</span>
                <div class="text-lg font-black text-slate-800 dark:text-slate-100 mt-0.5">{{ toPersianDigits(restoreResult.total_items) }}</div>
              </div>
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-emerald-200 dark:border-emerald-900">
                <span class="text-emerald-700 dark:text-emerald-300 font-bold text-[11px]">بازگردانی و تأیید موفق:</span>
                <div class="text-lg font-black text-emerald-800 dark:text-emerald-200 mt-0.5">{{ toPersianDigits(restoreResult.restored_items) }}</div>
              </div>
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-amber-200 dark:border-amber-900">
                <span class="text-amber-700 dark:text-amber-300 font-bold text-[11px]">رد شده / مغایرت:</span>
                <div class="text-lg font-black text-amber-800 dark:text-amber-200 mt-0.5">{{ toPersianDigits(restoreResult.skipped_items) }}</div>
              </div>
              <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-900">
                <span class="text-rose-700 dark:text-rose-300 font-bold text-[11px]">ناموفق:</span>
                <div class="text-lg font-black text-rose-800 dark:text-rose-200 mt-0.5">{{ toPersianDigits(restoreResult.failed_items) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-850/50">
          <button
            v-if="restoreStep === 2"
            type="button"
            @click="restoreStep = 1"
            class="py-2.5 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-100 transition-colors"
          >
            ← بازگشت به انتخاب فایل
          </button>
          <div v-else></div>

          <div class="flex items-center gap-2">
            <button
              v-if="restoreStep === 1"
              type="button"
              @click="runRestorePreview"
              :disabled="restoreLoadingPreview"
              class="py-2.5 px-5 rounded-2xl bg-violet-600 hover:bg-violet-700 text-white font-bold transition-all shadow-md shadow-violet-600/20 disabled:opacity-50 flex items-center gap-2 cursor-pointer"
            >
              <span v-if="restoreLoadingPreview" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>بررسی و پیش‌نمایش تفاوت قیمت‌ها</span>
              <Iconsax name="arrow-left" size="14" />
            </button>

            <button
              v-if="restoreStep === 2"
              type="button"
              @click="executePriceRestoreNow"
              :disabled="!restoreConfirmed || restoreExecuting"
              class="py-2.5 px-6 rounded-2xl bg-violet-600 hover:bg-violet-700 text-white font-bold transition-all shadow-md shadow-violet-600/20 disabled:opacity-50 flex items-center gap-2 cursor-pointer"
            >
              <span v-if="restoreExecuting" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>اجرای بازگردانی و تأیید در ووکامرس</span>
            </button>

            <button
              v-if="restoreStep === 3"
              type="button"
              @click="closePriceRestoreModal"
              class="py-2.5 px-5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold transition-colors cursor-pointer"
            >
              بستن پنجره
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import RefreshButton from '@/components/ui/RefreshButton.vue';
import api from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { useStoreContext } from '@/stores/storeContext';
import { useNotificationStore } from '@/stores/notification';
import { toPersianDigits, formatNumber, formatPrice, formatDateTime } from '@/utils/formatters';

const authStore = useAuthStore();
const storeContext = useStoreContext();
const notification = useNotificationStore();

const canExecuteBulk = computed(() => authStore.hasPermission('bulk.execute'));
const currentStoreName = computed(() => storeContext.currentStoreName);

// ==================== STATE ====================
const step = ref(1);
const selectionMode = ref('filter'); // 'filter' | 'manual' | 'all'
const targetScope = ref('both'); // 'parent' | 'variations' | 'both'
const categories = ref([]);
const categoryDropdownOpen = ref(false);
const categorySearchQuery = ref('');
const presets = ref([]);
const loadingMeta = ref(false);
const evaluatingTarget = ref(false);
const showSampleList = ref(false);

// Filters
const filters = reactive({
  categories: [],
  category: 'all',
  type: 'all',
  stock_status: 'all',
  status: 'all',
  min_price: '',
  max_price: '',
  sku: '',
  search: '',
});

const filteredCategories = computed(() => {
  if (!categorySearchQuery.value.trim()) return categories.value;
  const q = categorySearchQuery.value.trim().toLowerCase();
  return categories.value.filter(c => (c.name || '').toLowerCase().includes(q));
});

const toggleCategory = (catId) => {
  const idx = filters.categories.indexOf(catId);
  if (idx > -1) {
    filters.categories.splice(idx, 1);
  } else {
    filters.categories.push(catId);
  }
  debouncedEvaluate();
};

const removeCategory = (catId) => {
  const idx = filters.categories.indexOf(catId);
  if (idx > -1) {
    filters.categories.splice(idx, 1);
    debouncedEvaluate();
  }
};

const selectAllFilteredCategories = () => {
  const ids = filteredCategories.value.map(c => c.id);
  const set = new Set([...filters.categories, ...ids]);
  filters.categories = Array.from(set);
  debouncedEvaluate();
};

const clearCategorySelection = () => {
  filters.categories = [];
  debouncedEvaluate();
};

const getCategoryName = (catId) => {
  const c = categories.value.find(item => item.id === catId);
  return c ? c.name : `#${catId}`;
};

// Target Evaluation Data
const targetData = ref(null);

// Manual Selection state
const manualProductList = ref([]);
const manualSelectedIds = ref([]);
const manualSearch = ref('');

// Operation state (Step 2)
const activeOpCategory = ref('price'); // 'price' | 'inventory' | 'general' | 'metadata'
const selectedActionType = ref('increase_price_percent');
const actionParams = reactive({
  value: 10,
  status: 'publish',
  visibility: 'visible',
  manage_stock: true,
  mode: 'set',
  key: '',
  action: 'set',
});
const saveAsPreset = ref(false);
const presetTitle = ref('');

// Preview state (Step 3)
const loadingPreview = ref(false);
const previewData = ref(null);

// Execution state (Step 4 & 5)
const userConfirmedExecution = ref(false);
const isProcessing = ref(false);
const operationFinished = ref(false);
const currentActiveOp = ref(null);
const failedItemsList = ref([]);
const retryingFailed = ref(false);

const progressData = reactive({
  percent: 0,
  total: 0,
  processed: 0,
  succeeded: 0,
  failed: 0,
  skipped: 0,
});

// Operations History State
const loadingHistory = ref(false);
const operations = ref([]);
const selectedOpModal = ref(null);
const historyFilters = reactive({
  status: 'all',
  entity: 'all',
  page: 1,
  per_page: 10,
});
const historyMeta = reactive({
  current_page: 1,
  total_pages: 1,
  total: 0,
});

// ==================== QUICK ACTIONS SHORTCUTS ====================
const quickActions = [
  { key: 'inc_p_percent', title: 'افزایش قیمت', badge: '۱۰٪', icon: 'flash', color: 'text-emerald-400 bg-emerald-500/20', action: 'increase_price_percent', value: 10, cat: 'price' },
  { key: 'dec_p_percent', title: 'کاهش قیمت', badge: '۵٪', icon: 'flash', color: 'text-amber-400 bg-amber-500/20', action: 'decrease_price_percent', value: 5, cat: 'price' },
  { key: 'set_sale', title: 'فروش ویژه', badge: 'حراجی', icon: 'tag', color: 'text-rose-400 bg-rose-500/20', action: 'set_sale_price', value: null, cat: 'price' },
  { key: 'inc_stock', title: 'افزایش موجودی', badge: '+۱۰ عدد', icon: 'archive', color: 'text-blue-400 bg-blue-500/20', action: 'increase_stock', value: 10, cat: 'inventory' },
  { key: 'set_stock_st', title: 'وضعیت انبار', badge: 'موجود', icon: 'archive', color: 'text-teal-400 bg-teal-500/20', action: 'set_stock_status', status: 'instock', cat: 'inventory' },
  { key: 'set_status', title: 'تغییر وضعیت', badge: 'انتشار', icon: 'document', color: 'text-indigo-400 bg-indigo-500/20', action: 'set_status', status: 'publish', cat: 'general' },
  { key: 'set_sku', title: 'ویرایش SKU', badge: 'پیشوند/پسوند', icon: 'edit', color: 'text-cyan-400 bg-cyan-500/20', action: 'set_sku', mode: 'prefix', cat: 'general' },
  { key: 'custom_meta', title: 'متادیتا سفارشی', badge: 'فیلد خاص', icon: 'setting-2', color: 'text-purple-400 bg-purple-500/20', action: 'set_metadata', cat: 'metadata' },
];

const applyQuickAction = (qa) => {
  activeOpCategory.value = qa.cat;
  selectedActionType.value = qa.action;
  if (qa.value !== undefined) actionParams.value = qa.value;
  if (qa.status !== undefined) actionParams.status = qa.status;
  if (qa.mode !== undefined) actionParams.mode = qa.mode;
  step.value = 1;
  notification.info(`عملیات «${qa.title}» انتخاب شد. ابتدا کالاهای هدف را مشخص فرمایید.`);
};

// ==================== OPERATION DEFINITIONS ====================
const opCategories = [
  { key: 'price', title: 'قیمت و تخفیف‌ها', icon: 'tag' },
  { key: 'inventory', title: 'انبار و موجودی', icon: 'archive' },
  { key: 'general', title: 'عمومی و انتشار', icon: 'box' },
  { key: 'metadata', title: 'متادیتا سفارشی', icon: 'setting-2' },
];

const currentCategoryActions = computed(() => {
  switch (activeOpCategory.value) {
    case 'price':
      return [
        { key: 'increase_price_percent', name: 'افزایش درصد قیمت (+٪)', description: 'افزایش قیمت عادی بر اساس درصد مشخص (مثلاً +۱۰٪)', badge: 'درصدی' },
        { key: 'decrease_price_percent', name: 'کاهش درصد قیمت (-٪)', description: 'کاهش قیمت عادی بر اساس درصد مشخص (مثلاً -۵٪)', badge: 'درصدی' },
        { key: 'increase_price_amount', name: 'افزایش مبلغ ثابت (+)', description: 'افزودن یک مبلغ مشخص به قیمت عادی تمام کالاها', badge: 'تومان' },
        { key: 'decrease_price_amount', name: 'کاهش مبلغ ثابت (-)', description: 'کاهش یک مبلغ مشخص از قیمت عادی تمام کالاها', badge: 'تومان' },
        { key: 'set_regular_price', name: 'تنظیم قیمت عادی مشخص', description: 'تعیین قیمت عادی مشخص و یکسان برای کالاهای انتخابی', badge: 'مستقیم' },
        { key: 'set_sale_price', name: 'تنظیم قیمت فروش ویژه', description: 'تعیین قیمت حراجی و دارای تخفیف برای محصولات', badge: 'حراج' },
        { key: 'clear_sale_price', name: 'حذف قیمت فروش ویژه', description: 'حذف تخفیف و بازگرداندن به قیمت عادی بدون حراج', badge: 'لغو تخفیف' },
      ];
    case 'inventory':
      return [
        { key: 'set_stock', name: 'تنظیم موجودی انبار', description: 'تعیین تعداد دقیق کالای موجود در انبار', badge: 'تعداد' },
        { key: 'increase_stock', name: 'افزایش موجودی انبار (+)', description: 'افزودن تعداد مشخص به موجودی فعلی انبار', badge: 'افزایشی' },
        { key: 'decrease_stock', name: 'کاهش موجودی انبار (-)', description: 'کسر تعداد مشخص از موجودی فعلی انبار', badge: 'کاهشی' },
        { key: 'set_stock_status', name: 'تنظیم وضعیت موجودی', description: 'تغییر به وضعیت موجود، ناموجود یا پیش‌خرید', badge: 'وضعیت' },
        { key: 'set_manage_stock', name: 'تنظیم مدیریت انبار', description: 'فعال یا غیرفعال کردن قابلیت مدیریت موجودی در سطح ووکامرس', badge: 'تنظیمات' },
      ];
    case 'general':
      return [
        { key: 'set_status', name: 'تغییر وضعیت انتشار', description: 'تغییر وضعیت به منتشر شده، پیش‌نویس، خصوصی یا بررسی', badge: 'انتشار' },
        { key: 'set_catalog_visibility', name: 'قابلیت مشاهده در کاتالوگ', description: 'نحوه نمایش محصول در ویترین فروشگاه و جستجو', badge: 'کاتالوگ' },
        { key: 'set_featured', name: 'تعیین محصول ویژه', description: 'علامت‌گذاری یا لغو به عنوان محصول ستاره‌دار/ویژه', badge: 'Featured' },
        { key: 'set_weight', name: 'تنظیم وزن کالا', description: 'تعیین وزن فیزیکی کالا (کیلوگرم) برای حمل و نقل', badge: 'وزن' },
        { key: 'set_sku', name: 'ویرایش شناسه SKU', description: 'تنظیم یا افزودن پیشوند/پسوند به شناسه انبارداری کالاها', badge: 'SKU' },
      ];
    case 'metadata':
      return [
        { key: 'set_metadata', name: 'ویرایش متادیتا اختصاصی', description: 'تنظیم یا حذف کلیدهای متادیتای سفارشی ووکامرس', badge: 'Custom Meta' },
      ];
    default:
      return [];
  }
});

const currentActionDefinition = computed(() => {
  return currentCategoryActions.value.find(a => a.key === selectedActionType.value) || null;
});

const selectAction = (key) => {
  selectedActionType.value = key;
  if (key === 'increase_price_percent' && !actionParams.value) actionParams.value = 10;
  if (key === 'decrease_price_percent' && !actionParams.value) actionParams.value = 5;
};

// Value label & units
const needsValueInput = computed(() => {
  const a = selectedActionType.value;
  return [
    'increase_price_percent', 'decrease_price_percent',
    'increase_price_amount', 'decrease_price_amount',
    'set_regular_price', 'set_sale_price',
    'set_stock', 'increase_stock', 'decrease_stock',
    'set_weight',
  ].includes(a);
});

const valueInputLabel = computed(() => {
  switch (selectedActionType.value) {
    case 'increase_price_percent': return 'درصد افزایش قیمت:';
    case 'decrease_price_percent': return 'درصد کاهش قیمت:';
    case 'increase_price_amount': return 'مبلغ افزایش (تومان):';
    case 'decrease_price_amount': return 'مبلغ کاهش (تومان):';
    case 'set_regular_price': return 'قیمت عادی جدید (تومان):';
    case 'set_sale_price': return 'قیمت ویژه حراج (تومان):';
    case 'set_stock': return 'تعداد جدید موجودی:';
    case 'increase_stock': return 'تعداد افزایش موجودی:';
    case 'decrease_stock': return 'تعداد کاهش موجودی:';
    case 'set_weight': return 'وزن کالا (کیلوگرم):';
    default: return 'مقدار:';
  }
});

const valueInputUnit = computed(() => {
  if (selectedActionType.value.includes('percent')) return '٪';
  if (selectedActionType.value.includes('stock')) return 'عدد';
  if (selectedActionType.value === 'set_weight') return 'kg';
  return 'تومان';
});

const valueInputPlaceholder = computed(() => {
  if (selectedActionType.value.includes('percent')) return 'مثلاً ۱۰';
  if (selectedActionType.value.includes('stock')) return 'مثلاً ۵۰';
  return 'مثلاً ۱۰۰۰۰۰';
});

// Can Proceed checks
const canProceedStep1 = computed(() => {
  if (!targetData.value) return false;
  return (targetData.value.affected_count || 0) > 0;
});

const canProceedStep2 = computed(() => {
  if (!selectedActionType.value) return false;
  if (needsValueInput.value) {
    return actionParams.value !== null && actionParams.value !== '' && !isNaN(Number(actionParams.value));
  }
  if (selectedActionType.value === 'set_metadata') {
    return !!actionParams.key.trim();
  }
  return true;
});

// ==================== METHODS: TARGET EVALUATION ====================
let debounceTimer = null;
const debouncedEvaluate = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    evaluateTargetNow();
  }, 400);
};

const setSelectionMode = (mode) => {
  selectionMode.value = mode;
  if (mode === 'manual' && manualProductList.value.length === 0) {
    fetchManualProducts();
  }
  evaluateTargetNow();
};

const resetFilters = () => {
  filters.categories = [];
  filters.category = 'all';
  categorySearchQuery.value = '';
  categoryDropdownOpen.value = false;
  filters.type = 'all';
  filters.stock_status = 'all';
  filters.status = 'all';
  filters.min_price = '';
  filters.max_price = '';
  filters.sku = '';
  filters.search = '';
  evaluateTargetNow();
};

const buildSelectionPayload = () => {
  if (selectionMode.value === 'all') {
    return {
      entity: 'products',
      selection: { mode: 'filter' },
      filter: {},
      target_scope: targetScope.value,
    };
  }

  if (selectionMode.value === 'manual') {
    return {
      entity: 'products',
      selection: { mode: 'ids', ids: manualSelectedIds.value },
      filter: {},
      target_scope: targetScope.value,
    };
  }

  // Filter mode
  const cleanFilter = {};
  if (Array.isArray(filters.categories) && filters.categories.length > 0) {
    cleanFilter.categories = filters.categories;
  } else if (filters.category && filters.category !== 'all') {
    cleanFilter.category = filters.category;
  }
  if (filters.type && filters.type !== 'all') cleanFilter.type = filters.type;
  if (filters.stock_status && filters.stock_status !== 'all') cleanFilter.stock_status = filters.stock_status;
  if (filters.status && filters.status !== 'all') cleanFilter.status = filters.status;
  if (filters.min_price !== '') cleanFilter.min_price = filters.min_price;
  if (filters.max_price !== '') cleanFilter.max_price = filters.max_price;
  if (filters.sku) cleanFilter.sku = filters.sku;
  if (filters.search) cleanFilter.search = filters.search;

  return {
    entity: 'products',
    selection: { mode: 'filter' },
    filter: cleanFilter,
    target_scope: targetScope.value,
  };
};

const evaluateTargetNow = async () => {
  evaluatingTarget.value = true;
  try {
    const payload = buildSelectionPayload();
    const res = await api.post('/bulk-operations/evaluate-target', payload);
    if (res?.success !== false) {
      targetData.value = res.data;
    } else {
      notification.error(res.error?.message || 'خطا در ارزیابی محصولات');
    }
  } catch (err) {
    notification.error(err.message || 'خطا در ارتباط با سرور ووکامرس');
  } finally {
    evaluatingTarget.value = false;
  }
};

// Manual Products Loading
const fetchManualProducts = async () => {
  try {
    const params = { per_page: 30, page: 1 };
    if (manualSearch.value) params.search = manualSearch.value;
    const res = await api.get('/products', { params });
    if (res?.success !== false) {
      manualProductList.value = res.data || [];
    }
  } catch (err) {
    // Non-blocking
  }
};

const toggleManualId = (id) => {
  const idx = manualSelectedIds.value.indexOf(id);
  if (idx > -1) {
    manualSelectedIds.value.splice(idx, 1);
  } else {
    manualSelectedIds.value.push(id);
  }
  evaluateTargetNow();
};

const allManualSelectedOnPage = computed(() => {
  if (manualProductList.value.length === 0) return false;
  return manualProductList.value.every(p => manualSelectedIds.value.includes(p.id));
});

const toggleSelectAllManual = () => {
  if (allManualSelectedOnPage.value) {
    const pageIds = manualProductList.value.map(p => p.id);
    manualSelectedIds.value = manualSelectedIds.value.filter(id => !pageIds.includes(id));
  } else {
    const newIds = manualProductList.value.map(p => p.id);
    manualSelectedIds.value = Array.from(new Set([...manualSelectedIds.value, ...newIds]));
  }
  evaluateTargetNow();
};

// ==================== METHODS: PREVIEW (STEP 3) ====================
const buildActionPayload = () => {
  const selPayload = buildSelectionPayload();
  const act = {
    type: selectedActionType.value,
    target: targetScope.value,
    ...actionParams,
  };

  return {
    ...selPayload,
    action: act,
  };
};

const loadPreviewAndGoStep3 = async () => {
  loadingPreview.value = true;
  try {
    // If saveAsPreset checked, save preset first
    if (saveAsPreset.value && presetTitle.value.trim()) {
      await saveCurrentAsPreset();
    }

    const payload = buildActionPayload();
    const res = await api.post('/bulk-operations/preview', payload);
    if (res?.success !== false) {
      previewData.value = res.data;
      step.value = 3;
    } else {
      notification.error(res.error?.message || 'خطا در ارزیابی پیش‌نمایش');
    }
  } catch (err) {
    notification.error(err.message || 'خطا در محاسبه پیش‌نمایش');
  } finally {
    loadingPreview.value = false;
  }
};

// ==================== METHODS: CHUNKED EXECUTION (STEP 4) ====================
let abortRequested = false;

const startExecutionLoop = async () => {
  isProcessing.value = true;
  operationFinished.value = false;
  abortRequested = false;

  // Initialize progress
  progressData.percent = 0;
  progressData.total = previewData.value?.affected_count || 1;
  progressData.processed = 0;
  progressData.succeeded = 0;
  progressData.failed = 0;
  progressData.skipped = 0;

  try {
    // Step 1: Create the operation in DB with idempotency key
    const payload = buildActionPayload();
    payload.idempotency_key = `bulk_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;
    payload.create_only = true;

    const createRes = await api.post('/bulk-operations', payload);
    if (createRes?.success === false) {
      throw new Error(createRes.error?.message || 'خطا در ثبت اولیه عملیات');
    }

    const op = createRes.data;
    currentActiveOp.value = op;
    const opId = op.id;

    // Step 2: Loop chunks until done or cancelled
    let isDone = false;
    const chunkSize = 25;

    while (!isDone && !abortRequested) {
      const chunkRes = await api.post(`/bulk-operations/${opId}/chunk`, { chunk_size: chunkSize });
      if (chunkRes?.success === false) {
        throw new Error(chunkRes.error?.message || 'خطا در پردازش دسته‌ای');
      }

      const updated = chunkRes.data;
      currentActiveOp.value = updated;

      progressData.processed = updated.processed_items || 0;
      progressData.total = updated.total_items || progressData.total;
      progressData.succeeded = updated.success_items || 0;
      progressData.failed = updated.failed_items || 0;
      progressData.skipped = updated.skipped_items || 0;

      if (progressData.total > 0) {
        progressData.percent = Math.min(100, Math.round((progressData.processed / progressData.total) * 100));
      }

      if (updated.is_done || ['completed', 'partial', 'failed', 'cancelled'].includes(updated.status)) {
        isDone = true;
      }
    }

    // Step 3: Fetch final operation with full failure items
    const finalRes = await api.get(`/bulk-operations/${opId}`);
    if (finalRes?.success !== false) {
      currentActiveOp.value = finalRes.data;
      failedItemsList.value = (finalRes.data.items || []).filter(i => i.status === 'failed');
    }

    operationFinished.value = true;
    step.value = 5;
    fetchOperations(); // Refresh history
  } catch (err) {
    notification.error(err.message || 'خطا در حین اجرای عملیات');
  } finally {
    isProcessing.value = false;
  }
};

const cancelActiveOperation = async () => {
  abortRequested = true;
  if (!currentActiveOp.value?.id) return;
  try {
    const res = await api.post(`/bulk-operations/${currentActiveOp.value.id}/cancel`);
    if (res?.success !== false) {
      notification.info('دستور لغو ارسال شد. پردازش دسته‌ها متوقف گردید.');
    }
  } catch (err) {
    // Non-blocking
  }
};

// Retry Failed Items
const retryFailedItemsNow = async () => {
  if (!currentActiveOp.value?.id) return;
  retryingFailed.value = true;
  try {
    const res = await api.post(`/bulk-operations/${currentActiveOp.value.id}/retry-failed`);
    if (res?.success !== false) {
      notification.success('عملیات تلاش مجدد برای آیتم‌های ناموفق ایجاد شد.');
      currentActiveOp.value = res.data;
      // Start chunk execution on this new operation
      previewData.value = { affected_count: res.data.total_items };
      step.value = 4;
      userConfirmedExecution.value = true;
      await startExecutionLoop();
    } else {
      notification.error(res.error?.message || 'خطا در تلاش مجدد');
    }
  } catch (err) {
    notification.error(err.message || 'خطا در تلاش مجدد');
  } finally {
    retryingFailed.value = false;
  }
};

const resetWizard = () => {
  step.value = 1;
  previewData.value = null;
  userConfirmedExecution.value = false;
  isProcessing.value = false;
  operationFinished.value = false;
  currentActiveOp.value = null;
  failedItemsList.value = [];
  evaluateTargetNow();
};

const goToStep = (s) => {
  if (isProcessing.value) return;
  if (s === 1) { step.value = 1; }
  else if (s === 2 && canProceedStep1.value) { step.value = 2; }
  else if (s === 3 && previewData.value) { step.value = 3; }
  else if (s === 4 && previewData.value) { step.value = 4; }
};

// ==================== PRESETS MANAGEMENT ====================
const fetchPresets = async () => {
  try {
    const res = await api.get('/bulk-operations/presets');
    if (res?.success !== false) {
      presets.value = res.data || [];
    }
  } catch (err) {
    // Non-blocking
  }
};

const saveCurrentAsPreset = async () => {
  if (!presetTitle.value.trim()) return;
  try {
    const pPayload = {
      title: presetTitle.value.trim(),
      target_entity: 'products',
      filter_criteria: buildSelectionPayload().filter,
      action_data: {
        type: selectedActionType.value,
        target: targetScope.value,
        ...actionParams,
      },
    };
    const res = await api.post('/bulk-operations/presets', pPayload);
    if (res?.success !== false) {
      notification.success(`الگوی «${presetTitle.value}» با موفقیت ذخیره شد.`);
      presetTitle.value = '';
      saveAsPreset.value = false;
      fetchPresets();
    }
  } catch (err) {
    notification.error(err.message || 'خطا در ذخیره الگو');
  }
};

const loadPreset = (p) => {
  if (p.action_data?.type) {
    selectedActionType.value = p.action_data.type;
    if (p.action_data.target) targetScope.value = p.action_data.target;
    if (p.action_data.value !== undefined) actionParams.value = p.action_data.value;
    if (p.action_data.status !== undefined) actionParams.status = p.action_data.status;
  }
  if (p.filter_criteria) {
    selectionMode.value = 'filter';
    Object.assign(filters, p.filter_criteria);
  }
  evaluateTargetNow();
  step.value = 1;
  notification.info(`الگوی «${p.title}» بارگذاری شد.`);
};

const deletePreset = async (id) => {
  try {
    const res = await api.delete(`/bulk-operations/presets/${id}`);
    if (res?.success !== false) {
      notification.success('الگو با موفقیت حذف شد.');
      fetchPresets();
    }
  } catch (err) {
    notification.error('خطا در حذف الگو');
  }
};

// ==================== OPERATIONS HISTORY ====================
const fetchOperations = async () => {
  loadingHistory.value = true;
  try {
    const res = await api.get('/bulk-operations', { params: historyFilters });
    if (res?.success !== false) {
      operations.value = res.data || [];
      if (res.meta) {
        historyMeta.current_page = res.meta.current_page;
        historyMeta.total = res.meta.total;
        historyMeta.total_pages = res.meta.total_pages;
      }
    }
  } catch (err) {
    // Non-blocking
  } finally {
    loadingHistory.value = false;
  }
};

const changeHistoryPage = (p) => {
  historyFilters.page = p;
  fetchOperations();
};

const openDetails = async (op) => {
  try {
    const res = await api.get(`/bulk-operations/${op.id}`);
    if (res?.success !== false) {
      selectedOpModal.value = res.data || op;
    }
  } catch (e) {
    selectedOpModal.value = op;
  }
};

const retryFailedItemsFromModal = async (opId) => {
  selectedOpModal.value = null;
  try {
    const res = await api.post(`/bulk-operations/${opId}/retry-failed`);
    if (res?.success !== false) {
      notification.success('عملیات تلاش مجدد ایجاد شد.');
      currentActiveOp.value = res.data;
      previewData.value = { affected_count: res.data.total_items };
      step.value = 4;
      userConfirmedExecution.value = true;
      await startExecutionLoop();
    }
  } catch (err) {
    notification.error(err.message || 'خطا در تلاش مجدد');
  }
};

// ==================== METADATA & REFRESH ====================
const fetchCategories = async () => {
  loadingMeta.value = true;
  try {
    const res = await api.get('/product-categories', { params: { per_page: 100 } });
    if (res?.success !== false) {
      categories.value = res.data || [];
    }
  } catch (err) {
    // Non-blocking
  } finally {
    loadingMeta.value = false;
  }
};

const handleGlobalRefresh = async () => {
  await Promise.all([
    fetchCategories(),
    fetchPresets(),
    fetchOperations(),
    evaluateTargetNow(),
  ]);
  notification.success('اطلاعات فروشگاه با موفقیت تازه‌سازی شد.');
};

// Watch for active store changes
watch(() => storeContext.activeStoreId, (newId) => {
  if (newId) {
    handleGlobalRefresh();
  }
});

// Formatters
const formatDiffValue = (val) => {
  if (val === null || val === undefined || val === '') return '-';
  if (typeof val === 'object') {
    if (val.regular_price !== undefined || val.price !== undefined) {
      return formatPrice(val.regular_price || val.price || 0);
    }
    if (val.stock_quantity !== undefined) {
      return `${toPersianDigits(val.stock_quantity)} عدد (${val.stock_status || ''})`;
    }
    return Object.entries(val).map(([k, v]) => `${k}: ${v}`).join(' | ');
  }
  if (!isNaN(Number(val)) && Number(val) > 1000) {
    return formatPrice(val);
  }
  return String(val);
};

const formatActionName = (act) => {
  const map = {
    increase_price_percent: 'افزایش درصد قیمت (+٪)',
    decrease_price_percent: 'کاهش درصد قیمت (-٪)',
    increase_price_amount: 'افزایش مبلغ قیمت (+)',
    decrease_price_amount: 'کاهش مبلغ قیمت (-)',
    set_regular_price: 'تنظیم قیمت عادی',
    set_sale_price: 'تنظیم قیمت ویژه',
    clear_sale_price: 'حذف قیمت ویژه',
    set_stock: 'تنظیم موجودی انبار',
    increase_stock: 'افزایش موجودی (+)',
    decrease_stock: 'کاهش موجودی (-)',
    set_stock_status: 'تنظیم وضعیت انبار',
    set_manage_stock: 'مدیریت انبار',
    set_status: 'تغییر وضعیت انتشار',
    set_catalog_visibility: 'نمایش در کاتالوگ',
    set_featured: 'محصول ویژه',
    set_weight: 'تنظیم وزن کالا',
    set_sku: 'ویرایش شناسه SKU',
    set_metadata: 'ویرایش متادیتا سفارشی',
    change_status: 'تغییر وضعیت سفارشات',
  };
  return map[act] || act;
};

const getEntityLabel = (e) => {
  switch (e) {
    case 'products':
    case 'product': return 'محصولات';
    case 'orders':
    case 'order': return 'سفارش‌ها';
    case 'customers':
    case 'customer': return 'مشتریان';
    default: return e || 'محصولات';
  }
};

const getEntityBadgeClass = (e) => {
  switch (e) {
    case 'products':
    case 'product': return 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300';
    case 'orders':
    case 'order': return 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300';
    case 'customers':
    case 'customer': return 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300';
    default: return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
  }
};

const getStatusLabel = (s) => {
  switch (s) {
    case 'completed': return 'تکمیل شده';
    case 'partial': return 'تکمیل جزئی';
    case 'processing': return 'در حال پردازش';
    case 'pending': return 'در انتظار';
    case 'cancelled': return 'لغو شده';
    case 'failed': return 'ناموفق';
    default: return s;
  }
};

const getStatusBadgeClass = (s) => {
  switch (s) {
    case 'completed': return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200/60';
    case 'partial': return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200/60';
    case 'processing': return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200/60 animate-pulse';
    case 'cancelled': return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    case 'failed': return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200/60';
    default: return 'bg-slate-100 text-slate-600';
  }
};

const getStatusDotClass = (s) => {
  switch (s) {
    case 'completed': return 'bg-emerald-500';
    case 'partial': return 'bg-amber-500';
    case 'processing': return 'bg-indigo-500';
    case 'failed': return 'bg-rose-500';
    default: return 'bg-slate-400';
  }
};

const getStatusTextColor = (s) => {
  switch (s) {
    case 'completed': return 'text-emerald-600 dark:text-emerald-400';
    case 'partial': return 'text-amber-600 dark:text-amber-400';
    case 'processing': return 'text-indigo-600 dark:text-indigo-400';
    case 'failed': return 'text-rose-600 dark:text-rose-400';
    default: return 'text-slate-500';
  }
};

// Final Report Computed
const finalReportClass = computed(() => {
  if (progressData.failed > 0 && progressData.succeeded === 0) {
    return 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200';
  }
  if (progressData.failed > 0) {
    return 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-200';
  }
  return 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-900 text-emerald-800 dark:text-emerald-200';
});

const finalReportIconBg = computed(() => {
  if (progressData.failed > 0 && progressData.succeeded === 0) return 'bg-rose-100 dark:bg-rose-900/60 text-rose-600';
  if (progressData.failed > 0) return 'bg-amber-100 dark:bg-amber-900/60 text-amber-600';
  return 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600';
});

const finalReportIcon = computed(() => {
  if (progressData.failed > 0 && progressData.succeeded === 0) return 'close-circle';
  if (progressData.failed > 0) return 'warning';
  return 'tick-circle';
});

const finalReportTitle = computed(() => {
  if (progressData.failed > 0 && progressData.succeeded === 0) return 'عملیات با خطا مواجه شد';
  if (progressData.failed > 0) return 'عملیات با موفقیت جزئی پایان یافت';
  return 'عملیات گروهی با موفقیت کامل در ووکامرس اعمال شد';
});

const finalReportDescription = computed(() => {
  if (progressData.failed > 0) {
    return `از مجموع ${toPersianDigits(progressData.total)} مورد، تعداد ${toPersianDigits(progressData.succeeded)} مورد با موفقیت ثبت شده و ${toPersianDigits(progressData.failed)} مورد با خطا مواجه شدند. می‌توانید موارد ناموفق را با یک کلیک مجدداً تلاش نمایید.`;
  }
  return `تمام ${toPersianDigits(progressData.total)} کالا و تنوع با موفقیت در ووکامرس به‌روزرسانی شدند. کش‌های سیستم نوسازی شدند.`;
});

// ==================== PRICE VERIFICATION & BACKUP HELPERS ====================
const currentActiveOpPriceBackupUid = computed(() => {
  return currentActiveOp.value?.payload?.price_backup_uid || null;
});

const hasVerificationData = computed(() => {
  return (currentActiveOp.value?.items || []).some(i => i.verification_status);
});

const verificationItemsList = computed(() => {
  return (currentActiveOp.value?.items || []).filter(i => i.verification_status || i.expected_price !== null);
});

const verifiedItemsCount = computed(() => {
  return (currentActiveOp.value?.items || []).filter(i => i.verification_status === 'verified').length;
});

const discrepancyItemsCount = computed(() => {
  return (currentActiveOp.value?.items || []).filter(i => i.verification_status === 'discrepancy').length;
});

const failedVerificationCount = computed(() => {
  return (currentActiveOp.value?.items || []).filter(i => i.verification_status === 'failed' || i.verification_status === 'error').length;
});

const formatVerifiedPrice = (val) => {
  if (val === null || val === undefined || val === '') return '-';
  if (!isNaN(Number(val))) return `${Number(val).toLocaleString('fa-IR')} تومان`;
  return String(val);
};

// ==================== JSON PRICE RESTORE STATE & METHODS ====================
const showPriceRestoreModal = ref(false);
const restoreStep = ref(1);
const restoreFileInput = ref(null);
const restoreFileName = ref('');
const restoreRawJson = ref('');
const restoreLoadingPreview = ref(false);
const restorePreviewData = ref(null);
const restoreSkipDiscrepancies = ref(false);
const restoreConfirmed = ref(false);
const restoreExecuting = ref(false);
const restoreResult = ref(null);

const openPriceRestoreModal = () => {
  showPriceRestoreModal.value = true;
  restoreStep.value = 1;
  restoreFileName.value = '';
  restoreRawJson.value = '';
  restoreLoadingPreview.value = false;
  restorePreviewData.value = null;
  restoreSkipDiscrepancies.value = false;
  restoreConfirmed.value = false;
  restoreExecuting.value = false;
  restoreResult.value = null;
};

const closePriceRestoreModal = () => {
  showPriceRestoreModal.value = false;
};

const handleRestoreFileUpload = (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  restoreFileName.value = file.name;
  const reader = new FileReader();
  reader.onload = (e) => {
    restoreRawJson.value = e.target?.result || '';
  };
  reader.readAsText(file);
};

const runRestorePreview = async () => {
  if (!restoreRawJson.value.trim()) {
    notification.warning('لطفاً ابتدا فایل پشتیبان را انتخاب نمایید یا متن JSON را وارد کنید.');
    return;
  }
  let backupData;
  try {
    backupData = JSON.parse(restoreRawJson.value);
  } catch (e) {
    notification.error('فرمت متن JSON نامعتبر است. لطفاً فایل سالم انتخاب کنید.');
    return;
  }

  restoreLoadingPreview.value = true;
  try {
    const res = await api.post('/bulk-operations/price-backup/preview-restore', { backup_data: backupData });
    if (res?.success !== false) {
      restorePreviewData.value = res.data;
      restoreStep.value = 2;
    } else {
      notification.error(res.error?.message || 'خطا در اعتبارسنجی فایل پشتیبان');
    }
  } catch (err) {
    notification.error(err.message || 'خطا در ارزیابی ووکامرس');
  } finally {
    restoreLoadingPreview.value = false;
  }
};

const executePriceRestoreNow = async () => {
  if (!restoreConfirmed.value) {
    notification.warning('لطفاً ابتدا گزینه تأیید بازگردانی را علامت بزنید.');
    return;
  }
  let backupData;
  try {
    backupData = JSON.parse(restoreRawJson.value);
  } catch (e) {
    return;
  }

  restoreExecuting.value = true;
  try {
    const res = await api.post('/bulk-operations/price-backup/execute-restore', {
      backup_data: backupData,
      skip_discrepancies: restoreSkipDiscrepancies.value,
    });
    if (res?.success !== false) {
      restoreResult.value = res.data;
      restoreStep.value = 3;
      notification.success('بازگردانی قیمت‌ها با موفقیت در ووکامرس اعمال و تأیید شد.');
      fetchOperations();
    } else {
      notification.error(res.error?.message || 'خطا در اجرای بازگردانی قیمت‌ها');
    }
  } catch (err) {
    notification.error(err.message || 'خطا در بازگردانی قیمت‌ها');
  } finally {
    restoreExecuting.value = false;
  }
};

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchCategories(),
    fetchPresets(),
    fetchOperations(),
    evaluateTargetNow(),
  ]);
});
</script>

<style scoped>
@keyframes pulseGlow {
  0%, 100% { opacity: 0.6; }
  50% { opacity: 1; }
}
</style>
