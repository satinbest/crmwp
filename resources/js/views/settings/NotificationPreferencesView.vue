<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
          <Iconsax name="notification" size="22" />
        </div>
        <div>
          <h1 class="text-xl font-bold text-slate-900 dark:text-white">تنظیمات اعلان‌ها</h1>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            شخصی‌سازی اعلان‌های دریافتی، اولویت‌ها و دوره نگهداری پیام‌ها
          </p>
        </div>
      </div>

      <router-link
        to="/notifications"
        class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors flex items-center gap-1.5"
      >
        <Iconsax name="arrow-right" size="14" />
        <span>بازگشت به مرکز اعلان‌ها</span>
      </router-link>
    </div>

    <!-- Preferences Form -->
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 space-y-6">
      <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">دسته‌بندی رویدادهای اعلان</h2>
        <p class="text-xs text-slate-400 mt-1">
          مشخص کنید مایل به دریافت اعلان در کدام‌یک از رخدادهای زیر هستید:
        </p>
      </div>

      <div class="space-y-4">
        <!-- Task Notifications -->
        <label class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-850/50 cursor-pointer transition-colors">
          <div class="flex items-center gap-3.5">
            <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
              <Iconsax name="task" size="18" />
            </div>
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200">اعلان وظایف و پیگیری‌ها (Tasks)</div>
              <div class="text-[11px] text-slate-400">تخصیص وظیفه جدید، موعد تحویل و وظایف معوقه</div>
            </div>
          </div>
          <input
            type="checkbox"
            v-model="form.task_notifications"
            class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 dark:bg-slate-800"
          />
        </label>

        <!-- Order Notifications -->
        <label class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-850/50 cursor-pointer transition-colors">
          <div class="flex items-center gap-3.5">
            <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center">
              <Iconsax name="orders" size="18" />
            </div>
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200">اعلان وضعیت سفارش‌ها (Orders)</div>
              <div class="text-[11px] text-slate-400">تغییر وضعیت‌های حساس سفارش، یادداشت‌ها و استرداد وجه</div>
            </div>
          </div>
          <input
            type="checkbox"
            v-model="form.order_notifications"
            class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 dark:bg-slate-800"
          />
        </label>

        <!-- Inventory Notifications -->
        <label class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-850/50 cursor-pointer transition-colors">
          <div class="flex items-center gap-3.5">
            <div class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
              <Iconsax name="inventory" size="18" />
            </div>
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200">هشدار انبار و کمبود موجودی (Low Stock)</div>
              <div class="text-[11px] text-slate-400">کاهش موجودی کالاها به کمتر از آستانه هشدار یا اتمام موجودی</div>
            </div>
          </div>
          <input
            type="checkbox"
            v-model="form.inventory_notifications"
            class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 dark:bg-slate-800"
          />
        </label>

        <!-- Bulk Operations -->
        <label class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-850/50 cursor-pointer transition-colors">
          <div class="flex items-center gap-3.5">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <Iconsax name="bulk" size="18" />
            </div>
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200">عملیات گروهی (Bulk Operations)</div>
              <div class="text-[11px] text-slate-400">تکمیل یا خطای پردازش عملیات‌های دسته‌جمعی روی قیمت‌ها و محصولات</div>
            </div>
          </div>
          <input
            type="checkbox"
            v-model="form.bulk_notifications"
            class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 dark:bg-slate-800"
          />
        </label>

        <!-- System Notifications -->
        <label class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-850/50 cursor-pointer transition-colors">
          <div class="flex items-center gap-3.5">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Iconsax name="notification" size="18" />
            </div>
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200">اعلان‌های سیستمی و عمومی</div>
              <div class="text-[11px] text-slate-400">پیام‌های امنیتی، بروزرسانی نرم‌افزار و هشدارهای سرور</div>
            </div>
          </div>
          <input
            type="checkbox"
            v-model="form.system_notifications"
            class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-700 dark:bg-slate-800"
          />
        </label>
      </div>

      <!-- Retention Policy -->
      <div class="border-t border-slate-100 dark:border-slate-800 pt-6 space-y-3">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">دوره نگهداری اعلان‌ها (Retention Policy)</h3>
        <p class="text-xs text-slate-400">
          اعلان‌های قدیمی‌تر از این بازه به صورت خودکار از سیستم پاک خواهند شد:
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
          <label
            class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-all"
            :class="form.retention_days === 30 ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'"
          >
            <input type="radio" v-model="form.retention_days" :value="30" class="text-indigo-600" />
            <span class="text-xs font-bold">۳۰ روز (پیش‌فرض)</span>
          </label>

          <label
            class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-all"
            :class="form.retention_days === 60 ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'"
          >
            <input type="radio" v-model="form.retention_days" :value="60" class="text-indigo-600" />
            <span class="text-xs font-bold">۶۰ روز</span>
          </label>

          <label
            class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-all"
            :class="form.retention_days === 90 ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800'"
          >
            <input type="radio" v-model="form.retention_days" :value="90" class="text-indigo-600" />
            <span class="text-xs font-bold">۹۰ روز</span>
          </label>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="border-t border-slate-100 dark:border-slate-800 pt-6 flex items-center justify-between">
        <button
          @click="generateTestNotification"
          :disabled="isGeneratingTest"
          class="px-4 py-2.5 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex items-center gap-2 text-slate-700 dark:text-slate-300"
        >
          <Iconsax name="notification" size="16" />
          <span>{{ isGeneratingTest ? 'در حال ارسال...' : 'ارسال اعلان آزمایشی' }}</span>
        </button>

        <button
          @click="savePreferences"
          :disabled="notifStore.prefsLoading"
          class="px-6 py-2.5 rounded-xl text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition-colors flex items-center gap-2 shadow-md shadow-indigo-600/20 disabled:opacity-50"
        >
          <span>{{ notifStore.prefsLoading ? 'در حال ذخیره‌سازی...' : 'ذخیره تنظیمات' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import { useNotificationCenterStore } from '@/stores/notificationCenter';
import { useNotificationStore } from '@/stores/notification';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';

const notifStore = useNotificationCenterStore();
const toast = useNotificationStore();
const isGeneratingTest = ref(false);

const form = reactive({
  task_notifications: true,
  order_notifications: true,
  inventory_notifications: true,
  bulk_notifications: true,
  system_notifications: true,
  retention_days: 30,
});

const loadPrefs = async () => {
  const prefs = await notifStore.fetchPreferences();
  if (prefs) {
    form.task_notifications = prefs.task_notifications;
    form.order_notifications = prefs.order_notifications;
    form.inventory_notifications = prefs.inventory_notifications;
    form.bulk_notifications = prefs.bulk_notifications;
    form.system_notifications = prefs.system_notifications;
    form.retention_days = prefs.retention_days || 30;
  }
};

const savePreferences = async () => {
  await notifStore.updatePreferences({ ...form });
};

const generateTestNotification = async () => {
  isGeneratingTest.value = true;
  try {
    await apiClient.post('/notifications/test-generate', {
      type: 'task_assigned',
      title: 'اعلان تستی سامانه',
      message: 'این اعلان به صورت آزمایشی جهت اعتبارسنجی تنظیمات ارسال شده است.',
      priority: 'normal',
      action_url: '/notifications',
    });
    toast.success('اعلان آزمایشی با موفقیت در سیستم ثبت گردید.');
    notifStore.fetchUnreadCount();
  } catch (err) {
    toast.error('خطا در ایجاد اعلان آزمایشی.');
  } finally {
    isGeneratingTest.value = false;
  }
};

onMounted(() => {
  loadPrefs();
});
</script>
