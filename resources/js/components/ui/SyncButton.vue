<template>
  <div class="inline-flex items-center gap-2 flex-wrap">
    <!-- Honest Stale / Last Synced Indicator -->
    <div
      v-if="displayTimestamp || isStale"
      class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[11px] font-medium border"
      :class="isStale
        ? 'bg-amber-50 dark:bg-amber-950/60 border-amber-200 dark:border-amber-900 text-amber-700 dark:text-amber-300'
        : 'bg-slate-100/80 dark:bg-slate-800/80 border-slate-200/60 dark:border-slate-700/60 text-slate-500 dark:text-slate-400'"
      :title="isStale ? 'داده‌ها به دلیل فاصله زمانی از آخرین همگام‌سازی ممکن است قدیمی باشند' : 'زمان آخرین همگام‌سازی موفق با ووکامرس'"
    >
      <Iconsax
        :name="isStale ? 'warning-2' : 'clock'"
        size="13"
        :class="isStale ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'"
      />
      <span>{{ isStale ? 'اطلاعات نیازمند همگام‌سازی' : `آخرین بروزرسانی: ${displayTimestamp}` }}</span>
    </div>

    <!-- Manual Sync Action Button -->
    <button
      type="button"
      @click="handleSync"
      :disabled="syncing"
      class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl font-bold text-xs transition-all border border-indigo-200 dark:border-indigo-800/80 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 active:scale-95 disabled:opacity-50 disabled:pointer-events-none cursor-pointer shadow-xs"
      :title="`همگام‌سازی دستی اطلاعات ${entityLabel} با ووکامرس`"
    >
      <Iconsax
        name="refresh-circle"
        size="16"
        :class="{ 'animate-spin': syncing }"
        class="shrink-0 text-indigo-600 dark:text-indigo-400"
      />
      <span>{{ syncing ? 'در حال همگام‌سازی...' : (label || `همگام‌سازی ${entityLabel}`) }}</span>
    </button>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import api from '@/api/client';
import { useStoreContext } from '@/stores/storeContext';
import { useNotificationStore } from '@/stores/notification';
import { formatDateTime } from '@/utils/formatters';

const props = defineProps({
  entity: {
    type: String,
    default: 'all', // 'all', 'products', 'orders', 'customers', 'inventory'
  },
  label: {
    type: String,
    default: '',
  },
  meta: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['synced']);

const storeContext = useStoreContext();
const notification = useNotificationStore();

const syncing = ref(false);
const localMeta = ref(null);

const entityLabel = computed(() => {
  return matchEntityLabel(props.entity);
});

function matchEntityLabel(ent) {
  switch (ent) {
    case 'products': return 'محصولات';
    case 'orders': return 'سفارش‌ها';
    case 'customers': return 'مشتریان';
    case 'categories': return 'دسته‌بندی‌ها';
    case 'inventory': return 'انبار و موجودی';
    default: return 'فروشگاه';
  }
}

const currentMeta = computed(() => {
  return localMeta.value || props.meta || {};
});

const isStale = computed(() => {
  return Boolean(currentMeta.value?.is_stale);
});

const displayTimestamp = computed(() => {
  const ts = currentMeta.value?.last_synced_at || currentMeta.value?.last_successful_sync;
  if (!ts) return null;
  return formatDateTime(ts);
});

async function handleSync() {
  const storeId = storeContext.activeStoreId;
  if (!storeId) {
    notification.warning('لطفاً ابتدا یک فروشگاه انتخاب فرمایید.');
    return;
  }

  syncing.value = true;
  try {
    const res = await api.post(`/stores/${storeId}/sync/start`, {
      entity: props.entity,
      force: true,
    });

    if (res?.success !== false) {
      notification.success(res.data?.message || `همگام‌سازی ${entityLabel.value} با موفقیت انجام شد.`);
      if (res.data?.details) {
        localMeta.value = {
          last_synced_at: new Date().toISOString(),
          is_stale: false,
          sync_status: 'completed',
        };
      }
      emit('synced', res.data);
    } else {
      notification.error(res.error?.message || 'خطا در همگام‌سازی اطلاعات با ووکامرس');
    }
  } catch (err) {
    notification.error(err.message || 'خطا در برقراری ارتباط با سرور ووکامرس');
  } finally {
    syncing.value = false;
  }
}
</script>
