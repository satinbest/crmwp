<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-hidden">
    <!-- Backdrop -->
    <div
      @click="$emit('close')"
      class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
    ></div>

    <div class="fixed inset-y-0 left-0 max-w-full flex pl-0 md:pl-10">
      <div class="w-screen max-w-2xl bg-white dark:bg-slate-900 shadow-2xl flex flex-col border-r border-slate-200/80 dark:border-slate-800/80">
        <!-- Header -->
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-850/50">
          <div>
            <div class="flex items-center gap-2.5">
              <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
              <h2 class="text-base font-extrabold text-slate-900 dark:text-white">
                مدیریت مجوزهای نقش: {{ role?.display_name || role?.name }}
              </h2>
            </div>
            <p class="text-xs text-slate-400 mt-1">
              تنظیم دسترسی‌های ریزدانه به بخش‌ها و عملیات مختلف سامانه
            </p>
          </div>
          <button
            @click="$emit('close')"
            class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            <Iconsax name="close" size="20" />
          </button>
        </div>

        <!-- Role Summary & Quick Actions Bar -->
        <div class="px-6 py-3.5 bg-slate-50 dark:bg-slate-800/40 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
          <div class="flex items-center gap-2">
            <span class="text-slate-500">مجوزهای فعال:</span>
            <span class="font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950 px-2 py-0.5 rounded-full">
              {{ toPersianDigits(selectedPermissions.length) }} از {{ toPersianDigits(totalPermissionsCount) }}
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="selectAll"
              class="px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-medium hover:bg-indigo-100 dark:hover:bg-indigo-900 transition-colors"
            >
              انتخاب همه
            </button>
            <button
              type="button"
              @click="clearAll"
              class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
            >
              لغو همه
            </button>
          </div>
        </div>

        <!-- Search Bar -->
        <div class="p-4 border-b border-slate-100 dark:border-slate-800">
          <div class="relative">
            <Iconsax name="search" size="16" customClass="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="جستجو در مجوزها یا نام ماژول..."
              class="w-full pr-9 pl-4 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
            />
          </div>
        </div>

        <!-- Body / Grouped Permissions List -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
          <div v-if="loading" class="p-12 text-center">
            <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
            <div class="text-xs text-slate-400">در حال دریافت ماتریس مجوزها...</div>
          </div>

          <div v-else-if="filteredGroups.length === 0" class="p-12 text-center text-slate-400 text-xs">
            مجوزی مطابق عبارت جستجو شده یافت نشد.
          </div>

          <div
            v-else
            v-for="group in filteredGroups"
            :key="group.key"
            class="bg-slate-50/60 dark:bg-slate-800/30 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 transition-all"
          >
            <!-- Module Group Header -->
            <div class="flex items-center justify-between mb-3 pb-2.5 border-b border-slate-200/60 dark:border-slate-700/60">
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                <span class="font-bold text-xs text-slate-900 dark:text-white">{{ group.title }}</span>
                <span class="text-[10px] text-slate-400">({{ toPersianDigits(group.permissions.length) }})</span>
              </div>
              <div class="flex items-center gap-2 text-[11px]">
                <button
                  type="button"
                  @click="toggleGroup(group, true)"
                  class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                >
                  انتخاب گروه
                </button>
                <span class="text-slate-300 dark:text-slate-700">|</span>
                <button
                  type="button"
                  @click="toggleGroup(group, false)"
                  class="text-slate-500 hover:underline font-medium"
                >
                  لغو گروه
                </button>
              </div>
            </div>

            <!-- Permission Checkboxes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <label
                v-for="perm in group.permissions"
                :key="perm.name"
                class="flex items-start gap-2.5 p-2 rounded-xl hover:bg-slate-200/60 dark:hover:bg-slate-800/80 border border-transparent hover:border-slate-200/60 dark:hover:border-slate-700/60 transition-all cursor-pointer"
                :class="selectedPermissions.includes(perm.name) ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : ''"
              >
                <input
                  type="checkbox"
                  :value="perm.name"
                  v-model="selectedPermissions"
                  class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800"
                />
                <div class="min-w-0">
                  <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                    {{ perm.display_name || perm.name }}
                  </div>
                  <div class="text-[10px] font-mono text-slate-400 truncate dir-ltr text-right">
                    {{ perm.name }}
                  </div>
                  <div v-if="perm.description" class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1">
                    {{ perm.description }}
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-850/50 flex items-center justify-between">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            انصراف
          </button>

          <button
            type="button"
            @click="savePermissions"
            :disabled="saving"
            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-bold shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2"
          >
            <div v-if="saving" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            <span>ذخیره تغییرات مجوزها</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import { toPersianDigits } from '@/utils/formatters';

const props = defineProps({
  isOpen: Boolean,
  role: Object,
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const saving = ref(false);
const rawGroups = ref({});
const selectedPermissions = ref([]);
const searchQuery = ref('');

const moduleDisplayNames = {
  dashboard: 'پیشخوان و آمار کلی',
  customers: 'مشتریان',
  orders: 'سفارش‌ها',
  products: 'محصولات و تنوع‌ها',
  inventory: 'انبار و موجودی',
  crm: 'مدیریت ارتباط با مشتری (CRM)',
  segments: 'بخش‌بندی‌ها (Segments)',
  tags: 'برچسب‌ها (Tags)',
  tasks: 'وظایف (Tasks)',
  activities: 'فعالیت‌ها و تایم‌لاین',
  reports: 'گزارشات و خروجی‌ها',
  bulk: 'عملیات گروهی',
  users: 'کاربران و اعضای تیم',
  roles: 'نقش‌ها و دسترسی‌ها',
  stores: 'مدیریت و اتصال فروشگاه‌ها',
  logs: 'لاگ‌ها و ردپاها',
  settings: 'تنظیمات سامانه',
};

const totalPermissionsCount = computed(() => {
  let count = 0;
  for (const key in rawGroups.value) {
    count += rawGroups.value[key].length;
  }
  return count;
});

const filteredGroups = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  const result = [];

  for (const [key, perms] of Object.entries(rawGroups.value)) {
    const title = moduleDisplayNames[key] || key;
    let matchingPerms = perms;

    if (query) {
      matchingPerms = perms.filter(p => 
        (p.name && p.name.toLowerCase().includes(query)) ||
        (p.display_name && p.display_name.toLowerCase().includes(query)) ||
        (p.description && p.description.toLowerCase().includes(query)) ||
        title.toLowerCase().includes(query)
      );
    }

    if (matchingPerms.length > 0) {
      result.push({
        key,
        title,
        permissions: matchingPerms,
      });
    }
  }

  return result;
});

const loadPermissions = async () => {
  if (!props.role?.id) return;
  loading.value = true;
  try {
    const [allPermsRes, rolePermsRes] = await Promise.all([
      apiClient.get('/permissions'),
      apiClient.get(`/roles/${props.role.id}/permissions`),
    ]);

    rawGroups.value = allPermsRes.data.permissions || {};
    const rolePerms = rolePermsRes.data.permissions || [];
    selectedPermissions.value = rolePerms.map(p => (typeof p === 'string' ? p : p.name));
  } catch (err) {
    console.error('Failed to load permissions:', err);
  } finally {
    loading.value = false;
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    searchQuery.value = '';
    loadPermissions();
  }
});

const selectAll = () => {
  const all = [];
  for (const key in rawGroups.value) {
    for (const p of rawGroups.value[key]) {
      all.push(p.name);
    }
  }
  selectedPermissions.value = [...new Set(all)];
};

const clearAll = () => {
  selectedPermissions.value = [];
};

const toggleGroup = (group, select) => {
  const groupNames = group.permissions.map(p => p.name);
  if (select) {
    selectedPermissions.value = [...new Set([...selectedPermissions.value, ...groupNames])];
  } else {
    selectedPermissions.value = selectedPermissions.value.filter(name => !groupNames.includes(name));
  }
};

const savePermissions = async () => {
  if (!props.role?.id) return;
  saving.value = true;
  try {
    await apiClient.put(`/roles/${props.role.id}/permissions`, {
      permissions: selectedPermissions.value,
    });
    emit('saved', selectedPermissions.value);
    emit('close');
  } catch (err) {
    alert(err.message || 'خطا در ذخیره مجوزها');
  } finally {
    saving.value = false;
  }
};
</script>
