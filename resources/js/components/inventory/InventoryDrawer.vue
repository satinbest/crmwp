<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-hidden" dir="rtl">
    <!-- Backdrop -->
    <div
      class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
      @click="closeDrawer"
    ></div>

    <div class="fixed inset-y-0 left-0 max-w-full flex pl-0 md:pl-10">
      <div class="w-screen max-w-xl bg-white dark:bg-slate-900 shadow-2xl flex flex-col border-r border-slate-200 dark:border-slate-800">
        <!-- Header -->
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-start justify-between bg-slate-50/50 dark:bg-slate-850/50 shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
              <img
                v-if="item?.image"
                :src="item.image"
                :alt="item.product_name"
                class="w-full h-full object-cover"
              />
              <Iconsax v-else name="box" size="24" class="text-slate-400" />
            </div>

            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs px-2.5 py-0.5 rounded-full font-bold" :class="item?.is_variation ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'">
                  {{ item?.is_variation ? 'تنوع کالا' : (item?.type_label || 'محصول ساده') }}
                </span>
                <span v-if="item?.is_low_stock" class="text-xs px-2 py-0.5 rounded-full font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                  کمبود موجودی
                </span>
              </div>
              <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 mt-1 line-clamp-1" :title="item?.display_name">
                {{ item?.display_name || 'مدیریت موجودی' }}
              </h2>
              <p class="text-xs text-slate-400 mt-0.5 flex items-center gap-2">
                <span>SKU: <span class="font-mono dir-ltr inline-block">{{ item?.sku || 'فاقد کد' }}</span></span>
                <span>•</span>
                <span>شناسه: #{{ toPersianDigits(item?.variation_id || item?.product_id) }}</span>
              </p>
            </div>
          </div>

          <button
            @click="closeDrawer"
            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <Iconsax name="close" size="20" />
          </button>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-200 dark:border-slate-800 px-6 bg-white dark:bg-slate-900 text-xs shrink-0">
          <button
            @click="activeTab = 'stock'"
            class="py-3 px-4 font-bold border-b-2 transition-colors flex items-center gap-2"
            :class="activeTab === 'stock' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
          >
            <Iconsax name="box" size="16" />
            <span>تنظیم و تغییر موجودی</span>
          </button>

          <button
            @click="activeTab = 'config'"
            class="py-3 px-4 font-bold border-b-2 transition-colors flex items-center gap-2"
            :class="activeTab === 'config' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
          >
            <Iconsax name="settings" size="16" />
            <span>پیکربندی انبارداری</span>
          </button>

          <button
            v-if="item?.has_variations && !item?.is_variation"
            @click="activeTab = 'variations'"
            class="py-3 px-4 font-bold border-b-2 transition-colors flex items-center gap-2"
            :class="activeTab === 'variations' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
          >
            <Iconsax name="element-4" size="16" />
            <span>تنوع‌های کالایی ({{ formatNumber(item.variations_count) }})</span>
          </button>
        </div>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <!-- TAB 1: Stock Adjustment -->
          <div v-if="activeTab === 'stock'" class="space-y-6">
            <!-- Current Status Card -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
              <div>
                <span class="text-xs text-slate-400">موجودی فعلی در ووکامرس</span>
                <div class="flex items-baseline gap-2 mt-1">
                  <span class="text-3xl font-black text-slate-900 dark:text-slate-50">
                    {{ item?.manage_stock ? formatNumber(item?.stock_quantity ?? 0) : 'نامحدود' }}
                  </span>
                  <span v-if="item?.manage_stock" class="text-xs text-slate-500">عدد</span>
                  <span v-else class="text-xs text-slate-400">(فاقد مدیریت انبار عددی)</span>
                </div>
              </div>

              <div class="text-left space-y-1">
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold border" :class="getStockStatusBadgeClass(item?.stock_status)">
                  {{ item?.stock_status_label }}
                </span>
                <div v-if="item?.low_stock_amount !== null" class="text-[11px] text-slate-400">
                  آستانه هشدار: {{ formatNumber(item.low_stock_amount) }} عدد
                </div>
              </div>
            </div>

            <!-- Quick Stock Actions -->
            <div class="space-y-4">
              <h3 class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <span>عملیات سریع تغییر موجودی</span>
              </h3>

              <!-- Set Stock Form -->
              <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs">
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-200 flex items-center justify-between">
                  <span>تنظیم مقدار قطعی موجودی (Set)</span>
                  <span class="text-[11px] text-slate-400">جایگزینی موجودی جدید</span>
                </div>
                <div class="flex items-center gap-2">
                  <input
                    v-model.number="setAmount"
                    type="number"
                    min="0"
                    placeholder="مثلاً 50"
                    class="flex-1 py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                  />
                  <button
                    @click="executeStockChange('set', setAmount)"
                    :disabled="setAmount === null || setAmount < 0 || updatingStock"
                    class="py-2 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all disabled:opacity-50 shrink-0"
                  >
                    ثبت موجودی
                  </button>
                </div>
              </div>

              <!-- Relative Increase (+) Form -->
              <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs">
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-200 flex items-center justify-between">
                  <span>افزایش موجودی انبار (+ Increase)</span>
                  <span class="text-[11px] text-emerald-600 font-bold">+ ورودی جدید به انبار</span>
                </div>
                <!-- Presets -->
                <div class="flex items-center gap-2">
                  <button
                    v-for="amt in [1, 5, 10, 25, 50]"
                    :key="amt"
                    @click="increaseAmount = amt"
                    class="flex-1 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold hover:bg-emerald-50 dark:hover:bg-emerald-950/40 hover:text-emerald-600 transition-colors"
                    :class="increaseAmount === amt ? 'bg-emerald-600 text-white border-emerald-600' : 'text-slate-600 dark:text-slate-300'"
                  >
                    +{{ formatNumber(amt) }}
                  </button>
                </div>
                <div class="flex items-center gap-2">
                  <input
                    v-model.number="increaseAmount"
                    type="number"
                    min="1"
                    placeholder="تعداد افزایش"
                    class="flex-1 py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
                  />
                  <button
                    @click="executeStockChange('increase', increaseAmount)"
                    :disabled="!increaseAmount || increaseAmount <= 0 || updatingStock"
                    class="py-2 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all disabled:opacity-50 shrink-0"
                  >
                    افزایش موجودی
                  </button>
                </div>
              </div>

              <!-- Relative Decrease (-) Form -->
              <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs">
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-200 flex items-center justify-between">
                  <span>کاهش موجودی انبار (- Decrease)</span>
                  <span class="text-[11px] text-rose-600 font-bold">- خروج کالا از انبار</span>
                </div>
                <!-- Presets -->
                <div class="flex items-center gap-2">
                  <button
                    v-for="amt in [1, 5, 10, 25, 50]"
                    :key="amt"
                    @click="decreaseAmount = amt"
                    class="flex-1 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:text-rose-600 transition-colors"
                    :class="decreaseAmount === amt ? 'bg-rose-600 text-white border-rose-600' : 'text-slate-600 dark:text-slate-300'"
                  >
                    -{{ formatNumber(amt) }}
                  </button>
                </div>
                <div class="flex items-center gap-2">
                  <input
                    v-model.number="decreaseAmount"
                    type="number"
                    min="1"
                    placeholder="تعداد کاهش"
                    class="flex-1 py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-rose-500"
                  />
                  <button
                    @click="promptDecrease(decreaseAmount)"
                    :disabled="!decreaseAmount || decreaseAmount <= 0 || updatingStock"
                    class="py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-all disabled:opacity-50 shrink-0"
                  >
                    کاهش موجودی
                  </button>
                </div>
              </div>

              <!-- Change Stock Status -->
              <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-xs">
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                  تغییر وضعیت انبار (Stock Status)
                </div>
                <div class="flex items-center gap-2">
                  <select
                    v-model="quickStatus"
                    class="flex-1 py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="instock">موجود در انبار (instock)</option>
                    <option value="outofstock">ناموجود (outofstock)</option>
                    <option value="onbackorder">در پیش‌خرید (onbackorder)</option>
                  </select>
                  <button
                    @click="executeStatusChange"
                    :disabled="quickStatus === item?.stock_status || updatingStatus"
                    class="py-2 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-bold transition-all disabled:opacity-50 shrink-0"
                  >
                    ثبت وضعیت
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 2: Inventory Configuration -->
          <div v-else-if="activeTab === 'config'" class="space-y-5">
            <!-- Manage stock toggle -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800 space-y-2">
              <label class="flex items-center justify-between cursor-pointer">
                <div>
                  <span class="text-xs font-bold text-slate-800 dark:text-slate-100">مدیریت موجودی انبار (Manage Stock)</span>
                  <p class="text-[11px] text-slate-400 mt-0.5">
                    فعال‌سازی ردیابی عددی موجودی این کالا در ووکامرس
                  </p>
                </div>
                <input
                  type="checkbox"
                  v-model="configParams.manage_stock"
                  class="rounded text-indigo-600 focus:ring-0 w-4 h-4 cursor-pointer"
                />
              </label>
              <p v-if="!configParams.manage_stock" class="text-[11px] text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 p-2.5 rounded-xl border border-amber-200/60 dark:border-amber-900/60">
                توجه: در صورت غیرفعال بودن، ووکامرس تعداد عددی برای کالا ثبت نخواهد کرد و کالا فقط بر اساس وضعیت (موجود/ناموجود) سنجیده می‌شود.
              </p>
            </div>

            <!-- Low stock threshold -->
            <div class="space-y-1.5">
              <label class="text-xs font-bold text-slate-700 dark:text-slate-300">
                آستانه کمبود موجودی (Low Stock Amount):
              </label>
              <input
                v-model.number="configParams.low_stock_amount"
                type="number"
                min="0"
                placeholder="مثلاً 5 (خالی = پیش‌فرض فروشگاه)"
                class="w-full py-2.5 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
              />
              <p class="text-[11px] text-slate-400">
                هنگامی که موجودی کالا به این عدد یا کمتر برسد، هشدار کمبود موجودی صادر خواهد شد.
              </p>
            </div>

            <!-- Backorders setting -->
            <div class="space-y-1.5">
              <label class="text-xs font-bold text-slate-700 dark:text-slate-300">
                سیاست پیش‌خرید (Backorders):
              </label>
              <select
                v-model="configParams.backorders"
                class="w-full py-2.5 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500"
              >
                <option value="no">مجاز نیست (Do not allow)</option>
                <option value="notify">مجاز است، با اطلاع‌رسانی به مشتری (Allow, but notify customer)</option>
                <option value="yes">مجاز است بدون اطلاع (Allow)</option>
              </select>
            </div>

            <!-- Sold individually (parent products only) -->
            <div v-if="!item?.is_variation" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800">
              <label class="flex items-center justify-between cursor-pointer">
                <div>
                  <span class="text-xs font-bold text-slate-800 dark:text-slate-100">فروش تکی (Sold Individually)</span>
                  <p class="text-[11px] text-slate-400 mt-0.5">
                    مشتری در هر سفارش تنها می‌تواند ۱ عدد از این کالا خریداری نماید
                  </p>
                </div>
                <input
                  type="checkbox"
                  v-model="configParams.sold_individually"
                  class="rounded text-indigo-600 focus:ring-0 w-4 h-4 cursor-pointer"
                />
              </label>
            </div>

            <button
              @click="saveConfiguration"
              :disabled="savingConfig"
              class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20 disabled:opacity-50"
            >
              {{ savingConfig ? 'در حال ذخیره‌سازی...' : 'ذخیره تنظیمات انبارداری' }}
            </button>
          </div>

          <!-- TAB 3: Variations Inventory -->
          <div v-else-if="activeTab === 'variations'" class="space-y-4">
            <div v-if="loadingVariations" class="p-8 text-center text-xs text-slate-400">
              در حال فراخوانی تنوع‌های کالا از ووکامرس...
            </div>

            <div v-else-if="variationsList.length === 0" class="p-8 text-center text-xs text-slate-400">
              هیچ تنوعی برای این محصول متغیر یافت نشد.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="v in variationsList"
                :key="v.id"
                class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 text-xs"
              >
                <div>
                  <div class="font-bold text-slate-800 dark:text-slate-100">
                    {{ v.variation_name || ('تنوع #' + toPersianDigits(v.id)) }}
                  </div>
                  <div class="text-[11px] text-slate-400 mt-0.5">
                    <span>SKU: <span class="font-mono dir-ltr inline-block">{{ v.sku || '—' }}</span></span> | وضعیت: {{ v.stock_status_label }}
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <div class="text-right">
                    <span class="font-bold text-slate-900 dark:text-slate-50 text-sm">
                      {{ v.manage_stock ? formatNumber(v.stock_quantity ?? 0) : 'نامحدود' }}
                    </span>
                    <span v-if="v.manage_stock" class="text-[10px] text-slate-400 mr-1">عدد</span>
                  </div>

                  <button
                    @click="openVariationDrawer(v)"
                    class="py-1 px-2.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold hover:bg-indigo-100 text-[11px] transition-colors"
                  >
                    ویرایش
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs bg-slate-50/50 dark:bg-slate-850/50 shrink-0">
          <router-link
            :to="'/products/' + (item?.product_id || item?.id)"
            class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-bold"
          >
            <span>مشاهده صفحه کامل محصول</span>
            <Iconsax name="arrow-left" size="14" />
          </router-link>

          <span class="text-[11px] text-slate-400">
            بروزرسانی: {{ formatDateTime(item?.date_modified) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Decrease Stock Confirmation Modal -->
    <div
      v-if="confirmingDecrease"
      class="fixed inset-0 z-60 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-sm w-full p-6 shadow-2xl space-y-4 text-center">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto">
          <Iconsax name="warning" size="24" />
        </div>

        <div>
          <h3 class="font-bold text-base text-slate-800 dark:text-slate-100">تأیید کاهش موجودی</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
            موجودی فعلی <strong class="text-slate-700 dark:text-slate-200 font-semibold">{{ formatNumber(item?.stock_quantity) }}</strong> عدد است.
            با کاهش <strong class="text-rose-600 font-semibold">{{ formatNumber(pendingDecreaseAmount) }}</strong> عدد، موجودی جدید به
            <strong class="text-slate-900 dark:text-slate-100 font-semibold">{{ formatNumber(Math.max(0, (item?.stock_quantity ?? 0) - pendingDecreaseAmount)) }}</strong> عدد خواهد رسید.
          </p>
          <p v-if="(item?.stock_quantity ?? 0) - pendingDecreaseAmount <= 0" class="text-xs text-rose-600 font-bold mt-2">
            ⚠️ این عملیات ممکن است محصول را در فروشگاه ناموجود کند!
          </p>
        </div>

        <div class="flex items-center gap-2 pt-2">
          <button
            @click="executeStockChange('decrease', pendingDecreaseAmount)"
            class="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-all"
          >
            تأیید و کاهش موجودی
          </button>
          <button
            @click="confirmingDecrease = false"
            class="py-2.5 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            انصراف
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import api from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import { formatNumber, formatDateTime, toPersianDigits } from '@/utils/formatters';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  item: { type: Object, default: () => null },
});

const emit = defineEmits(['close', 'updated']);
const notification = useNotificationStore();

const activeTab = ref('stock');
const setAmount = ref(null);
const increaseAmount = ref(null);
const decreaseAmount = ref(null);
const quickStatus = ref('instock');

const updatingStock = ref(false);
const updatingStatus = ref(false);
const savingConfig = ref(false);

const confirmingDecrease = ref(false);
const pendingDecreaseAmount = ref(0);

const loadingVariations = ref(false);
const variationsList = ref([]);

const configParams = reactive({
  manage_stock: false,
  low_stock_amount: null,
  backorders: 'no',
  sold_individually: false,
});

watch(() => props.item, (newItem) => {
  if (newItem) {
    setAmount.value = newItem.stock_quantity;
    increaseAmount.value = 5;
    decreaseAmount.value = 1;
    quickStatus.value = newItem.stock_status || 'instock';

    configParams.manage_stock = !!newItem.manage_stock;
    configParams.low_stock_amount = newItem.low_stock_amount;
    configParams.backorders = newItem.backorders || 'no';
    configParams.sold_individually = !!newItem.sold_individually;

    if (newItem.has_variations && !newItem.is_variation) {
      loadVariations(newItem.product_id);
    }
  }
}, { immediate: true });

const closeDrawer = () => {
  confirmingDecrease.value = false;
  emit('close');
};

const getStockStatusBadgeClass = (status) => {
  switch (status) {
    case 'instock':
      return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800';
    case 'outofstock':
      return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border-rose-200 dark:border-rose-800';
    case 'onbackorder':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200 dark:border-amber-800';
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700';
  }
};

const promptDecrease = (amt) => {
  if (!amt || amt <= 0) return;
  pendingDecreaseAmount.value = amt;
  confirmingDecrease.value = true;
};

const executeStockChange = async (operation, amount) => {
  if (!props.item) return;
  updatingStock.value = true;
  confirmingDecrease.value = false;

  const productId = props.item.product_id;
  const variationId = props.item.variation_id;

  try {
    const url = variationId
      ? `/inventory/${productId}/variations/${variationId}`
      : `/inventory/${productId}/stock`;

    const payload = {
      operation,
      quantity: amount,
      variation_id: variationId,
    };

    const res = await api.patch(url, payload);
    if (res.data?.success) {
      notification.success(res.data.meta?.message || 'موجودی با موفقیت بروزرسانی شد.');
      emit('updated', res.data.data);
    }
  } catch (err) {
    notification.error(err.response?.data?.error?.message || 'خطا در بروزرسانی موجودی انبار');
  } finally {
    updatingStock.value = false;
  }
};

const executeStatusChange = async () => {
  if (!props.item) return;
  updatingStatus.value = true;

  const productId = props.item.product_id;
  const variationId = props.item.variation_id;

  try {
    const url = variationId
      ? `/inventory/${productId}/variations/${variationId}`
      : `/inventory/${productId}/status`;

    const payload = {
      stock_status: quickStatus.value,
      variation_id: variationId,
    };

    const res = await api.patch(url, payload);
    if (res.data?.success) {
      notification.success(res.data.meta?.message || 'وضعیت انبار با موفقیت بروزرسانی شد.');
      emit('updated', res.data.data);
    }
  } catch (err) {
    notification.error(err.response?.data?.error?.message || 'خطا در بروزرسانی وضعیت انبار');
  } finally {
    updatingStatus.value = false;
  }
};

const saveConfiguration = async () => {
  if (!props.item) return;
  savingConfig.value = true;

  const productId = props.item.product_id;
  const variationId = props.item.variation_id;

  try {
    const url = variationId
      ? `/inventory/${productId}/variations/${variationId}`
      : `/inventory/${productId}`;

    const payload = {
      ...configParams,
      variation_id: variationId,
    };

    const res = await api.patch(url, payload);
    if (res.data?.success) {
      notification.success(res.data.meta?.message || 'پیکربندی انبارداری ذخیره گردید.');
      emit('updated', res.data.data);
    }
  } catch (err) {
    notification.error(err.response?.data?.error?.message || 'خطا در ذخیره پیکربندی انبار');
  } finally {
    savingConfig.value = false;
  }
};

const loadVariations = async (prodId) => {
  loadingVariations.value = true;
  try {
    const res = await api.get(`/products/${prodId}/variations`);
    if (res.data?.success) {
      variationsList.value = res.data.data || [];
    }
  } catch (e) {
    console.error('Failed to load variations:', e);
  } finally {
    loadingVariations.value = false;
  }
};

const openVariationDrawer = (variation) => {
  emit('updated', {
    ...props.item,
    variation_id: variation.id,
    is_variation: true,
    display_name: `${props.item.product_name} (${variation.sku || '#' + variation.id})`,
    stock_quantity: variation.stock_quantity,
    stock_status: variation.stock_status,
    manage_stock: variation.manage_stock,
    low_stock_amount: variation.low_stock_amount,
    backorders: variation.backorders,
  });
};
</script>
