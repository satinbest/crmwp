<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :aria-disabled="disabled || loading"
    :aria-busy="loading"
    class="btn inline-flex items-center justify-center font-semibold transition-all select-none focus:outline-hidden disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none"
    :class="[
      variantClasses,
      sizeClasses,
      block ? 'w-full' : '',
      loading ? 'cursor-wait' : ''
    ]"
    v-bind="$attrs"
  >
    <!-- Loading Spinner -->
    <svg
      v-if="loading"
      class="animate-spin shrink-0"
      :class="spinnerSizeClasses"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      ></circle>
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      ></path>
    </svg>

    <!-- Leading Icon -->
    <Iconsax
      v-else-if="icon && iconPosition === 'right'"
      :name="icon"
      :size="iconSizeComputed"
      class="shrink-0"
    />

    <!-- Slot or Text -->
    <span v-if="$slots.default || loadingText" class="truncate">
      <template v-if="loading && loadingText">{{ loadingText }}</template>
      <slot v-else />
    </span>

    <!-- Trailing Icon -->
    <Iconsax
      v-if="!loading && icon && iconPosition === 'left'"
      :name="icon"
      :size="iconSizeComputed"
      class="shrink-0"
    />
  </button>
</template>

<script setup>
import { computed } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'outline', 'ghost', 'danger', 'success'].includes(v)
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v)
  },
  type: {
    type: String,
    default: 'button'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  loading: {
    type: Boolean,
    default: false
  },
  loadingText: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: ''
  },
  iconSize: {
    type: [Number, String],
    default: null
  },
  iconPosition: {
    type: String,
    default: 'right', // in RTL: right is leading
    validator: (v) => ['left', 'right'].includes(v)
  },
  block: {
    type: Boolean,
    default: false
  }
});

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'secondary':
      return 'btn-secondary bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-700 shadow-2xs focus-visible:ring-2 focus-visible:ring-indigo-500';
    case 'outline':
      return 'btn-outline bg-transparent border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-indigo-500';
    case 'ghost':
      return 'btn-ghost bg-transparent border border-transparent text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100 focus-visible:ring-2 focus-visible:ring-indigo-500';
    case 'danger':
      return 'btn-danger bg-rose-600 hover:bg-rose-700 text-white shadow-md shadow-rose-600/20 focus-visible:ring-2 focus-visible:ring-rose-500';
    case 'success':
      return 'btn-success bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-600/20 focus-visible:ring-2 focus-visible:ring-emerald-500';
    case 'primary':
    default:
      return 'btn-primary bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-600/20 focus-visible:ring-2 focus-visible:ring-indigo-500';
  }
});

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'h-8 px-3 text-xs rounded-lg gap-1.5';
    case 'lg':
      return 'h-11 px-5 text-sm rounded-xl gap-2.5';
    case 'md':
    default:
      return 'h-10 px-4 text-xs rounded-xl gap-2';
  }
});

const spinnerSizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'w-3.5 h-3.5';
    case 'lg':
      return 'w-5 h-5';
    case 'md':
    default:
      return 'w-4 h-4';
  }
});

const iconSizeComputed = computed(() => {
  if (props.iconSize) return Number(props.iconSize);
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
