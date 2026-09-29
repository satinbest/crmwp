<template>
  <div class="min-h-screen flex items-center justify-center p-4 bg-slate-50 dark:bg-slate-950 font-sans selection:bg-indigo-500 selection:text-white">
    <!-- Background subtle gradient decorative blurs -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-500/10 dark:bg-indigo-500/15 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-violet-500/10 dark:bg-violet-500/15 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">
      <!-- Theme Switcher Top -->
      <div class="flex justify-end mb-4">
        <button
          @click="themeStore.toggle"
          class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md text-slate-500 hover:text-slate-900 dark:hover:text-slate-100 transition-colors shadow-sm"
          :title="themeStore.isDark ? 'حالت روز' : 'حالت شب'"
        >
          <Iconsax :name="themeStore.isDark ? 'sun' : 'moon'" size="18" />
        </button>
      </div>

      <!-- Card Container -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-8 shadow-xl shadow-slate-200/50 dark:shadow-none backdrop-blur-xl">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
          <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 items-center justify-center text-white font-bold text-2xl shadow-lg shadow-indigo-500/25 mb-4">
            W
          </div>
          <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">سامانه مدیریت و CRM ووکامرس</h1>
          <p class="text-xs text-slate-400 mt-2 font-medium">ورود به پنل یکپارچه مدیریت فروشگاه</p>
        </div>

        <!-- Error Alert -->
        <div
          v-if="errorMessage"
          class="mb-6 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/40 text-rose-700 dark:text-rose-300 text-xs flex items-start gap-3"
        >
          <Iconsax name="close" size="18" class="text-rose-500 shrink-0 mt-0.5" />
          <div class="leading-relaxed">{{ errorMessage }}</div>
        </div>

        <!-- Login Form -->
        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              نام کاربری یا ایمیل
            </label>
            <div class="relative">
              <input
                v-model="username"
                type="text"
                autocomplete="username"
                required
                placeholder="admin"
                class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 focus:border-indigo-500 dark:focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 rounded-xl px-4 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 transition-all focus:outline-none"
              />
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                کلمه عبور
              </label>
            </div>
            <div class="relative">
              <input
                v-model="password"
                type="password"
                autocomplete="current-password"
                required
                placeholder="••••••••••••"
                class="w-full bg-slate-50 dark:bg-slate-850 border border-slate-200 dark:border-slate-800 focus:border-indigo-500 dark:focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 rounded-xl px-4 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 transition-all focus:outline-none"
              />
            </div>
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="w-full mt-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm shadow-lg shadow-indigo-600/25 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="loading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ loading ? 'در حال ورود...' : 'ورود به سامانه' }}</span>
          </button>
        </form>

        <!-- Demo Accounts Quick Fill -->
        <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800">
          <div class="text-[11px] font-semibold text-slate-400 mb-2.5 text-center">حساب‌های آزمایشی فاز ۱:</div>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              @click="fillDemo('admin', 'AdminPassword123!')"
              class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/60 text-right transition-colors text-xs"
            >
              <div class="font-bold text-slate-800 dark:text-slate-200">مدیر ارشد</div>
              <div class="text-[10px] text-slate-400">admin (دسترسی کامل)</div>
            </button>
            <button
              type="button"
              @click="fillDemo('manager', 'ManagerPassword123!')"
              class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/60 text-right transition-colors text-xs"
            >
              <div class="font-bold text-slate-800 dark:text-slate-200">مدیر فروشگاه</div>
              <div class="text-[10px] text-slate-400">manager (عملیات و CRM)</div>
            </button>
          </div>
        </div>
      </div>

      <!-- Security Notice -->
      <div class="mt-4 text-center text-[11px] text-slate-400">
        سامانه مبتنی بر نشست‌های امن سمت سرور و رمزنگاری داده‌ها
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import { useNotificationStore } from '@/stores/notification';
import Iconsax from '@/components/icons/Iconsax.vue';

const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();
const notification = useNotificationStore();

const username = ref('admin');
const password = ref('AdminPassword123!');
const loading = ref(false);
const errorMessage = ref('');

const fillDemo = (u, p) => {
  username.value = u;
  password.value = p;
  errorMessage.value = '';
};

const handleLogin = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    const user = await authStore.login(username.value, password.value);
    notification.success(`خوش آمدید، ${user.full_name || user.username}`);
    router.push('/dashboard');
  } catch (err) {
    errorMessage.value = err.message || 'خطا در احراز هویت. اطلاعات ورود را مجدداً بررسی فرمایید.';
  } finally {
    loading.value = false;
  }
};
</script>
