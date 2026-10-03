<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-3">
          <span>مدیریت محصولات</span>
          <span class="text-xs px-2.5 py-1 rounded-full font-medium bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
            ووکامرس
          </span>
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          مشاهده، جستجو، ویرایش قیمت و موجودی، و تنوع‌های کالایی
        </p>
      </div>

      <div class="flex items-center gap-3">
        <RefreshButton
          @click="fetchProducts"
          :loading="loading"
          label="به‌روزرسانی"
          title="به‌روزرسانی کاتالوگ محصولات"
        />

        <router-link
          to="/products/create"
          class="py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/20 transition-all hover:scale-[1.02]"
        >
          <Iconsax name="add" size="18" />
          <span>افزودن محصول جدید</span>
        </router-link>
      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Search Input -->
        <div class="lg:col-span-2 relative">
          <Iconsax name="search" size="18" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
          <input
            v-model="filters.search"
            @keyup.enter="handleSearch"
            type="text"
            placeholder="جستجو بر اساس نام، SKU یا شناسه..."
            class="w-full pl-3 pr-10 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all"
          />
        </div>

        <!-- Type Filter -->
        <div>
          <select
            v-model="filters.type"
            @change="handleFilterChange"
            class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="all">همه انواع محصول</option>
            <option value="simple">محصول ساده</option>
            <option value="variable">محصول متغیر</option>
            <option value="external">محصول خارجی / معرف</option>
            <option value="grouped">محصول گروهی</option>
          </select>
        </div>

        <!-- Status Filter -->
        <div>
          <select
            v-model="filters.status"
            @change="handleFilterChange"
            class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="all">همه وضعیت‌ها</option>
            <option value="publish">منتشر شده</option>
            <option value="draft">پیش‌نویس</option>
            <option value="pending">در انتظار بررسی</option>
            <option value="private">خصوصی</option>
          </select>
        </div>

        <!-- Stock Status Filter -->
        <div>
          <select
            v-model="filters.stock_status"
            @change="handleFilterChange"
            class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="all">وضعیت انبار (همه)</option>
            <option value="instock">موجود در انبار</option>
            <option value="outofstock">ناموجود</option>
            <option value="onbackorder">در پیش‌خرید</option>
          </select>
        </div>

        <!-- Category Filter -->
        <div>
          <select
            v-model="filters.category"
            @change="handleFilterChange"
            class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 truncate"
          >
            <option value="all">همه دسته‌بندی‌ها</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>
        </div>
      </div>

      <!-- Secondary Toolbar: Active Filters, Column Visibility & Bulk Selection -->
      <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
        <div class="flex items-center gap-3">
          <!-- Reset button -->
          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-medium"
          >
            <Iconsax name="close" size="14" />
            <span>حذف فیلترها</span>
          </button>

          <!-- Bulk on Filtered Items Button when none selected -->
          <button
            v-if="selectedIds.length === 0 && meta.total > 0"
            @click="openBulkForFiltered('')"
            class="text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 px-2.5 py-1 rounded-lg border border-indigo-200/60 dark:border-indigo-800/60 flex items-center gap-1.5 font-semibold transition-colors"
          >
            <Iconsax name="bulk" size="14" />
            <span>عملیات گروهی روی نتایج ({{ meta.total }} محصول)</span>
          </button>
        </div>

        <!-- Column Visibility Dropdown -->
        <div class="relative">
          <button
            @click="isColumnMenuOpen = !isColumnMenuOpen"
            class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-1.5 text-xs transition-colors"
          >
            <Iconsax name="settings" size="14" />
            <span>ستون‌ها</span>
          </button>

          <div
            v-if="isColumnMenuOpen"
            class="absolute left-0 mt-2 w-48 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl p-2 z-30 space-y-1"
          >
            <label v-for="(val, colKey) in visibleColumns" :key="colKey" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-lg cursor-pointer">
              <input type="checkbox" v-model="visibleColumns[colKey]" class="rounded text-indigo-600 focus:ring-0" />
              <span class="text-xs text-slate-700 dark:text-slate-300">{{ getColumnLabel(colKey) }}</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Bulk Selection Toolbar (When Records Selected) -->
      <div
        v-if="selectedIds.length > 0"
        class="bg-indigo-50/90 dark:bg-indigo-950/60 border border-indigo-200/80 dark:border-indigo-800/80 rounded-2xl p-3 flex flex-wrap items-center justify-between gap-3 animate-fadeIn"
      >
        <div class="flex items-center flex-wrap gap-2 text-xs">
          <span class="font-bold text-indigo-900 dark:text-indigo-200">
            {{ formatNumber(selectedIds.length) }} محصول انتخاب شده
          </span>
          <button
            v-if="meta.total > selectedIds.length"
            @click="openBulkForFiltered('')"
            class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold"
          >
            (انتخاب تمام {{ formatNumber(meta.total) }} محصول مطابق با فیلترها)
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
            @click="openBulk('increase_price_percent')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="percent" size="14" />
            <span>تغییر قیمت</span>
          </button>
          <button
            @click="openBulk('set_stock')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="box" size="14" />
            <span>موجودی انبار</span>
          </button>
          <button
            @click="openBulk('add_category')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="tag" size="14" />
            <span>دسته‌بندی و برچسب</span>
          </button>
          <button
            @click="openBulk('set_status')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="settings" size="14" />
            <span>تغییر وضعیت</span>
          </button>
          <button
            @click="openBulk('')"
            class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-xs"
          >
            <span>عملیات گروهی پیشرفته...</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Products Table Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
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
      <div v-else-if="products.length === 0" class="p-12 text-center space-y-3">
        <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto">
          <Iconsax name="products" size="32" />
        </div>
        <h3 class="font-bold text-base text-slate-800 dark:text-slate-100">محصولی یافت نشد</h3>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          با معیارهای جستجو و فیلترهای انتخابی شما هیچ محصولی در فروشگاه یافت نشد.
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
              <th class="p-3.5">نام محصول و شناسه</th>
              <th v-if="visibleColumns.sku" class="p-3.5">SKU</th>
              <th class="p-3.5">نوع محصول</th>
              <th class="p-3.5">قیمت</th>
              <th class="p-3.5">موجودی انبار</th>
              <th v-if="visibleColumns.categories" class="p-3.5">دسته‌بندی‌ها</th>
              <th class="p-3.5">وضعیت</th>
              <th v-if="visibleColumns.date_modified" class="p-3.5">بروزرسانی</th>
              <th class="p-3.5 text-center">عملیات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
            <tr
              v-for="prod in products"
              :key="prod.id"
              class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors group cursor-pointer"
              :class="{ 'bg-indigo-50/30 dark:bg-indigo-950/20': selectedIds.includes(prod.id) }"
              @click="openDrawer(prod.id)"
            >
              <!-- Checkbox -->
              <td class="p-3.5 text-center" @click.stop>
                <input
                  type="checkbox"
                  :value="prod.id"
                  v-model="selectedIds"
                  class="rounded text-indigo-600 focus:ring-0"
                />
              </td>

              <!-- Image Thumbnail -->
              <td class="p-3.5">
                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
                  <img
                    v-if="prod.primary_image"
                    :src="prod.primary_image"
                    :alt="prod.name"
                    class="w-full h-full object-cover"
                  />
                  <Iconsax v-else name="image" size="18" class="text-slate-400" />
                </div>
              </td>

              <!-- Name & ID -->
              <td class="p-3.5 max-w-xs">
                <div class="font-bold text-slate-800 dark:text-slate-100 truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors" :title="prod.name">
                  {{ prod.name }}
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1.5">
                  <span>#{{ toPersianDigits(prod.id) }}</span>
                  <span v-if="prod.featured" class="text-amber-500 font-bold" title="محصول ویژه">★</span>
                </div>
              </td>

              <!-- SKU -->
              <td v-if="visibleColumns.sku" class="p-3.5 text-slate-500 dark:text-slate-400 dir-ltr text-right">
                {{ prod.sku || '—' }}
              </td>

              <!-- Type -->
              <td class="p-3.5">
                <div class="flex flex-col gap-1 items-start">
                  <span class="px-2 py-0.5 rounded-full text-[11px] font-medium" :class="getTypeBadgeClass(prod.type)">
                    {{ prod.type_label }}
                  </span>
                  <span v-if="prod.type === 'variable' && (prod.variations_count || prod.variations?.length)" class="text-[10px] text-purple-600 dark:text-purple-400 font-medium">
                    {{ toPersianDigits(prod.variations_count || prod.variations.length) }} متغیر
                  </span>
                </div>
              </td>

              <!-- Pricing -->
              <td class="p-3.5 whitespace-nowrap">
                <div class="font-bold text-slate-800 dark:text-slate-100">
                  {{ formatPrice(prod.price) }}
                </div>
                <div v-if="prod.on_sale && prod.regular_price > prod.price" class="text-[11px] line-through text-slate-400">
                  {{ formatPrice(prod.regular_price) }}
                </div>
              </td>

              <!-- Stock Status -->
              <td class="p-3.5 whitespace-nowrap">
                <div class="flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full" :class="getStockIndicatorClass(prod.stock_status)"></span>
                  <span class="font-medium text-slate-700 dark:text-slate-300">{{ prod.stock_status_label }}</span>
                </div>
                <div v-if="prod.manage_stock" class="text-[10px] text-slate-400 mt-0.5">
                  {{ formatNumber(prod.stock_quantity ?? 0) }} عدد در انبار
                </div>
              </td>

              <!-- Categories -->
              <td v-if="visibleColumns.categories" class="p-3.5 max-w-[180px] truncate">
                <span v-if="prod.categories?.length" class="text-slate-600 dark:text-slate-300">
                  {{ prod.categories.map(c => c.name).join('، ') }}
                </span>
                <span v-else class="text-slate-400 italic">—</span>
              </td>

              <!-- Status Badge -->
              <td class="p-3.5 whitespace-nowrap">
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium" :class="getStatusBadgeClass(prod.status)">
                  {{ prod.status_label }}
                </span>
              </td>

              <!-- Date Modified -->
              <td v-if="visibleColumns.date_modified" class="p-3.5 whitespace-nowrap text-slate-500 dark:text-slate-400 text-[11px]">
                {{ formatDate(prod.date_modified) }}
              </td>

              <!-- Row Action Buttons -->
              <td class="p-3.5 text-center whitespace-nowrap" @click.stop>
                <div class="flex items-center justify-center gap-1">
                  <!-- Quick Drawer Trigger -->
                  <button
                    @click="openDrawer(prod.id)"
                    title="بررسی سریع"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                  >
                    <Iconsax name="eye" size="16" />
                  </button>

                  <!-- Full Edit Page -->
                  <router-link
                    :to="`/products/${prod.id}`"
                    title="صفحه کامل کالا"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                  >
                    <Iconsax name="edit" size="16" />
                  </router-link>

                  <!-- Delete Trigger -->
                  <button
                    @click="promptDelete(prod)"
                    title="حذف کالا"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                  >
                    <Iconsax name="trash" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div v-if="meta.total > 0" class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
        <div class="text-slate-500 dark:text-slate-400">
          نمایش
          <span class="font-bold text-slate-700 dark:text-slate-200">{{ formatNumber(((meta.page - 1) * meta.per_page) + 1) }}</span>
          تا
          <span class="font-bold text-slate-700 dark:text-slate-200">{{ formatNumber(Math.min(meta.page * meta.per_page, meta.total)) }}</span>
          از مجموع
          <span class="font-bold text-slate-700 dark:text-slate-200">{{ formatNumber(meta.total) }}</span>
          محصول
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="changePage(meta.page - 1)"
            :disabled="meta.page <= 1 || loading"
            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 disabled:opacity-40 transition-colors"
          >
            قبلی
          </button>

          <span class="px-3 py-1.5 font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
            صفحه {{ formatNumber(meta.page) }} از {{ formatNumber(meta.total_pages) }}
          </span>

          <button
            @click="changePage(meta.page + 1)"
            :disabled="meta.page >= meta.total_pages || loading"
            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 disabled:opacity-40 transition-colors"
          >
            بعدی
          </button>
        </div>
      </div>
    </div>

    <!-- Product Drawer Component -->
    <ProductDrawer
      :product-id="drawerProductId"
      :is-open="isDrawerOpen"
      @close="isDrawerOpen = false"
      @product-updated="handleProductUpdated"
    />

    <!-- Delete Confirmation Modal -->
    <div
      v-if="productToDelete"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto">
          <Iconsax name="trash" size="24" />
        </div>

        <div class="text-center space-y-2">
          <h3 class="font-bold text-base text-slate-800 dark:text-slate-100">تأیید حذف محصول</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            آیا از حذف محصول «<strong class="text-slate-700 dark:text-slate-200">{{ productToDelete.name }}</strong>» با شناسه #{{ toPersianDigits(productToDelete.id) }} اطمینان دارید؟
          </p>
        </div>

        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl text-xs space-y-2">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="deletePermanently" class="rounded text-rose-600 focus:ring-0" />
            <span class="text-slate-700 dark:text-slate-300 font-medium">حذف دائمی (بدون انتقال به زباله‌دان)</span>
          </label>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button
            @click="executeDelete"
            :disabled="deleting"
            class="flex-1 py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-colors disabled:opacity-50"
          >
            {{ deleting ? 'در حال حذف...' : 'تأیید و حذف محصول' }}
          </button>

          <button
            @click="productToDelete = null"
            :disabled="deleting"
            class="py-2.5 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            انصراف
          </button>
        </div>
      </div>
    </div>

    <!-- Bulk Operations Dialog Component -->
    <BulkOperationDialog
      :is-open="showBulkDialog"
      entity="products"
      :selected-ids="selectedIds"
      :filter="activeFilterObject"
      :selection-mode="bulkSelectionMode"
      :initial-action-type="initialBulkAction"
      :categories-list="categories"
      @close="showBulkDialog = false"
      @completed="onBulkCompleted"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import ProductDrawer from '@/components/products/ProductDrawer.vue';
import BulkOperationDialog from '@/components/bulk/BulkOperationDialog.vue';
import api from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import { formatNumber, formatPrice, formatDate, toPersianDigits } from '@/utils/formatters';

const notification = useNotificationStore();

const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const isColumnMenuOpen = ref(false);

const showBulkDialog = ref(false);
const bulkSelectionMode = ref('ids');
const initialBulkAction = ref('');

const selectedIds = ref([]);
const drawerProductId = ref(null);
const isDrawerOpen = ref(false);

const productToDelete = ref(null);
const deletePermanently = ref(false);
const deleting = ref(false);

const filters = reactive({
  search: '',
  type: 'all',
  status: 'all',
  stock_status: 'all',
  category: 'all',
});

const meta = reactive({
  page: 1,
  per_page: 15,
  total: 0,
  total_pages: 1,
});

const visibleColumns = reactive({
  sku: true,
  categories: true,
  date_modified: true,
});

const getColumnLabel = (key) => {
  const map = {
    sku: 'کد کالا (SKU)',
    categories: 'دسته‌بندی‌ها',
    date_modified: 'تاریخ ویرایش',
  };
  return map[key] || key;
};

const hasActiveFilters = computed(() => {
  return (
    filters.search.trim() !== '' ||
    filters.type !== 'all' ||
    filters.status !== 'all' ||
    filters.stock_status !== 'all' ||
    filters.category !== 'all'
  );
});

const isAllVisibleSelected = computed(() => {
  if (products.value.length === 0) return false;
  return products.value.every((p) => selectedIds.value.includes(p.id));
});

const toggleSelectAll = () => {
  if (isAllVisibleSelected.value) {
    selectedIds.value = [];
  } else {
    selectedIds.value = products.value.map((p) => p.id);
  }
};

const clearSelection = () => {
  selectedIds.value = [];
};

const activeFilterObject = computed(() => {
  const f = {};
  if (filters.search && filters.search.trim()) f.search = filters.search.trim();
  if (filters.type !== 'all') f.type = filters.type;
  if (filters.status !== 'all') f.status = filters.status;
  if (filters.stock_status !== 'all') f.stock_status = filters.stock_status;
  if (filters.category !== 'all') f.category = filters.category;
  return f;
});

const openBulk = (actionType = '') => {
  bulkSelectionMode.value = 'ids';
  initialBulkAction.value = actionType;
  showBulkDialog.value = true;
};

const openBulkForFiltered = (actionType = '') => {
  bulkSelectionMode.value = 'filter';
  initialBulkAction.value = actionType;
  showBulkDialog.value = true;
};

const onBulkCompleted = () => {
  clearSelection();
  fetchProducts();
};

const fetchCategories = async () => {
  try {
    const res = await api.get('/product-categories');
    categories.value = res.data || [];
  } catch (err) {
    console.error('Categories fetch error:', err);
  }
};

const fetchProducts = async () => {
  loading.value = true;
  try {
    const params = {
      page: meta.page,
      per_page: meta.per_page,
    };

    if (filters.search.trim()) params.search = filters.search.trim();
    if (filters.type !== 'all') params.type = filters.type;
    if (filters.status !== 'all') params.status = filters.status;
    if (filters.stock_status !== 'all') params.stock_status = filters.stock_status;
    if (filters.category !== 'all') params.category = filters.category;

    const res = await api.get('/products', { params });
    products.value = res.data || [];
    if (res.meta) {
      meta.total = res.meta.total || 0;
      meta.total_pages = res.meta.total_pages || 1;
    }
  } catch (err) {
    notification.error(err.message || 'خطا در بارگذاری لیست محصولات');
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  meta.page = 1;
  fetchProducts();
};

const handleFilterChange = () => {
  meta.page = 1;
  fetchProducts();
};

const resetFilters = () => {
  filters.search = '';
  filters.type = 'all';
  filters.status = 'all';
  filters.stock_status = 'all';
  filters.category = 'all';
  meta.page = 1;
  fetchProducts();
};

const changePage = (p) => {
  if (p < 1 || p > meta.total_pages) return;
  meta.page = p;
  fetchProducts();
};

const openDrawer = (id) => {
  drawerProductId.value = id;
  isDrawerOpen.value = true;
};

const handleProductUpdated = (updated) => {
  const idx = products.value.findIndex((p) => p.id === updated.id);
  if (idx !== -1) {
    products.value[idx] = updated;
  }
};

const promptDelete = (prod) => {
  productToDelete.value = prod;
  deletePermanently.value = false;
};

const executeDelete = async () => {
  if (!productToDelete.value) return;
  deleting.value = true;
  try {
    const res = await api.delete(`/products/${productToDelete.value.id}`, {
      params: { force: deletePermanently.value },
    });
    if (res.success || res.data) {
      notification.success(res.meta?.message || 'محصول با موفقیت حذف گردید.');
      productToDelete.value = null;
      fetchProducts();
    }
  } catch (err) {
    notification.error(err.message || 'خطا در حذف محصول');
  } finally {
    deleting.value = false;
  }
};


const getTypeBadgeClass = (type) => {
  switch (type) {
    case 'variable':
      return 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400';
    case 'external':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';
    case 'grouped':
      return 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400';
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
  }
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'publish':
      return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400';
    case 'draft':
      return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
    case 'pending':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';
    default:
      return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400';
  }
};

const getStockIndicatorClass = (status) => {
  switch (status) {
    case 'instock':
      return 'bg-emerald-500';
    case 'outofstock':
      return 'bg-rose-500';
    default:
      return 'bg-amber-500';
  }
};

onMounted(() => {
  fetchCategories();
  fetchProducts();
});
</script>
