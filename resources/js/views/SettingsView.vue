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
            class="flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all"
            :class="!themeStore.isDark ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'border-slate-200 dark:border-slate-700 text-slate-400'"
          >
            <Iconsax name="sun" size="16" />
            <span>روز (روشن)</span>
          </button>
          <button
            @click="themeStore.setTheme(true)"
            class="flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all"
            :class="themeStore.isDark ? 'bg-indigo-950/80 border-indigo-600 text-indigo-300' : 'border-slate-200 dark:border-slate-700 text-slate-400'"
          >
            <Iconsax name="moon" size="16" />
            <span>شب (تیره)</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Typography & Bundling Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
      <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-2">استقلال کامل محیط تولید (Production Independence)</h3>
      <p class="text-xs text-slate-400 leading-relaxed mb-4">
        بر اساس بند ۴۲ مستند مشخصات، این سامانه در محیط پروداکشن به هیچ عنوان به اینترنت، CDN، فونت‌های گوگل یا بسته‌های آنلاین وابسته نیست.
      </p>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
          <div class="font-bold text-slate-800 dark:text-slate-200 mb-1">تایپوگرافی فارسی</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400">بسته فونت استاندارد Vazirmatn به صورت محلی و خودکفا باندل شده است.</div>
        </div>
        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40">
          <div class="font-bold text-slate-800 dark:text-slate-200 mb-1">آیکون‌های سیستمی</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400">آیکون‌های سبک Iconsax به صورت کامپوننت‌های بهینه SVG محلی پیاده شده‌اند.</div>
        </div>
      </div>
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
