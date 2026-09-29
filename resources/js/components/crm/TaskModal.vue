<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div
      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden transition-all text-xs"
      @click.stop
    >
      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <h3 class="font-bold text-sm text-slate-900 dark:text-white">
          {{ isEdit ? 'ویرایش وظیفه و پیگیری' : 'ثبت وظیفه جدید' }}
        </h3>
        <button
          @click="close"
          class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          ✕
        </button>
      </div>

      <!-- Form Body -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <!-- Title -->
        <div>
          <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
            عنوان وظیفه <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.title"
            type="text"
            required
            placeholder="مثال: تماس با مشتری جهت هماهنگی ارسال سفارش..."
            class="w-full rounded-xl px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
          />
        </div>

        <!-- Description -->
        <div>
          <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">شرح تکمیلی و نکات</label>
          <textarea
            v-model="form.description"
            rows="3"
            placeholder="جزئیات وظیفه، سوابق صحبت با مشتری یا دستورالعمل..."
            class="w-full rounded-xl px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 resize-none"
          ></textarea>
        </div>

        <!-- Priority & Status Row -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">اولویت</label>
            <select
              v-model="form.priority"
              class="w-full rounded-xl px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
            >
              <option value="low">کم (Low)</option>
              <option value="normal">عادی (Normal)</option>
              <option value="high">بالا (High)</option>
              <option value="urgent">فوری و اضطراری (Urgent)</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">وضعیت</label>
            <select
              v-model="form.status"
              class="w-full rounded-xl px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
            >
              <option value="pending">در انتظار انجام (Pending)</option>
              <option value="in_progress">در حال انجام (In Progress)</option>
              <option value="completed">تکمیل شده (Completed)</option>
              <option value="cancelled">لغو شده (Cancelled)</option>
            </select>
          </div>
        </div>

        <!-- Due Date & Assignee Row -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">موعد انجام (Due Date)</label>
            <input
              v-model="form.due_at"
              type="datetime-local"
              class="w-full rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">ارجاع به همکار</label>
            <select
              v-model="form.assigned_to"
              class="w-full rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
            >
              <option :value="null">بدون ارجاع (من اختصاص دهید)</option>
              <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.full_name || user.username }} ({{ user.username }})
              </option>
            </select>
          </div>
        </div>

        <!-- Customer ID & Order ID -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">شناسه مشتری (اختیاری)</label>
            <input
              v-model.number="form.customer_id"
              type="number"
              placeholder="مثال: ۱۰۵"
              class="w-full rounded-xl px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">شناسه سفارش (اختیاری)</label>
            <input
              v-model.number="form.order_id"
              type="number"
              placeholder="مثال: ۱۰۰۲"
              class="w-full rounded-xl px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
            />
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-4 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
          <button
            type="button"
            @click="close"
            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-medium"
          >
            انصراف
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-md shadow-indigo-500/20 disabled:opacity-50"
          >
            {{ saving ? 'در حال ذخیره...' : (isEdit ? 'بروزرسانی وظیفه' : 'ایجاد وظیفه') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';

const props = defineProps({
  isOpen: Boolean,
  task: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'saved']);
const notification = useNotificationStore();

const isEdit = ref(false);
const saving = ref(false);
const users = ref([]);

const form = ref({
  title: '',
  description: '',
  priority: 'normal',
  status: 'pending',
  due_at: '',
  assigned_to: null,
  customer_id: null,
  order_id: null,
});

const fetchUsers = async () => {
  try {
    const res = await apiClient.get('/users');
    users.value = res.data || [];
  } catch (e) {
    //
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    fetchUsers();
    if (props.task) {
      isEdit.value = true;
      form.value = {
        title: props.task.title || '',
        description: props.task.description || '',
        priority: props.task.priority || 'normal',
        status: props.task.status || 'pending',
        due_at: props.task.due_date ? props.task.due_date.replace(' ', 'T').slice(0, 16) : '',
        assigned_to: props.task.assigned_user_id || null,
        customer_id: props.task.wc_customer_id || null,
        order_id: props.task.wc_order_id || null,
      };
    } else {
      isEdit.value = false;
      form.value = {
        title: '',
        description: '',
        priority: 'normal',
        status: 'pending',
        due_at: '',
        assigned_to: null,
        customer_id: null,
        order_id: null,
      };
    }
  }
});

const close = () => {
  emit('close');
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    if (isEdit.value && props.task?.id) {
      await apiClient.patch(`/tasks/${props.task.id}`, form.value);
      notification.success('وظیفه با موفقیت ویرایش شد.');
    } else {
      await apiClient.post('/tasks', form.value);
      notification.success('وظیفه جدید با موفقیت ثبت شد.');
    }
    emit('saved');
    close();
  } catch (err) {
    notification.error(err.message || 'خطا در ذخیره وظیفه.');
  } finally {
    saving.value = false;
  }
};
</script>
