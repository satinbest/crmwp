<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-3">
          <span>مدیریت انبار و موجودی کالا</span>
          <span class="text-xs px-2.5 py-1 rounded-full font-medium bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
            ووکامرس (مرجع اصلی)
          </span>
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          پایش بلادرنگ موجودی، هشدارهای کسری کالا، مدیریت پیش‌خرید و اعمال تغییرات انبارداری
        </p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Store Selector if multiple stores exist -->
        <select
          v-if="storeContext.stores.length > 1"
          v-model="selectedStoreId"
          @change="onStoreChange"
          class="text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-700 dark:text-slate-200 font-medium shadow-xs focus:ring-2 focus:ring-indigo-500"
        >
          <option v-for="st in storeContext.stores" :key="st.id" :value="st.id">
            {{ st.name }}
          </option>
        </select>

        <RefreshButton
          @click="refreshData"
          :loading="loading"
          label="بروزرسانی انبار"
          title="همگام‌سازی و بازخوانی آخرین موجودی از ووکامرس"
        />

        <router-link
          to="/products"
          class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-200 text-xs font-bold flex items-center gap-2 transition-colors"
        >
          <Iconsax name="products" size="16" />
          <span>فهرست کامل کالاها</span>
        </router-link>
      </div>
    </div>

    <!-- Dashboard KPI Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <!-- In Stock Card -->
      <div
        @click="filterByStatus('instock')"
        class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-emerald-500 dark:hover:border-emerald-500 transition-all cursor-pointer shadow-xs group"
        :class="{ 'ring-2 ring-emerald-500': filters.stock_status === 'instock' }"
      >
        <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
          <span class="text-xs font-semibold">موجود در انبار</span>
          <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 flex items-center justify-center">
            <Iconsax name="box" size="18" />
          </div>
        </div>
        <div class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-2">
          {{ formatNumber(metrics.instock) }}
        </div>
        <span class="text-[11px] text-slate-400 group-hover:text-emerald-600 transition-colors">مشاهده کالاها ←</span>
      </div>

      <!-- Low Stock Card -->
      <div
        @click="filterByStatus('low_stock')"
        class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-amber-500 dark:hover:border-amber-500 transition-all cursor-pointer shadow-xs group"
        :class="{ 'ring-2 ring-amber-500': filters.stock_status === 'low_stock' }"
      >
        <div class="flex items-center justify-between text-amber-600 dark:text-amber-400">
          <span class="text-xs font-semibold">کمبود موجودی</span>
          <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 flex items-center justify-center">
            <Iconsax name="warning" size="18" />
          </div>
        </div>
        <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-2">
          {{ formatNumber(metrics.low_stock) }}
        </div>
        <span class="text-[11px] text-slate-400 group-hover:text-amber-600 transition-colors">مشاهده هشدارها ←</span>
      </div>

      <!-- Out of Stock Card -->
      <div
        @click="filterByStatus('outofstock')"
        class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-rose-500 dark:hover:border-rose-500 transition-all cursor-pointer shadow-xs group"
        :class="{ 'ring-2 ring-rose-500': filters.stock_status === 'outofstock' }"
      >
        <div class="flex items-center justify-between text-rose-600 dark:text-rose-400">
          <span class="text-xs font-semibold">ناموجود در انبار</span>
          <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/60 flex items-center justify-center">
            <Iconsax name="close" size="18" />
          </div>
        </div>
        <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-2">
          {{ formatNumber(metrics.outofstock) }}
        </div>
        <span class="text-[11px] text-slate-400 group-hover:text-rose-600 transition-colors">مشاهده کسری‌ها ←</span>
      </div>

      <!-- Backorder Card -->
      <div
        @click="filterByStatus('onbackorder')"
        class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-purple-500 dark:hover:border-purple-500 transition-all cursor-pointer shadow-xs group"
        :class="{ 'ring-2 ring-purple-500': filters.stock_status === 'onbackorder' }"
      >
        <div class="flex items-center justify-between text-purple-600 dark:text-purple-400">
          <span class="text-xs font-semibold">در پیش‌خرید</span>
          <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/60 flex items-center justify-center">
            <Iconsax name="archive" size="18" />
          </div>
        </div>
        <div class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-2">
          {{ formatNumber(metrics.onbackorder) }}
        </div>
        <span class="text-[11px] text-slate-400 group-hover:text-purple-600 transition-colors">مشاهده کالاها ←</span>
      </div>

      <!-- Managing Stock Card -->
      <div
        @click="filterByManageStock('yes')"
        class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-indigo-500 dark:hover:border-indigo-500 transition-all cursor-pointer shadow-xs group"
        :class="{ 'ring-2 ring-indigo-500': filters.manage_stock === 'yes' }"
      >
        <div class="flex items-center justify-between text-indigo-600 dark:text-indigo-400">
          <span class="text-xs font-semibold">مدیریت عددی فعال</span>
          <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center">
            <Iconsax name="settings" size="18" />
          </div>
        </div>
        <div class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-2">
          {{ formatNumber(metrics.managing_stock) }}
        </div>
        <span class="text-[11px] text-slate-400 group-hover:text-indigo-600 transition-colors">فیلتر شده‌ها ←</span>
      </div>

      <!-- Total Catalog Items -->
      <div
        @click="resetFilters"
        class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-slate-400 transition-all cursor-pointer shadow-xs group"
      >
        <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
          <span class="text-xs font-semibold">کل کالاهای کاتالوگ</span>
          <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
            <Iconsax name="products" size="18" />
          </div>
        </div>
        <div class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-2">
          {{ formatNumber(metrics.total_products) }}
        </div>
        <span class="text-[11px] text-slate-400 group-hover:text-slate-600 transition-colors">مشاهده همه ←</span>
      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-xs space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Search Input -->
        <div class="lg:col-span-2 relative">
          <Iconsax name="search" size="18" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
          <input
            v-model="filters.search"
            @keyup.enter="handleSearch"
            type="text"
            placeholder="جستجوی نام کالا، کد انبار (SKU) یا شناسه..."
            class="w-full pl-3 pr-10 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
          />
        </div>

        <!-- Stock Status Filter -->
        <div>
          <select
            v-model="filters.stock_status"
            @change="handleFilterChange"
            class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="all">همه وضعیت‌های انبار</option>
            <option value="instock">موجود در انبار</option>
            <option value="low_stock">⚠️ کمبود موجودی (آستانه هشدار)</option>
            <option value="outofstock">ناموجود در انبار</option>
            <option value="onbackorder">در وضعیت پیش‌خرید</option>
          </select>
        </div>

        <!-- Manage Stock Filter -->
        <div>
          <select
            v-model="filters.manage_stock"
            @change="handleFilterChange"
            class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="all">مدیریت موجودی (همه)</option>
            <option value="yes">ردیابی عددی فعال</option>
            <option value="no">ردیابی عددی غیرفعال</option>
          </select>
        </div>

        <!-- Product Type Filter -->
        <div>
          <select
            v-model="filters.type"
            @change="handleFilterChange"
            class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="all">همه انواع کالا</option>
            <option value="simple">کالای ساده</option>
            <option value="variable">کالای متغیر (چند تنوع)</option>
          </select>
        </div>

        <!-- Quantity Comparison Filter -->
        <div class="flex items-center gap-1.5">
          <select
            v-model="filters.stock_op"
            @change="handleFilterChange"
            class="w-24 py-2 px-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="">موجودی</option>
            <option value="gt">بزرگتر از</option>
            <option value="lt">کمتر از</option>
            <option value="eq">برابر با</option>
            <option value="lte">حداکثر</option>
          </select>
          <input
            v-model="filters.stock_val"
            @keyup.enter="handleFilterChange"
            type="number"
            placeholder="مقدار"
            class="w-full py-2 px-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
          />
        </div>
      </div>

      <!-- Secondary Toolbar: Active Filters & Bulk Selection Indicator -->
      <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
        <div class="flex items-center gap-3">
          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-medium"
          >
            <Iconsax name="close" size="14" />
            <span>حذف فیلترها</span>
          </button>

          <!-- Bulk button on all filtered items when none selected -->
          <button
            v-if="selectedIds.length === 0 && meta.total > 0"
            @click="openBulkForFiltered('set_stock')"
            class="text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 px-2.5 py-1 rounded-lg border border-indigo-200/60 dark:border-indigo-800/60 flex items-center gap-1.5 font-semibold transition-colors"
          >
            <Iconsax name="bulk" size="14" />
            <span>عملیات گروهی انبار روی کل نتایج ({{ formatNumber(meta.total) }} کالا)</span>
          </button>
        </div>

        <div class="text-xs text-slate-400">
          <span>نمایش {{ formatNumber(items.length) }} کالا از مجموع {{ formatNumber(meta.total) }}</span>
        </div>
      </div>

      <!-- Bulk Selection Toolbar (When Records Selected) -->
      <div
        v-if="selectedIds.length > 0"
        class="bg-indigo-50/90 dark:bg-indigo-950/60 border border-indigo-200/80 dark:border-indigo-800/80 rounded-2xl p-3 flex flex-wrap items-center justify-between gap-3 animate-fadeIn"
      >
        <div class="flex items-center flex-wrap gap-2 text-xs">
          <span class="font-bold text-indigo-900 dark:text-indigo-200">
            {{ formatNumber(selectedIds.length) }} کالا انتخاب شده است
          </span>
          <button
            v-if="meta.total > selectedIds.length"
            @click="openBulkForFiltered('set_stock')"
            class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold"
          >
            (انتخاب تمام {{ formatNumber(meta.total) }} کالا مطابق با فیلترها)
          </button>
          <span class="text-indigo-300 dark:text-indigo-700">•</span>
          <button
            @click="clearSelection"
            class="text-rose-600 dark:text-rose-400 hover:underline"
          >
            لغو انتخاب
          </button>
        </div>

        <div class="flex items-center flex-wrap gap-1.5">
          <button
            @click="openBulk('set_stock')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="box" size="14" />
            <span>تنظیم موجودی</span>
          </button>

          <button
            @click="openBulk('increase_stock')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-emerald-50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <span>+ افزایش موجودی</span>
          </button>

          <button
            @click="openBulk('decrease_stock')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-rose-50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <span>- کاهش موجودی</span>
          </button>

          <button
            @click="openBulk('set_stock_status')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="settings" size="14" />
            <span>تغییر وضعیت انبار</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Inventory Table Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
      <!-- Loading Skeleton -->
      <div v-if="loading" class="p-8 space-y-4">
        <div v-for="i in 5" :key="i" class="flex items-center gap-4 animate-pulse">
          <div class="w-12 h-12 bg-slate-200 dark:bg-slate-800 rounded-xl shrink-0"></div>
          <div class="flex-1 space-y-2">
            <div class="w-1/3 h-3.5 bg-slate-200 dark:bg-slate-800 rounded"></div>
            <div class="w-1/4 h-2.5 bg-slate-100 dark:bg-slate-850 rounded"></div>
          </div>
          <div class="w-24 h-4 bg-slate-200 dark:bg-slate-800 rounded"></div>
          <div class="w-16 h-6 bg-slate-200 dark:bg-slate-800 rounded-full"></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="items.length === 0" class="p-12 text-center space-y-3">
        <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto">
          <Iconsax name="box" size="32" />
        </div>
        <h3 class="font-bold text-base text-slate-800 dark:text-slate-100">موردی یافت نشد</h3>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          با فیلترهای انتخابی شما هیچ رکوردی در انبارداری فروشگاه یافت نشد.
        </p>
        <button
          v-if="hasActiveFilters"
          @click="resetFilters"
          class="mt-2 text-xs py-2 px-4 rounded-xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-medium hover:bg-indigo-100 transition-colors"
        >
          پاک کردن فیلترها
        </button>
      </div>

      <!-- Table View -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-right text-xs">
          <thead class="bg-slate-50 dark:bg-slate-850/60 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400">
            <tr>
              <th class="p-3.5 w-10 text-center">
                <input
                  type="checkbox"
                  :checked="isAllVisibleSelected"
                  @change="toggleSelectAll"
                  class="rounded text-indigo-600 focus:ring-0"
                />
              </th>
              <th class="p-3.5 w-14">تصویر</th>
              <th class="p-3.5">نام کالا و شناسه</th>
              <th class="p-3.5">SKU انبار</th>
              <th class="p-3.5">نوع کالا</th>
              <th class="p-3.5 text-center">موجودی انبار</th>
              <th class="p-3.5">وضعیت موجودی</th>
              <th class="p-3.5 text-center">آستانه هشدار</th>
              <th class="p-3.5">سیاست پیش‌خرید</th>
              <th class="p-3.5 text-center">مدیریت عددی</th>
              <th class="p-3.5 text-center">عملیات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
            <tr
              v-for="item in items"
              :key="item.product_id + '-' + (item.variation_id || 0)"
              class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors group cursor-pointer"
              :class="{ 'bg-indigo-50/30 dark:bg-indigo-950/20': selectedIds.includes(item.product_id) }"
              @click="openDrawer(item)"
            >
              <!-- Checkbox -->
              <td class="p-3.5 text-center" @click.stop>
                <input
                  type="checkbox"
                  :value="item.product_id"
                  v-model="selectedIds"
                  class="rounded text-indigo-600 focus:ring-0"
                />
              </td>

              <!-- Image -->
              <td class="p-3.5">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
                  <img
                    v-if="item.image"
                    :src="item.image"
                    :alt="item.product_name"
                    class="w-full h-full object-cover"
                  />
                  <Iconsax v-else name="box" size="18" class="text-slate-400" />
                </div>
              </td>

              <!-- Name & SKU -->
              <td class="p-3.5 max-w-xs">
                <div class="font-bold text-slate-800 dark:text-slate-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" :title="item.display_name">
                  {{ item.display_name }}
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1.5">
                  <span>#{{ toPersianDigits(item.product_id) }}</span>
                  <span v-if="item.is_low_stock" class="text-amber-500 font-bold" title="کمبود موجودی">⚠️ کمبود انبار</span>
                </div>
              </td>

              <!-- SKU -->
              <td class="p-3.5 text-slate-500 dark:text-slate-400 dir-ltr text-right">
                {{ item.sku || '—' }}
              </td>

              <!-- Type -->
              <td class="p-3.5">
                <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-semibold" :class="item.has_variations ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'">
                  {{ item.has_variations ? 'متغیر (' + formatNumber(item.variations_count) + ' تنوع)' : 'ساده' }}
                </span>
              </td>

              <!-- Stock Quantity -->
              <td class="p-3.5 text-center">
                <div v-if="item.manage_stock">
                  <span
                    class="font-black text-sm px-2.5 py-1 rounded-xl"
                    :class="getStockQuantityClass(item)"
                  >
                    {{ formatNumber(item.stock_quantity ?? 0) }}
                  </span>
                </div>
                <div v-else class="text-slate-400 text-[11px]">
                  نامحدود
                </div>
              </td>

              <!-- Stock Status -->
              <td class="p-3.5">
                <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border" :class="getStockStatusBadgeClass(item.stock_status)">
                  {{ item.stock_status_label }}
                </span>
              </td>

              <!-- Low Stock Threshold -->
              <td class="p-3.5 text-center text-slate-500 dark:text-slate-400">
                {{ item.low_stock_amount !== null ? formatNumber(item.low_stock_amount) : '—' }}
              </td>

              <!-- Backorders -->
              <td class="p-3.5 text-slate-600 dark:text-slate-300">
                {{ item.backorders_label }}
              </td>

              <!-- Manage Stock -->
              <td class="p-3.5 text-center">
                <span
                  class="inline-block w-2.5 h-2.5 rounded-full"
                  :class="item.manage_stock ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-650'"
                  :title="item.manage_stock ? 'مدیریت موجودی فعال' : 'مدیریت موجودی غیرفعال'"
                ></span>
              </td>

              <!-- Actions -->
              <td class="p-3.5 text-center" @click.stop>
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    @click="openDrawer(item)"
                    class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 transition-colors"
                    title="مدیریت و ویرایش موجودی"
                  >
                    <Iconsax name="edit" size="14" />
                  </button>
                  <router-link
                    :to="'/products/' + item.product_id"
                    class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 transition-colors"
                    title="مشاهده صفحه محصول"
                  >
                    <Iconsax name="eye" size="14" />
                  </router-link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <div class="text-slate-400">
          صفحه {{ formatNumber(meta.page) }} از {{ formatNumber(meta.total_pages) }} (مجموع {{ formatNumber(meta.total) }} کالا)
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="changePage(meta.page - 1)"
            :disabled="meta.page <= 1 || loading"
            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            قبلی
          </button>
          <span class="px-2 font-bold">{{ formatNumber(meta.page) }}</span>
          <button
            @click="changePage(meta.page + 1)"
            :disabled="meta.page >= meta.total_pages || loading"
            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            بعدی
          </button>
        </div>
      </div>
    </div>

    <!-- Inventory Drawer Component -->
    <InventoryDrawer
      :is-open="isDrawerOpen"
      :item="selectedDrawerItem"
      @close="isDrawerOpen = false"
      @updated="onItemUpdated"
    />

    <!-- Bulk Operations Dialog Component -->
    <BulkOperationDialog
      :is-open="showBulkDialog"
      entity="products"
      :selected-ids="selectedIds"
      :filter="activeFilterObject"
      :selection-mode="bulkSelectionMode"
      :initial-action-type="initialBulkAction"
      @close="showBulkDialog = false"
      @completed="onBulkCompleted"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Iconsax from '@/components/icons/Iconsax.vue';
import InventoryDrawer from '@/components/inventory/InventoryDrawer.vue';
import BulkOperationDialog from '@/components/bulk/BulkOperationDialog.vue';
import api from '@/api/client';
import { useStoreContext } from '@/stores/storeContext';
import { useNotificationStore } from '@/stores/notification';
import { formatNumber, toPersianDigits } from '@/utils/formatters';

const route = useRoute();
const storeContext = useStoreContext();
const notification = useNotificationStore();

const loading = ref(false);
const items = ref([]);
const selectedIds = ref([]);
const selectedStoreId = ref(null);

const isDrawerOpen = ref(false);
const selectedDrawerItem = ref(null);

const showBulkDialog = ref(false);
const bulkSelectionMode = ref('ids');
const initialBulkAction = ref('set_stock');

const metrics = reactive({
  total_products: 0,
  instock: 0,
  outofstock: 0,
  onbackorder: 0,
  low_stock: 0,
  managing_stock: 0,
  not_managing_stock: 0,
});

const filters = reactive({
  search: '',
  stock_status: 'all',
  manage_stock: 'all',
  type: 'all',
  stock_op: '',
  stock_val: '',
});

const meta = reactive({
  page: 1,
  per_page: 20,
  total: 0,
  total_pages: 1,
});

const hasActiveFilters = computed(() => {
  return (
    filters.search.trim() !== '' ||
    filters.stock_status !== 'all' ||
    filters.manage_stock !== 'all' ||
    filters.type !== 'all' ||
    filters.stock_op !== '' ||
    filters.stock_val !== ''
  );
});

const activeFilterObject = computed(() => {
  const f = {};
  if (filters.search.trim()) f.search = filters.search.trim();
  if (filters.stock_status !== 'all') f.stock_status = filters.stock_status;
  if (filters.type !== 'all') f.type = filters.type;
  return f;
});

const isAllVisibleSelected = computed(() => {
  if (items.value.length === 0) return false;
  return items.value.every((p) => selectedIds.value.includes(p.product_id));
});

const toggleSelectAll = () => {
  if (isAllVisibleSelected.value) {
    selectedIds.value = [];
  } else {
    selectedIds.value = items.value.map((p) => p.product_id);
  }
};

const clearSelection = () => {
  selectedIds.value = [];
};


const getStockStatusBadgeClass = (status) => {
  switch (status) {
    case 'instock':
      return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800';
    case 'outofstock':
      return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border-rose-200 dark:border-rose-800';
    case 'onbackorder':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200 dark:border-amber-800';
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700';
  }
};

const getStockQuantityClass = (item) => {
  const qty = item.stock_quantity ?? 0;
  if (qty <= 0) {
    return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200';
  }
  if (item.is_low_stock) {
    return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200';
  }
  return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200';
};

const fetchMetrics = async () => {
  try {
    const res = await api.get('/inventory/metrics');
    if (res.data) {
      Object.assign(metrics, res.data);
    }
  } catch (err) {
    console.error('Error fetching inventory metrics:', err);
  }
};

const fetchInventory = async () => {
  loading.value = true;
  try {
    const params = {
      page: meta.page,
      per_page: meta.per_page,
    };

    if (filters.search.trim()) params.search = filters.search.trim();
    if (filters.stock_status !== 'all') params.stock_status = filters.stock_status;
    if (filters.manage_stock !== 'all') params.manage_stock = filters.manage_stock;
    if (filters.type !== 'all') params.type = filters.type;
    if (filters.stock_op && filters.stock_val !== '') {
      params.stock_op = filters.stock_op;
      params.stock_val = filters.stock_val;
    }

    const res = await api.get('/inventory', { params });
    items.value = res.data || [];
    if (res.meta) {
      meta.total = res.meta.total || 0;
      meta.total_pages = res.meta.total_pages || 1;
    }
  } catch (err) {
    notification.error(err.message || 'خطا در بارگذاری موجودی کالاها از ووکامرس');
  } finally {
    loading.value = false;
  }
};

const refreshData = async () => {
  await Promise.all([fetchMetrics(), fetchInventory()]);
  notification.success('اطلاعات انبار با موفقیت از ووکامرس بروزرسانی شد.');
};

const handleSearch = () => {
  meta.page = 1;
  fetchInventory();
};

const handleFilterChange = () => {
  meta.page = 1;
  fetchInventory();
};

const filterByStatus = (status) => {
  filters.stock_status = (filters.stock_status === status) ? 'all' : status;
  handleFilterChange();
};

const filterByManageStock = (val) => {
  filters.manage_stock = (filters.manage_stock === val) ? 'all' : val;
  handleFilterChange();
};

const resetFilters = () => {
  filters.search = '';
  filters.stock_status = 'all';
  filters.manage_stock = 'all';
  filters.type = 'all';
  filters.stock_op = '';
  filters.stock_val = '';
  meta.page = 1;
  fetchInventory();
};

const changePage = (p) => {
  if (p < 1 || p > meta.total_pages) return;
  meta.page = p;
  fetchInventory();
};

const openDrawer = (item) => {
  selectedDrawerItem.value = item;
  isDrawerOpen.value = true;
};

const onItemUpdated = (updated) => {
  const idx = items.value.findIndex(
    (i) => i.product_id === updated.product_id && (i.variation_id || null) === (updated.variation_id || null)
  );
  if (idx !== -1) {
    items.value[idx] = updated;
  }
  selectedDrawerItem.value = updated;
  fetchMetrics();
};

const openBulk = (actionType = 'set_stock') => {
  bulkSelectionMode.value = 'ids';
  initialBulkAction.value = actionType;
  showBulkDialog.value = true;
};

const openBulkForFiltered = (actionType = 'set_stock') => {
  bulkSelectionMode.value = 'filter';
  initialBulkAction.value = actionType;
  showBulkDialog.value = true;
};

const onBulkCompleted = () => {
  clearSelection();
  refreshData();
};

const onStoreChange = () => {
  const store = storeContext.stores.find((s) => s.id == selectedStoreId.value);
  if (store) {
    storeContext.setActiveStore(store);
    meta.page = 1;
    clearSelection();
    refreshData();
  }
};

onMounted(async () => {
  if (!storeContext.activeStore) {
    await storeContext.fetchStores();
  }
  if (storeContext.activeStore) {
    selectedStoreId.value = storeContext.activeStore.id;
  }

  // Read route query parameters if navigated from shortcut links
  if (route.query.stock_status) {
    filters.stock_status = route.query.stock_status;
  }

  fetchMetrics();
  fetchInventory();
});
</script>
