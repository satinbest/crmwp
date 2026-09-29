<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2.5">
          <span>🏷️</span>
          <span>برچسب‌های مشتریان (Customer Tags)</span>
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          نشانه‌گذاری و برچسب‌گذاری دستی و گروهی مشتریان جهت دسته‌بندی و پیگیری‌های اختصاصی
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchTags"
          :disabled="loading"
          class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 transition-colors shadow-xs"
          title="بروزرسانی"
        >
          <span :class="{'inline-block animate-spin': loading}">↺</span>
        </button>

        <button
          @click="openModal(null)"
          class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition-all flex items-center gap-1.5"
        >
          <span>+ تعریف برچسب جدید</span>
        </button>
      </div>
    </div>

    <!-- Search Toolbar -->
    <div class="p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-3">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="جستجوی نام برچسب..."
        class="w-full sm:max-w-xs rounded-xl px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs"
      />
    </div>

    <!-- Tags Grid -->
    <div v-if="loading && tags.length === 0" class="py-12 text-center text-xs text-slate-400">
      در حال بارگذاری برچسب‌ها...
    </div>

    <div v-else-if="filteredTags.length === 0" class="py-12 text-center text-xs text-slate-400 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800">
      هیچ برچسبی یافت نشد.
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <div
        v-for="tag in filteredTags"
        :key="tag.id"
        class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3 hover:border-slate-300 dark:hover:border-slate-700 transition-colors"
      >
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span
              class="w-4 h-4 rounded-full border border-white dark:border-slate-800 shadow-xs shrink-0"
              :style="{ backgroundColor: tag.color }"
            ></span>
            <span class="font-bold text-sm text-slate-900 dark:text-white">{{ tag.name }}</span>
          </div>

          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
            {{ formatNumber(tag.customers_count || 0) }} مشتری
          </span>
        </div>

        <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-100 dark:border-slate-800 text-slate-400">
          <span class="text-[10px]">{{ formatDate(tag.created_at) }}</span>

          <div class="flex items-center gap-1.5">
            <button
              @click="openModal(tag)"
              class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
              title="ویرایش"
            >
              ✏️
            </button>
            <button
              @click="deleteTag(tag.id)"
              class="p-1.5 rounded-lg border border-rose-200 dark:border-rose-900 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950"
              title="حذف"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tag Modal Component -->
    <TagModal
      :is-open="isModalOpen"
      :tag="selectedTag"
      @close="isModalOpen = false"
      @saved="fetchTags"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import TagModal from '@/components/crm/TagModal.vue';
import { formatNumber, formatDate } from '@/utils/formatters';

const notification = useNotificationStore();
const tags = ref([]);
const loading = ref(false);
const searchQuery = ref('');

const isModalOpen = ref(false);
const selectedTag = ref(null);

const fetchTags = async () => {
  loading.value = true;
  try {
    const res = await apiClient.get('/tags');
    tags.value = res.data || [];
  } catch (err) {
    notification.error('خطا در دریافت لیست برچسب‌ها.');
  } finally {
    loading.value = false;
  }
};

const filteredTags = computed(() => {
  if (!searchQuery.value.trim()) return tags.value;
  const q = searchQuery.value.toLowerCase();
  return tags.value.filter(t => t.name.toLowerCase().includes(q));
});

const openModal = (tag = null) => {
  selectedTag.value = tag;
  isModalOpen.value = true;
};

const deleteTag = async (id) => {
  if (!confirm('آیا از حذف این برچسب اطمینان دارید؟ انتساب‌های آن از مشتریان نیز حذف خواهد شد.')) return;
  try {
    await apiClient.delete(`/tags/${id}`);
    notification.success('برچسب با موفقیت حذف شد.');
    await fetchTags();
  } catch (err) {
    notification.error('خطا در حذف برچسب.');
  }
};

onMounted(() => {
  fetchTags();
});
</script>
