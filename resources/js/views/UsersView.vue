<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">کاربران و سطوح دسترسی (RBAC)</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          مدیریت نقش‌ها، کاربران و بررسی مجوزهای ریزدانه‌ای اعمال شده در سطح سرور
        </p>
      </div>

      <!-- Tab Switcher -->
      <div class="flex bg-slate-200/80 dark:bg-slate-800 p-1 rounded-xl text-xs font-semibold">
        <button
          @click="activeTab = 'users'"
          class="px-4 py-1.5 rounded-lg transition-all"
          :class="activeTab === 'users' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
        >
          فهرست کاربران
        </button>
        <button
          @click="activeTab = 'roles'"
          class="px-4 py-1.5 rounded-lg transition-all"
          :class="activeTab === 'roles' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
        >
          ماتریس نقش‌ها و مجوزها
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-12 text-center">
      <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <div class="text-xs text-slate-400">در حال بارگذاری اطلاعات کاربران و نقش‌ها...</div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 p-6 rounded-2xl text-center">
      <div class="text-sm font-bold text-rose-700 dark:text-rose-300 mb-2">خطا در دریافت اطلاعات</div>
      <p class="text-xs text-rose-600 dark:text-rose-400 mb-4">{{ error }}</p>
      <button
        @click="loadData"
        class="px-4 py-2 bg-rose-600 text-white rounded-xl text-xs font-semibold hover:bg-rose-500"
      >
        تلاش مجدد
      </button>
    </div>

    <!-- Users List Tab -->
    <div v-else-if="activeTab === 'users'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
      <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">اعضای دارای دسترسی به پنل</h3>
        <span class="text-xs text-slate-400">مجموع: {{ users.length }} کاربر</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-right text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 font-semibold border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th class="p-4">کاربر</th>
              <th class="p-4">شناسه / ایمیل</th>
              <th class="p-4">نقش سازمانی</th>
              <th class="p-4">دسترسی فروشگاه‌ها</th>
              <th class="p-4">وضعیت</th>
              <th class="p-4">آخرین ورود</th>
              <th class="p-4 text-center">عملیات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
              <td class="p-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-sm">
                    {{ (user.first_name || user.username).charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <div class="font-bold text-slate-900 dark:text-white text-xs">{{ user.full_name }}</div>
                    <div class="text-[10px] text-slate-400">شناسه: #{{ toPersianDigits(user.id) }}</div>
                  </div>
                </div>
              </td>
              <td class="p-4 text-slate-600 dark:text-slate-300">
                <div class="dir-ltr text-right">{{ user.username }}</div>
                <div class="text-[10px] text-slate-400 dir-ltr text-right">{{ user.email }}</div>
              </td>
              <td class="p-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="role in user.roles"
                    :key="role.id"
                    class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-800/50"
                  >
                    {{ role.display_name }}
                  </span>
                </div>
              </td>
              <td class="p-4">
                <div v-if="isUserAdmin(user)" class="flex items-center gap-1">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200/50">
                    همه فروشگاه‌ها (مدیر ارشد)
                  </span>
                </div>
                <div v-else-if="user.stores && user.stores.length > 0" class="flex flex-wrap gap-1">
                  <span
                    v-for="s in user.stores"
                    :key="s.id"
                    class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60"
                  >
                    {{ s.name }}
                  </span>
                </div>
                <div v-else class="text-[11px] text-rose-500 font-medium">
                  بدون دسترسی فروشگاه
                </div>
              </td>
              <td class="p-4">
                <span
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
                  :class="user.is_active ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 text-slate-600'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="user.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                  {{ user.is_active ? 'فعال' : 'غیرفعال' }}
                </span>
              </td>
              <td class="p-4 text-slate-500 dark:text-slate-400 text-[11px]">
                {{ user.last_login_at ? formatDateTime(user.last_login_at) : '—' }}
              </td>
              <td class="p-4 text-center">
                <button
                  @click="openStoreAccessModal(user)"
                  class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors"
                  title="تخصیص دسترسی فروشگاه‌ها"
                >
                  دسترسی فروشگاه‌ها
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Roles & Permissions Matrix Tab -->
    <div v-else class="space-y-6">
      <!-- Role Cards Overview -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="role in roles"
          :key="role.id"
          class="bg-white dark:bg-slate-900 border rounded-2xl p-5 shadow-sm transition-all"
          :class="selectedRole?.id === role.id ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200/80 dark:border-slate-800/80'"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="font-bold text-sm text-slate-900 dark:text-white">{{ role.display_name }}</span>
            <span class="text-xs font-mono dir-ltr text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950 px-2 py-0.5 rounded">
              {{ role.name }}
            </span>
          </div>
          <p class="text-xs text-slate-400 leading-relaxed mb-4 min-h-[32px]">{{ role.description }}</p>
          <div class="flex items-center justify-between text-xs pt-3 border-t border-slate-100 dark:border-slate-800">
            <span class="text-slate-500">تعداد مجوزها:</span>
            <span class="font-bold text-slate-900 dark:text-white">{{ toPersianDigits(role.permissions?.length || 0) }} مجوز</span>
          </div>
        </div>
      </div>

      <!-- Granular Permissions by Module -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
        <div class="mb-5">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">ماتریس مجوزهای ریزدانه سامانه‌ای (Permissions)</h3>
          <p class="text-xs text-slate-400 mt-1">کلیه ۲۲ مجوز تعریف شده در مستند مشخصات، دسته‌بندی شده بر اساس ماژول</p>
        </div>

        <div class="space-y-6">
          <div v-for="(groupPerms, groupName) in permissions" :key="groupName" class="border-b border-slate-100 dark:border-slate-800 pb-5 last:border-b-0 last:pb-0">
            <div class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mb-3 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-indigo-600 dark:bg-indigo-400"></span>
              <span>ماژول: {{ groupName }}</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
              <div
                v-for="p in groupPerms"
                :key="p.name"
                class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40 text-xs"
              >
                <div class="font-bold text-slate-800 dark:text-slate-200">{{ p.display_name }}</div>
                <div class="font-mono dir-ltr text-right text-[10px] text-slate-400 mt-0.5">{{ p.name }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{{ p.description }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= USER STORE ACCESS MODAL ================= -->
    <div
      v-if="selectedUserForStores"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
      @click.self="selectedUserForStores = null"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div>
            <h3 class="font-extrabold text-base text-slate-900 dark:text-white">دسترسی به فروشگاه‌ها</h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ selectedUserForStores.full_name }} ({{ selectedUserForStores.username }})</p>
          </div>
          <button @click="selectedUserForStores = null" class="p-1.5 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
            <span class="text-sm font-bold">✕</span>
          </button>
        </div>

        <!-- Content -->
        <div class="p-6 space-y-4">
          <div v-if="isUserAdmin(selectedUserForStores)" class="p-3 bg-purple-50 dark:bg-purple-950/40 border border-purple-200/60 rounded-xl text-xs text-purple-700 dark:text-purple-300 leading-relaxed">
            <span class="font-bold">توجه: </span>
            این کاربر دارای نقش «مدیر ارشد» است و سیستم به طور خودکار به تمام فروشگاه‌ها دسترسی کامل می‌دهد. اما همچنان می‌توانید فروشگاه‌های مشخص را علامت‌گذاری فرمایید.
          </div>

          <div class="space-y-2">
            <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">انتخاب فروشگاه‌های مجاز:</div>
            <label
              v-for="s in allStores"
              :key="s.id"
              class="flex items-center justify-between p-3 rounded-xl border transition-colors cursor-pointer"
              :class="userStoreIds.includes(s.id)
                ? 'bg-indigo-50/60 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800'
                : 'hover:bg-slate-50 dark:hover:bg-slate-800/40 border-slate-200/70 dark:border-slate-800'"
            >
              <div class="flex items-center gap-3">
                <input
                  type="checkbox"
                  :value="s.id"
                  v-model="userStoreIds"
                  class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300"
                />
                <div>
                  <div class="font-bold text-xs text-slate-800 dark:text-slate-200">{{ s.name }}</div>
                  <div class="text-[10px] text-slate-400 font-mono dir-ltr text-right">{{ s.currency }} • {{ s.url }}</div>
                </div>
              </div>
              <span
                class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                :class="s.status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950 text-emerald-600' : 'bg-slate-100 text-slate-500'"
              >
                {{ s.status === 'active' ? 'فعال' : 'غیرفعال' }}
              </span>
            </label>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-850 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
          <button
            @click="selectedUserForStores = null"
            class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-semibold"
          >
            انصراف
          </button>
          <button
            @click="saveUserStores"
            :disabled="savingStores"
            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold disabled:opacity-50"
          >
            {{ savingStores ? 'در حال ذخیره...' : 'ذخیره دسترسی‌ها' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useNotificationStore } from '@/stores/notification';
import apiClient from '@/api/client';
import { formatDateTime, toPersianDigits } from '@/utils/formatters';

const notification = useNotificationStore();

const activeTab = ref('users');
const users = ref([]);
const roles = ref([]);
const permissions = ref({});
const allStores = ref([]);
const loading = ref(true);
const error = ref(null);
const selectedRole = ref(null);

// User Stores Modal
const selectedUserForStores = ref(null);
const userStoreIds = ref([]);
const savingStores = ref(false);

const isUserAdmin = (user) => {
  return user?.roles?.some(r => r.slug === 'admin' || r.name === 'Admin' || r.slug === 'administrator');
};

const openStoreAccessModal = (user) => {
  selectedUserForStores.value = user;
  userStoreIds.value = (user.stores || []).map(s => s.id);
};

const saveUserStores = async () => {
  if (!selectedUserForStores.value) return;

  savingStores.value = true;
  try {
    const userId = selectedUserForStores.value.id;
    await apiClient.patch(`/users/${userId}`, {
      stores: userStoreIds.value,
    });

    notification.success(`دسترسی فروشگاه‌های کاربر «${selectedUserForStores.value.full_name}» با موفقیت ذخیره شد.`);
    selectedUserForStores.value = null;
    await loadData();
  } catch (err) {
    notification.error(err.message || 'خطا در ذخیره دسترسی فروشگاه‌ها');
  } finally {
    savingStores.value = false;
  }
};

const loadData = async () => {
  loading.value = true;
  error.value = null;
  try {
    const [uRes, rRes, pRes, sRes] = await Promise.all([
      apiClient.get('/users'),
      apiClient.get('/roles'),
      apiClient.get('/permissions'),
      apiClient.get('/stores').catch(() => ({ data: [] })),
    ]);
    users.value = uRes.data;
    roles.value = rRes.data;
    permissions.value = pRes.data;
    allStores.value = Array.isArray(sRes.data) ? sRes.data : (sRes.data?.data || []);
    if (roles.value.length > 0) {
      selectedRole.value = roles.value[0];
    }
  } catch (err) {
    error.value = err.message || 'خطا در بارگذاری اطلاعات RBAC';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadData();
});
</script>
