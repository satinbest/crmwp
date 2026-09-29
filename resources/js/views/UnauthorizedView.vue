<template>
  <div class="min-h-[70vh] flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-8 shadow-xl text-center space-y-6">
      <!-- Shield / Forbidden Icon -->
      <div class="w-20 h-20 mx-auto rounded-3xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 flex items-center justify-center text-rose-500 shadow-inner">
        <Iconsax name="shield-cross" size="40" />
      </div>

      <div class="space-y-2">
        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-950 text-rose-600 dark:text-rose-400">
          خطای ۴۰۳ — دسترسی غیرمجاز
        </span>
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white">
          دسترسی به این بخش برای حساب کاربری شما فعال نیست
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          شما مجوز لازم برای مشاهده یا تغییر این بخش از سامانه را ندارید. در صورتی که فکر می‌کنید این یک اشتباه است، با مدیر سامانه تماس بگیرید.
        </p>
      </div>

      <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
        <router-link
          to="/dashboard"
          class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-600/20 transition-all text-center"
        >
          بازگشت به پیشخوان
        </router-link>
        <button
          @click="$router.back()"
          class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-medium transition-all"
        >
          صفحه قبلی
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import Iconsax from '@/components/icons/Iconsax.vue';
</script>
