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
            <Iconsax name="receipt" size="22" />
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="font-bold text-base text-slate-900 dark:text-slate-100 truncate">
                سفارش #{{ toPersianDigits(order?.number || order?.id) }}
              </h3>
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold tracking-wide"
                :class="getStatusBadgeClass(order?.status)"
              >
                {{ order?.status_label || order?.status }}
              </span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2 mt-0.5">
              <span>{{ formatDate(order?.date_created) }}</span>
              <span class="text-slate-300 dark:text-slate-600">•</span>
              <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ formatPrice(order?.total) }}</span>
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

      <!-- Quick Actions Bar -->
      <div class="p-3 bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2 overflow-x-auto">
        <router-link
          :to="'/orders/' + order?.id"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-xs transition-colors shrink-0"
        >
          <Iconsax name="eye" size="15" />
          <span>مشاهده جزئیات کامل</span>
        </router-link>

        <button
          @click="showStatusModal = true"
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shrink-0"
        >
          <Iconsax name="edit" size="14" />
          <span>تغییر وضعیت</span>
        </button>

        <button
          @click="activeTab = 'notes'; showNoteForm = true"
          class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shrink-0"
        >
          <Iconsax name="edit" size="14" />
          <span>افزودن یادداشت</span>
        </button>
      </div>

      <!-- Drawer Scrollable Content -->
      <div class="flex-1 overflow-y-auto p-5 space-y-5">
        <!-- Customer Info Card -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100">اطلاعات خریدار</span>
            <router-link
              v-if="order?.customer_id && !order?.customer?.is_guest"
              :to="'/customers/' + order.customer_id"
              class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline font-semibold"
            >
              مشاهده پرونده مشتری
            </router-link>
            <span v-else class="text-[10px] px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
              خرید به عنوان مهمان
            </span>
          </div>

          <div class="text-xs text-slate-700 dark:text-slate-200 font-semibold">
            {{ order?.customer?.name || order?.billing?.full_name || 'نامشخص' }}
          </div>
          <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <Iconsax name="mail" size="15" />
            <span>{{ order?.customer?.email || order?.billing?.email || '—' }}</span>
          </div>
          <div v-if="order?.customer?.phone || order?.billing?.phone" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <Iconsax name="phone" size="15" />
            <span dir="ltr">{{ order?.customer?.phone || order?.billing?.phone }}</span>
          </div>
        </div>

        <!-- Order Items Summary -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 space-y-3">
          <div class="flex items-center justify-between text-xs font-bold text-slate-900 dark:text-slate-100">
            <span>اقلام سفارش ({{ formatNumber(order?.items_count || 0) }} عدد)</span>
            <span>مبلغ</span>
          </div>

          <div class="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs">
            <div
              v-for="item in (order?.items || [])"
              :key="item.id"
              class="py-2.5 flex items-center justify-between gap-3"
            >
              <div class="min-w-0">
                <div class="font-semibold text-slate-800 dark:text-slate-200 truncate">
                  {{ item.name }}
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                  تعداد: {{ formatNumber(item.quantity) }} × {{ formatPrice(item.price) }}
                  <span v-if="item.sku">• SKU: <span class="dir-ltr inline-block">{{ item.sku }}</span></span>
                </div>
              </div>
              <div class="font-bold text-slate-900 dark:text-slate-100 shrink-0">
                {{ formatPrice(item.total) }}
              </div>
            </div>
          </div>

          <!-- Total Breakdown -->
          <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
            <div class="flex justify-between">
              <span>جمع جزء اقلام:</span>
              <span>{{ formatPrice(order?.subtotal) }}</span>
            </div>
            <div v-if="order?.shipping_total > 0" class="flex justify-between">
              <span>هزینه ارسال ({{ order?.shipping_method || 'پست' }}):</span>
              <span>{{ formatPrice(order?.shipping_total) }}</span>
            </div>
            <div v-if="order?.discount_total > 0" class="flex justify-between text-emerald-600 dark:text-emerald-400">
              <span>تخفیف:</span>
              <span>- {{ formatPrice(order?.discount_total) }}</span>
            </div>
            <div class="flex justify-between font-black text-sm text-slate-900 dark:text-slate-100 pt-2 border-t border-slate-100 dark:border-slate-800">
              <span>مبلغ نهایی:</span>
              <span class="text-indigo-600 dark:text-indigo-400">{{ formatPrice(order?.total) }}</span>
            </div>
          </div>
        </div>

        <!-- Delivery & Payment Info -->
        <div class="grid grid-cols-2 gap-3 text-xs">
          <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
            <div class="text-[11px] font-medium text-slate-400">روش پرداخت</div>
            <div class="font-bold text-slate-800 dark:text-slate-200 mt-1">
              {{ order?.payment_method_title || order?.payment_method || 'نامشخص' }}
            </div>
          </div>

          <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
            <div class="text-[11px] font-medium text-slate-400">روش ارسال</div>
            <div class="font-bold text-slate-800 dark:text-slate-200 mt-1 truncate">
              {{ order?.shipping_method || 'ارسال پیش‌فرض' }}
            </div>
          </div>
        </div>

        <!-- Navigation Tabs: Notes & Activities -->
        <div>
          <div class="flex items-center border-b border-slate-200 dark:border-slate-800">
            <button
              @click="activeTab = 'notes'"
              class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold border-b-2 transition-colors -mb-px"
              :class="activeTab === 'notes' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
            >
              <Iconsax name="edit" size="15" />
              <span>یادداشت‌های سفارش</span>
            </button>
            <button
              @click="activeTab = 'activities'"
              class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold border-b-2 transition-colors -mb-px"
              :class="activeTab === 'activities' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
            >
              <Iconsax name="activity" size="15" />
              <span>فعالیت‌ها</span>
            </button>
          </div>

          <!-- Notes Tab Content -->
          <div v-if="activeTab === 'notes'" class="pt-4 space-y-3">
            <!-- Add Note Box -->
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 space-y-2">
              <textarea
                v-model="newNoteText"
                rows="2"
                placeholder="متن یادداشت سفارش..."
                class="w-full text-xs rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 p-2 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
              ></textarea>
              <div class="flex items-center justify-between">
                <label class="flex items-center gap-1.5 text-[11px] text-slate-600 dark:text-slate-300 cursor-pointer">
                  <input type="checkbox" v-model="isCustomerNote" class="rounded text-indigo-600 w-3.5 h-3.5" />
                  <span>ارسال برای خریدار (یادداشت به مشتری)</span>
                </label>
                <button
                  @click="saveNote"
                  :disabled="submittingNote || !newNoteText.trim()"
                  class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-medium transition-colors"
                >
                  {{ submittingNote ? 'در حال ثبت...' : 'ثبت یادداشت' }}
                </button>
              </div>
            </div>

            <!-- Notes List -->
            <div v-if="loadingNotes" class="py-4 text-center text-xs text-slate-400">در حال دریافت یادداشت‌ها...</div>
            <div v-else-if="notes.length === 0" class="py-4 text-center text-xs text-slate-400">یادداشتی برای این سفارش ثبت نشده است.</div>
            <div v-else class="space-y-2">
              <div
                v-for="n in notes"
                :key="n.id"
                class="p-3 rounded-xl bg-white dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 space-y-1"
              >
                <div class="flex items-center justify-between text-[11px] text-slate-400">
                  <span class="font-semibold">{{ n.author }}</span>
                  <span
                    class="px-1.5 py-0.2 rounded text-[9px] font-medium"
                    :class="n.customer_note ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                  >
                    {{ n.customer_note ? 'قابل مشاهده برای مشتری' : 'یادداشت داخلی' }}
                  </span>
                </div>
                <p class="text-xs text-slate-700 dark:text-slate-200 whitespace-pre-line leading-relaxed pt-1">
                  {{ n.note }}
                </p>
                <div class="text-[10px] text-slate-400 text-left">
                  {{ formatDate(n.date_created) }}
                </div>
              </div>
            </div>
          </div>

          <!-- Activities Tab Content -->
          <div v-if="activeTab === 'activities'" class="pt-4 space-y-2">
            <div v-if="loadingActivities" class="py-4 text-center text-xs text-slate-400">در حال دریافت فعالیت‌ها...</div>
            <div v-else-if="activities.length === 0" class="py-4 text-center text-xs text-slate-400">فعالیتی ثبت نشده است.</div>
            <div v-else class="space-y-2">
              <div
                v-for="act in activities"
                :key="act.id"
                class="p-3 rounded-xl bg-white dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 text-xs space-y-1"
              >
                <div class="flex items-center justify-between text-slate-400 text-[11px]">
                  <span class="font-bold text-slate-700 dark:text-slate-200">{{ formatActivityTitle(act.action_type) }}</span>
                  <span>{{ formatDate(act.created_at) }}</span>
                </div>
                <div class="text-slate-500 text-[11px]">توسط: {{ act.user_name || 'کاربر سیستم' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Status Change Modal -->
    <div v-if="showStatusModal" class="fixed inset-0 z-60 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 max-w-sm w-full space-y-4 shadow-xl">
        <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
          تغییر وضعیت سفارش #{{ order?.number || order?.id }}
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          لطفاً وضعیت جدید را انتخاب کنید. این تغییر به صورت مستقیم در ووکامرس اعمال خواهد شد.
        </p>

        <select
          v-model="targetStatus"
          class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 p-2.5"
        >
          <option v-for="st in availableStatuses" :key="st.slug" :value="st.slug">
            {{ st.name }}
          </option>
        </select>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            @click="showStatusModal = false"
            class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200"
          >
            انصراف
          </button>
          <button
            @click="confirmStatusChange"
            :disabled="changingStatus || targetStatus === order?.status"
            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs disabled:opacity-50"
          >
            {{ changingStatus ? 'در حال اعمال...' : 'تأیید و ذخیره' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import Iconsax from '@/components/icons/Iconsax.vue';
import { formatNumber, formatPrice, formatDate, toPersianDigits } from '@/utils/formatters';

const props = defineProps({
  order: {
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

const activeTab = ref('notes');
const showNoteForm = ref(false);

const notes = ref([]);
const loadingNotes = ref(false);
const newNoteText = ref('');
const isCustomerNote = ref(false);
const submittingNote = ref(false);

const activities = ref([]);
const loadingActivities = ref(false);

const showStatusModal = ref(false);
const targetStatus = ref('processing');
const changingStatus = ref(false);
const availableStatuses = ref([
  { slug: 'pending', name: 'در انتظار پرداخت' },
  { slug: 'processing', name: 'در حال پردازش' },
  { slug: 'on-hold', name: 'در انتظار بررسی' },
  { slug: 'completed', name: 'تکمیل شده' },
  { slug: 'cancelled', name: 'لغو شده' },
  { slug: 'refunded', name: 'مسترد شده' },
  { slug: 'failed', name: 'ناموفق' },
]);

const close = () => {
  emit('close');
};

watch(() => props.order, (ord) => {
  if (ord && props.isOpen) {
    targetStatus.value = ord.status;
    loadOrderData();
  }
}, { immediate: true });

watch(() => props.isOpen, (open) => {
  if (open && props.order) {
    targetStatus.value = props.order.status;
    loadOrderData();
  }
});

onMounted(async () => {
  try {
    const res = await apiClient.get('/orders/statuses');
    if (res.data && res.data.length > 0) {
      availableStatuses.value = res.data;
    }
  } catch (e) {
    // fallback to defaults
  }
});

const loadOrderData = () => {
  if (!props.order?.id) return;
  loadNotes();
  loadActivities();
};

const loadNotes = async () => {
  loadingNotes.value = true;
  try {
    const res = await apiClient.get(`/orders/${props.order.id}/notes`);
    notes.value = res.data || [];
  } catch (e) {
    notes.value = [];
  } finally {
    loadingNotes.value = false;
  }
};

const saveNote = async () => {
  if (!newNoteText.value.trim()) return;
  submittingNote.value = true;
  try {
    await apiClient.post(`/orders/${props.order.id}/notes`, {
      note: newNoteText.value.trim(),
      customer_note: isCustomerNote.value,
    });
    newNoteText.value = '';
    isCustomerNote.value = false;
    notification.success('یادداشت سفارش با موفقیت ثبت شد.');
    loadNotes();
    emit('refresh');
  } catch (e) {
    notification.error(e.message || 'خطا در ثبت یادداشت.');
  } finally {
    submittingNote.value = false;
  }
};

const loadActivities = async () => {
  loadingActivities.value = true;
  try {
    const res = await apiClient.get(`/orders/${props.order.id}/activities`);
    activities.value = res.data || [];
  } catch (e) {
    activities.value = [];
  } finally {
    loadingActivities.value = false;
  }
};

const confirmStatusChange = async () => {
  changingStatus.value = true;
  try {
    await apiClient.patch(`/orders/${props.order.id}/status`, {
      status: targetStatus.value,
    });
    notification.success('وضعیت سفارش در ووکامرس تغییر یافت.');
    showStatusModal.value = false;
    emit('refresh');
  } catch (e) {
    notification.error(e.message || 'خطا در تغییر وضعیت سفارش.');
  } finally {
    changingStatus.value = false;
  }
};

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


const formatActivityTitle = (type) => {
  return {
    order_status_changed: 'تغییر وضعیت سفارش',
    order_note_added: 'ثبت یادداشت سفارش',
    order_refunded: 'استرداد وجه سفارش',
    order_task_created: 'ایجاد وظیفه جدید',
  }[type] || type;
};
</script>
