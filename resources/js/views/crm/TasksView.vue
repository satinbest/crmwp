<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2.5">
          <span>📋</span>
          <span>مدیریت وظایف و پیگیری‌ها (Tasks)</span>
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          سازماندهی پیگیری سفارشات، تماس‌های مشتریان و وظایف پرسنل فروشگاه
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchTasks"
          :disabled="loading"
          class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors shadow-xs"
          title="بروزرسانی"
        >
          <span :class="{'inline-block animate-spin': loading}">↺</span>
        </button>

        <button
          @click="openModal(null)"
          class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition-all flex items-center gap-1.5"
        >
          <span>+ ثبت وظیفه جدید</span>
        </button>
      </div>
    </div>

    <!-- Tabs & Filters Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-4 shadow-xs space-y-3">
      <!-- Tabs -->
      <div class="flex items-center gap-1 overflow-x-auto pb-1 text-xs border-b border-slate-100 dark:border-slate-800">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id; fetchTasks()"
          class="px-3 py-2 rounded-xl font-bold transition-all shrink-0 flex items-center gap-1.5"
          :class="activeTab === tab.id ? 'bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400' : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/60'"
        >
          <span>{{ tab.title }}</span>
        </button>
      </div>

      <!-- Filters Row -->
      <div class="flex flex-wrap items-center gap-3 text-xs">
        <input
          v-model="filters.search"
          type="text"
          placeholder="جستجوی عنوان یا توضیحات وظیفه..."
          @keyup.enter="fetchTasks"
          class="flex-1 min-w-[200px] rounded-xl px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
        />

        <select
          v-model="filters.priority"
          @change="fetchTasks"
          class="rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
        >
          <option value="">همه اولویت‌ها</option>
          <option value="urgent">فوری (Urgent)</option>
          <option value="high">بالا (High)</option>
          <option value="normal">عادی (Normal)</option>
          <option value="low">کم (Low)</option>
        </select>

        <select
          v-model="filters.status"
          @change="fetchTasks"
          class="rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
        >
          <option value="">همه وضعیت‌ها</option>
          <option value="pending">در انتظار</option>
          <option value="in_progress">در حال انجام</option>
          <option value="completed">تکمیل شده</option>
          <option value="cancelled">لغو شده</option>
        </select>
      </div>
    </div>

    <!-- Tasks List Table / Cards -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
      <div v-if="loading && tasks.length === 0" class="py-12 text-center text-xs text-slate-400">
        در حال بارگذاری وظایف...
      </div>

      <div v-else-if="tasks.length === 0" class="py-12 text-center text-xs text-slate-400 space-y-2">
        <div>هیچ وظیفه‌ای با این مشخصات یافت نشد.</div>
        <button @click="openModal(null)" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline">
          ثبت وظیفه جدید
        </button>
      </div>

      <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
        <div
          v-for="task in tasks"
          :key="task.id"
          class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-3 hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors text-xs"
        >
          <!-- Left: Checkbox & Info -->
          <div class="flex items-start gap-3 min-w-0 pr-1">
            <input
              type="checkbox"
              :checked="task.status === 'completed'"
              @change="toggleStatus(task)"
              class="w-4 h-4 mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
              title="تغییر وضعیت انجام"
            />

            <div class="space-y-1 min-w-0">
              <div class="flex items-center gap-2">
                <span
                  class="font-bold text-slate-900 dark:text-white"
                  :class="{'line-through text-slate-400 dark:text-slate-500': task.status === 'completed'}"
                >
                  {{ task.title }}
                </span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="getPriorityClass(task.priority)"
                >
                  {{ getPriorityLabel(task.priority) }}
                </span>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="getStatusClass(task.status)"
                >
                  {{ getStatusLabel(task.status) }}
                </span>
              </div>

              <p v-if="task.description" class="text-slate-500 dark:text-slate-400 line-clamp-1">
                {{ task.description }}
              </p>

              <div class="flex flex-wrap items-center gap-3 text-[11px] text-slate-400 pt-0.5">
                <span v-if="task.assigned_user_name">👤 مسوول: <strong class="text-slate-700 dark:text-slate-300">{{ task.assigned_user_name }}</strong></span>
                <span v-if="task.due_date" :class="isOverdue(task) ? 'text-rose-500 font-bold' : 'text-slate-500'">
                  ⏰ موعد: {{ task.due_date }}
                </span>
                <router-link
                  v-if="task.wc_customer_id"
                  :to="'/customers/' + task.wc_customer_id"
                  class="text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                  مشتری #{{ task.wc_customer_id }}
                </router-link>
                <router-link
                  v-if="task.wc_order_id"
                  :to="'/orders/' + task.wc_order_id"
                  class="text-indigo-600 dark:text-indigo-400 hover:underline"
                >
                  سفارش #{{ task.wc_order_id }}
                </router-link>
              </div>
            </div>
          </div>

          <!-- Right: Actions -->
          <div class="flex items-center gap-1.5 shrink-0 self-end md:self-center">
            <button
              @click="openModal(task)"
              class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
              title="ویرایش"
            >
              ✏️
            </button>
            <button
              @click="deleteTask(task.id)"
              class="p-2 rounded-xl border border-rose-200 dark:border-rose-900 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950"
              title="حذف"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Task Modal Component -->
    <TaskModal
      :is-open="isModalOpen"
      :task="selectedTask"
      @close="isModalOpen = false"
      @saved="fetchTasks"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import TaskModal from '@/components/crm/TaskModal.vue';

const route = useRoute();
const notification = useNotificationStore();

const tasks = ref([]);
const loading = ref(false);
const activeTab = ref(route.query.view || 'all');

const isModalOpen = ref(false);
const selectedTask = ref(null);

const tabs = [
  { id: 'all', title: 'همه وظایف' },
  { id: 'my', title: 'وظایف من' },
  { id: 'today', title: 'سررسید امروز' },
  { id: 'upcoming', title: 'آینده' },
  { id: 'overdue', title: 'معوقه' },
  { id: 'completed', title: 'تکمیل شده' },
];

const filters = ref({
  search: '',
  priority: '',
  status: '',
});

const fetchTasks = async () => {
  loading.value = true;
  try {
    const params = {
      view: activeTab.value,
      search: filters.value.search,
      priority: filters.value.priority,
      status: filters.value.status,
    };
    const res = await apiClient.get('/tasks', { params });
    tasks.value = res.data || [];
  } catch (err) {
    notification.error('خطا در دریافت وظایف.');
  } finally {
    loading.value = false;
  }
};

const openModal = (task = null) => {
  selectedTask.value = task;
  isModalOpen.value = true;
};

const toggleStatus = async (task) => {
  try {
    if (task.status === 'completed') {
      await apiClient.post(`/tasks/${task.id}/reopen`);
      notification.info('وظیفه مجدداً بازگشایی شد.');
    } else {
      await apiClient.post(`/tasks/${task.id}/complete`);
      notification.success('وظیفه با موفقیت تکمیل شد.');
    }
    await fetchTasks();
  } catch (err) {
    notification.error('خطا در تغییر وضعیت وظیفه.');
  }
};

const deleteTask = async (id) => {
  if (!confirm('آیا از حذف این وظیفه اطمینان دارید؟')) return;
  try {
    await apiClient.delete(`/tasks/${id}`);
    notification.success('وظیفه با موفقیت حذف شد.');
    await fetchTasks();
  } catch (err) {
    notification.error('خطا در حذف وظیفه.');
  }
};

const isOverdue = (task) => {
  if (task.status === 'completed' || task.status === 'cancelled') return false;
  if (!task.due_date) return false;
  return new Date(task.due_date) < new Date();
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

const getStatusClass = (status) => {
  switch (status) {
    case 'completed': return 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400';
    case 'in_progress': return 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-400';
    case 'cancelled': return 'bg-slate-100 dark:bg-slate-800 text-slate-500';
    default: return 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400';
  }
};

const getStatusLabel = (status) => {
  switch (status) {
    case 'completed': return 'تکمیل شده';
    case 'in_progress': return 'در حال انجام';
    case 'cancelled': return 'لغو شده';
    default: return 'در انتظار';
  }
};

onMounted(() => {
  fetchTasks();
});
</script>
