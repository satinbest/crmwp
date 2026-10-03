<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">تنظیمات سامانه</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        پیکربندی محیط اجرایی، ظاهر، نشست‌های امنیتی و یکپارچگی سیستم
      </p>
    </div>

    <!-- Settings Quick Navigation -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <router-link
        to="/settings/users"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex items-center gap-3.5 group"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
          <Iconsax name="users" size="20" />
        </div>
        <div>
          <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">کاربران و تیم</div>
          <div class="text-[10px] text-slate-400 mt-0.5">مدیریت کاربران، حساب‌ها و وضعیت</div>
        </div>
      </router-link>

      <router-link
        to="/settings/roles"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex items-center gap-3.5 group"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
          <Iconsax name="shield-tick" size="20" />
        </div>
        <div>
          <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">نقش‌ها و مجوزها</div>
          <div class="text-[10px] text-slate-400 mt-0.5">ماتریس دسترسی و تکثیر نقش‌ها</div>
        </div>
      </router-link>

      <router-link
        to="/settings/profile"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex items-center gap-3.5 group"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
          <Iconsax name="customers" size="20" />
        </div>
        <div>
          <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">پروفایل من</div>
          <div class="text-[10px] text-slate-400 mt-0.5">تغییر تصویر، کلمه عبور و مشخصات</div>
        </div>
      </router-link>

      <router-link
        to="/settings/notifications"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex items-center gap-3.5 group"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
          <Iconsax name="notification" size="20" />
        </div>
        <div>
          <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">تنظیمات اعلان‌ها</div>
          <div class="text-[10px] text-slate-400 mt-0.5">شخصی‌سازی هشدارهای سیستم و کانال‌ها</div>
        </div>
      </router-link>

      <router-link
        to="/settings/webhooks"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex items-center gap-3.5 group"
      >
        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
          <Iconsax name="activity" size="20" />
        </div>
        <div>
          <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">وب‌هوک‌ها و سلامت ادغام</div>
          <div class="text-[10px] text-slate-400 mt-0.5">پایش رویدادها، تطبیق و لاگ خطاهای ووکامرس</div>
        </div>
      </router-link>

      <router-link
        to="/settings/cache"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex items-center gap-3.5 group"
      >
        <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
          <Iconsax name="flash" size="20" />
        </div>
        <div>
          <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">اتصال به Object Cache</div>
          <div class="text-[10px] text-slate-400 mt-0.5">تست اتصال، وضعیت کش حافظه و عیب‌یابی</div>
        </div>
      </router-link>

      <router-link
        to="/settings/about"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-800 transition-all flex items-center gap-3.5 group"
      >
        <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
          <Iconsax name="shop" size="20" />
        </div>
        <div>
          <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 transition-colors">درباره برنامه</div>
          <div class="text-[10px] text-slate-400 mt-0.5">مشخصات سامانه، نسخه، تکنولوژی‌ها و توسعه‌دهنده</div>
        </div>
      </router-link>
    </div>

    <!-- Appearance Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">پوسته و ظاهر (Appearance)</h3>
          <p class="text-xs text-slate-400 mt-0.5">انتخاب بین حالت تیره و روشن، ذخیره شده در مرورگر</p>
        </div>
        <div class="flex items-center gap-2">
          <button
            @click="themeStore.setTheme(false)"
            class="flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer"
            :class="!themeStore.isDark ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'border-slate-200 dark:border-slate-700 text-slate-400'"
          >
            <Iconsax name="sun" size="16" />
            <span>روز (روشن)</span>
          </button>
          <button
            @click="themeStore.setTheme(true)"
            class="flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer"
            :class="themeStore.isDark ? 'bg-indigo-950/80 border-indigo-600 text-indigo-300' : 'border-slate-200 dark:border-slate-700 text-slate-400'"
          >
            <Iconsax name="moon" size="16" />
            <span>شب (تیره)</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Object Cache Diagnostics Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">اتصال و عیب‌یابی Object Cache</h3>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
            عملیاتی
          </span>
        </div>
        <p class="text-xs text-slate-400">
          تست زنده اتصال Memcached، Redis و لایه کش چنددرایوره با پایش سرعت پاسخ‌دهی و پاکسازی سراسری
        </p>
      </div>
      <router-link
        to="/settings/cache"
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-sm shadow-indigo-600/20 shrink-0"
      >
        <span>پیکربندی و تست کش</span>
        <Iconsax name="chevron-left" size="14" />
      </router-link>
    </div>

    <!-- About Application Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm flex items-center justify-between">
      <div class="space-y-1">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">درباره سامانه و مشخصات فنی</h3>
        <p class="text-xs text-slate-400">
          مشاهده نسخه برنامه، مشخصات معماری، توسعه‌دهنده، وضعیت ارتباط با ووکامرس و تکنولوژی‌ها
        </p>
      </div>
      <router-link
        to="/settings/about"
        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all"
      >
        <span>مشاهده جزئیات برنامه</span>
        <Iconsax name="chevron-left" size="14" />
      </router-link>
    </div>

    <!-- Security & Sessions Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">امنیت و نشست کاربری</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs mt-3">
        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
          <div class="text-slate-400 text-[11px]">طول عمر نشست</div>
          <div class="font-bold text-slate-900 dark:text-white mt-1">۲ ساعت (7200 ثانیه)</div>
        </div>
        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
          <div class="text-slate-400 text-[11px]">ویژگی‌های کوکی</div>
          <div class="font-bold text-slate-900 dark:text-white mt-1">HttpOnly, SameSite: Lax</div>
        </div>
        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
          <div class="text-slate-400 text-[11px]">محدودیت تلاش ورود (Rate Limit)</div>
          <div class="font-bold text-slate-900 dark:text-white mt-1">حداکثر ۵ بار در دقیقه</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useThemeStore } from '@/stores/theme';
import Iconsax from '@/components/icons/Iconsax.vue';

const themeStore = useThemeStore();
</script>
