<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col justify-between transition-all">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3 mb-4">
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full bg-violet-500 shadow-sm shadow-violet-500/50"></span>
        <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">فعالیت‌های اخیر فروشگاه و CRM</h3>
      </div>

      <router-link
        to="/crm/activities"
        class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
      >
        <span>مشاهده همه فعالیت‌ها</span>
        <Iconsax name="arrow-left" size="14" />
      </router-link>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="space-y-3 py-2 animate-pulse">
      <div v-for="i in 4" :key="i" class="flex items-start gap-3 p-2.5">
        <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 shrink-0"></div>
        <div class="space-y-1.5 flex-1">
          <div class="w-48 h-3 bg-slate-200 dark:bg-slate-700 rounded"></div>
          <div class="w-24 h-2.5 bg-slate-200 dark:bg-slate-700 rounded"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="activities.length === 0"
      class="h-56 flex flex-col items-center justify-center text-center p-6 bg-slate-50/50 dark:bg-slate-850/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-800"
    >
      <div class="w-12 h-12 rounded-2xl bg-violet-50 dark:bg-violet-950/60 text-violet-500 flex items-center justify-center mb-3">
        <Iconsax name="activity" size="24" />
      </div>
      <h4 class="text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-300">فعالیتی ثبت نشده است</h4>
      <p class="text-xs text-slate-400 mt-1 max-w-xs">
        با ثبت سفارش، تغییر وضعیت، یا فعالیت‌های CRM، رخدادها در اینجا نمایش می‌یابند.
      </p>
    </div>

    <!-- Activities Feed -->
    <div v-else class="space-y-3">
      <div
        v-for="act in activities"
        :key="act.id"
        class="flex items-start gap-3 p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
      >
        <!-- Action Type Icon -->
        <div
          class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
          :class="getActionBadgeClass(act.action_type)"
        >
          <Iconsax :name="getActionIcon(act.action_type)" size="16" />
        </div>

        <!-- Activity Body -->
        <div class="min-w-0 flex-1 text-xs">
          <div class="text-slate-800 dark:text-slate-200 leading-snug">
            <span class="font-bold text-slate-900 dark:text-white">{{ act.user_name || 'سیستم' }}</span>
            <span class="mx-1 text-slate-400">•</span>
            <span>{{ formatActionDescription(act) }}</span>
          </div>

          <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-1">
            <span>{{ formatRelativeTime(act.created_at) }}</span>
            <span v-if="act.entity_type">
              ({{ getEntityLabel(act.entity_type) }} #{{ act.entity_id }})
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Iconsax from '@/components/icons/Iconsax.vue';
import { formatRelativeTime } from '@/utils/formatters';

const props = defineProps({
  activities: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const getActionIcon = (type) => {
  if (!type) return 'activity';
  if (type.includes('order')) return 'orders';
  if (type.includes('customer')) return 'user';
  if (type.includes('product') || type.includes('stock')) return 'box';
  if (type.includes('bulk')) return 'bulk';
  if (type.includes('task')) return 'task';
  return 'activity';
};

const getActionBadgeClass = (type) => {
  if (!type) return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
  if (type.includes('order')) return 'bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400';
  if (type.includes('customer')) return 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400';
  if (type.includes('product') || type.includes('stock')) return 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400';
  if (type.includes('bulk')) return 'bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400';
  return 'bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400';
};

const formatActionDescription = (act) => {
  const type = act.action_type || '';
  const d = act.details || {};

  switch (type) {
    case 'order_created':
      return `سفارش جدید #${act.entity_id} با مبلغ ${d.total || ''} ثبت شد`;
    case 'order_status_changed':
      return `وضعیت سفارش #${act.entity_id} به «${d.new_label || d.new_status || ''}» تغییر یافت`;
    case 'order_refunded':
      return `مبلغ استرداد برای سفارش #${act.entity_id} ثبت گردید`;
    case 'customer_created':
      return `مشتری جدید «${d.name || d.email || ''}» ثبت شد`;
    case 'product_updated':
      return `اطلاعات محصول #${act.entity_id} بروزرسانی شد`;
    case 'stock_updated':
      return `موجودی انبار محصول #${act.entity_id} تغییر کرد`;
    case 'bulk_operation_executed':
      return `عملیات گروهی روی اقلام ووکامرس انجام شد`;
    case 'order_task_created':
    case 'customer_task_created':
      return `وظیفه جدید «${d.title || ''}» ایجاد شد`;
    default:
      return type.replace(/_/g, ' ');
  }
};

const getEntityLabel = (entityType) => {
  switch (entityType) {
    case 'order': return 'سفارش';
    case 'customer': return 'مشتری';
    case 'product': return 'محصول';
    case 'task': return 'وظیفه';
    default: return entityType;
  }
};
</script>
