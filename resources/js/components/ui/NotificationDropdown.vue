<template>
  <div class="relative" ref="dropdownRef">
    <!-- Bell Trigger Button -->
    <button
      @click="toggleDropdown"
      class="relative p-2 rounded-xl text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none"
      :class="{ 'bg-slate-100 dark:bg-slate-800 text-indigo-600 dark:text-indigo-400': isOpen }"
      title="مرکز اعلان‌ها"
    >
      <Iconsax name="notification" size="20" />

      <!-- Unread Ping Indicator Badge -->
      <span
        v-if="notifStore.unreadCount > 0"
        class="absolute top-1.5 left-1.5 flex h-4 min-w-4 px-1 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-slate-900 animate-pulse"
      >
        {{ notifStore.unreadCount > 99 ? '99+' : notifStore.unreadCount }}
      </span>
    </button>

    <!-- Floating Dropdown Panel -->
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 translate-y-2 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-2 scale-95"
    >
      <div
        v-if="isOpen"
        class="absolute left-0 mt-2 w-80 sm:w-96 max-w-[calc(100vw-2rem)] sm:max-w-none bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 z-notification-layer overflow-hidden flex flex-col max-h-[520px]"
      >
        <!-- Panel Header -->
        <div class="p-3.5 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50 backdrop-blur-sm">
          <div class="flex items-center gap-2">
            <span class="font-bold text-sm text-slate-800 dark:text-slate-100">مرکز اعلان‌ها</span>
            <span
              v-if="notifStore.unreadCount > 0"
              class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/50 dark:border-indigo-800/50"
            >
              {{ notifStore.unreadCount }} جدید
            </span>
          </div>

          <div class="flex items-center gap-1.5">
            <button
              v-if="notifStore.unreadCount > 0"
              @click="markAllAsRead"
              :disabled="notifStore.markingRead"
              class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-medium px-2 py-1 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors disabled:opacity-50"
              title="خواندن همه اعلان‌ها"
            >
              {{ notifStore.markingRead ? 'در حال ثبت...' : 'خواندن همه' }}
            </button>
            <router-link
              to="/settings/notifications"
              @click="isOpen = false"
              class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              title="تنظیمات اعلان‌ها"
            >
              <Iconsax name="settings" size="16" />
            </router-link>
          </div>
        </div>

        <!-- Quick Filter Tabs -->
        <div class="px-3 pt-2.5 pb-1 flex items-center gap-2 border-b border-slate-100 dark:border-slate-800/50 text-xs">
          <button
            @click="activeTab = 'all'"
            class="pb-1.5 font-medium transition-colors relative"
            :class="activeTab === 'all' ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
          >
            همه
            <span v-if="activeTab === 'all'" class="absolute bottom-0 right-0 left-0 h-0.5 bg-indigo-600 rounded-full"></span>
          </button>

          <button
            @click="activeTab = 'unread'"
            class="pb-1.5 font-medium transition-colors relative flex items-center gap-1"
            :class="activeTab === 'unread' ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
          >
            خوانده‌نشده
            <span
              v-if="notifStore.unreadCount > 0"
              class="w-1.5 h-1.5 rounded-full bg-rose-500 inline-block"
            ></span>
            <span v-if="activeTab === 'unread'" class="absolute bottom-0 right-0 left-0 h-0.5 bg-indigo-600 rounded-full"></span>
          </button>
        </div>

        <!-- Notification Items List -->
        <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/40">
          <div v-if="loading" class="p-8 text-center text-slate-400 text-xs flex flex-col items-center gap-2">
            <div class="w-6 h-6 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
            <span>در حال بارگذاری اعلان‌ها...</span>
          </div>

          <div
            v-else-if="filteredNotifications.length === 0"
            class="p-8 text-center text-slate-400 text-xs flex flex-col items-center gap-2"
          >
            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
              <Iconsax name="notification" size="24" />
            </div>
            <div class="font-medium text-slate-600 dark:text-slate-300">
              {{ activeTab === 'unread' ? 'هیچ اعلان خوانده‌نشده‌ای ندارید.' : 'هیچ اعلانی یافت نشد.' }}
            </div>
            <div class="text-[11px] text-slate-400">پیام‌های جدید سیستم در اینجا نمایش داده خواهند شد.</div>
          </div>

          <div
            v-for="item in filteredNotifications"
            :key="item.id"
            @click="handleNotificationClick(item)"
            class="p-3.5 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors flex items-start gap-3 relative group"
            :class="{ 'bg-indigo-50/30 dark:bg-indigo-950/20': !item.is_read }"
          >
            <!-- Type Icon Indicator -->
            <div
              class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
              :class="getTypeIconClass(item.type, item.priority)"
            >
              <Iconsax :name="getTypeIconName(item.type)" size="18" />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0 pr-1">
              <div class="flex items-center justify-between gap-1 mb-1">
                <span class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">
                  {{ item.title }}
                </span>
                <span class="text-[10px] text-slate-400 shrink-0">
                  {{ formatRelativeTime(item.created_at) }}
                </span>
              </div>

              <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed line-clamp-2">
                {{ item.message }}
              </p>

              <div v-if="item.action_url" class="mt-2 flex items-center gap-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400">
                <span>مشاهده جزییات</span>
                <Iconsax name="arrow-left" size="12" />
              </div>
            </div>

            <!-- Unread Pulse Dot -->
            <span
              v-if="!item.is_read"
              class="w-2 h-2 rounded-full bg-indigo-600 ring-2 ring-indigo-300 dark:ring-indigo-900 shrink-0 mt-1"
              title="خوانده‌نشده"
            ></span>

            <!-- Quick Action Hover Menu -->
            <div
              class="absolute left-2 top-2 opacity-0 group-hover:opacity-100 transition-opacity bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 flex items-center p-0.5 gap-0.5"
              @click.stop
            >
              <button
                v-if="!item.is_read"
                @click="markSingleAsRead(item.id)"
                class="p-1 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/50 rounded"
                title="علامت به عنوان خوانده‌شده"
              >
                <Iconsax name="tick" size="14" />
              </button>
              <button
                @click="deleteItem(item.id)"
                class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded"
                title="حذف اعلان"
              >
                <Iconsax name="trash" size="14" />
              </button>
            </div>
          </div>
        </div>

        <!-- Panel Footer -->
        <div class="p-2.5 bg-slate-50 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
          <router-link
            to="/notifications"
            @click="isOpen = false"
            class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold px-2 py-1 rounded"
          >
            مشاهده تمام اعلان‌ها ({{ notifStore.meta.total || notifStore.notifications.length }})
          </router-link>

          <span class="text-[11px] text-slate-400">مرکز اعلان‌های بلادرنگ</span>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationCenterStore } from '@/stores/notificationCenter';
import Iconsax from '@/components/icons/Iconsax.vue';

const router = useRouter();
const notifStore = useNotificationCenterStore();

const isOpen = ref(false);
const loading = ref(false);
const activeTab = ref('all');
const dropdownRef = ref(null);

const filteredNotifications = computed(() => {
  if (activeTab.value === 'unread') {
    return notifStore.notifications.filter(n => !n.is_read);
  }
  return notifStore.notifications;
});

const toggleDropdown = async () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    loading.value = true;
    try {
      await notifStore.fetchNotifications({ per_page: 15 });
    } finally {
      loading.value = false;
    }
  }
};

const markSingleAsRead = async (id) => {
  await notifStore.markAsRead(id);
};

const markAllAsRead = async () => {
  await notifStore.markAllAsRead();
};

const deleteItem = async (id) => {
  await notifStore.deleteNotification(id);
};

const handleNotificationClick = async (item) => {
  if (!item.is_read) {
    await notifStore.markAsRead(item.id);
  }
  isOpen.value = false;

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

const formatRelativeTime = (isoString) => {
  if (!isoString) return '';
  const date = new Date(isoString.replace(' ', 'T'));
  const now = new Date();
  const diffSec = Math.floor((now - date) / 1000);

  if (diffSec < 60) return 'هم‌اکنون';
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)} دقیقه پیش`;
  if (diffSec < 86400) return `${Math.floor(diffSec / 3600)} ساعت پیش`;
  if (diffSec < 172800) return 'دیروز';
  return `${Math.floor(diffSec / 86400)} روز پیش`;
};

const handleClickOutside = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  // Initial fetch of unread counter
  notifStore.fetchUnreadCount();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>
