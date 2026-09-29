<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2.5">
          <span>🎯</span>
          <span>بخش‌بندی هوشمند مشتریان (Segments)</span>
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          گروه‌بندی پویا بر اساس الگوهای خرید، ارزش سبد، موقعیت جغرافیایی و تعاملات CRM
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchSegments"
          :disabled="loading"
          class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors shadow-xs"
          title="بروزرسانی"
        >
          <span :class="{'inline-block animate-spin': loading}">↺</span>
        </button>

        <button
          @click="openCreateDrawer"
          class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition-all flex items-center gap-1.5"
        >
          <span>+ تعریف سگمنت هوشمند جدید</span>
        </button>
      </div>
    </div>

    <!-- Segments Table / Cards -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl overflow-hidden shadow-xs">
      <div v-if="loading && segments.length === 0" class="py-12 text-center text-xs text-slate-400">
        در حال بارگذاری سگمنت‌ها...
      </div>

      <div v-else-if="segments.length === 0" class="py-12 text-center text-xs text-slate-400 space-y-2">
        <div>هنوز سگمنتی ایجاد نشده است.</div>
        <button
          @click="openCreateDrawer"
          class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline"
        >
          تعریف اولین سگمنت هوشمند
        </button>
      </div>

      <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
        <div
          v-for="seg in segments"
          :key="seg.id"
          class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors"
        >
          <div class="space-y-1.5 min-w-0">
            <div class="flex items-center gap-2.5">
              <span class="font-bold text-sm text-slate-900 dark:text-white">{{ seg.name }}</span>
              <span class="px-2 py-0.5 rounded-full text-[10px] bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-bold">
                {{ toPersianDigits(seg.rules?.rules?.length || 0) }} شرط
              </span>
            </div>
            <p v-if="seg.description" class="text-xs text-slate-500 dark:text-slate-400">
              {{ seg.description }}
            </p>
            <div class="text-[10px] text-slate-400">
              ثبت: {{ formatDateTime(seg.created_at) }}
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <button
              @click="runPreview(seg)"
              class="px-3 py-1.5 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-bold text-xs hover:bg-indigo-100 transition-colors"
            >
              👁️ پیش‌نمایش مشتریان
            </button>
            <button
              @click="editSegment(seg)"
              class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              title="ویرایش سگمنت"
            >
              ✏️
            </button>
            <button
              @click="deleteSegment(seg.id)"
              class="p-2 rounded-xl border border-rose-200 dark:border-rose-900/60 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
              title="حذف سگمنت"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Preview Results Modal -->
    <div v-if="isPreviewOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden text-xs">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
          <div>
            <h3 class="font-bold text-sm text-slate-900 dark:text-white">
              پیش‌نمایش اعضای سگمنت: {{ activePreviewSegment?.name || 'آزمایشی' }}
            </h3>
            <p class="text-[11px] text-slate-400 mt-0.5">
              تعداد منطبق در کاتالوگ فروشگاه: <span class="font-bold text-indigo-600">{{ toPersianDigits(previewData?.total_matching ?? 0) }} مشتری</span>
            </p>
          </div>
          <button @click="isPreviewOpen = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">✕</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-3">
          <div v-if="previewLoading" class="py-8 text-center text-slate-400">
            در حال ارزیابی قوانین سگمنت...
          </div>
          <div v-else-if="!previewData?.sample_customers || previewData.sample_customers.length === 0" class="py-8 text-center text-slate-400">
            هیچ مشتری منطبق با این شروط یافت نشد.
          </div>
          <div v-else class="space-y-2">
            <div
              v-for="cust in previewData.sample_customers"
              :key="cust.id"
              class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/60 flex items-center justify-between"
            >
              <div>
                <div class="font-bold text-slate-900 dark:text-white">{{ cust.name }}</div>
                <div class="text-[11px] text-slate-400 flex items-center gap-2">
                  <span>{{ cust.email || 'بدون ایمیل' }}</span>
                  <span>•</span>
                  <span>{{ cust.orders_count }} سفارش</span>
                  <span>•</span>
                  <span>مجموع خرید: {{ Number(cust.total_spent).toLocaleString('fa-IR') }} تومان</span>
                </div>
              </div>
              <router-link
                :to="'/customers/' + cust.id"
                class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-bold hover:bg-indigo-100"
              >
                پروفایل
              </router-link>
            </div>
          </div>
        </div>

        <div class="px-6 py-3 border-t border-slate-100 dark:border-slate-800 flex justify-end shrink-0">
          <button @click="isPreviewOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
            بستن
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Segment Drawer -->
    <div v-if="isDrawerOpen" class="fixed inset-0 z-50 flex justify-end bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 w-full max-w-xl h-full flex flex-col shadow-2xl text-xs overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0">
          <h3 class="font-bold text-sm text-slate-900 dark:text-white">
            {{ isEdit ? 'ویرایش سگمنت هوشمند' : 'تعریف سگمنت هوشمند جدید' }}
          </h3>
          <button @click="isDrawerOpen = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">✕</button>
        </div>

        <!-- Form Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-5">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
              عنوان سگمنت <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="مثال: مشتریان وفادار تهران (بیش از ۵ خرید)"
              class="w-full rounded-xl px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
            />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">توضیحات</label>
            <textarea
              v-model="form.description"
              rows="2"
              placeholder="شرح هدف این بخش‌بندی..."
              class="w-full rounded-xl px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 resize-none"
            ></textarea>
          </div>

          <!-- Rule Builder -->
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-2">قوانین و شرایط فیلتر</label>
            <SegmentRuleBuilder v-model="form.rules" />
          </div>

          <!-- Live Test Preview Button inside Drawer -->
          <div class="pt-2">
            <button
              type="button"
              @click="testLiveRules"
              :disabled="previewLoading"
              class="w-full py-2.5 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 font-bold hover:bg-indigo-100 flex items-center justify-center gap-2"
            >
              <span>{{ previewLoading ? 'در حال ارزیابی...' : '👁️ تست و پیش‌نمایش زنده شروط' }}</span>
            </button>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2 shrink-0">
          <button
            type="button"
            @click="isDrawerOpen = false"
            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300"
          >
            انصراف
          </button>
          <button
            type="button"
            @click="saveSegment"
            :disabled="saving"
            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-md shadow-indigo-500/20 disabled:opacity-50"
          >
            {{ saving ? 'در حال ذخیره...' : (isEdit ? 'بروزرسانی سگمنت' : 'ذخیره سگمنت') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import SegmentRuleBuilder from '@/components/crm/SegmentRuleBuilder.vue';
import { toPersianDigits, formatDateTime } from '@/utils/formatters';

const notification = useNotificationStore();
const segments = ref([]);
const loading = ref(false);
const saving = ref(false);
const previewLoading = ref(false);

const isDrawerOpen = ref(false);
const isEdit = ref(false);
const editId = ref(null);

const isPreviewOpen = ref(false);
const activePreviewSegment = ref(null);
const previewData = ref(null);

const form = ref({
  name: '',
  description: '',
  rules: { combinator: 'AND', rules: [] },
});

const fetchSegments = async () => {
  loading.value = true;
  try {
    const res = await apiClient.get('/segments');
    segments.value = res.data || [];
  } catch (err) {
    notification.error('خطا در دریافت سگمنت‌ها.');
  } finally {
    loading.value = false;
  }
};

const openCreateDrawer = () => {
  isEdit.value = false;
  editId.value = null;
  form.value = {
    name: '',
    description: '',
    rules: {
      combinator: 'AND',
      rules: [
        { field: 'order_count', operator: 'greater_than', value: 2 },
      ],
    },
  };
  isDrawerOpen.value = true;
};

const editSegment = (seg) => {
  isEdit.value = true;
  editId.value = seg.id;
  form.value = {
    name: seg.name,
    description: seg.description || '',
    rules: JSON.parse(JSON.stringify(seg.rules || { combinator: 'AND', rules: [] })),
  };
  isDrawerOpen.value = true;
};

const saveSegment = async () => {
  if (!form.value.name.trim()) {
    notification.warning('لطفاً عنوان سگمنت را وارد نمایید.');
    return;
  }

  saving.value = true;
  try {
    if (isEdit.value && editId.value) {
      await apiClient.patch(`/segments/${editId.value}`, form.value);
      notification.success('سگمنت هوشمند با موفقیت بروزرسانی شد.');
    } else {
      await apiClient.post('/segments', form.value);
      notification.success('سگمنت هوشمند با موفقیت ایجاد شد.');
    }
    isDrawerOpen.value = false;
    await fetchSegments();
  } catch (err) {
    notification.error(err.message || 'خطا در ذخیره‌سازی سگمنت.');
  } finally {
    saving.value = false;
  }
};

const deleteSegment = async (id) => {
  if (!confirm('آیا از حذف این بخش‌بندی هوشمند اطمینان دارید؟')) return;
  try {
    await apiClient.delete(`/segments/${id}`);
    notification.success('سگمنت با موفقیت حذف شد.');
    await fetchSegments();
  } catch (err) {
    notification.error('خطا در حذف سگمنت.');
  }
};

const runPreview = async (seg) => {
  activePreviewSegment.value = seg;
  isPreviewOpen.value = true;
  previewLoading.value = true;
  try {
    const res = await apiClient.post('/segments/preview', { rules: seg.rules });
    previewData.value = res.data;
  } catch (err) {
    notification.error('خطا در ارزیابی پیش‌نمایش سگمنت.');
  } finally {
    previewLoading.value = false;
  }
};

const testLiveRules = async () => {
  activePreviewSegment.value = { name: form.value.name || 'پیش‌نویس' };
  isPreviewOpen.value = true;
  previewLoading.value = true;
  try {
    const res = await apiClient.post('/segments/preview', { rules: form.value.rules });
    previewData.value = res.data;
  } catch (err) {
    notification.error('خطا در ارزیابی پیش‌نمایش.');
  } finally {
    previewLoading.value = false;
  }
};

onMounted(() => {
  fetchSegments();
});
</script>
