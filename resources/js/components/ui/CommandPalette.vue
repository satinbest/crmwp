<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-start justify-center pt-20 px-4 bg-slate-900/60 backdrop-blur-sm">
    <div
      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden transition-all"
      @click.stop
    >
      <!-- Search Input -->
      <div class="flex items-center px-4 py-3.5 border-b border-slate-100 dark:border-slate-800 gap-3">
        <Iconsax name="search" size="20" class="text-slate-400" />
        <input
          ref="searchInput"
          v-model="query"
          type="text"
          placeholder="جستجو یا دستور مورد نظر خود را بنویسید... (Ctrl+K)"
          class="w-full bg-transparent text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none"
          @keydown.esc="close"
          @keydown.down.prevent="moveSelection(1)"
          @keydown.up.prevent="moveSelection(-1)"
          @keydown.enter="executeSelected"
        />
        <span class="text-[10px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-500 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700">ESC</span>
      </div>

      <!-- Action List -->
      <div class="max-h-80 overflow-y-auto p-2">
        <div v-if="filteredActions.length === 0" class="p-6 text-center text-slate-400 text-sm">
          نتیجه‌ای برای جستجوی شما یافت نشد.
        </div>
        <div
          v-for="(action, idx) in filteredActions"
          :key="action.id"
          class="flex items-center justify-between px-3 py-2.5 rounded-xl cursor-pointer transition-colors"
          :class="selectedIndex === idx ? 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
          @click="selectAction(action)"
          @mouseenter="selectedIndex = idx"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors"
              :class="selectedIndex === idx ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
            >
              <Iconsax :name="action.icon" size="18" />
            </div>
            <div>
              <div class="text-sm font-semibold">{{ action.title }}</div>
              <div class="text-xs text-slate-400">{{ action.category }}</div>
            </div>
          </div>
          <span v-if="action.shortcut" class="text-xs font-mono opacity-60">{{ action.shortcut }}</span>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-4 py-2.5 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-400">
        <span>حرکت با ↑ ↓</span>
        <span>اجرا با Enter</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import Iconsax from '@/components/icons/Iconsax.vue';
import { useNotificationStore } from '@/stores/notification';
import { useNotificationCenterStore } from '@/stores/notificationCenter';

const router = useRouter();
const notification = useNotificationStore();

const isOpen = ref(false);
const query = ref('');
const selectedIndex = ref(0);
const searchInput = ref(null);

const actions = [
  { id: 'dash', title: 'داشبورد اصلی', category: 'ناوبری', icon: 'dashboard', action: () => router.push('/dashboard') },
  { id: 'crm', title: 'پیشخوان ارتباط با مشتریان (CRM)', category: 'CRM', icon: 'dashboard', action: () => router.push('/crm') },
  { id: 'crm-tasks', title: 'مدیریت وظایف و پیگیری‌ها', category: 'CRM', icon: 'task', action: () => router.push('/crm/tasks') },
  { id: 'crm-my-tasks', title: 'وظایف من (My Tasks)', category: 'CRM', icon: 'task', action: () => router.push('/crm/tasks?view=my') },
  { id: 'crm-overdue-tasks', title: 'وظایف معوقه و نیازمند اقدام فوری', category: 'CRM', icon: 'task', action: () => router.push('/crm/tasks?view=overdue') },
  { id: 'crm-segments', title: 'بخش‌بندی‌های هوشمند (Segments)', category: 'CRM', icon: 'segments', action: () => router.push('/crm/segments') },
  { id: 'crm-tags', title: 'مدیریت برچسب‌های مشتریان (Tags)', category: 'CRM', icon: 'tag', action: () => router.push('/crm/tags') },
  { id: 'crm-activities', title: 'تایم‌لاین فعالیت‌ها و تعاملات', category: 'CRM', icon: 'activity', action: () => router.push('/crm/activities') },
  { id: 'inventory', title: 'انبار و موجودی کالاها', category: 'فروشگاه', icon: 'inventory', action: () => router.push('/inventory') },
  { id: 'bulk-ops', title: 'عملیات دسته‌جمعی و سرورساید', category: 'فروشگاه', icon: 'bulk', action: () => router.push('/bulk-operations') },
  { id: 'products', title: 'مدیریت کاتالوگ محصولات', category: 'فروشگاه', icon: 'products', action: () => router.push('/products') },
  { id: 'orders', title: 'مدیریت سفارش‌ها', category: 'فروشگاه', icon: 'orders', action: () => router.push('/orders') },
  { id: 'customers', title: 'بانک مشتریان', category: 'CRM', icon: 'customers', action: () => router.push('/customers') },
  { id: 'users', title: 'کاربران و سطوح دسترسی (RBAC)', category: 'مدیریت', icon: 'users', action: () => router.push('/users') },
  { id: 'stores', title: 'فروشگاه‌های ووکامرس', category: 'یکپارچه‌سازی', icon: 'shop', action: () => router.push('/stores') },
  { id: 'notifs', title: 'مرکز اعلان‌ها و رویدادها', category: 'اعلان‌ها', icon: 'notification', action: () => router.push('/notifications') },
  { id: 'notifs-unread', title: 'نمایش اعلان‌های خوانده‌نشده', category: 'اعلان‌ها', icon: 'notification', action: () => router.push('/notifications?status=unread') },
  { id: 'notifs-mark-read', title: 'علامت‌گذاری تمام اعلان‌ها به عنوان خوانده‌شده', category: 'اعلان‌ها', icon: 'tick', action: async () => { const s = useNotificationCenterStore(); await s.markAllAsRead(); } },
  { id: 'notifs-settings', title: 'تنظیمات اعلان‌ها و اطلاع‌رسانی', category: 'پیکربندی', icon: 'settings', action: () => router.push('/settings/notifications') },
  { id: 'new-product', title: 'تعریف محصول جدید', category: 'فروشگاه', icon: 'products', action: () => router.push('/products/create') },
];

const filteredActions = computed(() => {
  if (!query.value.trim()) return actions;
  const q = query.value.toLowerCase();
  return actions.filter(a => a.title.toLowerCase().includes(q) || a.category.toLowerCase().includes(q));
});

const open = () => {
  isOpen.value = true;
  query.value = '';
  selectedIndex.value = 0;
  nextTick(() => searchInput.value?.focus());
};

const close = () => {
  isOpen.value = false;
};

const moveSelection = (dir) => {
  const max = filteredActions.value.length;
  if (max === 0) return;
  selectedIndex.value = (selectedIndex.value + dir + max) % max;
};

const selectAction = (action) => {
  close();
  action.action();
};

const executeSelected = () => {
  if (filteredActions.value[selectedIndex.value]) {
    selectAction(filteredActions.value[selectedIndex.value]);
  }
};

const handleKeyDown = (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    if (isOpen.value) {
      close();
    } else {
      open();
    }
  } else if (e.key === 'Escape' && isOpen.value) {
    close();
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});

defineExpose({ open, close });
</script>
