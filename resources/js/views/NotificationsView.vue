<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm">
      <div class="space-y-1">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold shadow-sm">
            <Iconsax name="notification" size="22" />
          </div>
          <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">مرکز اعلان‌ها و رویدادها</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              مشاهده، مدیریت و پیگیری تمامی اعلان‌های سیستمی، وظایف، انبار و سفارش‌ها
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          v-if="notifStore.unreadCount > 0"
          @click="markAllAsRead"
          :disabled="notifStore.markingRead"
          class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 transition-colors flex items-center gap-2 border border-indigo-200/50 dark:border-indigo-800/50"
        >
          <Iconsax name="tick" size="16" />
          <span>{{ notifStore.markingRead ? 'در حال ثبت...' : 'خواندن همه اعلان‌ها' }}</span>
        </button>

        <router-link
          to="/settings/notifications"
          class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors flex items-center gap-2"
        >
          <Iconsax name="settings" size="16" />
          <span>تنظیمات اعلان‌ها</span>
        </router-link>
      </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between">
        <div>
          <div class="text-xs text-slate-400 font-medium">کل اعلان‌ها</div>
          <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">
            {{ notifStore.meta.total || notifStore.notifications.length }}
          </div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center">
          <Iconsax name="notification" size="20" />
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between">
        <div>
          <div class="text-xs text-slate-400 font-medium">خوانده‌نشده</div>
          <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">
            {{ notifStore.unreadCount }}
          </div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
          <Iconsax name="message" size="20" />
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between">
        <div>
          <div class="text-xs text-slate-400 font-medium">خوانده‌شده</div>
          <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
            {{ Math.max(0, (notifStore.meta.total || notifStore.notifications.length) - notifStore.unreadCount) }}
          </div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <Iconsax name="tick" size="20" />
        </div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <!-- Status Filter Tabs -->
        <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs">
          <button
            @click="setStatusFilter('')"
            class="px-3.5 py-1.5 rounded-lg font-bold transition-all"
            :class="statusFilter === '' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
          >
            همه اعلان‌ها
          </button>
          <button
            @click="setStatusFilter('unread')"
            class="px-3.5 py-1.5 rounded-lg font-bold transition-all flex items-center gap-1.5"
            :class="statusFilter === 'unread' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
          >
            <span>خوانده‌نشده</span>
            <span v-if="notifStore.unreadCount > 0" class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
          </button>
          <button
            @click="setStatusFilter('read')"
            class="px-3.5 py-1.5 rounded-lg font-bold transition-all"
            :class="statusFilter === 'read' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
          >
            خوانده‌شده
          </button>
        </div>

        <!-- Dropdown Filters -->
        <div class="flex flex-wrap items-center gap-2.5">
          <!-- Type Filter -->
          <div class="flex items-center gap-1.5">
            <label class="text-xs text-slate-400 font-medium">نوع رویداد:</label>
            <select
              v-model="typeFilter"
              @change="applyFilters"
              class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-800 dark:text-slate-200"
            >
              <option value="">همه انواع رویدادها</option>
              <option value="task_assigned">تخصیص وظیفه</option>
              <option value="low_stock">هشدار کمبود موجودی</option>
              <option value="bulk_operation_completed">پایان عملیات گروهی</option>
              <option value="order_attention">هشدار سفارش</option>
              <option value="system">پیام سیستمی</option>
            </select>
          </div>

          <!-- Priority Filter -->
          <div class="flex items-center gap-1.5">
            <label class="text-xs text-slate-400 font-medium">اولویت:</label>
            <select
              v-model="priorityFilter"
              @change="applyFilters"
              class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-800 dark:text-slate-200"
            >
              <option value="">همه اولویت‌ها</option>
              <option value="urgent">فوری (Urgent)</option>
              <option value="high">بالا (High)</option>
              <option value="normal">عادی (Normal)</option>
              <option value="low">پایین (Low)</option>
            </select>
          </div>

          <!-- Reset Filters -->
          <button
            v-if="hasActiveFilters"
            @click="resetFilters"
            class="text-xs text-rose-600 dark:text-rose-400 hover:underline px-2 py-1 font-medium"
          >
            پاک‌سازی فیلترها
          </button>
        </div>
      </div>
    </div>

    <!-- Notifications List -->
    <div class="space-y-3">
      <!-- Loading State -->
      <div v-if="notifStore.loading" class="bg-white dark:bg-slate-900 rounded-3xl p-12 border border-slate-200/80 dark:border-slate-800/80 text-center text-slate-400 flex flex-col items-center gap-3">
        <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        <span class="text-sm font-medium">در حال بارگذاری اعلان‌ها...</span>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="notifStore.notifications.length === 0"
        class="bg-white dark:bg-slate-900 rounded-3xl p-12 border border-slate-200/80 dark:border-slate-800/80 text-center space-y-3"
      >
        <div class="w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mx-auto">
          <Iconsax name="notification" size="32" />
        </div>
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">اعلانی یافت نشد</h3>
        <p class="text-xs text-slate-400 max-w-sm mx-auto">
          هیچ اعلانی مطابق با فیلترهای انتخابی شما در سامانه وجود ندارد.
        </p>
        <button
          v-if="hasActiveFilters"
          @click="resetFilters"
          class="px-4 py-2 rounded-xl text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition-colors inline-block"
        >
          مشاهده تمام اعلان‌ها
        </button>
      </div>

      <!-- Notification Cards -->
      <div
        v-for="item in notifStore.notifications"
        :key="item.id"
        class="bg-white dark:bg-slate-900 rounded-2xl p-5 border transition-all hover:shadow-md flex flex-col sm:flex-row sm:items-center justify-between gap-4"
        :class="item.is_read ? 'border-slate-200/80 dark:border-slate-800/80 opacity-90' : 'border-indigo-200 dark:border-indigo-900/60 bg-gradient-to-r from-indigo-50/20 to-transparent dark:from-indigo-950/20'"
      >
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <!-- Icon -->
          <div
            class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 shadow-sm"
            :class="getTypeIconClass(item.type, item.priority)"
          >
            <Iconsax :name="getTypeIconName(item.type)" size="24" />
          </div>

          <!-- Body -->
          <div class="space-y-1.5 min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-bold text-sm text-slate-900 dark:text-white">
                {{ item.title }}
              </span>

              <!-- Priority Badge -->
              <span
                v-if="item.priority === 'urgent'"
                class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-rose-100 dark:bg-rose-950 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900"
              >
                فوری
              </span>
              <span
                v-else-if="item.priority === 'high'"
                class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-900"
              >
                اولویت بالا
              </span>

              <!-- Store Tag -->
              <span
                v-if="item.store_name"
                class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500"
              >
                {{ item.store_name }}
              </span>

              <!-- Unread status tag -->
              <span
                v-if="!item.is_read"
                class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400"
              >
                جدید
              </span>
            </div>

            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
              {{ item.message }}
            </p>

            <div class="flex items-center gap-3 text-[11px] text-slate-400 pt-1">
              <span>زمان ثبت: {{ formatPersianDate(item.created_at) }}</span>
              <span v-if="item.read_at" class="text-emerald-600 dark:text-emerald-400">
                ✓ خوانده شده در: {{ formatPersianDate(item.read_at) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
          <button
            v-if="item.action_url"
            @click="navigateToEntity(item)"
            class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition-colors flex items-center gap-1.5 shadow-sm shadow-indigo-600/20"
          >
            <span>مشاهده جزییات</span>
            <Iconsax name="arrow-left" size="14" />
          </button>

          <button
            v-if="!item.is_read"
            @click="notifStore.markAsRead(item.id)"
            class="p-2 rounded-xl text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 transition-colors"
            title="علامت به عنوان خوانده‌شده"
          >
            <Iconsax name="tick" size="18" />
          </button>

          <button
            @click="deleteNotification(item.id)"
            class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
            title="حذف اعلان"
          >
            <Iconsax name="trash" size="18" />
          </button>
        </div>
      </div>
    </div>

    <!-- Pagination Controls -->
    <div
      v-if="notifStore.meta.total_pages > 1"
      class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-between text-xs"
    >
      <div class="text-slate-400">
        صفحه {{ notifStore.meta.current_page }} از {{ notifStore.meta.total_pages }} (مجموع {{ notifStore.meta.total }} مورد)
      </div>

      <div class="flex items-center gap-1.5">
        <button
          @click="changePage(notifStore.meta.current_page - 1)"
          :disabled="notifStore.meta.current_page <= 1"
          class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
        >
          قبلی
        </button>

        <span class="px-3 py-1.5 font-bold text-indigo-600 dark:text-indigo-400">
          {{ notifStore.meta.current_page }}
        </span>

        <button
          @click="changePage(notifStore.meta.current_page + 1)"
          :disabled="notifStore.meta.current_page >= notifStore.meta.total_pages"
          class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
        >
          بعدی
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useNotificationCenterStore } from '@/stores/notificationCenter';
import Iconsax from '@/components/icons/Iconsax.vue';

const router = useRouter();
const route = useRoute();
const notifStore = useNotificationCenterStore();

const statusFilter = ref(route.query.status || '');
const typeFilter = ref(route.query.type || '');
const priorityFilter = ref(route.query.priority || '');
const currentPage = ref(1);

const hasActiveFilters = computed(() => {
  return statusFilter.value !== '' || typeFilter.value !== '' || priorityFilter.value !== '';
});

const loadNotifications = async () => {
  const params = {
    page: currentPage.value,
    per_page: 15,
  };
  if (statusFilter.value) params.status = statusFilter.value;
  if (typeFilter.value) params.type = typeFilter.value;
  if (priorityFilter.value) params.priority = priorityFilter.value;

  await notifStore.fetchNotifications(params);
};

const setStatusFilter = (status) => {
  statusFilter.value = status;
  currentPage.value = 1;
  loadNotifications();
};

const applyFilters = () => {
  currentPage.value = 1;
  loadNotifications();
};

const resetFilters = () => {
  statusFilter.value = '';
  typeFilter.value = '';
  priorityFilter.value = '';
  currentPage.value = 1;
  loadNotifications();
};

const changePage = (page) => {
  currentPage.value = page;
  loadNotifications();
};

const markAllAsRead = async () => {
  await notifStore.markAllAsRead();
  loadNotifications();
};

const deleteNotification = async (id) => {
  if (confirm('آیا از حذف این اعلان اطمینان دارید؟')) {
    await notifStore.deleteNotification(id);
  }
};

const navigateToEntity = async (item) => {
  if (!item.is_read) {
    await notifStore.markAsRead(item.id);
  }
  if (item.action_url) {
    router.push(item.action_url);
  }
};

const getTypeIconName = (type) => {
  if (type.startsWith('task_')) return 'task';
  if (type.startsWith('bulk_')) return 'bulk';
  if (type === 'low_stock') return 'inventory';
  if (type.startsWith('order_')) return 'orders';
  return 'notification';
};

const getTypeIconClass = (type, priority) => {
  if (type === 'low_stock' || priority === 'urgent') {
    return 'bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400';
  }
  if (type.startsWith('task_')) {
    return 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400';
  }
  if (type.startsWith('bulk_')) {
    return 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400';
  }
  if (type.startsWith('order_')) {
    return 'bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400';
  }
  return 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400';
};

const formatPersianDate = (isoString) => {
  if (!isoString) return '';
  const date = new Date(isoString.replace(' ', 'T'));
  return new Intl.DateTimeFormat('fa-IR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
};

onMounted(() => {
  loadNotifications();
});
</script>
