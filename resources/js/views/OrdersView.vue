<template>
  <div class="space-y-6">
    <!-- Header Page Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl md:text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">
            سفارش‌های ووکامرس
          </h1>
          <span
            v-if="!loading && totalOrders > 0"
            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60"
          >
            {{ totalOrders }} سفارش
          </span>
        </div>
        <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
          مدیریت، پیگیری وضعیت و استرداد سفارش‌های ثبت شده در فروشگاه «{{ storeContext.currentStoreName }}»
        </p>
      </div>

      <!-- Header Actions -->
      <div class="flex items-center gap-3">
        <!-- Store Selector if multiple stores exist -->
        <select
          v-if="storeContext.stores.length > 1"
          v-model="selectedStoreId"
          @change="onStoreChange"
          class="text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-700 dark:text-slate-200 font-medium shadow-2xs focus:ring-2 focus:ring-indigo-500"
        >
          <option v-for="st in storeContext.stores" :key="st.id" :value="st.id">
            {{ st.name }}
          </option>
        </select>

        <SyncButton
          entity="orders"
          @synced="fetchOrders"
        />

        <RefreshButton
          @click="fetchOrders"
          :loading="loading"
          label="به‌روزرسانی"
          title="به‌روزرسانی فهرست سفارش‌ها"
        />
      </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-2xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <!-- Search Input -->
        <div class="lg:col-span-2 relative">
          <Iconsax name="search" size="18" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
          <input
            v-model="searchQuery"
            @input="onSearchInput"
            type="text"
            placeholder="شماره سفارش، نام خریدار یا ایمیل..."
            class="w-full pr-10 pl-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden transition-all"
          />
          <button
            v-if="searchQuery"
            @click="clearSearch"
            class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
          >
            <Iconsax name="close" size="14" />
          </button>
        </div>

        <!-- Dynamic Status Filter -->
        <div>
          <select
            v-model="selectedStatus"
            @change="currentPage = 1; fetchOrders()"
            class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          >
            <option value="all">همه وضعیت‌ها</option>
            <option v-for="st in availableStatuses" :key="st.slug" :value="st.slug">
              {{ st.name }}
            </option>
          </select>
        </div>

        <!-- Date Range Filter (Store Timezone Respected) -->
        <div>
          <select
            v-model="selectedDatePreset"
            @change="currentPage = 1; fetchOrders()"
            class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          >
            <option value="">همه زمان‌ها</option>
            <option value="today">امروز</option>
            <option value="yesterday">دیروز</option>
            <option value="last_7_days">۷ روز گذشته</option>
            <option value="last_30_days">۳۰ روز گذشته</option>
            <option value="this_month">ماه جاری</option>
            <option value="last_month">ماه گذشته</option>
          </select>
        </div>

        <!-- Sort Column -->
        <div>
          <select
            v-model="sortColumn"
            @change="fetchOrders"
            class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          >
            <option value="date">تاریخ ثبت سفارش</option>
            <option value="id">شناسه سفارش</option>
          </select>
        </div>

        <!-- Sort Direction & Reset -->
        <div class="flex items-center gap-2">
          <select
            v-model="sortDirection"
            @change="fetchOrders"
            class="flex-1 py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          >
            <option value="desc">جدیدترین به قدیمی‌ترین</option>
            <option value="asc">قدیمی‌ترین به جدیدترین</option>
          </select>

          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            class="p-2 rounded-xl text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
            title="پاکسازی فیلترها"
          >
            <Iconsax name="close" size="17" />
          </button>
        </div>
      </div>

      <!-- Action row when no items selected but orders exist -->
      <div v-if="selectedOrderIds.length === 0 && totalOrders > 0" class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800 text-xs">
        <button
          @click="openBulkForFiltered('')"
          class="text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 px-3 py-1.5 rounded-lg border border-indigo-200/60 dark:border-indigo-800/60 flex items-center gap-1.5 font-semibold transition-colors"
        >
          <Iconsax name="bulk" size="14" />
          <span>عملیات گروهی روی سفارش‌های فیلتر شده ({{ totalOrders }} سفارش)</span>
        </button>
      </div>

      <!-- Bulk Selection Toolbar -->
      <div
        v-if="selectedOrderIds.length > 0"
        class="bg-indigo-50/90 dark:bg-indigo-950/60 border border-indigo-200/80 dark:border-indigo-800/80 rounded-2xl p-3 flex flex-wrap items-center justify-between gap-3 animate-fadeIn"
      >
        <div class="flex items-center flex-wrap gap-2 text-xs">
          <span class="font-bold text-indigo-900 dark:text-indigo-200">
            {{ formatNumber(selectedOrderIds.length) }} سفارش انتخاب شده است
          </span>
          <button
            v-if="totalOrders > selectedOrderIds.length"
            @click="openBulkForFiltered('')"
            class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold"
          >
            (انتخاب تمام {{ formatNumber(totalOrders) }} سفارش مطابق با فیلترها)
          </button>
          <span class="text-indigo-300 dark:text-indigo-700">•</span>
          <button
            @click="clearSelection"
            class="text-rose-600 dark:text-rose-400 hover:underline"
          >
            لغو انتخاب‌ها
          </button>
        </div>

        <div class="flex items-center flex-wrap gap-1.5">
          <button
            @click="openBulk('change_status')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="settings" size="14" />
            <span>تغییر وضعیت گروهی</span>
          </button>
          <button
            @click="openBulk('add_note')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="message" size="14" />
            <span>افزودن یادداشت گروهی</span>
          </button>
          <button
            @click="openBulk('')"
            class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-xs"
          >
            <span>عملیات انبوه...</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="errorMessage && !loading"
      class="p-6 rounded-2xl bg-rose-50/80 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-center space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto">
        <Iconsax name="close" size="24" />
      </div>
      <div>
        <h4 class="font-bold text-sm text-rose-900 dark:text-rose-200">خطا در دریافت سفارش‌ها از ووکامرس</h4>
        <p class="text-xs text-rose-700 dark:text-rose-400 mt-1 max-w-md mx-auto">{{ errorMessage }}</p>
      </div>
      <button
        @click="fetchOrders"
        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs transition-colors"
      >
        تلاش مجدد
      </button>
    </div>

    <!-- Data Table Container -->
    <div v-else class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-2xs overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
          <thead>
            <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold select-none">
              <!-- Checkbox header -->
              <th class="p-4 w-12 text-center">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate="isIndeterminate"
                  @change="toggleSelectAll"
                  class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                />
              </th>
              <th class="py-3.5 px-4">شماره سفارش</th>
              <th class="py-3.5 px-4">خریدار</th>
              <th class="py-3.5 px-4">تاریخ ثبت</th>
              <th class="py-3.5 px-4">وضعیت</th>
              <th class="py-3.5 px-4">روش پرداخت</th>
              <th class="py-3.5 px-4">اقلام</th>
              <th class="py-3.5 px-4">مبلغ کل</th>
              <th class="py-3.5 px-4 text-center w-28">عملیات</th>
            </tr>
          </thead>

          <!-- Skeleton Loading -->
          <tbody v-if="loading" class="divide-y divide-slate-100 dark:divide-slate-800/60 animate-pulse">
            <tr v-for="i in perPage" :key="i">
              <td class="p-4 text-center"><div class="w-4 h-4 bg-slate-200 dark:bg-slate-800 rounded mx-auto"></div></td>
              <td class="p-4"><div class="w-20 h-4 bg-slate-200 dark:bg-slate-800 rounded"></div></td>
              <td class="p-4">
                <div class="space-y-1.5">
                  <div class="w-28 h-3.5 bg-slate-200 dark:bg-slate-800 rounded"></div>
                  <div class="w-20 h-2.5 bg-slate-100 dark:bg-slate-800/60 rounded"></div>
                </div>
              </td>
              <td class="p-4"><div class="w-24 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div></td>
              <td class="p-4"><div class="w-16 h-5 bg-slate-200 dark:bg-slate-800 rounded-full"></div></td>
              <td class="p-4"><div class="w-24 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div></td>
              <td class="p-4"><div class="w-12 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div></td>
              <td class="p-4"><div class="w-20 h-4 bg-slate-200 dark:bg-slate-800 rounded"></div></td>
              <td class="p-4 text-center"><div class="w-16 h-7 bg-slate-200 dark:bg-slate-800 rounded-lg mx-auto"></div></td>
            </tr>
          </tbody>

          <!-- Empty State -->
          <tbody v-else-if="orders.length === 0">
            <tr>
              <td colspan="9" class="py-12 text-center">
                <div class="max-w-xs mx-auto space-y-3">
                  <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto">
                    <Iconsax name="receipt" size="24" />
                  </div>
                  <div>
                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">هیچ سفارشی یافت نشد</h4>
                    <p class="text-xs text-slate-400 mt-1">با فیلترها یا عبارت جستجوی فعلی، سفارشی وجود ندارد.</p>
                  </div>
                  <button
                    v-if="hasActiveFilters"
                    @click="resetFilters"
                    class="px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-medium text-xs hover:bg-indigo-100 transition-colors"
                  >
                    پاکسازی فیلترها
                  </button>
                </div>
              </td>
            </tr>
          </tbody>

          <!-- Orders Data Rows -->
          <tbody v-else class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr
              v-for="order in orders"
              :key="order.id"
              class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors group cursor-pointer"
              @click="openOrderDrawer(order)"
            >
              <!-- Checkbox -->
              <td class="p-4 text-center" @click.stop>
                <input
                  type="checkbox"
                  :value="order.id"
                  v-model="selectedOrderIds"
                  class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                />
              </td>

              <!-- Order Number -->
              <td class="py-3 px-4 font-black text-slate-900 dark:text-slate-100">
                #{{ toPersianDigits(order.number || order.id) }}
              </td>

              <!-- Customer info with profile link -->
              <td class="py-3 px-4" @click.stop>
                <div class="space-y-0.5">
                  <div class="font-bold text-slate-900 dark:text-slate-100 truncate flex items-center gap-1.5">
                    <router-link
                      v-if="order.customer_id && !order.customer?.is_guest"
                      :to="'/customers/' + order.customer_id"
                      class="hover:text-indigo-600 hover:underline transition-colors"
                    >
                      {{ order.customer?.name || order.billing?.full_name || 'مشتری ووکامرس' }}
                    </router-link>
                    <span v-else>
                      {{ order.customer?.name || order.billing?.full_name || 'کاربر مهمان' }}
                    </span>
                    <span
                      v-if="order.customer?.is_guest"
                      class="text-[9px] px-1.5 py-0.2 rounded bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800"
                    >
                      مهمان
                    </span>
                  </div>
                  <div class="text-[11px] text-slate-400 truncate">
                    {{ order.customer?.email || order.billing?.email || 'بدون ایمیل' }}
                  </div>
                </div>
              </td>

              <!-- Date Created -->
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                {{ formatDate(order.date_created) }}
              </td>

              <!-- Status Badge -->
              <td class="py-3 px-4">
                <span
                  class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide"
                  :class="getStatusBadgeClass(order.status)"
                >
                  {{ order.status_label || order.status }}
                </span>
              </td>

              <!-- Payment Method -->
              <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                {{ order.payment_method_title || order.payment_method || 'نامشخص' }}
              </td>

              <!-- Items count -->
              <td class="py-3 px-4">
                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                  {{ formatNumber(order.items_count || 0) }} عدد
                </span>
              </td>

              <!-- Total price -->
              <td class="py-3 px-4 font-black text-indigo-600 dark:text-indigo-400 text-sm">
                {{ formatPrice(order.total) }}
              </td>

              <!-- Actions -->
              <td class="py-3 px-4 text-center" @click.stop>
                <div class="flex items-center justify-center gap-1.5 opacity-90 group-hover:opacity-100">
                  <button
                    @click="openOrderDrawer(order)"
                    class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-800 transition-colors"
                    title="پیش‌نمایش سریع سفارش"
                  >
                    <Iconsax name="eye" size="17" />
                  </button>

                  <router-link
                    :to="'/orders/' + order.id"
                    class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-800 transition-colors"
                    title="مشاهده صفحه کامل سفارش"
                  >
                    <Iconsax name="chevron-left" size="17" />
                  </router-link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
        <div class="flex items-center gap-3">
          <span>تعداد در صفحه:</span>
          <select
            v-model="perPage"
            @change="currentPage = 1; fetchOrders()"
            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500"
          >
            <option :value="10">۱۰</option>
            <option :value="15">۱۵</option>
            <option :value="25">۲۵</option>
            <option :value="50">۵۰</option>
          </select>
          <span v-if="totalOrders > 0">
            نمایش {{ formatNumber(paginationStart) }} تا {{ formatNumber(paginationEnd) }} از مجموع {{ formatNumber(totalOrders) }} سفارش
          </span>
        </div>

        <div class="flex items-center gap-1.5">
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage <= 1 || loading"
            class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
          >
            قبلی
          </button>

          <span class="px-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
            صفحه {{ formatNumber(currentPage) }} از {{ formatNumber(totalPages || 1) }}
          </span>

          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage >= totalPages || loading"
            class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
          >
            بعدی
          </button>
        </div>
      </div>
    </div>

    <!-- Order Drawer Component -->
    <OrderDrawer
      :order="activeOrder"
      :is-open="isDrawerOpen"
      @close="isDrawerOpen = false"
      @refresh="fetchOrders"
    />

    <!-- Bulk Operations Dialog Component -->
    <BulkOperationDialog
      :is-open="showBulkDialog"
      entity="orders"
      :selected-ids="selectedOrderIds"
      :filter="activeFilterObject"
      :selection-mode="bulkSelectionMode"
      :initial-action-type="initialBulkAction"
      :order-statuses-list="availableStatuses"
      @close="showBulkDialog = false"
      @completed="onBulkCompleted"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import apiClient from '@/api/client';
import { useStoreContext } from '@/stores/storeContext';
import Iconsax from '@/components/icons/Iconsax.vue';
import RefreshButton from '@/components/ui/RefreshButton.vue';
import SyncButton from '@/components/ui/SyncButton.vue';
import OrderDrawer from '@/components/orders/OrderDrawer.vue';
import BulkOperationDialog from '@/components/bulk/BulkOperationDialog.vue';
import { formatNumber, formatPrice, formatDate, toPersianDigits } from '@/utils/formatters';

const storeContext = useStoreContext();

// Bulk Operation State
const showBulkDialog = ref(false);
const bulkSelectionMode = ref('ids');
const initialBulkAction = ref('');

// Orders Data State
const orders = ref([]);
const loading = ref(false);
const errorMessage = ref(null);

// Pagination State
const currentPage = ref(1);
const perPage = ref(15);
const totalOrders = ref(0);
const totalPages = ref(1);

// Filters State
const searchQuery = ref('');
let searchDebounceTimeout = null;
const selectedStatus = ref('all');
const selectedDatePreset = ref('');
const sortColumn = ref('date');
const sortDirection = ref('desc');
const selectedStoreId = ref(null);

const availableStatuses = ref([
  { slug: 'pending', name: 'در انتظار پرداخت' },
  { slug: 'processing', name: 'در حال پردازش' },
  { slug: 'on-hold', name: 'در انتظار بررسی' },
  { slug: 'completed', name: 'تکمیل شده' },
  { slug: 'cancelled', name: 'لغو شده' },
  { slug: 'refunded', name: 'مسترد شده' },
  { slug: 'failed', name: 'ناموفق' },
]);

// Bulk Selection
const selectedOrderIds = ref([]);

// Order Drawer State
const activeOrder = ref(null);
const isDrawerOpen = ref(false);

const hasActiveFilters = computed(() => {
  return searchQuery.value.trim() !== '' || selectedStatus.value !== 'all' || selectedDatePreset.value !== '' || sortColumn.value !== 'date' || sortDirection.value !== 'desc';
});

const isAllSelected = computed(() => {
  return orders.value.length > 0 && selectedOrderIds.value.length === orders.value.length;
});

const isIndeterminate = computed(() => {
  return selectedOrderIds.value.length > 0 && selectedOrderIds.value.length < orders.value.length;
});

const paginationStart = computed(() => {
  if (totalOrders.value === 0) return 0;
  return (currentPage.value - 1) * perPage.value + 1;
});

const paginationEnd = computed(() => {
  return Math.min(currentPage.value * perPage.value, totalOrders.value);
});

onMounted(async () => {
  if (!storeContext.activeStore) {
    await storeContext.fetchStores();
  }
  if (storeContext.activeStore) {
    selectedStoreId.value = storeContext.activeStore.id;
  }
  fetchStatuses();
  fetchOrders();
});

const fetchStatuses = async () => {
  try {
    const res = await apiClient.get('/orders/statuses');
    if (res.data && res.data.length > 0) {
      availableStatuses.value = res.data;
    }
  } catch (e) {
    // fallback
  }
};

const onStoreChange = () => {
  const store = storeContext.stores.find(s => s.id == selectedStoreId.value);
  if (store) {
    storeContext.setActiveStore(store);
    currentPage.value = 1;
    selectedOrderIds.value = [];
    fetchStatuses();
    fetchOrders();
  }
};

const onSearchInput = () => {
  clearTimeout(searchDebounceTimeout);
  searchDebounceTimeout = setTimeout(() => {
    currentPage.value = 1;
    fetchOrders();
  }, 400);
};

const clearSearch = () => {
  searchQuery.value = '';
  currentPage.value = 1;
  fetchOrders();
};

const resetFilters = () => {
  searchQuery.value = '';
  selectedStatus.value = 'all';
  selectedDatePreset.value = '';
  sortColumn.value = 'date';
  sortDirection.value = 'desc';
  currentPage.value = 1;
  fetchOrders();
};

const fetchOrders = async () => {
  loading.value = true;
  errorMessage.value = null;

  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      search: searchQuery.value.trim() || undefined,
      status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
      date_preset: selectedDatePreset.value || undefined,
      sort: sortColumn.value,
      direction: sortDirection.value,
    };

    const res = await apiClient.get('/orders', { params });
    orders.value = res.data || [];
    if (res.meta) {
      totalOrders.value = res.meta.total || 0;
      totalPages.value = res.meta.total_pages || 1;
    }
  } catch (err) {
    errorMessage.value = err.message || 'خطا در برقراری ارتباط با فروشگاه.';
    orders.value = [];
  } finally {
    loading.value = false;
  }
};

const goToPage = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
  fetchOrders();
};

// Selection Handling
const toggleSelectAll = (e) => {
  if (e.target.checked) {
    selectedOrderIds.value = orders.value.map(o => o.id);
  } else {
    selectedOrderIds.value = [];
  }
};

const selectAllVisible = () => {
  selectedOrderIds.value = orders.value.map(o => o.id);
};

const clearSelection = () => {
  selectedOrderIds.value = [];
};

const activeFilterObject = computed(() => {
  const f = {};
  if (searchQuery.value && searchQuery.value.trim()) f.search = searchQuery.value.trim();
  if (selectedStatus.value !== 'all') f.status = selectedStatus.value;
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
  fetchOrders();
};

// Drawer Handling
const openOrderDrawer = (order) => {
  activeOrder.value = order;
  isDrawerOpen.value = true;
};

// Badges & Formatting
const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'completed':
      return 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800';
    case 'processing':
      return 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800';
    case 'on-hold':
      return 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800';
    case 'pending':
      return 'bg-violet-50 dark:bg-violet-950/60 text-violet-700 dark:text-violet-400 border border-violet-200 dark:border-violet-800';
    case 'cancelled':
    case 'failed':
      return 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800';
    case 'refunded':
      return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700';
    default:
      return 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800';
  }
};

</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.2s ease-out forwards;
}
</style>
