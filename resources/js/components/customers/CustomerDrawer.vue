<template>
  <div v-if="isOpen">
    <!-- Backdrop -->
    <div
      class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-xs transition-opacity"
      @click="close"
    ></div>

    <!-- Drawer Panel (Right Side RTL) -->
    <aside
      class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white dark:bg-slate-900 shadow-2xl border-l border-slate-200 dark:border-slate-800 flex flex-col transform transition-transform duration-300 ease-in-out"
      :class="isOpen ? 'translate-x-0' : 'translate-x-full'"
    >
      <!-- Drawer Header -->
      <div class="p-5 border-b border-slate-100 dark:border-slate-800/80 flex items-start justify-between bg-slate-50/50 dark:bg-slate-800/30">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-lg shadow-md shadow-indigo-500/20 shrink-0">
            {{ customerInitials }}
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="font-bold text-base text-slate-900 dark:text-slate-100 truncate">
                {{ customer?.full_name || 'مشتری بدون نام' }}
              </h3>
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide"
                :class="customer?.customer_type === 'guest'
                  ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800'
                  : 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'"
              >
                {{ customer?.customer_type === 'guest' ? 'کاربر مهمان' : 'مشتری ثبت‌نامی' }}
              </span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2 mt-0.5">
              <span>شناسه: #{{ customer?.id }}</span>
              <span v-if="customer?.username" class="text-slate-300 dark:text-slate-600">•</span>
              <span v-if="customer?.username">@{{ customer?.username }}</span>
            </div>
          </div>
        </div>

        <button
          @click="close"
          class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          title="بستن پنجره"
        >
          <Iconsax name="close" size="20" />
        </button>
      </div>

      <!-- Quick Action Buttons -->
      <div class="p-3 bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2 overflow-x-auto">
        <router-link
          :to="'/customers/' + customer?.id"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-xs transition-colors shrink-0"
        >
          <Iconsax name="eye" size="15" />
          <span>مشاهده پرونده کامل</span>
        </router-link>

        <button
          @click="activeTab = 'notes'; showNoteForm = true"
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shrink-0"
        >
          <Iconsax name="edit" size="14" />
          <span>افزودن یادداشت</span>
        </button>

        <button
          @click="activeTab = 'tags'"
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shrink-0"
        >
          <Iconsax name="tag" size="14" />
          <span>برچسب‌ها</span>
        </button>

        <button
          @click="activeTab = 'tasks'; showTaskForm = true"
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shrink-0"
        >
          <Iconsax name="task" size="14" />
          <span>ایجاد وظیفه</span>
        </button>
      </div>

      <!-- Drawer Content Scrollable Area -->
      <div class="flex-1 overflow-y-auto p-5 space-y-5">
        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-2 gap-3">
          <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">تعداد کل سفارش‌ها</div>
            <div class="text-lg font-bold text-slate-900 dark:text-slate-100 mt-1">
              {{ customer?.orders_count || 0 }} <span class="text-xs font-normal text-slate-400">سفارش</span>
            </div>
          </div>

          <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">مجموع خرید</div>
            <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400 mt-1">
              {{ formatPrice(customer?.total_spent) }}
            </div>
          </div>

          <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">میانگین هر سفارش</div>
            <div class="text-sm font-bold text-slate-800 dark:text-slate-200 mt-1">
              {{ formatPrice(customer?.average_order_value) }}
            </div>
          </div>

          <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
            <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">تاریخ ثبت نام</div>
            <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mt-1.5">
              {{ formatDate(customer?.date_created) }}
            </div>
          </div>
        </div>

        <!-- Contact Information -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-800/30 border border-slate-200/70 dark:border-slate-700/60 space-y-2.5">
          <div class="text-xs font-bold text-slate-900 dark:text-slate-100 mb-2">اطلاعات تماس و نشانی</div>

          <div class="flex items-center gap-2.5 text-xs text-slate-600 dark:text-slate-300">
            <Iconsax name="mail" size="16" class="text-slate-400 shrink-0" />
            <span class="truncate">{{ customer?.email || 'بدون ایمیل' }}</span>
          </div>

          <div class="flex items-center gap-2.5 text-xs text-slate-600 dark:text-slate-300">
            <Iconsax name="phone" size="16" class="text-slate-400 shrink-0" />
            <span dir="ltr">{{ customer?.phone || 'بدون تلفن' }}</span>
          </div>

          <div v-if="customer?.billing?.city || customer?.billing?.address_1" class="flex items-start gap-2.5 text-xs text-slate-600 dark:text-slate-300">
            <span class="text-slate-400 font-bold shrink-0">آدرس:</span>
            <span>
              {{ customer?.billing?.state ? customer.billing.state + '، ' : '' }}
              {{ customer?.billing?.city ? customer.billing.city + '، ' : '' }}
              {{ customer?.billing?.address_1 }}
            </span>
          </div>
        </div>

        <!-- Tags Pills -->
        <div v-if="customer?.tags && customer.tags.length > 0" class="space-y-1.5">
          <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">برچسب‌های CRM:</div>
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="tag in customer.tags"
              :key="tag.id"
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium text-white shadow-2xs"
              :style="{ backgroundColor: tag.color || '#4F46E5' }"
            >
              {{ tag.name }}
            </span>
          </div>
        </div>

        <!-- Drawer Navigation Tabs -->
        <div>
          <div class="flex items-center border-b border-slate-200 dark:border-slate-800">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="flex items-center gap-1.5 px-3 py-2 text-xs font-medium border-b-2 transition-colors -mb-px"
              :class="activeTab === tab.id
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold'
                : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
            >
              <Iconsax :name="tab.icon" size="15" />
              <span>{{ tab.label }}</span>
            </button>
          </div>

          <!-- Tab Content: Orders -->
          <div v-if="activeTab === 'orders'" class="pt-4 space-y-3">
            <div v-if="loadingOrders" class="py-6 text-center text-xs text-slate-400">
              در حال بارگذاری سفارش‌ها از ووکامرس...
            </div>
            <div v-else-if="orders.length === 0" class="py-6 text-center text-xs text-slate-400">
              هیچ سفارشی برای این مشتری یافت نشد.
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="order in orders"
                :key="order.id"
                class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex items-center justify-between"
              >
                <div>
                  <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                    سفارش #{{ order.number }}
                  </div>
                  <div class="text-[11px] text-slate-400 mt-0.5">
                    {{ formatDate(order.date_created) }} • {{ order.items_count }} آیتم
                  </div>
                </div>
                <div class="text-left">
                  <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                    {{ formatPrice(order.total) }}
                  </div>
                  <span class="inline-block text-[10px] px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-medium mt-0.5">
                    {{ order.status_label }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab Content: Notes -->
          <div v-if="activeTab === 'notes'" class="pt-4 space-y-3">
            <!-- New Note Form -->
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 space-y-2">
              <textarea
                v-model="newNoteContent"
                rows="2"
                placeholder="ثبت یادداشت جدید برای مشتری..."
                class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 p-2 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
              ></textarea>
              <div class="flex justify-end">
                <button
                  @click="saveNote"
                  :disabled="submittingNote || !newNoteContent.trim()"
                  class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-medium transition-colors"
                >
                  {{ submittingNote ? 'در حال ثبت...' : 'ثبت یادداشت' }}
                </button>
              </div>
            </div>

            <!-- Notes List -->
            <div v-if="loadingNotes" class="py-4 text-center text-xs text-slate-400">در حال دریافت یادداشت‌ها...</div>
            <div v-else-if="notes.length === 0" class="py-4 text-center text-xs text-slate-400">یادداشتی ثبت نشده است.</div>
            <div v-else class="space-y-2">
              <div
                v-for="note in notes"
                :key="note.id"
                class="p-3 rounded-xl bg-white dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 space-y-1.5"
              >
                <div class="flex items-center justify-between text-[11px] text-slate-400">
                  <span>{{ note.author_name || 'کاربر سیستم' }}</span>
                  <div class="flex items-center gap-2">
                    <span>{{ formatDate(note.created_at) }}</span>
                    <button
                      @click="deleteNote(note.id)"
                      class="text-rose-500 hover:text-rose-700 transition-colors"
                      title="حذف"
                    >
                      <Iconsax name="trash" size="13" />
                    </button>
                  </div>
                </div>
                <div class="text-xs text-slate-700 dark:text-slate-200 whitespace-pre-line leading-relaxed">
                  {{ note.content }}
                </div>
              </div>
            </div>
          </div>

          <!-- Tab Content: Tasks -->
          <div v-if="activeTab === 'tasks'" class="pt-4 space-y-3">
            <!-- Add Task Input -->
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 space-y-2">
              <input
                v-model="newTaskTitle"
                type="text"
                placeholder="عنوان وظیفه جدید..."
                class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 p-2 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
              />
              <div class="flex items-center justify-between">
                <select
                  v-model="newTaskPriority"
                  class="text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 p-1.5"
                >
                  <option value="low">اولویت کم</option>
                  <option value="medium">اولویت متوسط</option>
                  <option value="high">اولویت زیاد</option>
                  <option value="urgent">فوری</option>
                </select>
                <button
                  @click="saveTask"
                  :disabled="submittingTask || !newTaskTitle.trim()"
                  class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-medium transition-colors"
                >
                  {{ submittingTask ? 'در حال ثبت...' : 'افزودن وظیفه' }}
                </button>
              </div>
            </div>

            <!-- Task List -->
            <div v-if="loadingTasks" class="py-4 text-center text-xs text-slate-400">در حال دریافت وظایف...</div>
            <div v-else-if="tasks.length === 0" class="py-4 text-center text-xs text-slate-400">وظیفه‌ای ثبت نشده است.</div>
            <div v-else class="space-y-2">
              <div
                v-for="task in tasks"
                :key="task.id"
                class="p-3 rounded-xl bg-white dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex items-center justify-between"
              >
                <div class="flex items-center gap-2.5">
                  <input
                    type="checkbox"
                    :checked="task.status === 'completed'"
                    @change="toggleTaskStatus(task)"
                    class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4"
                  />
                  <div>
                    <div
                      class="text-xs font-medium"
                      :class="task.status === 'completed' ? 'line-through text-slate-400' : 'text-slate-800 dark:text-slate-200'"
                    >
                      {{ task.title }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">
                      {{ task.creator_user_name || 'کاربر سیستم' }} • {{ formatDate(task.created_at) }}
                    </div>
                  </div>
                </div>
                <span
                  class="text-[10px] px-2 py-0.5 rounded font-medium"
                  :class="{
                    'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300': task.priority === 'low',
                    'bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400': task.priority === 'medium',
                    'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400': task.priority === 'high',
                    'bg-rose-50 text-rose-600 dark:bg-rose-950 dark:text-rose-400': task.priority === 'urgent',
                  }"
                >
                  {{ formatPriority(task.priority) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Tab Content: Tags -->
          <div v-if="activeTab === 'tags'" class="pt-4 space-y-3">
            <div class="flex items-center gap-2">
              <input
                v-model="newTagName"
                type="text"
                placeholder="عنوان برچسب جدید..."
                class="flex-1 text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 p-2 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
              />
              <input
                v-model="newTagColor"
                type="color"
                class="w-9 h-9 rounded-lg border-0 cursor-pointer p-0.5"
                title="انتخاب رنگ"
              />
              <button
                @click="saveTag"
                :disabled="submittingTag || !newTagName.trim()"
                class="px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-medium transition-colors"
              >
                افزودن
              </button>
            </div>

            <!-- Existing Customer Tags -->
            <div class="space-y-2 pt-2">
              <div v-if="tags.length === 0" class="text-xs text-slate-400 py-3 text-center">
                برچسبی اختصاص داده نشده است.
              </div>
              <div v-else class="flex flex-wrap gap-2">
                <div
                  v-for="tag in tags"
                  :key="tag.id"
                  class="flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-medium text-white shadow-2xs"
                  :style="{ backgroundColor: tag.color || '#4F46E5' }"
                >
                  <span>{{ tag.name }}</span>
                  <button
                    @click="removeTag(tag.id)"
                    class="hover:opacity-75 transition-opacity"
                    title="حذف برچسب"
                  >
                    <Iconsax name="close" size="13" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </aside>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import Iconsax from '@/components/icons/Iconsax.vue';

const props = defineProps({
  customer: {
    type: Object,
    default: null,
  },
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'refresh']);
const notification = useNotificationStore();

const activeTab = ref('orders');
const tabs = [
  { id: 'orders', label: 'سفارش‌ها', icon: 'orders' },
  { id: 'notes', label: 'یادداشت‌ها', icon: 'edit' },
  { id: 'tasks', label: 'وظایف', icon: 'task' },
  { id: 'tags', label: 'برچسب‌ها', icon: 'tag' },
];

const customerInitials = computed(() => {
  const name = props.customer?.full_name || props.customer?.first_name || 'U';
  return name.charAt(0).toUpperCase();
});

// Data states
const orders = ref([]);
const loadingOrders = ref(false);

const notes = ref([]);
const loadingNotes = ref(false);
const newNoteContent = ref('');
const submittingNote = ref(false);

const tasks = ref([]);
const loadingTasks = ref(false);
const newTaskTitle = ref('');
const newTaskPriority = ref('medium');
const submittingTask = ref(false);

const tags = ref([]);
const newTagName = ref('');
const newTagColor = ref('#4F46E5');
const submittingTag = ref(false);

const close = () => {
  emit('close');
};

watch(() => props.customer, (newVal) => {
  if (newVal && props.isOpen) {
    loadCustomerData();
  }
}, { immediate: true });

watch(() => props.isOpen, (isOpen) => {
  if (isOpen && props.customer) {
    loadCustomerData();
  }
});

const loadCustomerData = async () => {
  if (!props.customer?.id) return;
  loadOrders();
  loadNotes();
  loadTasks();
  loadTags();
};

const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    const res = await apiClient.get(`/customers/${props.customer.id}/orders`);
    orders.value = res.data || [];
  } catch (e) {
    orders.value = [];
  } finally {
    loadingOrders.value = false;
  }
};

const loadNotes = async () => {
  loadingNotes.value = true;
  try {
    const res = await apiClient.get(`/customers/${props.customer.id}/notes`);
    notes.value = res.data || [];
  } catch (e) {
    notes.value = [];
  } finally {
    loadingNotes.value = false;
  }
};

const saveNote = async () => {
  if (!newNoteContent.value.trim()) return;
  submittingNote.value = true;
  try {
    await apiClient.post(`/customers/${props.customer.id}/notes`, {
      content: newNoteContent.value.trim(),
    });
    newNoteContent.value = '';
    notification.success('یادداشت با موفقیت ثبت شد.');
    loadNotes();
    emit('refresh');
  } catch (e) {
    notification.error(e.message || 'خطا در ثبت یادداشت.');
  } finally {
    submittingNote.value = false;
  }
};

const deleteNote = async (noteId) => {
  try {
    await apiClient.delete(`/customers/${props.customer.id}/notes/${noteId}`);
    notification.success('یادداشت حذف شد.');
    loadNotes();
    emit('refresh');
  } catch (e) {
    notification.error(e.message || 'خطا در حذف یادداشت.');
  }
};

const loadTasks = async () => {
  loadingTasks.value = true;
  try {
    const res = await apiClient.get(`/customers/${props.customer.id}/tasks`);
    tasks.value = res.data || [];
  } catch (e) {
    tasks.value = [];
  } finally {
    loadingTasks.value = false;
  }
};

const saveTask = async () => {
  if (!newTaskTitle.value.trim()) return;
  submittingTask.value = true;
  try {
    await apiClient.post(`/customers/${props.customer.id}/tasks`, {
      title: newTaskTitle.value.trim(),
      priority: newTaskPriority.value,
    });
    newTaskTitle.value = '';
    notification.success('وظیفه با موفقیت ثبت شد.');
    loadTasks();
    emit('refresh');
  } catch (e) {
    notification.error(e.message || 'خطا در ثبت وظیفه.');
  } finally {
    submittingTask.value = false;
  }
};

const toggleTaskStatus = async (task) => {
  const newStatus = task.status === 'completed' ? 'pending' : 'completed';
  try {
    await apiClient.patch(`/customers/${props.customer.id}/tasks/${task.id}`, {
      status: newStatus,
    });
    task.status = newStatus;
    notification.success('وضعیت وظیفه به‌روز شد.');
    emit('refresh');
  } catch (e) {
    notification.error(e.message || 'خطا در به‌روزرسانی وظیفه.');
  }
};

const loadTags = async () => {
  try {
    const res = await apiClient.get(`/customers/${props.customer.id}/tags`);
    tags.value = res.data || [];
  } catch (e) {
    tags.value = [];
  }
};

const saveTag = async () => {
  if (!newTagName.value.trim()) return;
  submittingTag.value = true;
  try {
    await apiClient.post(`/customers/${props.customer.id}/tags`, {
      name: newTagName.value.trim(),
      color: newTagColor.value,
    });
    newTagName.value = '';
    notification.success('برچسب با موفقیت افزوده شد.');
    loadTags();
    emit('refresh');
  } catch (e) {
    notification.error(e.message || 'خطا در افزودن برچسب.');
  } finally {
    submittingTag.value = false;
  }
};

const removeTag = async (tagId) => {
  try {
    await apiClient.delete(`/customers/${props.customer.id}/tags/${tagId}`);
    notification.success('برچسب از مشتری حذف شد.');
    loadTags();
    emit('refresh');
  } catch (e) {
    notification.error(e.message || 'خطا در حذف برچسب.');
  }
};

const formatPrice = (val) => {
  if (!val && val !== 0) return '۰ تومان';
  const num = Math.round(Number(val));
  return new Intl.NumberFormat('fa-IR').format(num) + ' تومان';
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  try {
    return new Date(dateStr).toLocaleDateString('fa-IR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  } catch (e) {
    return dateStr;
  }
};

const formatPriority = (p) => {
  return {
    low: 'کم',
    medium: 'متوسط',
    high: 'زیاد',
    urgent: 'فوری',
  }[p] || p;
};
</script>
