<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div
      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-sm w-full overflow-hidden transition-all text-xs"
      @click.stop
    >
      <!-- Header -->
      <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <h3 class="font-bold text-sm text-slate-900 dark:text-white">
          {{ isEdit ? 'ویرایش برچسب' : 'تعریف برچسب جدید' }}
        </h3>
        <button
          @click="close"
          class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          ✕
        </button>
      </div>

      <form @submit.prevent="handleSubmit" class="p-5 space-y-4">
        <div>
          <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
            عنوان برچسب <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="مثال: خریدار وفادار، پیگیری مجدد، VIP..."
            class="w-full rounded-xl px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100"
          />
        </div>

        <div>
          <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1.5">رنگ برچسب</label>
          <div class="flex items-center gap-2 mb-2">
            <span
              class="w-7 h-7 rounded-lg border border-slate-300 dark:border-slate-700 shrink-0"
              :style="{ backgroundColor: form.color }"
            ></span>
            <input
              v-model="form.color"
              type="text"
              class="w-full rounded-xl px-3 py-1.5 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-mono text-xs"
            />
          </div>
          <!-- Presets -->
          <div class="flex flex-wrap gap-2">
            <button
              v-for="color in presetColors"
              :key="color"
              type="button"
              @click="form.color = color"
              class="w-6 h-6 rounded-lg transition-transform hover:scale-110 border border-white dark:border-slate-800 shadow-xs"
              :style="{ backgroundColor: color }"
            ></button>
          </div>
        </div>

        <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
          <button
            type="button"
            @click="close"
            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            انصراف
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold shadow-md shadow-indigo-500/20 disabled:opacity-50"
          >
            {{ saving ? 'در حال ذخیره...' : (isEdit ? 'بروزرسانی' : 'ایجاد برچسب') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';

const props = defineProps({
  isOpen: Boolean,
  tag: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'saved']);
const notification = useNotificationStore();

const isEdit = ref(false);
const saving = ref(false);

const presetColors = [
  '#4F46E5', '#6366F1', '#3B82F6', '#06B6D4',
  '#10B981', '#14B8A6', '#F59E0B', '#EF4444',
  '#8B5CF6', '#EC4899', '#64748B', '#334155'
];

const form = ref({
  name: '',
  color: '#4F46E5',
});

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    if (props.tag) {
      isEdit.value = true;
      form.value = {
        name: props.tag.name || '',
        color: props.tag.color || '#4F46E5',
      };
    } else {
      isEdit.value = false;
      form.value = {
        name: '',
        color: '#4F46E5',
      };
    }
  }
});

const close = () => {
  emit('close');
};

const handleSubmit = async () => {
  saving.value = true;
  try {
    if (isEdit.value && props.tag?.id) {
      await apiClient.patch(`/tags/${props.tag.id}`, form.value);
      notification.success('برچسب با موفقیت ویرایش شد.');
    } else {
      await apiClient.post('/tags', form.value);
      notification.success('برچسب جدید با موفقیت ایجاد شد.');
    }
    emit('saved');
    close();
  } catch (err) {
    notification.error(err.message || 'خطا در ثبت برچسب.');
  } finally {
    saving.value = false;
  }
};
</script>
