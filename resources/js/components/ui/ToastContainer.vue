<template>
  <div class="fixed bottom-5 left-5 z-toast-layer flex flex-col gap-2 max-w-sm w-full pointer-events-none">
    <transition-group
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in store.toasts"
        :key="toast.id"
        class="pointer-events-auto p-4 rounded-xl shadow-lg border backdrop-blur-md flex items-start gap-3 transition-all"
        :class="getToastClasses(toast.type)"
      >
        <div class="mt-0.5">
          <Iconsax v-if="toast.type === 'success'" name="check" size="20" class="text-emerald-500" />
          <Iconsax v-else-if="toast.type === 'error'" name="close" size="20" class="text-rose-500" />
          <Iconsax v-else-if="toast.type === 'warning'" name="notification" size="20" class="text-amber-500" />
          <Iconsax v-else name="activity" size="20" class="text-indigo-500" />
        </div>
        <div class="flex-1 text-right">
          <h4 class="text-sm font-bold">{{ toast.title }}</h4>
          <p class="text-xs mt-1 opacity-90 leading-relaxed">{{ toast.message }}</p>
        </div>
        <button
          @click="store.remove(toast.id)"
          class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
        >
          <Iconsax name="close" size="16" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { useNotificationStore } from '@/stores/notification';
import Iconsax from '@/components/icons/Iconsax.vue';

const store = useNotificationStore();

const getToastClasses = (type) => {
  switch (type) {
    case 'success':
      return 'bg-white/95 dark:bg-slate-900/95 border-emerald-500/30 text-slate-800 dark:text-slate-100';
    case 'error':
      return 'bg-white/95 dark:bg-slate-900/95 border-rose-500/30 text-slate-800 dark:text-slate-100';
    case 'warning':
      return 'bg-white/95 dark:bg-slate-900/95 border-amber-500/30 text-slate-800 dark:text-slate-100';
    default:
      return 'bg-white/95 dark:bg-slate-900/95 border-indigo-500/30 text-slate-800 dark:text-slate-100';
  }
};
</script>
