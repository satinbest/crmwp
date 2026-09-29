<template>
  <div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <router-link to="/settings" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            تنظیمات
          </router-link>
          <span class="text-xs text-slate-400">/</span>
          <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">نقش‌ها و دسترسی‌ها</span>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">مدیریت نقش‌ها و مجوزها</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          تعریف سمت‌های سازمانی، تکثیر الگوها و اعطای اختیارات دسترسی به ماژول‌های سامانه
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button
          v-if="canCreateRole"
          @click="openCreateModal"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-600/20 transition-all"
        >
          <Iconsax name="add" size="18" />
          <span>ایجاد نقش جدید</span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="p-16 text-center">
      <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <div class="text-xs text-slate-400">در حال دریافت فهرست نقش‌ها...</div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 p-6 rounded-2xl text-center">
      <div class="text-sm font-bold text-rose-700 dark:text-rose-300 mb-1">خطا در دریافت نقش‌ها</div>
      <p class="text-xs text-rose-600 dark:text-rose-400 mb-4">{{ error }}</p>
      <button
        @click="fetchRoles"
        class="px-4 py-2 bg-rose-600 text-white rounded-xl text-xs font-semibold hover:bg-rose-500"
      >
        تلاش مجدد
      </button>
    </div>

    <!-- Role Cards Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="role in roles"
        :key="role.id"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between"
      >
        <div>
          <!-- Card Header -->
          <div class="flex items-start justify-between gap-3 mb-3">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
                  {{ role.display_name || role.name }}
                </h3>
                <span
                  v-if="isSystemRole(role)"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60"
                  title="این نقش اصلی سامانه است و قابل حذف نمی‌باشد"
                >
                  سیستمی
                </span>
              </div>
              <div class="text-[11px] text-slate-400 mt-0.5">
                شناسه: <span class="font-mono dir-ltr inline-block">{{ role.slug || role.name }}</span>
              </div>
            </div>

            <span
              class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
              :class="role.status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="role.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
              {{ role.status === 'active' ? 'فعال' : 'غیرفعال' }}
            </span>
          </div>

          <!-- Description -->
          <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mb-4 min-h-[38px]">
            {{ role.description || 'بدون توضیحات ثبت شده' }}
          </p>

          <!-- Badges & Stats -->
          <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl mb-4 text-xs">
            <div>
              <div class="text-[10px] text-slate-400">کاربران منتسب</div>
              <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                {{ toPersianDigits(role.users_count || 0) }} کاربر
              </div>
            </div>
            <div>
              <div class="text-[10px] text-slate-400">تعداد مجوزها</div>
              <div class="font-bold text-indigo-600 dark:text-indigo-400 mt-0.5">
                {{ toPersianDigits(role.permissions_count || 0) }} مجوز
              </div>
            </div>
          </div>
        </div>

        <!-- Card Actions -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
          <button
            @click="openPermissionDrawer(role)"
            class="flex-1 flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-semibold transition-colors"
          >
            <Iconsax name="shield-tick" size="16" />
            <span>تنظیم مجوزها</span>
          </button>

          <div class="flex items-center gap-1">
            <button
              v-if="canCreateRole"
              @click="duplicateRole(role)"
              title="تکثیر این نقش"
              class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            >
              <Iconsax name="copy" size="16" />
            </button>

            <button
              v-if="canEditRole"
              @click="openEditModal(role)"
              title="ویرایش اطلاعات نقش"
              class="p-2 rounded-xl text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors"
            >
              <Iconsax name="edit" size="16" />
            </button>

            <button
              v-if="canDeleteRole && !isSystemRole(role)"
              @click="deleteRole(role)"
              title="حذف نقش"
              class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
            >
              <Iconsax name="trash" size="16" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
            {{ isEditing ? 'ویرایش مشخصات نقش' : 'ایجاد نقش سازمانی جدید' }}
          </h3>
          <button @click="showModal = false" class="p-2 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <form @submit.prevent="saveRole" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              عنوان فارسی نقش *
            </label>
            <input
              v-model="form.display_name"
              type="text"
              required
              placeholder="مثلاً: کارشناس فروشگاه"
              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              شناسه سیستمی (Slug / Machine Name) *
            </label>
            <input
              v-model="form.name"
              type="text"
              required
              :disabled="isEditing && isSystemRole(selectedRole)"
              placeholder="مثلاً: sales_specialist"
              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 disabled:opacity-50"
            />
            <p class="text-[10px] text-slate-400 mt-1">فقط حروف انگلیسی کوچک، اعداد و خط فاصله یا زیرخط</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              توضیحات نقش
            </label>
            <textarea
              v-model="form.description"
              rows="3"
              placeholder="مسئولیت‌ها و اختیارات کلی این نقش..."
              class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              وضعیت
            </label>
            <select
              v-model="form.status"
              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
            >
              <option value="active">فعال</option>
              <option value="inactive">غیرفعال</option>
            </select>
          </div>

          <div v-if="formError" class="p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 dark:text-rose-400">
            {{ formError }}
          </div>

          <div class="pt-4 flex items-center justify-end gap-3">
            <button
              type="button"
              @click="showModal = false"
              class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
              انصراف
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-bold shadow-md shadow-indigo-600/20 flex items-center gap-2"
            >
              <div v-if="submitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <span>{{ isEditing ? 'بروزرسانی نقش' : 'ایجاد نقش' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Role Permission Drawer Component -->
    <RolePermissionDrawer
      :is-open="showDrawer"
      :role="selectedRole"
      @close="showDrawer = false"
      @saved="handlePermissionsSaved"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import RolePermissionDrawer from './RolePermissionDrawer.vue';
import { toPersianDigits } from '@/utils/formatters';

const authStore = useAuthStore();

const roles = ref([]);
const loading = ref(true);
const error = ref(null);

const showModal = ref(false);
const isEditing = ref(false);
const submitting = ref(false);
const formError = ref(null);

const showDrawer = ref(false);
const selectedRole = ref(null);

const form = reactive({
  name: '',
  display_name: '',
  description: '',
  status: 'active',
});

const canCreateRole = computed(() => authStore.hasPermission('roles.create'));
const canEditRole = computed(() => authStore.hasPermission('roles.update'));
const canDeleteRole = computed(() => authStore.hasPermission('roles.delete'));

const isSystemRole = (role) => {
  if (!role) return false;
  const slug = (role.slug || role.name || '').toLowerCase();
  return slug === 'admin' || slug === 'administrator';
};

const fetchRoles = async () => {
  loading.value = true;
  error.value = null;
  try {
    const res = await apiClient.get('/roles');
    roles.value = res.data.roles || [];
  } catch (err) {
    error.value = err.message || 'خطا در بارگذاری نقش‌ها';
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  isEditing.value = false;
  form.name = '';
  form.display_name = '';
  form.description = '';
  form.status = 'active';
  formError.value = null;
  showModal.value = true;
};

const openEditModal = (role) => {
  isEditing.value = true;
  selectedRole.value = role;
  form.name = role.name || role.slug;
  form.display_name = role.display_name || role.name;
  form.description = role.description || '';
  form.status = role.status || 'active';
  formError.value = null;
  showModal.value = true;
};

const saveRole = async () => {
  submitting.value = true;
  formError.value = null;
  try {
    if (isEditing.value) {
      await apiClient.patch(`/roles/${selectedRole.value.id}`, {
        display_name: form.display_name,
        description: form.description,
        status: form.status,
      });
    } else {
      await apiClient.post('/roles', {
        name: form.name,
        display_name: form.display_name,
        description: form.description,
        status: form.status,
      });
    }
    showModal.value = false;
    await fetchRoles();
  } catch (err) {
    formError.value = err.message || 'خطا در ثبت اطلاعات نقش';
  } finally {
    submitting.value = false;
  }
};

const duplicateRole = async (role) => {
  const newName = prompt(`نام نمایشی برای نسخه تکثیر شده از نقش "${role.display_name || role.name}":`, `${role.display_name || role.name} (کپی)`);
  if (!newName) return;

  try {
    await apiClient.post(`/roles/${role.id}/duplicate`, {
      display_name: newName,
    });
    await fetchRoles();
  } catch (err) {
    alert(err.message || 'خطا در تکثیر نقش');
  }
};

const deleteRole = async (role) => {
  if (isSystemRole(role)) {
    alert('نقش‌های سیستمی سامانه قابل حذف نیستند.');
    return;
  }
  if (!confirm(`آیا از حذف نقش "${role.display_name || role.name}" اطمینان دارید؟`)) return;

  try {
    await apiClient.delete(`/roles/${role.id}`);
    await fetchRoles();
  } catch (err) {
    alert(err.message || 'خطا در حذف نقش');
  }
};

const openPermissionDrawer = (role) => {
  selectedRole.value = role;
  showDrawer.value = true;
};

const handlePermissionsSaved = () => {
  fetchRoles();
};

onMounted(() => {
  fetchRoles();
});
</script>
