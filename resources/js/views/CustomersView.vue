<template>
  <div class="space-y-6">
    <!-- Header Page Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl md:text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">
            مشتریان ووکامرس
          </h1>
          <span
            v-if="!loading && totalCustomers > 0"
            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60"
          >
            {{ totalCustomers }} مشتری
          </span>
        </div>
        <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
          مشاهده، جستجو و مدیریت پرونده مشتریان متصل به فروشگاه «{{ storeContext.currentStoreName }}»
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
          entity="customers"
          @synced="fetchCustomers"
        />

        <RefreshButton
          @click="fetchCustomers"
          :loading="loading"
          label="به‌روزرسانی"
          title="به‌روزرسانی فهرست مشتریان"
        />
      </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-2xs space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Search Input -->
        <div class="lg:col-span-2 relative">
          <Iconsax name="search" size="18" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
          <input
            v-model="searchQuery"
            @input="onSearchInput"
            type="text"
            placeholder="جستجوی سروری بر اساس نام، ایمیل، نام‌کاربری..."
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

        <!-- Customer Type Filter -->
        <div>
          <select
            v-model="selectedRole"
            @change="fetchCustomers"
            class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          >
            <option value="all">همه انواع مشتریان</option>
            <option value="customer">مشتریان ثبت‌نامی</option>
            <option value="subscriber">مشترکین</option>
          </select>
        </div>

        <!-- Sort Column -->
        <div>
          <select
            v-model="sortColumn"
            @change="fetchCustomers"
            class="w-full py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          >
            <option value="id">مرتب‌سازی بر اساس شناسه</option>
            <option value="name">مرتب‌سازی بر اساس نام</option>
            <option value="registered_date">تاریخ ثبت نام</option>
          </select>
        </div>

        <!-- Sort Direction & Reset -->
        <div class="flex items-center gap-2">
          <select
            v-model="sortDirection"
            @change="fetchCustomers"
            class="flex-1 py-2 px-3 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          >
            <option value="desc">نزولی (جدیدترین)</option>
            <option value="asc">صعودی (قدیمی‌ترین)</option>
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

      <!-- Action row when no items selected but customers exist -->
      <div v-if="selectedCustomerIds.length === 0 && totalCustomers > 0" class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800 text-xs">
        <button
          @click="openBulkForFiltered('')"
          class="text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 px-3 py-1.5 rounded-lg border border-indigo-200/60 dark:border-indigo-800/60 flex items-center gap-1.5 font-semibold transition-colors"
        >
          <Iconsax name="bulk" size="14" />
          <span>عملیات گروهی روی مشتریان فیلتر شده ({{ totalCustomers }} مشتری)</span>
        </button>
      </div>

      <!-- Bulk Selection Toolbar (When Items Selected) -->
      <div
        v-if="selectedCustomerIds.length > 0"
        class="bg-indigo-50/90 dark:bg-indigo-950/60 border border-indigo-200/80 dark:border-indigo-800/80 rounded-2xl p-3 flex flex-wrap items-center justify-between gap-3 animate-fadeIn"
      >
        <div class="flex items-center flex-wrap gap-2 text-xs">
          <span class="font-bold text-indigo-900 dark:text-indigo-200">
            {{ formatNumber(selectedCustomerIds.length) }} مشتری انتخاب شده است
          </span>
          <button
            v-if="totalCustomers > selectedCustomerIds.length"
            @click="openBulkForFiltered('')"
            class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold"
          >
            (انتخاب تمام {{ formatNumber(totalCustomers) }} مشتری مطابق با فیلترها)
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
            @click="openBulk('add_tag')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="tag" size="14" />
            <span>افزودن برچسب CRM</span>
          </button>
          <button
            @click="openBulk('remove_tag')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="close" size="14" />
            <span>حذف برچسب</span>
          </button>
          <button
            @click="openBulk('create_task')"
            class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-indigo-50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold flex items-center gap-1.5 transition-all shadow-xs"
          >
            <Iconsax name="task" size="14" />
            <span>ایجاد وظیفه (تسک)</span>
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
        <h4 class="font-bold text-sm text-rose-900 dark:text-rose-200">خطا در دریافت مشتریان از ووکامرس</h4>
        <p class="text-xs text-rose-700 dark:text-rose-400 mt-1 max-w-md mx-auto">{{ errorMessage }}</p>
      </div>
      <button
        @click="fetchCustomers"
        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs transition-colors"
      >
        تلاش مجدد
      </button>
    </div>

    <!-- Data Table Container -->
    <div v-else class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-2xs overflow-hidden">
      <!-- Table View -->
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
              <th class="py-3.5 px-4">مشتری</th>
              <th class="py-3.5 px-4">تماس (ایمیل / تلفن)</th>
              <th class="py-3.5 px-4">تعداد سفارش‌ها</th>
              <th class="py-3.5 px-4">مجموع خرید</th>
              <th class="py-3.5 px-4">تاریخ ثبت نام</th>
              <th class="py-3.5 px-4">برچسب‌ها</th>
              <th class="py-3.5 px-4 text-center w-28">عملیات</th>
            </tr>
          </thead>

          <!-- Skeleton Loading -->
          <tbody v-if="loading" class="divide-y divide-slate-100 dark:divide-slate-800/60 animate-pulse">
            <tr v-for="i in perPage" :key="i">
              <td class="p-4 text-center"><div class="w-4 h-4 bg-slate-200 dark:bg-slate-800 rounded mx-auto"></div></td>
              <td class="p-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-slate-200 dark:bg-slate-800"></div>
                  <div class="space-y-1.5 flex-1">
                    <div class="w-24 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div>
                    <div class="w-16 h-2.5 bg-slate-100 dark:bg-slate-800/60 rounded"></div>
                  </div>
                </div>
              </td>
              <td class="p-4"><div class="w-32 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div></td>
              <td class="p-4"><div class="w-14 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div></td>
              <td class="p-4"><div class="w-20 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div></td>
              <td class="p-4"><div class="w-20 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div></td>
              <td class="p-4"><div class="w-16 h-4 bg-slate-200 dark:bg-slate-800 rounded-full"></div></td>
              <td class="p-4 text-center"><div class="w-16 h-7 bg-slate-200 dark:bg-slate-800 rounded-lg mx-auto"></div></td>
            </tr>
          </tbody>

          <!-- Empty State -->
          <tbody v-else-if="customers.length === 0">
            <tr>
              <td colspan="8" class="py-12 text-center">
                <div class="max-w-xs mx-auto space-y-3">
                  <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto">
                    <Iconsax name="customers" size="24" />
                  </div>
                  <div>
                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">هیچ مشتری‌ای یافت نشد</h4>
                    <p class="text-xs text-slate-400 mt-1">با فیلترها یا عبارت جستجوی فعلی، رکوردی وجود ندارد.</p>
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

          <!-- Customers Data Rows -->
          <tbody v-else class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr
              v-for="customer in customers"
              :key="customer.id"
              class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors group cursor-pointer"
              @click="openCustomerDrawer(customer)"
            >
              <!-- Checkbox -->
              <td class="p-4 text-center" @click.stop>
                <input
                  type="checkbox"
                  :value="customer.id"
                  v-model="selectedCustomerIds"
                  class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
                />
              </td>

              <!-- Customer info -->
              <td class="py-3 px-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-500 to-violet-500 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
                    {{ getInitials(customer.full_name) }}
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-slate-900 dark:text-slate-100 truncate flex items-center gap-1.5">
                      <span>{{ customer.full_name }}</span>
                      <span
                        v-if="customer.customer_type === 'guest'"
                        class="text-[9px] px-1.5 py-0.2 rounded bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800"
                      >
                        مهمان
                      </span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                      شناسه: #{{ customer.id }}
                      <span v-if="customer.username">• @{{ customer.username }}</span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Email & Phone -->
              <td class="py-3 px-4">
                <div class="space-y-0.5">
                  <div class="text-xs text-slate-700 dark:text-slate-200 truncate">
                    {{ customer.email || '—' }}
                  </div>
                  <div v-if="customer.phone" class="text-[11px] text-slate-400 dir-ltr text-right">
                    {{ customer.phone }}
                  </div>
                </div>
              </td>

              <!-- Orders Count -->
              <td class="py-3 px-4">
                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                  {{ formatNumber(customer.orders_count || 0) }} سفارش
                </span>
              </td>

              <!-- Total Spent -->
              <td class="py-3 px-4 font-bold text-indigo-600 dark:text-indigo-400">
                {{ formatPrice(customer.total_spent) }}
              </td>

              <!-- Registration Date -->
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                {{ formatDate(customer.date_created) }}
              </td>

              <!-- Tags -->
              <td class="py-3 px-4" @click.stop>
                <div class="flex flex-wrap gap-1 max-w-xs">
                  <span
                    v-for="tag in (customer.tags || [])"
                    :key="tag.id"
                    class="inline-block px-2 py-0.5 rounded-md text-[10px] font-medium text-white shadow-2xs"
                    :style="{ backgroundColor: tag.color || '#4F46E5' }"
                  >
                    {{ tag.name }}
                  </span>
                  <span v-if="!customer.tags || customer.tags.length === 0" class="text-[11px] text-slate-300 dark:text-slate-600">
                    —
                  </span>
                </div>
              </td>

              <!-- Actions -->
              <td class="py-3 px-4 text-center" @click.stop>
                <div class="flex items-center justify-center gap-1.5 opacity-90 group-hover:opacity-100">
                  <button
                    @click="openCustomerDrawer(customer)"
                    class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-800 transition-colors"
                    title="پیش‌نمایش سریع"
                  >
                    <Iconsax name="eye" size="17" />
                  </button>

                  <router-link
                    :to="'/customers/' + customer.id"
                    class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-800 transition-colors"
                    title="مشاهده پرونده کامل"
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
        <!-- Page size selector & info -->
        <div class="flex items-center gap-3">
          <span>تعداد در صفحه:</span>
          <select
            v-model="perPage"
            @change="currentPage = 1; fetchCustomers()"
            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500"
          >
            <option :value="10">۱۰</option>
            <option :value="15">۱۵</option>
            <option :value="25">۲۵</option>
            <option :value="50">۵۰</option>
          </select>
          <span v-if="totalCustomers > 0">
            نمایش {{ formatNumber(paginationStart) }} تا {{ formatNumber(paginationEnd) }} از مجموع {{ formatNumber(totalCustomers) }} مشتری
          </span>
        </div>

        <!-- Pagination Controls -->
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

    <!-- Customer Drawer Modal -->
    <CustomerDrawer
      :customer="activeCustomer"
      :is-open="isDrawerOpen"
      @close="isDrawerOpen = false"
      @refresh="fetchCustomers"
    />

    <!-- Bulk Operations Dialog Component -->
    <BulkOperationDialog
      :is-open="showBulkDialog"
      entity="customers"
      :selected-ids="selectedCustomerIds"
      :filter="activeFilterObject"
      :selection-mode="bulkSelectionMode"
      :initial-action-type="initialBulkAction"
      @close="showBulkDialog = false"
      @completed="onBulkCompleted"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import apiClient from '@/api/client';
import { useStoreContext } from '@/stores/storeContext';
import { useNotificationStore } from '@/stores/notification';
import Iconsax from '@/components/icons/Iconsax.vue';
import RefreshButton from '@/components/ui/RefreshButton.vue';
import SyncButton from '@/components/ui/SyncButton.vue';
import CustomerDrawer from '@/components/customers/CustomerDrawer.vue';
import BulkOperationDialog from '@/components/bulk/BulkOperationDialog.vue';
import { formatNumber, formatPrice, formatDate, toPersianDigits } from '@/utils/formatters';

const storeContext = useStoreContext();
const notification = useNotificationStore();

// Bulk Operation State
const showBulkDialog = ref(false);
const bulkSelectionMode = ref('ids');
const initialBulkAction = ref('');

// Customers List State
const customers = ref([]);
const loading = ref(false);
const errorMessage = ref(null);

// Pagination State
const currentPage = ref(1);
const perPage = ref(15);
const totalCustomers = ref(0);
const totalPages = ref(1);

// Filters State
const searchQuery = ref('');
let searchDebounceTimeout = null;
const selectedRole = ref('all');
const sortColumn = ref('id');
const sortDirection = ref('desc');
const selectedStoreId = ref(null);

// Bulk Selection
const selectedCustomerIds = ref([]);

// Customer Drawer State
const activeCustomer = ref(null);
const isDrawerOpen = ref(false);

const hasActiveFilters = computed(() => {
  return searchQuery.value.trim() !== '' || selectedRole.value !== 'all' || sortColumn.value !== 'id' || sortDirection.value !== 'desc';
});

const isAllSelected = computed(() => {
  return customers.value.length > 0 && selectedCustomerIds.value.length === customers.value.length;
});

const isIndeterminate = computed(() => {
  return selectedCustomerIds.value.length > 0 && selectedCustomerIds.value.length < customers.value.length;
});

const paginationStart = computed(() => {
  if (totalCustomers.value === 0) return 0;
  return (currentPage.value - 1) * perPage.value + 1;
});

const paginationEnd = computed(() => {
  return Math.min(currentPage.value * perPage.value, totalCustomers.value);
});

onMounted(async () => {
  if (!storeContext.activeStore) {
    await storeContext.fetchStores();
  }
  if (storeContext.activeStore) {
    selectedStoreId.value = storeContext.activeStore.id;
  }
  fetchCustomers();
});

const onStoreChange = () => {
  const store = storeContext.stores.find(s => s.id == selectedStoreId.value);
  if (store) {
    storeContext.setActiveStore(store);
    currentPage.value = 1;
    selectedCustomerIds.value = [];
    fetchCustomers();
  }
};

const onSearchInput = () => {
  clearTimeout(searchDebounceTimeout);
  searchDebounceTimeout = setTimeout(() => {
    currentPage.value = 1;
    fetchCustomers();
  }, 400);
};

const clearSearch = () => {
  searchQuery.value = '';
  currentPage.value = 1;
  fetchCustomers();
};

const resetFilters = () => {
  searchQuery.value = '';
  selectedRole.value = 'all';
  sortColumn.value = 'id';
  sortDirection.value = 'desc';
  currentPage.value = 1;
  fetchCustomers();
};

const fetchCustomers = async () => {
  loading.value = true;
  errorMessage.value = null;

  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      search: searchQuery.value.trim() || undefined,
      role: selectedRole.value !== 'all' ? selectedRole.value : undefined,
      sort: sortColumn.value,
      direction: sortDirection.value,
    };

    const res = await apiClient.get('/customers', { params });
    customers.value = res.data || [];
    if (res.meta) {
      totalCustomers.value = res.meta.total || 0;
      totalPages.value = res.meta.total_pages || 1;
    }
  } catch (err) {
    errorMessage.value = err.message || 'خطا در برقراری ارتباط با فروشگاه.';
    customers.value = [];
  } finally {
    loading.value = false;
  }
};

const goToPage = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
  fetchCustomers();
};

// Selection Handling
const toggleSelectAll = (e) => {
  if (e.target.checked) {
    selectedCustomerIds.value = customers.value.map(c => c.id);
  } else {
    selectedCustomerIds.value = [];
  }
};

const selectAllVisible = () => {
  selectedCustomerIds.value = customers.value.map(c => c.id);
};

const clearSelection = () => {
  selectedCustomerIds.value = [];
};

const activeFilterObject = computed(() => {
  const f = {};
  if (searchQuery.value && searchQuery.value.trim()) f.search = searchQuery.value.trim();
  if (selectedRole.value !== 'all') f.role = selectedRole.value;
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
  fetchCustomers();
};

// Drawer Handling
const openCustomerDrawer = (customer) => {
  activeCustomer.value = customer;
  isDrawerOpen.value = true;
};

// Formatting helpers
const getInitials = (name) => {
  if (!name) return 'U';
  return name.trim().charAt(0).toUpperCase();
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
