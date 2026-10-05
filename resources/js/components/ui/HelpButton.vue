<template>
  <div class="relative inline-flex items-center" ref="containerRef">
    <!-- Trigger Button -->
    <button
      type="button"
      @click.stop="toggleHelp"
      class="p-1 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-all cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
      :class="{ 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60': isOpen }"
      :title="`راهنمای ${item.title || 'این بخش'}`"
      :aria-label="`راهنمای ${item.title || 'بخش'}`"
      :aria-expanded="isOpen"
    >
      <Iconsax name="info-circle" :size="size" />
    </button>

    <!-- Floating Help Popover (Teleported to body for guaranteed escape from card overflow) -->
    <teleport to="body">
      <transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 scale-95 -translate-y-1"
        enter-to-class="opacity-100 scale-100 translate-y-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 scale-100 translate-y-0"
        leave-to-class="opacity-0 scale-95 -translate-y-1"
      >
        <div
          v-if="isOpen"
          ref="popoverRef"
          :style="popoverStyles"
          class="fixed z-modal-layer w-72 sm:w-80 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xl text-right font-sans select-none"
          role="dialog"
          :aria-label="item.title"
          @click.stop
        >
          <!-- Header -->
          <div class="flex items-start justify-between gap-2 pb-2.5 border-b border-slate-100 dark:border-slate-800/80">
            <div class="flex items-center gap-2">
              <div class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                <Iconsax name="info-circle" size="14" />
              </div>
              <h4 class="text-xs font-black text-slate-900 dark:text-white">
                {{ item.title }}
              </h4>
            </div>

            <button
              type="button"
              @click="closeHelp"
              class="p-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              aria-label="بستن راهنما"
            >
              <Iconsax name="close" size="14" />
            </button>
          </div>

          <!-- Description Body -->
          <div class="py-2.5 text-xs text-slate-600 dark:text-slate-300 leading-relaxed font-normal">
            {{ item.description }}
          </div>

          <!-- Source & Usage Metadata (if available) -->
          <div v-if="item.source || item.usage" class="pt-2 border-t border-slate-100 dark:border-slate-800/80 space-y-1.5 text-[10px]">
            <div v-if="item.source" class="flex items-start gap-1.5 text-slate-400">
              <span class="font-bold text-slate-500 dark:text-slate-400 shrink-0">منبع داده:</span>
              <span class="text-slate-600 dark:text-slate-300 leading-tight">{{ item.source }}</span>
            </div>
            <div v-if="item.usage" class="flex items-start gap-1.5 text-slate-400">
              <span class="font-bold text-indigo-500 dark:text-indigo-400 shrink-0">کاربرد:</span>
              <span class="text-slate-600 dark:text-slate-300 leading-tight">{{ item.usage }}</span>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import { getHelp } from '@/config/helpContent';

const props = defineProps({
  helpKey: {
    type: String,
    required: true,
  },
  size: {
    type: [Number, String],
    default: 16,
  },
});

const isOpen = ref(false);
const containerRef = ref(null);
const popoverRef = ref(null);
const popoverPos = ref({ top: 0, left: 0 });

const item = computed(() => {
  return getHelp(props.helpKey);
});

const popoverStyles = computed(() => {
  return {
    top: `${popoverPos.value.top}px`,
    left: `${popoverPos.value.left}px`,
  };
});

const calculatePosition = () => {
  if (!containerRef.value) return;
  const rect = containerRef.value.getBoundingClientRect();
  const width = window.innerWidth >= 640 ? 320 : 288;
  const margin = 10;

  // Try aligning to right side of button (for RTL)
  let left = rect.right - width;

  // Keep within viewport boundaries
  if (left < margin) {
    left = margin;
  }
  if (left + width > window.innerWidth - margin) {
    left = window.innerWidth - width - margin;
  }

  // Vertical: below button or flip above if close to bottom
  let top = rect.bottom + 8;
  if (top + 220 > window.innerHeight && rect.top > 230) {
    top = rect.top - 210;
  }

  popoverPos.value = { top, left };
};

const toggleHelp = async () => {
  if (isOpen.value) {
    isOpen.value = false;
  } else {
    calculatePosition();
    isOpen.value = true;
    await nextTick();
    calculatePosition();
  }
};

const closeHelp = () => {
  isOpen.value = false;
};

const handleClickOutside = (e) => {
  if (!isOpen.value) return;
  if (
    containerRef.value &&
    !containerRef.value.contains(e.target) &&
    popoverRef.value &&
    !popoverRef.value.contains(e.target)
  ) {
    closeHelp();
  }
};

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isOpen.value) {
    closeHelp();
  }
};

const handleScrollOrResize = () => {
  if (isOpen.value) {
    calculatePosition();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleKeyDown);
  window.addEventListener('resize', handleScrollOrResize);
  window.addEventListener('scroll', handleScrollOrResize, true);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleKeyDown);
  window.removeEventListener('resize', handleScrollOrResize);
  window.removeEventListener('scroll', handleScrollOrResize, true);
});
</script>
