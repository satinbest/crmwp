<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2.5">
          <span>⏱️</span>
          <span>تایم‌لاین جامع فعالیت‌ها و تعاملات (Activities)</span>
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          ثبت رخدادهای تجاری، سفارشات، تعاملات مشتریان، برچسب‌ها و وظایف پرسنل
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchActivities"
          :disabled="loading"
          class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors shadow-xs"
          title="بروزرسانی"
        >
          <span :class="{'inline-block animate-spin': loading}">↺</span>
        </button>
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-4 shadow-xs flex flex-wrap items-center gap-3 text-xs">
      <select
        v-model="filters.entity_type"
        @change="fetchActivities"
        class="rounded-xl px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
      >
        <option value="">همه موجودیت‌ها</option>
        <option value="customer">مشتریان (Customer)</option>
        <option value="order">سفارش‌ها (Order)</option>
        <option value="product">محصولات (Product)</option>
        <option value="task">وظایف (Task)</option>
      </select>

      <select
        v-model="filters.action_type"
        @change="fetchActivities"
        class="rounded-xl px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
      >
        <option value="">همه انواع عملیات</option>
        <option value="order_created">ثبت سفارش</option>
        <option value="order_status_changed">تغییر وضعیت سفارش</option>
        <option value="task_created">ثبت وظیفه</option>
        <option value="task_completed">تکمیل وظیفه</option>
        <option value="tag_added">افزودن برچسب</option>
        <option value="tag_removed">حذف برچسب</option>
        <option value="note_added">ثبت یادداشت</option>
      </select>
    </div>

    <!-- Timeline Container -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-6 shadow-xs">
      <div v-if="loading && activities.length === 0" class="py-12 text-center text-xs text-slate-400">
        در حال بارگذاری فعالیت‌ها...
      </div>

      <div v-else-if="activities.length === 0" class="py-12 text-center text-xs text-slate-400">
        هیچ فعالیتی ثبت نشده است.
      </div>

      <div v-else class="space-y-4 relative before:absolute before:top-2 before:bottom-2 before:right-4 before:w-0.5 before:bg-slate-100 dark:before:bg-slate-800">
        <div
          v-for="act in activities"
          :key="act.id"
          class="flex items-start gap-4 pr-2 relative text-xs"
        >
          <!-- Timeline Dot -->
          <div
            class="w-5 h-5 rounded-full border-2 border-white dark:border-slate-900 flex items-center justify-center shrink-0 mt-0.5 shadow-xs text-[10px]"
            :class="getEntityColor(act.entity_type)"
          >
            ●
          </div>

          <!-- Content Card -->
          <div class="flex-1 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800 space-y-2">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
              <div class="flex items-center gap-2">
                <span class="font-bold text-sm text-slate-900 dark:text-white">
                  {{ formatAction(act.action_type) }}
                </span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                  <span class="dir-ltr">{{ act.entity_type }}</span> #{{ toPersianDigits(act.entity_id) }}
                </span>
              </div>
              <span class="text-[11px] text-slate-400">{{ formatDateTime(act.created_at) }}</span>
            </div>

            <!-- User Context -->
            <div v-if="act.user_name" class="text-[11px] text-slate-500 dark:text-slate-400">
              توسط کاربر: <strong class="text-slate-700 dark:text-slate-300">{{ act.user_name }}</strong>
            </div>

            <!-- Details Payload -->
            <div v-if="act.details && Object.keys(act.details).length > 0" class="pt-1">
              <div class="p-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-700/60 text-[11px] font-mono text-slate-600 dark:text-slate-300 overflow-x-auto">
                <div v-for="(val, key) in act.details" :key="key" class="flex gap-2">
                  <span class="text-slate-400">{{ key }}:</span>
                  <span class="font-semibold">{{ val }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import { toPersianDigits, formatDateTime } from '@/utils/formatters';

const notification = useNotificationStore();
const activities = ref([]);
const loading = ref(false);

const filters = ref({
  entity_type: '',
  action_type: '',
});

const fetchActivities = async () => {
  loading.value = true;
  try {
    const params = {
      entity_type: filters.value.entity_type,
      action_type: filters.value.action_type,
    };
    const res = await apiClient.get('/activities', { params });
    activities.value = res.data || [];
  } catch (err) {
    notification.error('خطا در بارگذاری فعالیت‌ها.');
  } finally {
    loading.value = false;
  }
};

const getEntityColor = (entity) => {
  switch (entity) {
    case 'order': return 'bg-emerald-500 text-white';
    case 'customer': return 'bg-indigo-500 text-white';
    case 'product': return 'bg-amber-500 text-white';
    case 'task': return 'bg-blue-500 text-white';
    default: return 'bg-slate-400 text-white';
  }
};

const formatAction = (type) => {
  const map = {
    'order_created': 'ثبت سفارش در ووکامرس',
    'order_status_changed': 'تغییر وضعیت سفارش',
    'task_created': 'ایجاد وظیفه جدید',
    'task_completed': 'تکمیل وظیفه',
    'task_status_changed': 'تغییر وضعیت وظیفه',
    'tag_added': 'افزودن برچسب به مشتری',
    'tag_removed': 'حذف برچسب مشتری',
    'note_added': 'ثبت یادداشت جدید برای مشتری',
    'product_price_changed': 'تغییر قیمت محصول',
    'product_stock_changed': 'تغییر موجودی انبار',
  };
  return map[type] || type;
};

onMounted(() => {
  fetchActivities();
});
</script>
