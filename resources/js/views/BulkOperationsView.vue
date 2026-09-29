<template>
  <div class="space-y-6" dir="rtl">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-3">
          <span>مرکز عملیات گروهی</span>
          <span class="text-xs px-2.5 py-1 rounded-full font-medium bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
            موتور عملیات گروهی
          </span>
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          مشاهده، پیگیری وضعیت، بررسی خطاهای احتمالی و لاگ عملیات‌های انبوه سامانه
        </p>
      </div>

      <div class="flex items-center gap-3">
        <RefreshButton
          @click="fetchOperations"
          :loading="loading"
          label="به‌روزرسانی"
          title="به‌روزرسانی داده‌ها"
        />
      </div>
    </div>

    <!-- Filters & Stats Toolbar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Entity Filter -->
        <div class="flex items-center gap-2">
          <span class="text-slate-400 font-medium">موجودیت:</span>
          <select
            v-model="filters.entity"
            @change="fetchOperations"
            class="py-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="all">همه موجودیت‌ها</option>
            <option value="products">محصولات (Products)</option>
            <option value="orders">سفارش‌ها (Orders)</option>
            <option value="customers">مشتریان (Customers)</option>
          </select>
        </div>

        <!-- Status Filter -->
        <div class="flex items-center gap-2">
          <span class="text-slate-400 font-medium">وضعیت:</span>
          <select
            v-model="filters.status"
            @change="fetchOperations"
            class="py-1.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="all">همه وضعیت‌ها</option>
            <option value="completed">تکمیل شده</option>
            <option value="partial">تکمیل جزئی</option>
            <option value="processing">در حال پردازش</option>
            <option value="pending">در انتظار</option>
            <option value="cancelled">لغو شده</option>
            <option value="failed">ناموفق</option>
          </select>
        </div>
      </div>

      <!-- Quick Summary Counter -->
      <div class="text-slate-400">
        مجموع عملیات ثبت‌شده: <strong class="text-slate-700 dark:text-slate-200">{{ toPersianDigits(meta.total) }}</strong>
      </div>
    </div>

    <!-- Operations Table Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
      <!-- Loading Skeleton -->
      <div v-if="loading" class="p-8 space-y-4">
        <div v-for="i in 5" :key="i" class="flex items-center gap-4 animate-pulse">
          <div class="w-10 h-10 bg-slate-200 dark:bg-slate-800 rounded-xl shrink-0"></div>
          <div class="flex-1 space-y-2">
            <div class="w-1/4 h-3 bg-slate-200 dark:bg-slate-800 rounded"></div>
            <div class="w-1/3 h-2 bg-slate-100 dark:bg-slate-850 rounded"></div>
          </div>
          <div class="w-20 h-6 bg-slate-200 dark:bg-slate-800 rounded-full"></div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="operations.length === 0" class="p-12 text-center space-y-3">
        <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto">
          <Iconsax name="bulk" size="32" />
        </div>
        <h3 class="font-bold text-base text-slate-800 dark:text-slate-100">عملیاتی ثبت نشده است</h3>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          تاکنون هیچ عملیات گروهی روی محصولات، سفارش‌ها یا مشتریان اجرا نشده است.
        </p>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-right text-xs">
          <thead class="bg-slate-50 dark:bg-slate-850/60 border-b border-slate-100 dark:border-slate-800 text-slate-500">
            <tr>
              <th class="p-3.5">شناسه</th>
              <th class="p-3.5">موجودیت</th>
              <th class="p-3.5">عملیات</th>
              <th class="p-3.5">اجراکننده</th>
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
              <td class="p-3.5 text-slate-400 font-semibold">#{{ toPersianDigits(op.id) }}</td>
              <td class="p-3.5">
                <span
                  class="px-2.5 py-1 rounded-xl text-[11px] font-bold"
                  :class="getEntityBadgeClass(op.entity_type)"
                >
                  {{ getEntityLabel(op.entity_type) }}
                </span>
              </td>
              <td class="p-3.5 font-medium text-slate-800 dark:text-slate-100">
                {{ formatActionName(op.action_type || op.type) }}
              </td>
              <td class="p-3.5 text-slate-500">
                {{ op.user_name || 'مدیر' }}
              </td>
              <td class="p-3.5 text-center">
                <div class="inline-flex flex-col items-center gap-1 w-24">
                  <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
                    <div
                      class="bg-indigo-600 h-full rounded-full transition-all"
                      :style="{ width: `${op.percent}%` }"
                    ></div>
                  </div>
                  <span class="text-[10px] text-slate-400">
                    {{ toPersianDigits(op.processed_items) }} / {{ toPersianDigits(op.total_items) }}
                  </span>
                </div>
              </td>
              <td class="p-3.5 text-center">
                <span
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold inline-flex items-center gap-1.5"
                  :class="getStatusBadgeClass(op.status)"
                >
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
                  title="مشاهده جزئیات"
                >
                  <Iconsax name="search" size="16" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="meta.total_pages > 1" class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
        <span class="text-slate-400">صفحه {{ toPersianDigits(meta.current_page) }} از {{ toPersianDigits(meta.total_pages) }}</span>
        <div class="flex items-center gap-2">
          <button
            :disabled="meta.current_page <= 1"
            @click="changePage(meta.current_page - 1)"
            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 disabled:opacity-40"
          >
            قبلی
          </button>
          <button
            :disabled="meta.current_page >= meta.total_pages"
            @click="changePage(meta.current_page + 1)"
            class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 disabled:opacity-40"
          >
            بعدی
          </button>
        </div>
      </div>
    </div>

    <!-- Operation Detail Drawer / Modal -->
    <div v-if="selectedOp" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div
        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden flex flex-col max-h-[90vh] animate-fadeIn"
      >
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-850/50">
          <div class="flex items-center gap-3">
            <span class="text-xs font-bold text-slate-400">#{{ toPersianDigits(selectedOp.id) }}</span>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold" :class="getEntityBadgeClass(selectedOp.entity_type)">
              {{ getEntityLabel(selectedOp.entity_type) }}
            </span>
            <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">
              {{ formatActionName(selectedOp.action_type || selectedOp.type) }}
            </h3>
          </div>
          <button
            @click="selectedOp = null"
            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <!-- Detail Content -->
        <div class="p-6 overflow-y-auto space-y-6 text-xs flex-1">
          <!-- Status & Stats Card -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/80 dark:border-slate-800">
              <span class="text-slate-400 text-[11px]">وضعیت کلی</span>
              <div class="mt-1 font-bold flex items-center gap-1.5" :class="getStatusTextColor(selectedOp.status)">
                <span class="w-2 h-2 rounded-full" :class="getStatusDotClass(selectedOp.status)"></span>
                <span>{{ getStatusLabel(selectedOp.status) }}</span>
              </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/60 dark:border-emerald-900/40">
              <span class="text-emerald-700 dark:text-emerald-300 text-[11px]">موفق</span>
              <div class="text-lg font-black text-emerald-800 dark:text-emerald-200 mt-0.5">
                {{ formatNumber(selectedOp.success_items) }}
              </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-900/40">
              <span class="text-amber-700 dark:text-amber-300 text-[11px]">رد شده (اسکیپ)</span>
              <div class="text-lg font-black text-amber-800 dark:text-amber-200 mt-0.5">
                {{ formatNumber(selectedOp.skipped_items) }}
              </div>
            </div>
            <div class="p-3.5 rounded-2xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200/60 dark:border-rose-900/40">
              <span class="text-rose-700 dark:text-rose-300 text-[11px]">ناموفق</span>
              <div class="text-lg font-black text-rose-800 dark:text-rose-200 mt-0.5">
                {{ formatNumber(selectedOp.failed_items) }}
              </div>
            </div>
          </div>

          <!-- Metadata Grid -->
          <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <span class="text-slate-400">کاربر مجری:</span>
              <span class="font-bold text-slate-800 dark:text-slate-100 mr-2">{{ selectedOp.user_name }}</span>
            </div>
            <div>
              <span class="text-slate-400">فروشگاه:</span>
              <span class="font-bold text-slate-800 dark:text-slate-100 mr-2">{{ selectedOp.store_name }}</span>
            </div>
            <div>
              <span class="text-slate-400">شروع عملیات:</span>
              <span class="text-slate-600 dark:text-slate-300 mr-2">{{ selectedOp.started_at ? formatDateTime(selectedOp.started_at) : '-' }}</span>
            </div>
            <div>
              <span class="text-slate-400">پایان عملیات:</span>
              <span class="text-slate-600 dark:text-slate-300 mr-2">{{ selectedOp.completed_at ? formatDateTime(selectedOp.completed_at) : '-' }}</span>
            </div>
          </div>

          <!-- Action Parameters & Filter Details -->
          <div class="space-y-2">
            <h4 class="font-bold text-slate-700 dark:text-slate-200">پارامترهای عملیات و فیلترها:</h4>
            <div class="p-3.5 rounded-xl bg-slate-900 text-slate-200 font-mono text-[11px] overflow-x-auto" dir="ltr">
              {{ JSON.stringify(selectedOp.payload, null, 2) }}
            </div>
          </div>

          <!-- Items Execution Table (Errors / Results Inspector) -->
          <div v-if="selectedOp.items && selectedOp.items.length > 0" class="space-y-2">
            <div class="flex items-center justify-between">
              <h4 class="font-bold text-slate-700 dark:text-slate-200">
                بررسی آیتم‌ها و خطاهای ثبت‌شده (نمونه):
              </h4>
              <span class="text-[10px] text-slate-400">{{ toPersianDigits(selectedOp.items.length) }} رکورد</span>
            </div>

            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden max-h-64 overflow-y-auto">
              <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-850 border-b border-slate-200 dark:border-slate-800 text-slate-500 sticky top-0">
                  <tr>
                    <th class="p-2.5">شناسه رکورد</th>
                    <th class="p-2.5">وضعیت</th>
                    <th class="p-2.5">توضیحات خطا یا پیام</th>
                    <th class="p-2.5">مقدار قبلی</th>
                    <th class="p-2.5">مقدار جدید</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="it in selectedOp.items" :key="it.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                    <td class="p-2.5 text-slate-400 font-semibold">#{{ toPersianDigits(it.entity_id) }}</td>
                    <td class="p-2.5">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="getItemStatusBadge(it.status)">
                        {{ getStatusLabel(it.status) }}
                      </span>
                    </td>
                    <td class="p-2.5 text-slate-600 dark:text-slate-300 max-w-[200px] truncate">
                      <span v-if="it.error_message" class="text-rose-600 dark:text-rose-400">
                        {{ it.error_message }}
                      </span>
                      <span v-else class="text-slate-400">-</span>
                    </td>
                    <td class="p-2.5 text-[11px] text-slate-400 max-w-[120px] truncate dir-ltr text-right">
                      {{ formatValue(it.old_value) }}
                    </td>
                    <td class="p-2.5 text-[11px] text-emerald-600 dark:text-emerald-400 font-bold max-w-[120px] truncate dir-ltr text-right">
                      {{ formatValue(it.new_value) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-850/50">
          <div>
            <button
              v-if="selectedOp.status === 'processing' || selectedOp.status === 'pending'"
              @click="cancelOperation(selectedOp.id)"
              class="py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-colors"
            >
              لغو عملیات
            </button>
          </div>
          <button
            @click="selectedOp = null"
            class="py-2 px-5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            بستن
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import api from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import { toPersianDigits, formatNumber, formatDateTime } from '@/utils/formatters';

const notification = useNotificationStore();

const loading = ref(false);
const operations = ref([]);
const selectedOp = ref(null);

const filters = reactive({
  entity: 'all',
  status: 'all',
  page: 1,
  per_page: 20,
});

const meta = reactive({
  current_page: 1,
  per_page: 20,
  total: 0,
  total_pages: 1,
});

const fetchOperations = async () => {
  loading.value = true;
  try {
    const res = await api.get('/bulk-operations', { params: filters });
    if (res.data?.success) {
      operations.value = res.data.data;
      if (res.data.meta) {
        meta.current_page = res.data.meta.current_page;
        meta.total = res.data.meta.total;
        meta.total_pages = res.data.meta.total_pages;
      }
    }
  } catch (err) {
    notification.error('خطا در دریافت لیست عملیات‌های گروهی.');
  } finally {
    loading.value = false;
  }
};

const openDetails = async (op) => {
  try {
    const res = await api.get(`/bulk-operations/${op.id}`);
    if (res.data?.success) {
      selectedOp.value = res.data.data;
    }
  } catch (err) {
    selectedOp.value = op;
  }
};

const cancelOperation = async (id) => {
  try {
    const res = await api.post(`/bulk-operations/${id}/cancel`);
    if (res.data?.success) {
      notification.success('عملیات با موفقیت لغو شد.');
      fetchOperations();
      if (selectedOp.value?.id === id) {
        selectedOp.value.status = 'cancelled';
      }
    }
  } catch (err) {
    notification.error(err.response?.data?.error?.message || 'خطا در لغو عملیات');
  }
};

const changePage = (p) => {
  filters.page = p;
  fetchOperations();
};

const getEntityLabel = (ent) => {
  switch (ent) {
    case 'products':
    case 'product': return 'محصولات';
    case 'orders':
    case 'order': return 'سفارش‌ها';
    case 'customers':
    case 'customer': return 'مشتریان';
    default: return ent;
  }
};

const getEntityBadgeClass = (ent) => {
  switch (ent) {
    case 'products':
    case 'product': return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300';
    case 'orders':
    case 'order': return 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300';
    case 'customers':
    case 'customer': return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300';
    default: return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
  }
};

const formatActionName = (act) => {
  const map = {
    increase_price_percent: 'افزایش درصد قیمت',
    decrease_price_percent: 'کاهش درصد قیمت',
    increase_price_amount: 'افزایش مبلغ ثابت قیمت',
    decrease_price_amount: 'کاهش مبلغ ثابت قیمت',
    set_regular_price: 'تنظیم قیمت عادی',
    set_sale_price: 'تنظیم قیمت حراج/ویژه',
    clear_sale_price: 'حذف قیمت حراج (تخفیف)',
    set_stock: 'تنظیم موجودی انبار',
    increase_stock: 'افزایش موجودی انبار',
    decrease_stock: 'کاهش موجودی انبار',
    set_stock_status: 'تنظیم وضعیت انبار',
    add_category: 'افزودن دسته‌بندی',
    remove_category: 'حذف دسته‌بندی',
    add_tag: 'افزودن برچسب',
    remove_tag: 'حذف برچسب',
    set_status: 'تغییر وضعیت انتشار',
    change_status: 'تغییر وضعیت سفارش',
    add_note: 'افزودن یادداشت سفارش',
    create_task: 'ایجاد وظیفه پیگیری (CRM)',
  };
  return map[act] || act;
};

const getStatusLabel = (st) => {
  switch (st) {
    case 'completed': return 'تکمیل شده';
    case 'partial': return 'تکمیل جزئی';
    case 'processing': return 'در حال پردازش';
    case 'pending': return 'در انتظار';
    case 'cancelled': return 'لغو شده';
    case 'failed': return 'ناموفق';
    default: return st;
  }
};

const getStatusBadgeClass = (st) => {
  switch (st) {
    case 'completed': return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60';
    case 'partial': return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/60';
    case 'processing': return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60 animate-pulse';
    case 'pending': return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    case 'cancelled': return 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    case 'failed': return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200/60 dark:border-rose-800/60';
    default: return 'bg-slate-100 text-slate-700';
  }
};

const getStatusDotClass = (st) => {
  switch (st) {
    case 'completed': return 'bg-emerald-500';
    case 'partial': return 'bg-amber-500';
    case 'processing': return 'bg-indigo-500 animate-ping';
    case 'cancelled': return 'bg-slate-400';
    case 'failed': return 'bg-rose-500';
    default: return 'bg-slate-400';
  }
};

const getStatusTextColor = (st) => {
  switch (st) {
    case 'completed': return 'text-emerald-600 dark:text-emerald-400';
    case 'partial': return 'text-amber-600 dark:text-amber-400';
    case 'processing': return 'text-indigo-600 dark:text-indigo-400';
    case 'failed': return 'text-rose-600 dark:text-rose-400';
    default: return 'text-slate-500';
  }
};

const getItemStatusBadge = (st) => {
  switch (st) {
    case 'completed':
    case 'success': return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
    case 'skipped': return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
    case 'failed': return 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300';
    default: return 'bg-slate-100 text-slate-700';
  }
};

const formatValue = (val) => {
  if (val === null || val === undefined) return '-';
  if (typeof val === 'object') {
    return Object.entries(val)
      .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`)
      .join(' | ');
  }
  return String(val);
};

onMounted(() => {
  fetchOperations();
});
</script>
