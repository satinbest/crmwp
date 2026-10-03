<template>
  <div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
          <router-link to="/products" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
            محصولات
          </router-link>
          <span>/</span>
          <span class="text-slate-600 dark:text-slate-200 font-medium">افزودن محصول جدید</span>
        </div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">تعریف محصول جدید در فروشگاه</h1>
      </div>

      <router-link
        to="/products"
        class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-100 flex items-center gap-1.5 transition-colors"
      >
        <Iconsax name="arrow-left" size="16" />
        <span>انصراف و بازگشت</span>
      </router-link>
    </div>

    <!-- Creation Form Card -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-6">
      <!-- Product Type & Status -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-850/60 border border-slate-100 dark:border-slate-800">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">نوع محصول *</label>
          <select
            v-model="form.type"
            class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium"
          >
            <option value="simple">محصول ساده (Simple)</option>
            <option value="variable">محصول متغیر (Variable)</option>
            <option value="external">محصول خارجی / معرف (External)</option>
            <option value="grouped">محصول گروهی (Grouped)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">وضعیت انتشار</label>
          <select
            v-model="form.status"
            class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-medium"
          >
            <option value="publish">منتشر شده (Publish)</option>
            <option value="draft">پیش‌نویس (Draft)</option>
            <option value="pending">در انتظار بررسی (Pending)</option>
          </select>
        </div>

        <div class="flex items-center gap-2 pt-6">
          <input type="checkbox" id="featured" v-model="form.featured" class="rounded text-indigo-600 focus:ring-0" />
          <label for="featured" class="text-xs text-slate-700 dark:text-slate-300 cursor-pointer font-medium">نشانه‌گذاری به عنوان محصول ویژه</label>
        </div>
      </div>

      <!-- Core Info -->
      <div class="space-y-4">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">اطلاعات پایه کالا</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">نام محصول *</label>
            <input
              v-model="form.name"
              type="text"
              placeholder="مثال: گوشی هوشمند سامسونگ مدل S24"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">شناسه کالا (SKU)</label>
            <input
              v-model="form.sku"
              type="text"
              dir="ltr"
              placeholder="مثال: SAM-S24-128"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <!-- External Product Fields -->
        <div v-if="form.type === 'external'" class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">پیوند محصول خارجی (External URL) *</label>
            <input
              v-model="form.external_url"
              type="url"
              dir="ltr"
              placeholder="https://example.com/item"
              class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">متن دکمه خرید</label>
            <input
              v-model="form.button_text"
              type="text"
              placeholder="مثال: خرید از فروشنده"
              class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <!-- Pricing Fields (for Simple & External) -->
        <div v-if="form.type !== 'variable'" class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">قیمت عادی (تومان) *</label>
            <input
              v-model="form.regular_price"
              type="number"
              min="0"
              placeholder="0"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">قیمت فروش ویژه (تومان)</label>
            <input
              v-model="form.sale_price"
              type="number"
              min="0"
              placeholder="اختیاری"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <div v-else class="p-4 rounded-xl bg-purple-50 dark:bg-purple-950/30 border border-purple-200/60 dark:border-purple-900/40 text-xs text-purple-700 dark:text-purple-300">
          💡 برای محصولات متغیر، قیمت و موجودی در بخش تنوع‌های کالایی پس از ایجاد کالا تنظیم خواهند شد.
        </div>

        <!-- Inventory Settings -->
        <div v-if="form.type === 'simple'" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-100 dark:border-slate-800 space-y-3">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.manage_stock" class="rounded text-indigo-600 focus:ring-0" />
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">ردیابی موجودی انبار</span>
          </label>

          <div v-if="form.manage_stock" class="grid grid-cols-2 gap-4 pt-1">
            <div>
              <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">تعداد موجودی</label>
              <input
                v-model="form.stock_quantity"
                type="number"
                min="0"
                class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">وضعیت انبار</label>
              <select
                v-model="form.stock_status"
                class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="instock">موجود در انبار</option>
                <option value="outofstock">ناموجود</option>
                <option value="onbackorder">در پیش‌خرید</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Descriptions -->
        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">توضیحات کوتاه</label>
          <textarea
            v-model="form.short_description"
            rows="3"
            placeholder="معرفی کوتاه کالا..."
            class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 leading-relaxed"
          ></textarea>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">توضیحات کامل محصول</label>
          <textarea
            v-model="form.description"
            rows="5"
            placeholder="توضیحات تکمیلی یا متن HTML محصول..."
            class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 leading-relaxed"
          ></textarea>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
        <router-link
          to="/products"
          class="py-2.5 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
        >
          انصراف
        </router-link>

        <button
          @click="submitCreate"
          :disabled="creating"
          class="py-2.5 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/20 transition-all disabled:opacity-50"
        >
          <div v-if="creating" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          <Iconsax v-else name="tick" size="18" />
          <span>{{ creating ? 'در حال ثبت...' : 'ذخیره و ایجاد محصول' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import Iconsax from '@/components/icons/Iconsax.vue';
import api from '@/api/client';
import { useNotificationStore } from '@/stores/notification';

const router = useRouter();
const notification = useNotificationStore();

const creating = ref(false);

const form = reactive({
  name: '',
  type: 'simple',
  status: 'publish',
  featured: false,
  sku: '',
  regular_price: '',
  sale_price: '',
  manage_stock: false,
  stock_quantity: 1,
  stock_status: 'instock',
  short_description: '',
  description: '',
  external_url: '',
  button_text: '',
});

const submitCreate = async () => {
  if (!form.name.trim()) {
    notification.error('نام محصول الزامی است.');
    return;
  }

  creating.value = true;
  try {
    const payload = {
      name: form.name.trim(),
      type: form.type,
      status: form.status,
      featured: form.featured,
      sku: form.sku.trim(),
      short_description: form.short_description,
      description: form.description,
    };

    if (form.type !== 'variable') {
      payload.regular_price = String(form.regular_price || '0');
      if (form.sale_price) payload.sale_price = String(form.sale_price);
    }

    if (form.type === 'simple') {
      payload.manage_stock = form.manage_stock;
      payload.stock_status = form.stock_status;
      if (form.manage_stock) {
        payload.stock_quantity = Number(form.stock_quantity);
      }
    }

    if (form.type === 'external') {
      payload.external_url = form.external_url;
      payload.button_text = form.button_text;
    }

    const res = await api.post('/products', payload);
    if (res.data || res.success) {
      notification.success(res.meta?.message || 'محصول با موفقیت در ووکامرس ایجاد شد.');
      const newId = res.data?.id;
      if (newId) {
        router.push(`/products/${newId}`);
      } else {
        router.push('/products');
      }
    }
  } catch (err) {
    notification.error(err.message || 'خطا در ایجاد محصول');
  } finally {
    creating.value = false;
  }
};
</script>
