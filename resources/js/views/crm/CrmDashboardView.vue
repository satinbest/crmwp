<template>
  <div class="space-y-6">
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2.5">
          <span>👥</span>
          <span>پیشخوان ارتباط با مشتریان (CRM)</span>
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          مدیریت هوشمند پیگیری‌ها، وظایف، برچسب‌ها و بخش‌بندی مشتریان ووکامرس
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchSummary"
          :disabled="loading"
          class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors shadow-xs"
          title="بروزرسانی داده‌ها"
        >
          <span :class="{'inline-block animate-spin': loading}">↺</span>
        </button>

        <button
          @click="openTaskModal(null)"
          class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition-all flex items-center gap-1.5"
        >
          <span>+ وظیفه جدید</span>
        </button>

        <router-link
          to="/crm/segments"
          class="px-4 py-2.5 rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/60 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-bold text-xs hover:bg-indigo-100 transition-all"
        >
          سگمنت‌های هوشمند
        </router-link>
      </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <!-- Open Tasks -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-1">
        <div class="text-[11px] font-semibold text-slate-400 flex items-center justify-between">
          <span>وظایف باز</span>
          <span class="text-indigo-500">📋</span>
        </div>
        <div class="text-xl font-extrabold text-slate-900 dark:text-white">
          {{ toPersianDigits(summary?.kpis?.open_tasks ?? 0) }}
        </div>
        <div class="text-[10px] text-slate-400">در حال انجام / معلق</div>
      </div>

      <!-- Overdue Tasks -->
      <div class="p-4 rounded-2xl bg-rose-50/60 dark:bg-rose-950/20 border border-rose-200/80 dark:border-rose-900/40 shadow-xs space-y-1">
        <div class="text-[11px] font-bold text-rose-700 dark:text-rose-400 flex items-center justify-between">
          <span>وظایف معوقه</span>
          <span>⚠️</span>
        </div>
        <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400">
          {{ toPersianDigits(summary?.kpis?.overdue_tasks ?? 0) }}
        </div>
        <div class="text-[10px] text-rose-600/70 dark:text-rose-400/70">از موعد گذشته</div>
      </div>

      <!-- Tasks Due Today -->
      <div class="p-4 rounded-2xl bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-900/40 shadow-xs space-y-1">
        <div class="text-[11px] font-bold text-amber-700 dark:text-amber-400 flex items-center justify-between">
          <span>سررسید امروز</span>
          <span>⏰</span>
        </div>
        <div class="text-xl font-extrabold text-amber-600 dark:text-amber-400">
          {{ toPersianDigits(summary?.kpis?.due_today ?? 0) }}
        </div>
        <div class="text-[10px] text-amber-600/70 dark:text-amber-400/70">پیگیری تا پایان روز</div>
      </div>

      <!-- Upcoming Tasks -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-1">
        <div class="text-[11px] font-semibold text-slate-400 flex items-center justify-between">
          <span>برنامه‌ریزی آینده</span>
          <span class="text-blue-500">📅</span>
        </div>
        <div class="text-xl font-extrabold text-slate-900 dark:text-white">
          {{ toPersianDigits(summary?.kpis?.upcoming_tasks ?? 0) }}
        </div>
        <div class="text-[10px] text-slate-400">روزهای آتی</div>
      </div>

      <!-- Segments Count -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-1">
        <div class="text-[11px] font-semibold text-slate-400 flex items-center justify-between">
          <span>سگمنت‌های پویا</span>
          <span class="text-violet-500">🎯</span>
        </div>
        <div class="text-xl font-extrabold text-slate-900 dark:text-white">
          {{ toPersianDigits(summary?.kpis?.segments_count ?? 0) }}
        </div>
        <div class="text-[10px] text-slate-400">بخش‌بندی فعال</div>
      </div>

      <!-- Tags Count -->
      <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-1">
        <div class="text-[11px] font-semibold text-slate-400 flex items-center justify-between">
          <span>برچسب‌های مشتری</span>
          <span class="text-emerald-500">🏷️</span>
        </div>
        <div class="text-xl font-extrabold text-slate-900 dark:text-white">
          {{ toPersianDigits(summary?.kpis?.tags_count ?? 0) }}
        </div>
        <div class="text-[10px] text-slate-400">برچسب تعریف‌شده</div>
      </div>
    </div>

    <!-- Main Grid: Attention Tasks & Customers Follow-up -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Tasks Needing Attention -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">وظایف نیازمند اقدام فوری</h2>
          </div>
          <router-link to="/crm/tasks?view=overdue" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
            مشاهده همه وظایف ←
          </router-link>
        </div>

        <div v-if="!summary?.tasks_needing_attention || summary.tasks_needing_attention.length === 0" class="py-8 text-center text-xs text-slate-400">
          ✅ در حال حاضر هیچ وظیفه معوقه یا بحرانی ثبت نشده است.
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="task in summary.tasks_needing_attention"
            :key="task.id"
            class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 text-xs transition-colors hover:border-slate-300"
          >
            <div class="space-y-1 min-w-0 pr-2">
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-900 dark:text-white truncate">{{ task.title }}</span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="getPriorityClass(task.priority)"
                >
                  {{ getPriorityLabel(task.priority) }}
                </span>
              </div>
              <div class="text-[11px] text-slate-400 flex items-center gap-3">
                <span v-if="task.assigned_user_name">👤 ارجاع: {{ task.assigned_user_name }}</span>
                <span v-if="task.due_date" class="text-rose-500">⏰ مهلت: {{ formatDate(task.due_date) }}</span>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <button
                @click="completeTask(task.id)"
                class="px-2.5 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 font-bold text-[11px] transition-colors"
                title="علامت‌گذاری به عنوان انجام شده"
              >
                ✓ تکمیل
              </button>
              <button
                @click="openTaskModal(task)"
                class="p-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-200/60 dark:hover:bg-slate-700 transition-colors"
                title="ویرایش وظیفه"
              >
                ✏️
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Customers Requiring Follow-up -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">مشتریان نیازمند پیگیری</h2>
          </div>
          <router-link to="/customers" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
            بانک مشتریان ←
          </router-link>
        </div>

        <div v-if="!summary?.customers_requiring_follow_up || summary.customers_requiring_follow_up.length === 0" class="py-8 text-center text-xs text-slate-400">
          هیچ مشتری اولویت‌داری در صف پیگیری امروز نیست.
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="cust in summary.customers_requiring_follow_up"
            :key="cust.id"
            class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 text-xs hover:border-slate-300 transition-colors"
          >
            <div class="space-y-1">
              <div class="font-bold text-slate-900 dark:text-white">{{ cust.name }}</div>
              <div class="text-[11px] text-slate-400 flex items-center gap-2">
                <span class="dir-ltr">{{ cust.email || 'بدون ایمیل' }}</span>
                <span>•</span>
                <span>{{ toPersianDigits(cust.orders_count) }} سفارش</span>
              </div>
            </div>

            <router-link
              :to="'/customers/' + cust.id"
              class="px-3 py-1.5 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-bold hover:bg-indigo-100 transition-colors shrink-0"
            >
              مشاهده پرونده
            </router-link>
          </div>
        </div>
      </div>
    </div>

    <!-- Secondary Grid: Activities Timeline & Segments -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Activities Timeline (2 Cols) -->
      <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-indigo-500 font-bold">⏱️</span>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">رویدادها و فعالیت‌های اخیر سامانه</h2>
          </div>
          <router-link to="/crm/activities" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
            تایم‌لاین کامل فعالیت‌ها ←
          </router-link>
        </div>

        <div v-if="!summary?.recent_activities || summary.recent_activities.length === 0" class="py-8 text-center text-xs text-slate-400">
          هنوز فعالیتی ثبت نشده است.
        </div>

        <div v-else class="space-y-3 relative before:absolute before:top-2 before:bottom-2 before:right-4 before:w-0.5 before:bg-slate-100 dark:before:bg-slate-800">
          <div
            v-for="act in summary.recent_activities"
            :key="act.id"
            class="flex items-start gap-3.5 pr-2 relative text-xs"
          >
            <div class="w-4 h-4 rounded-full bg-indigo-500 border-2 border-white dark:border-slate-900 shrink-0 mt-0.5 shadow-xs"></div>
            <div class="flex-1 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800">
              <div class="flex items-center justify-between">
                <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatActionType(act.action_type) }}</span>
                <span class="text-[10px] text-slate-400">{{ formatDateTime(act.created_at) }}</span>
              </div>
              <div v-if="act.user_name" class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                توسط کاربر: <span class="font-semibold">{{ act.user_name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Segments (1 Col) -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">سگمنت‌های هوشمند</h2>
          <router-link to="/crm/segments" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
            مدیریت ←
          </router-link>
        </div>

        <div v-if="!summary?.recent_segments || summary.recent_segments.length === 0" class="py-8 text-center text-xs text-slate-400">
          هیچ سگمنتی ایجاد نشده است.
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="seg in summary.recent_segments"
            :key="seg.id"
            class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 space-y-1.5"
          >
            <div class="flex items-center justify-between">
              <div class="font-bold text-xs text-slate-900 dark:text-white">{{ seg.name }}</div>
              <router-link
                :to="'/crm/segments?id=' + seg.id"
                class="text-[11px] text-indigo-600 dark:text-indigo-400 font-bold hover:underline"
              >
                بررسی
              </router-link>
            </div>
            <div v-if="seg.description" class="text-[11px] text-slate-400 truncate">
              {{ seg.description }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Task Modal Component -->
    <TaskModal
      :is-open="isTaskModalOpen"
      :task="selectedTask"
      @close="isTaskModalOpen = false"
      @saved="fetchSummary"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import TaskModal from '@/components/crm/TaskModal.vue';
import { toPersianDigits, formatDate, formatDateTime } from '@/utils/formatters';

const notification = useNotificationStore();
const summary = ref(null);
const loading = ref(false);

const isTaskModalOpen = ref(false);
const selectedTask = ref(null);

const fetchSummary = async () => {
  loading.value = true;
  try {
    const res = await apiClient.get('/crm/summary');
    summary.value = res.data;
  } catch (err) {
    notification.error('خطا در دریافت خلاصه اطلاعات CRM.');
  } finally {
    loading.value = false;
  }
};

const openTaskModal = (task = null) => {
  selectedTask.value = task;
  isTaskModalOpen.value = true;
};

const completeTask = async (taskId) => {
  try {
    await apiClient.post(`/tasks/${taskId}/complete`);
    notification.success('وظیفه با موفقیت تکمیل شد.');
    await fetchSummary();
  } catch (err) {
    notification.error('خطا در تکمیل وظیفه.');
  }
};

const getPriorityClass = (priority) => {
  switch (priority) {
    case 'urgent': return 'bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-400';
    case 'high': return 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400';
    case 'low': return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';
    default: return 'bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-400';
  }
};

const getPriorityLabel = (priority) => {
  switch (priority) {
    case 'urgent': return 'فوری';
    case 'high': return 'بالا';
    case 'low': return 'کم';
    default: return 'عادی';
  }
};

const formatActionType = (type) => {
  const map = {
    'task_created': 'ایجاد وظیفه جدید',
    'task_completed': 'تکمیل وظیفه پیگیری',
    'task_status_changed': 'تغییر وضعیت وظیفه',
    'tag_added': 'افزودن برچسب به مشتری',
    'tag_removed': 'حذف برچسب مشتری',
    'note_added': 'ثبت یادداشت جدید',
    'order_created': 'ثبت سفارش جدید در ووکامرس',
    'order_status_changed': 'تغییر وضعیت سفارش',
  };
  return map[type] || type;
};

onMounted(() => {
  fetchSummary();
});
</script>
