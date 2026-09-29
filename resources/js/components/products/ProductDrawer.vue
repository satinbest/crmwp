<template>
  <div>
    <!-- Backdrop -->
    <div
      v-if="isOpen"
      @click="close"
      class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm transition-opacity"
    ></div>

    <!-- Slide-over Drawer -->
    <div
      class="fixed inset-y-0 left-0 z-50 w-full max-w-xl bg-white dark:bg-slate-900 shadow-2xl border-r border-slate-200 dark:border-slate-800 transform transition-transform duration-300 ease-in-out flex flex-col"
      :class="isOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Drawer Header -->
      <div class="h-16 px-6 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
            <Iconsax name="products" size="22" />
          </div>
          <div>
            <div class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
              <span>بررسی سریع محصول</span>
              <span v-if="product" class="text-xs text-slate-400">#{{ toPersianDigits(product.id) }}</span>
            </div>
            <div class="text-xs text-slate-400">WooCommerce Product Preview</div>
          </div>
        </div>
        <button
          @click="close"
          class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
        >
          <Iconsax name="close" size="20" />
        </button>
      </div>

      <!-- Drawer Content -->
      <div v-if="loading" class="flex-1 p-6 flex flex-col items-center justify-center text-slate-400 gap-3">
        <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        <span class="text-sm">در حال بارگذاری اطلاعات محصول...</span>
      </div>

      <div v-else-if="product" class="flex-1 overflow-y-auto p-6 space-y-6">
        <!-- Top Info Card -->
        <div class="flex gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-100 dark:border-slate-800">
          <div class="w-24 h-24 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 overflow-hidden flex items-center justify-center shrink-0 shadow-sm">
            <img
              v-if="product.primary_image"
              :src="product.primary_image"
              :alt="product.name"
              class="w-full h-full object-cover"
            />
            <Iconsax v-else name="image" size="32" class="text-slate-300 dark:text-slate-600" />
          </div>

          <div class="flex-1 min-w-0 flex flex-col justify-between">
            <div>
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium" :class="getTypeBadgeClass(product.type)">
                  {{ product.type_label }}
                </span>
                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium" :class="getStatusBadgeClass(product.status)">
                  {{ product.status_label }}
                </span>
                <span v-if="product.featured" class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">
                  ویژه ⭐
                </span>
              </div>
              <h3 class="font-bold text-base text-slate-800 dark:text-slate-100 truncate" :title="product.name">
                {{ product.name }}
              </h3>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 mt-2">
              <span>کد کالا: <span class="font-mono dir-ltr inline-block">{{ product.sku || 'ندارد' }}</span></span>
              <span v-if="product.variations_count > 0" class="text-indigo-600 dark:text-indigo-400 font-medium">
                {{ formatNumber(product.variations_count) }} متغیر
              </span>
            </div>
          </div>
        </div>

        <!-- Price & Inventory Metric Grid -->
        <div class="grid grid-cols-2 gap-3">
          <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80">
            <div class="text-xs text-slate-400 mb-1">قیمت فروش</div>
            <div class="flex items-baseline gap-1">
              <span class="text-lg font-bold text-slate-800 dark:text-slate-100">
                {{ formatPrice(product.price) }}
              </span>
            </div>
            <div v-if="product.on_sale && product.regular_price > product.price" class="text-xs line-through text-slate-400 mt-0.5">
              {{ formatPrice(product.regular_price) }}
            </div>
          </div>

          <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80">
            <div class="text-xs text-slate-400 mb-1">وضعیت موجودی</div>
            <div class="flex items-center gap-1.5 mt-0.5">
              <span class="w-2.5 h-2.5 rounded-full" :class="getStockIndicatorClass(product.stock_status)"></span>
              <span class="text-sm font-bold text-slate-800 dark:text-slate-100">
                {{ product.stock_status_label }}
              </span>
            </div>
            <div class="text-xs text-slate-400 mt-1">
              <span v-if="product.manage_stock">تعداد: {{ formatNumber(product.stock_quantity ?? 0) }} عدد</span>
              <span v-else>بدون ردیابی تعداد انبار</span>
            </div>
          </div>
        </div>

        <!-- Quick Status & Inventory Changer -->
        <div class="p-4 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 space-y-3">
          <div class="text-xs font-bold text-slate-700 dark:text-slate-200">عملیات سریع وضعیت و انبار</div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[11px] text-slate-500 mb-1">وضعیت انتشار</label>
              <select
                v-model="quickStatus"
                @change="updateQuickStatus"
                :disabled="savingStatus"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="publish">منتشر شده</option>
                <option value="draft">پیش‌نویس</option>
                <option value="pending">در انتظار بررسی</option>
                <option value="private">خصوصی</option>
              </select>
            </div>

            <div>
              <label class="block text-[11px] text-slate-500 mb-1">وضعیت انبار</label>
              <select
                v-model="quickStockStatus"
                @change="updateQuickStock"
                :disabled="savingStatus"
                class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-2 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="instock">موجود در انبار</option>
                <option value="outofstock">ناموجود</option>
                <option value="onbackorder">در پیش‌خرید</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Categories & Tags -->
        <div class="space-y-3">
          <div class="text-xs font-bold text-slate-700 dark:text-slate-300">دسته‌بندی‌ها و برچسب‌ها</div>
          <div>
            <div class="text-[11px] text-slate-400 mb-1.5">دسته‌بندی‌ها:</div>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="cat in product.categories"
                :key="cat.id"
                class="px-2.5 py-1 rounded-lg text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700"
              >
                {{ cat.name }}
              </span>
              <span v-if="!product.categories?.length" class="text-xs text-slate-400 italic">بدون دسته‌بندی</span>
            </div>
          </div>

          <div>
            <div class="text-[11px] text-slate-400 mb-1.5">برچسب‌ها:</div>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="tag in product.tags"
                :key="tag.id"
                class="px-2.5 py-0.5 rounded-full text-xs bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700"
              >
                #{{ tag.name }}
              </span>
              <span v-if="!product.tags?.length" class="text-xs text-slate-400 italic">بدون برچسب</span>
            </div>
          </div>
        </div>

        <!-- Short Description -->
        <div v-if="product.short_description" class="space-y-1.5">
          <div class="text-xs font-bold text-slate-700 dark:text-slate-300">توضیحات کوتاه</div>
          <div
            class="text-xs text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-850 p-3 rounded-xl border border-slate-100 dark:border-slate-800 leading-relaxed"
            v-html="product.short_description"
          ></div>
        </div>

        <!-- Attributes -->
        <div v-if="product.attributes?.length" class="space-y-2">
          <div class="text-xs font-bold text-slate-700 dark:text-slate-300">ویژگی‌های کالا (Attributes)</div>
          <div class="space-y-1.5">
            <div
              v-for="attr in product.attributes"
              :key="attr.id || attr.name"
              class="flex items-center justify-between p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/60 text-xs"
            >
              <span class="font-medium text-slate-700 dark:text-slate-300">{{ attr.name }}:</span>
              <span class="text-slate-500 dark:text-slate-400">{{ (attr.options || []).join('، ') }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Drawer Footer Actions -->
      <div class="p-4 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-900/50 flex items-center gap-3">
        <router-link
          v-if="product"
          :to="`/products/${product.id}`"
          @click="close"
          class="flex-1 py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold flex items-center justify-center gap-2 shadow-md shadow-indigo-600/20 transition-colors"
        >
          <Iconsax name="edit" size="18" />
          <span>مشاهده و ویرایش کامل محصول</span>
        </router-link>

        <button
          @click="close"
          class="py-2.5 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
        >
          بستن
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import api from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import { formatNumber, formatPrice, toPersianDigits } from '@/utils/formatters';

const props = defineProps({
  productId: {
    type: [Number, String],
    default: null,
  },
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'product-updated']);

const notification = useNotificationStore();
const product = ref(null);
const loading = ref(false);
const savingStatus = ref(false);
const quickStatus = ref('publish');
const quickStockStatus = ref('instock');

const fetchProduct = async () => {
  if (!props.productId) return;
  loading.value = true;
  try {
    const res = await api.get(`/products/${props.productId}`);
    if (res.data?.success) {
      product.value = res.data.data;
      quickStatus.value = product.value.status;
      quickStockStatus.value = product.value.stock_status;
    }
  } catch (err) {
    notification.error(err.response?.data?.error?.message || 'خطا در دریافت اطلاعات محصول');
    emit('close');
  } finally {
    loading.value = false;
  }
};

watch(
  () => props.isOpen,
  (open) => {
    if (open && props.productId) {
      fetchProduct();
    } else {
      product.value = null;
    }
  }
);

const close = () => {
  emit('close');
};


const getTypeBadgeClass = (type) => {
  switch (type) {
    case 'variable':
      return 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400';
    case 'external':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';
    case 'grouped':
      return 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400';
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
  }
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'publish':
      return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400';
    case 'draft':
      return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
    case 'pending':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';
    default:
      return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400';
  }
};

const getStockIndicatorClass = (status) => {
  switch (status) {
    case 'instock':
      return 'bg-emerald-500 ring-4 ring-emerald-500/20';
    case 'outofstock':
      return 'bg-rose-500 ring-4 ring-rose-500/20';
    default:
      return 'bg-amber-500 ring-4 ring-amber-500/20';
  }
};

const updateQuickStatus = async () => {
  if (!product.value) return;
  savingStatus.value = true;
  try {
    const res = await api.patch(`/products/${product.value.id}`, { status: quickStatus.value });
    if (res.data?.success) {
      product.value = res.data.data;
      notification.success('وضعیت محصول با موفقیت تغییر یافت.');
      emit('product-updated', product.value);
    }
  } catch (err) {
    quickStatus.value = product.value.status;
    notification.error(err.response?.data?.error?.message || 'خطا در تغییر وضعیت محصول');
  } finally {
    savingStatus.value = false;
  }
};

const updateQuickStock = async () => {
  if (!product.value) return;
  savingStatus.value = true;
  try {
    const res = await api.patch(`/products/${product.value.id}`, { stock_status: quickStockStatus.value });
    if (res.data?.success) {
      product.value = res.data.data;
      notification.success('وضعیت موجودی کالا بروز شد.');
      emit('product-updated', product.value);
    }
  } catch (err) {
    quickStockStatus.value = product.value.stock_status;
    notification.error(err.response?.data?.error?.message || 'خطا در تغییر وضعیت موجودی');
  } finally {
    savingStatus.value = false;
  }
};
</script>
