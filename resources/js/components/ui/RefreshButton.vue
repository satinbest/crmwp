<template>
  <button
    type="button"
    :disabled="loading || disabled"
    :aria-disabled="loading || disabled"
    :aria-label="title || label || 'به‌روزرسانی داده‌ها'"
    :title="title || label || 'به‌روزرسانی داده‌ها'"
    class="btn-refresh inline-flex items-center justify-center font-semibold transition-all select-none shrink-0 border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-700 active:bg-slate-200 dark:active:bg-slate-700 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-500 shadow-2xs disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none"
    :class="[
      sizeClasses,
      iconOnly ? iconOnlyClasses : ''
    ]"
    v-bind="$attrs"
  >
    <Iconsax
      name="refresh"
      :size="iconSize"
      :class="{ 'animate-spin': loading }"
      class="shrink-0 transition-transform"
    />
    <span v-if="!iconOnly && label" class="truncate hidden sm:inline text-xs">
      {{ label }}
    </span>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';

const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  label: {
    type: String,
    default: ''
  },
  title: {
    type: String,
    default: 'به‌روزرسانی داده‌ها'
  },
  iconOnly: {
    type: Boolean,
    default: false
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v)
  }
});

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return props.iconOnly ? 'w-8 h-8 rounded-lg' : 'h-8 px-2.5 rounded-lg gap-1.5 text-xs';
    case 'lg':
      return props.iconOnly ? 'w-11 h-11 rounded-xl' : 'h-11 px-4 rounded-xl gap-2.5 text-sm';
    case 'md':
    default:
      return props.iconOnly ? 'w-10 h-10 rounded-xl' : 'h-10 px-3.5 rounded-xl gap-2 text-xs';
  }
});

const iconOnlyClasses = computed(() => 'p-0 flex items-center justify-center');

const iconSize = computed(() => {
  switch (props.size) {
    case 'sm':
      return 14;
    case 'lg':
      return 18;
    case 'md':
    default:
      return 16;
  }
});
</script>
