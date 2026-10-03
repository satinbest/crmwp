<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div class="flex items-center gap-3">
        <router-link
          to="/automations"
          class="p-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-sm"
          title="بازگشت به لیست اتوماسیون‌ها"
        >
          <Iconsax name="arrow-right" size="18" />
        </router-link>

        <div>
          <h1 class="text-xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
            <span>تاریخچه و لاگ‌های اجرا</span>
            <span v-if="automation" class="text-xs bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold px-2 py-0.5 rounded-full border border-indigo-200/50 dark:border-indigo-800/50">
              {{ automation.name }}
            </span>
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            سوابق اجرای تریگرها، بررسی Idempotency، شبیه‌سازی و وضعیت اقدامات انجام‌شده
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <select
          v-model="statusFilter"
          @change="fetchRuns"
          class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="all">همه وضعیت‌ها</option>
          <option value="completed">موفق (Completed)</option>
          <option value="failed">ناموفق (Failed)</option>
          <option value="partial">ناقص (Partial)</option>
          <option value="skipped">رد شده (Skipped)</option>
        </select>

        <RefreshButton
          @click="fetchRuns"
          :loading="loading"
          label="به‌روزرسانی"
          title="بروزرسانی لاگ‌ها"
        />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 4" :key="i" class="h-16 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl animate-pulse"></div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="runs.length === 0"
      class="bg-white dark:bg-slate-900 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center"
    >
      <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-3">
        <Iconsax name="clock" size="28" />
      </div>
      <h3 class="text-sm font-bold text-slate-900 dark:text-white">هنوز هیچ لاگ اجرایی ثبت نشده است</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
        با وقوع رویدادهای تعریف‌شده یا اجرای دستی، سوابق اجرا در این بخش ثبت و قابل رهگیری خواهند بود.
      </p>
    </div>

    <!-- Runs Table -->
    <div v-else class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
          <thead class="bg-slate-50/75 dark:bg-slate-800/40 text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800/80">
            <tr>
              <th class="py-3.5 px-4 font-bold">شناسه</th>
              <th class="py-3.5 px-4 font-bold">وضعیت اجرا</th>
              <th class="py-3.5 px-4 font-bold">رویداد محرک</th>
              <th class="py-3.5 px-4 font-bold">مدت زمان</th>
              <th class="py-3.5 px-4 font-bold">زمان اجرا</th>
              <th class="py-3.5 px-4 font-bold">اقدامات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr
              v-for="run in runs"
              :key="run.id"
              class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
            >
              <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                #{{ toPersianDigits(run.id) }}
              </td>

              <td class="py-3 px-4">
                <span
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                  :class="getStatusBadgeClass(run.status)"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotClass(run.status)"></span>
                  <span>{{ getStatusLabel(run.status) }}</span>
                </span>
              </td>

              <td class="py-3 px-4">
                <div class="dir-ltr text-right text-slate-700 dark:text-slate-300 font-semibold">{{ run.trigger_type }}</div>
                <div class="text-[10px] text-slate-400 dir-ltr text-right">{{ run.event_id || 'manual' }}</div>
              </td>

              <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                {{ run.duration_ms ? toPersianDigits(run.duration_ms) + ' میلی‌ثانیه' : '—' }}
              </td>

              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                {{ formatDateTime(run.created_at) }}
              </td>

              <td class="py-3 px-4 flex items-center gap-2">
                <button
                  @click="openDetails(run)"
                  class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg text-xs font-semibold transition-colors"
                >
                  جزئیات
                </button>

                <button
                  v-if="run.status === 'failed' && authStore.hasPermission('automations.run')"
                  @click="retryRun(run)"
                  :disabled="retryingId === run.id"
                  class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/60 dark:hover:bg-amber-900/60 text-amber-700 dark:text-amber-300 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1"
                >
                  <Iconsax name="refresh" size="12" :class="{ 'animate-spin': retryingId === run.id }" />
                  <span>تلاش مجدد</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Run Details Modal -->
    <div
      v-if="selectedRun"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[85vh]">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span
              class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold"
              :class="getStatusBadgeClass(selectedRun.status)"
            >
              #{{ toPersianDigits(selectedRun.id) }}
            </span>
            <div>
              <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">
                جزئیات اجرای اتوماسیون
              </h2>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">
                رویداد: <span class="dir-ltr">{{ selectedRun.trigger_type }}</span> | زمان: {{ formatDateTime(selectedRun.created_at) }}
              </div>
            </div>
          </div>
          <button
            @click="selectedRun = null"
            class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <div class="p-6 overflow-y-auto space-y-4 text-xs">
          <!-- Status & Summary -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 dark:bg-slate-800/40 p-3 rounded-2xl border border-slate-200/80 dark:border-slate-700/60">
            <div>
              <span class="text-slate-400 block text-[10px]">وضعیت:</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ getStatusLabel(selectedRun.status) }}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">مدت زمان:</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ selectedRun.duration_ms ? toPersianDigits(selectedRun.duration_ms) + ' میلی‌ثانیه' : '—' }}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">کلید یکتایی (Idempotency):</span>
              <span class="dir-ltr text-[10px] text-slate-600 dark:text-slate-300 truncate block" :title="selectedRun.idempotency_key">
                {{ selectedRun.idempotency_key ? selectedRun.idempotency_key.substring(0, 16) + '...' : '—' }}
              </span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">فروشگاه:</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ selectedRun.store_name || '#' + toPersianDigits(selectedRun.store_id) }}</span>
            </div>
          </div>

          <!-- Error trace if failed -->
          <div v-if="selectedRun.error" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 rounded-xl text-rose-800 dark:text-rose-300">
            <span class="font-bold block mb-1">خطای اجرا:</span>
            <div class="dir-ltr text-[11px]">{{ selectedRun.error }}</div>
          </div>

          <!-- Action Results Breakdown -->
          <div v-if="selectedRun.result?.actions?.results" class="space-y-2">
            <h4 class="font-bold text-slate-800 dark:text-slate-200">نتایج اقدامات اجراشده:</h4>
            <div
              v-for="(r, idx) in selectedRun.result.actions.results"
              :key="idx"
              class="p-3 rounded-xl border flex items-center justify-between"
              :class="r.status === 'success' ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-200/60 dark:border-emerald-800/50 text-emerald-800 dark:text-emerald-300' : 'bg-rose-50/50 dark:bg-rose-950/30 border-rose-200/60 dark:border-rose-800/50 text-rose-800 dark:text-rose-300'"
            >
              <div>
                <span class="font-bold">اقدام {{ toPersianDigits(idx + 1) }}: <span class="dir-ltr">{{ r.type }}</span></span>
                <div v-if="r.error" class="text-[10px] text-rose-600 dark:text-rose-400 mt-0.5">{{ r.error }}</div>
              </div>
              <span class="px-2 py-0.5 rounded text-[10px] font-bold" :class="r.status === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">
                {{ r.status === 'success' ? 'موفق' : 'ناموفق' }}
              </span>
            </div>
          </div>

          <!-- Raw Execution Payload -->
          <div>
            <h4 class="font-bold text-slate-800 dark:text-slate-200 mb-1.5">کالبد رویداد (Event Payload - Sanitized):</h4>
            <pre class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700 dir-ltr text-[11px] text-slate-700 dark:text-slate-300 overflow-x-auto max-h-52">{{ JSON.stringify(selectedRun.context?.event || selectedRun.context, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import { formatDateTime, toPersianDigits } from '@/utils/formatters';

const route = useRoute();
const authStore = useAuthStore();

const automationId = route.params.id;
const automation = ref(null);
const runs = ref([]);
const loading = ref(false);
const statusFilter = ref('all');
const selectedRun = ref(null);
const retryingId = ref(null);

const getStatusLabel = (status) => {
  switch (status) {
    case 'completed': return 'تکمیل شده';
    case 'failed': return 'ناموفق';
    case 'partial': return 'ناقص';
    case 'skipped': return 'رد شده';
    case 'running': return 'در حال اجرا';
    case 'pending': return 'در صف';
    default: return status;
  }
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'completed': return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200/50';
    case 'failed': return 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200/50';
    case 'partial': return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200/50';
    case 'skipped': return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200/50';
    case 'running': return 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200/50';
    default: return 'bg-slate-100 text-slate-600';
  }
};

const getStatusDotClass = (status) => {
  switch (status) {
    case 'completed': return 'bg-emerald-500';
    case 'failed': return 'bg-rose-500';
    case 'partial': return 'bg-amber-500';
    case 'skipped': return 'bg-slate-400';
    case 'running': return 'bg-blue-500 animate-ping';
    default: return 'bg-slate-400';
  }
};


const fetchRuns = async () => {
  loading.value = true;
  try {
    // 1. Fetch automation details
    const autoRes = await apiClient.get(`/automations/${automationId}`);
    automation.value = autoRes.data;

    // 2. Fetch runs
    const params = {
      status: statusFilter.value,
      per_page: 50,
    };
    const res = await apiClient.get(`/automations/${automationId}/runs`, { params });
    runs.value = res.data || [];
  } catch (err) {
    console.error('Fetch runs error:', err);
  } finally {
    loading.value = false;
  }
};

const openDetails = (run) => {
  selectedRun.value = run;
};

const retryRun = async (run) => {
  retryingId.value = run.id;
  try {
    await apiClient.post(`/automations/${automationId}/runs/${run.id}/retry`);
    await fetchRuns();
    alert('اجرای مجدد با موفقیت انجام شد.');
  } catch (err) {
    alert(err.message || 'خطا در اجرای مجدد');
  } finally {
    retryingId.value = null;
  }
};

onMounted(() => {
  fetchRuns();
});
</script>
