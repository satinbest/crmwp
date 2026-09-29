<template>
  <div class="relative" ref="dropdownRef">
    <!-- Store Switcher Trigger Button -->
    <button
      @click="toggleDropdown"
      class="flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium transition-all shadow-sm focus:outline-none"
      :class="{ 'ring-2 ring-indigo-500/20 border-indigo-500/50 dark:border-indigo-500/50': isOpen }"
      :title="storeContext.activeStore ? `فروشگاه فعال: ${storeContext.activeStore.name}` : 'انتخاب فروشگاه'"
    >
      <!-- Store Status Dot Indicator -->
      <span class="relative flex h-2.5 w-2.5 shrink-0">
        <span
          v-if="storeContext.isConnected"
          class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"
        ></span>
        <span
          class="relative inline-flex rounded-full h-2.5 w-2.5"
          :class="statusDotClass(storeContext.activeStore?.status)"
        ></span>
      </span>

      <!-- Store Icon / Name -->
      <div class="flex items-center gap-1.5 min-w-0 max-w-[130px] sm:max-w-[200px]">
        <Iconsax name="shop" size="16" class="text-indigo-600 dark:text-indigo-400 shrink-0" />
        <span class="truncate font-semibold text-slate-800 dark:text-slate-100">
          {{ storeContext.currentStoreName }}
        </span>
      </div>

      <!-- Currency Badge -->
      <span
        v-if="storeContext.activeStore?.currency"
        class="hidden sm:inline-flex text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60"
      >
        {{ storeContext.activeStore.currency }}
      </span>

      <!-- Chevron Icon -->
      <Iconsax
        name="arrow-down-1"
        size="14"
        class="text-slate-400 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
      />
    </button>

    <!-- Floating Dropdown Panel -->
    <transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0 translate-y-1 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-1 scale-95"
    >
      <div
        v-if="isOpen"
        class="absolute right-0 mt-2 w-72 sm:w-84 bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 z-50 overflow-hidden flex flex-col"
      >
        <!-- Panel Header -->
        <div class="p-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/70 dark:bg-slate-850/70">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">فروشگاه‌های من</span>
            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
              {{ toPersianDigits(storeContext.stores.length) }} فروشگاه
            </span>
          </div>

          <router-link
            to="/stores"
            @click="isOpen = false"
            class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 font-medium hover:underline"
          >
            مدیریت
          </router-link>
        </div>

        <!-- Stores List -->
        <div class="max-h-72 overflow-y-auto p-1.5 space-y-1">
          <div
            v-if="storeContext.stores.length === 0"
            class="p-4 text-center text-xs text-slate-400"
          >
            هیچ فروشگاهی برای حساب شما یافت نشد.
          </div>

          <button
            v-for="store in storeContext.stores"
            :key="store.id"
            @click="switchStore(store)"
            class="w-full flex items-center justify-between p-2.5 rounded-xl text-right transition-colors group"
            :class="store.id === storeContext.activeStore?.id
              ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200/60 dark:border-indigo-800/40 text-indigo-900 dark:text-indigo-100 font-medium'
              : 'hover:bg-slate-50 dark:hover:bg-slate-800/60 text-slate-700 dark:text-slate-200 border border-transparent'"
          >
            <div class="flex items-start gap-2.5 min-w-0 flex-1">
              <!-- Store Icon / Status Avatar -->
              <div
                class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5 border"
                :class="store.id === storeContext.activeStore?.id
                  ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700 group-hover:border-indigo-300'"
              >
                <Iconsax :name="store.icon || 'shop'" size="16" />
              </div>

              <!-- Store Info -->
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-bold truncate">{{ store.name }}</span>
                  <span
                    v-if="store.is_demo"
                    class="text-[9px] px-1 py-0.2 rounded bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 font-mono"
                  >
                    DEMO
                  </span>
                </div>

                <div class="text-[10px] text-slate-400 truncate dir-ltr text-right mt-0.5">
                  {{ cleanUrl(store.url) }}
                </div>

                <!-- Status & Currency -->
                <div class="flex items-center gap-2 mt-1">
                  <span class="flex items-center gap-1 text-[10px]" :class="statusTextClass(store.status)">
                    <span class="w-1.5 h-1.5 rounded-full" :class="statusDotClass(store.status)"></span>
                    {{ statusLabel(store.status) }}
                  </span>

                  <span class="text-slate-300 dark:text-slate-600">•</span>

                  <span class="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                    {{ store.currency || 'IRR' }}
                  </span>

                  <span v-if="store.timezone" class="text-slate-300 dark:text-slate-600 hidden sm:inline">•</span>

                  <span v-if="store.timezone" class="text-[9px] text-slate-400 hidden sm:inline">
                    {{ store.timezone }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Active Checkmark Indicator -->
            <div
              v-if="store.id === storeContext.activeStore?.id"
              class="w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center shrink-0 mr-2 shadow-sm"
              title="فروشگاه فعال"
            >
              <Iconsax name="tick-circle" size="14" />
            </div>
          </button>
        </div>

        <!-- Panel Footer -->
        <div class="p-2 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-850/50">
          <router-link
            to="/stores"
            @click="isOpen = false"
            class="flex items-center justify-center gap-1.5 w-full py-2 px-3 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-950/30 transition-colors"
          >
            <Iconsax name="add" size="16" />
            <span>افزودن یا اتصال فروشگاه جدید</span>
          </router-link>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useStoreContext } from '@/stores/storeContext';
import Iconsax from '@/components/icons/Iconsax.vue';
import { toPersianDigits } from '@/utils/formatters';

const storeContext = useStoreContext();
const isOpen = ref(false);
const dropdownRef = ref(null);

const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
};

const switchStore = (store) => {
  if (storeContext.activeStore?.id !== store.id) {
    storeContext.setActiveStore(store);
  }
  isOpen.value = false;
};

const cleanUrl = (url) => {
  if (!url) return '';
  return url.replace(/^https?:\/\//i, '').replace(/^demo:\/\//i, '').replace(/\/$/, '');
};

const statusDotClass = (status) => {
  switch (status) {
    case 'active':
      return 'bg-emerald-500';
    case 'inactive':
    case 'disabled':
      return 'bg-slate-400';
    case 'connection_error':
    case 'error':
      return 'bg-rose-500';
    default:
      return 'bg-amber-500';
  }
};

const statusTextClass = (status) => {
  switch (status) {
    case 'active':
      return 'text-emerald-600 dark:text-emerald-400';
    case 'inactive':
    case 'disabled':
      return 'text-slate-500 dark:text-slate-400';
    case 'connection_error':
    case 'error':
      return 'text-rose-600 dark:text-rose-400';
    default:
      return 'text-amber-600 dark:text-amber-400';
  }
};

const statusLabel = (status) => {
  switch (status) {
    case 'active':
      return 'متصل و فعال';
    case 'inactive':
      return 'غیرفعال';
    case 'disabled':
      return 'غیرفعال شده';
    case 'connection_error':
      return 'خطای اتصال';
    case 'error':
      return 'خطای سیستمی';
    default:
      return 'در انتظار بررسی';
  }
};

const handleClickOutside = (e) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
    isOpen.value = false;
  }
};

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isOpen.value) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleKeyDown);
});
</script>
