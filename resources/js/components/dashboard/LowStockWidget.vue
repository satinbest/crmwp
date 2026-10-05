<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col justify-between transition-all">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3 mb-4">
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-sm shadow-amber-500/50"></span>
        <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">محصولات کم‌موجودی و ناموجود</h3>
        <HelpButton help-key="dashboard_low_stock_widget" size="14" />
        <span v-if="items.length > 0" class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300">
          {{ formatNumber(items.length) }} کالا
        </span>
      </div>

      <router-link
        to="/inventory"
        class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
      >
        <span>مدیریت انبار</span>
        <Iconsax name="arrow-left" size="14" />
      </router-link>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="space-y-3 py-2 animate-pulse">
      <div v-for="i in 4" :key="i" class="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-850 rounded-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-slate-200 dark:bg-slate-700"></div>
          <div class="space-y-1.5">
            <div class="w-32 h-3 bg-slate-200 dark:bg-slate-700 rounded"></div>
            <div class="w-20 h-2.5 bg-slate-200 dark:bg-slate-700 rounded"></div>
          </div>
        </div>
        <div class="w-16 h-3 bg-slate-200 dark:bg-slate-700 rounded"></div>
      </div>
    </div>

    <!-- Empty State (No low stock items) -->
    <div
      v-else-if="items.length === 0"
      class="h-56 flex flex-col items-center justify-center text-center p-6 bg-emerald-50/30 dark:bg-emerald-950/10 rounded-xl border border-dashed border-emerald-200/60 dark:border-emerald-900/40"
    >
      <div class="w-12 h-12 rounded-2xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-3">
        <Iconsax name="shield-tick" size="24" />
      </div>
      <h4 class="text-xs sm:text-sm font-bold text-emerald-800 dark:text-emerald-300">موجودی انبار پایدار است</h4>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xs">
        در حال حاضر هیچ کالایی در آستانه اتمام موجودی یا ناموجود قرار ندارد.
      </p>
    </div>

    <!-- Products List -->
    <div v-else class="space-y-2.5">
      <div
        v-for="item in items"
        :key="item.id || item.product_id"
        class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/70 dark:bg-slate-850/60 border border-slate-100 dark:border-slate-800 hover:border-slate-200 dark:hover:border-slate-700 transition-colors"
      >
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <!-- Product Image -->
          <div class="w-11 h-11 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shrink-0 flex items-center justify-center">
            <img
              v-if="getProductImage(item)"
              :src="getProductImage(item)"
              :alt="item.name || item.product_name"
              class="w-full h-full object-cover"
              loading="lazy"
            />
            <Iconsax v-else name="box" size="20" class="text-slate-400" />
          </div>

          <!-- Product Details -->
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <router-link
                :to="getProductLink(item)"
                class="text-xs font-bold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors truncate block"
                :title="item.name || item.product_name"
              >
                {{ item.name || item.product_name }}
              </router-link>
              <span
                v-if="item.type === 'variable' || item.variation_id"
                class="text-[9px] px-1.5 py-0.2 rounded bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 shrink-0 font-bold"
              >
                متغیر
              </span>
            </div>

            <div class="flex items-center gap-2 text-[11px] text-slate-400 mt-0.5">
              <span class="dir-ltr text-right">SKU: {{ item.sku || 'ندارد' }}</span>
              <span>•</span>
              <span class="font-semibold text-slate-600 dark:text-slate-300">
                {{ formatCurrency(item.price || item.regular_price || 0, currencySymbol) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Stock Status & Quantity -->
        <div class="text-left shrink-0 pl-1">
          <div
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold"
            :class="isOutOfStock(item) ? 'bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300' : 'bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300'"
          >
            <span>●</span>
            <span>{{ isOutOfStock(item) ? 'ناموجود' : `${formatNumber(item.stock_quantity ?? item.stock ?? 0)} عدد` }}</span>
          </div>

          <div class="mt-1">
            <router-link
              :to="'/inventory?search=' + (item.sku || item.id || item.product_id)"
              class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
            >
              افزایش موجودی ←
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Iconsax from '@/components/icons/Iconsax.vue';
import HelpButton from '@/components/ui/HelpButton.vue';
import { formatNumber, formatCurrency } from '@/utils/formatters';

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  currencySymbol: {
    type: String,
    default: 'تومان',
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const getProductImage = (item) => {
  if (item.images && item.images.length > 0) {
    return item.images[0].src || item.images[0];
  }
  if (item.image) {
    return typeof item.image === 'string' ? item.image : item.image.src;
  }
  return null;
};

const getProductLink = (item) => {
  const id = item.id || item.product_id;
  return `/products/${id}`;
};

const isOutOfStock = (item) => {
  const qty = Number(item.stock_quantity ?? item.stock ?? 0);
  const status = item.stock_status || '';
  return status === 'outofstock' || qty <= 0;
};
</script>
