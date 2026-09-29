<template>
  <div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <router-link to="/settings" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            تنظیمات
          </router-link>
          <span class="text-xs text-slate-400">/</span>
          <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">پروفایل کاربری من</span>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">پروفایل و حساب کاربری</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          مشاهده و ویرایش مشخصات فردی، تصویر آواتار، تغییر کلمه عبور و بررسی دسترسی‌ها
        </p>
      </div>

      <div class="flex items-center gap-2">
        <span
          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/50"
        >
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          وضعیت حساب: فعال
        </span>
      </div>
    </div>

    <!-- Success / Error Alerts -->
    <div v-if="successMsg" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl flex items-center justify-between text-xs text-emerald-700 dark:text-emerald-300">
      <span>{{ successMsg }}</span>
      <button @click="successMsg = null" class="text-emerald-500 hover:text-emerald-700">✕</button>
    </div>

    <div v-if="errorMsg" class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-2xl flex items-center justify-between text-xs text-rose-700 dark:text-rose-300">
      <span>{{ errorMsg }}</span>
      <button @click="errorMsg = null" class="text-rose-500 hover:text-rose-700">✕</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Left Column: Avatar & Role Summary -->
      <div class="space-y-6">
        <!-- Avatar Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm text-center">
          <div class="relative w-28 h-28 mx-auto mb-4 group">
            <div
              v-if="avatarPreview || authStore.user?.avatar"
              class="w-full h-full rounded-full overflow-hidden border-4 border-indigo-100 dark:border-indigo-950/80 shadow-md bg-slate-100"
            >
              <img :src="avatarPreview || authStore.user?.avatar" alt="Avatar" class="w-full h-full object-cover" />
            </div>
            <div
              v-else
              class="w-full h-full rounded-full bg-gradient-to-tr from-indigo-500 to-violet-600 text-white flex items-center justify-center font-bold text-3xl shadow-md"
            >
              {{ userInitial }}
            </div>

            <!-- Upload Overlay -->
            <label
              class="absolute inset-0 rounded-full bg-slate-900/50 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 cursor-pointer transition-all text-[11px] font-semibold"
            >
              <Iconsax name="edit" size="20" class="mb-1" />
              <span>تغییر تصویر</span>
              <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="handleAvatarFile" />
            </label>
          </div>

          <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">
            {{ authStore.user?.full_name || authStore.user?.username }}
          </h3>
          <p class="text-xs text-slate-400 mt-0.5 dir-ltr">{{ authStore.user?.username }}</p>

          <div v-if="avatarFile" class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-center gap-2">
            <button
              @click="uploadAvatar"
              :disabled="avatarUploading"
              class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all disabled:opacity-50"
            >
              {{ avatarUploading ? 'در حال ارسال...' : 'ثبت تصویر جدید' }}
            </button>
            <button
              @click="cancelAvatarUpload"
              class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
              انصراف
            </button>
          </div>

          <p class="text-[10px] text-slate-400 mt-3 leading-relaxed">
            فرمت‌های مجاز: JPG, PNG, WEBP (حداکثر ۲ مگابایت)
          </p>
        </div>

        <!-- Assigned Roles & Stores (Read Only) -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm space-y-4">
          <div>
            <h4 class="text-xs font-bold text-slate-900 dark:text-white mb-2">نقش‌های سازمانی شما</h4>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="r in authStore.user?.roles"
                :key="r.id"
                class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50"
              >
                {{ r.display_name || r.name }}
              </span>
            </div>
          </div>

          <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
            <h4 class="text-xs font-bold text-slate-900 dark:text-white mb-2">فروشگاه‌های مجاز شما</h4>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-if="isAdmin"
                class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200/50"
              >
                دسترسی کامل به تمام فروشگاه‌ها
              </span>
              <template v-else>
                <span
                  v-for="s in authStore.user?.stores"
                  :key="s.id"
                  class="px-2 py-0.5 rounded-full text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"
                >
                  {{ s.name }}
                </span>
                <span v-if="!authStore.user?.stores || authStore.user.stores.length === 0" class="text-xs text-rose-500">
                  فروشگاهی به شما منتسب نشده است
                </span>
              </template>
            </div>
          </div>

          <div class="pt-3 border-t border-slate-100 dark:border-slate-800 text-[10px] text-slate-400">
            * تغییر نقش‌ها و محدوده‌های فروشگاهی منحصراً توسط مدیر سامانه امکان‌پذیر است.
          </div>
        </div>
      </div>

      <!-- Right Column: Personal Information & Password Change -->
      <div class="md:col-span-2 space-y-6">
        <!-- Personal Information Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
          <h3 class="text-sm font-extrabold text-slate-900 dark:text-white mb-1">اطلاعات فردی</h3>
          <p class="text-xs text-slate-400 mb-5">مشخصات اولیه نمایش‌داده شده در پنل کاربری و ثبت وقایع</p>

          <form @submit.prevent="updateProfile" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">نام</label>
                <input
                  v-model="profileForm.first_name"
                  type="text"
                  placeholder="نام خود را وارد کنید"
                  class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                />
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">نام خانوادگی</label>
                <input
                  v-model="profileForm.last_name"
                  type="text"
                  placeholder="نام خانوادگی خود را وارد کنید"
                  class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ایمیل *</label>
                <input
                  v-model="profileForm.email"
                  type="email"
                  required
                  placeholder="email@domain.local"
                  class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                />
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">نام کاربری</label>
                <input
                  :value="authStore.user?.username"
                  disabled
                  class="w-full px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-400 cursor-not-allowed"
                />
              </div>
            </div>

            <div class="pt-2 flex justify-end">
              <button
                type="submit"
                :disabled="savingProfile"
                class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-bold shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2"
              >
                <div v-if="savingProfile" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                <span>ذخیره تغییرات مشخصات</span>
              </button>
            </div>
          </form>
        </div>

        <!-- Change Password Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm">
          <h3 class="text-sm font-extrabold text-slate-900 dark:text-white mb-1">تغییر کلمه عبور</h3>
          <p class="text-xs text-slate-400 mb-5">جهت حفظ امنیت حساب خود، از کلمات عبور قوی و ترکیبی استفاده فرمایید</p>

          <form @submit.prevent="changePassword" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">کلمه عبور فعلی *</label>
              <input
                v-model="passwordForm.current_password"
                type="password"
                required
                placeholder="کلمه عبور فعلی را وارد نمایید"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">کلمه عبور جدید *</label>
                <input
                  v-model="passwordForm.new_password"
                  type="password"
                  required
                  placeholder="حداقل ۸ کاراکتر"
                  class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                />
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">تکرار کلمه عبور جدید *</label>
                <input
                  v-model="passwordForm.new_password_confirmation"
                  type="password"
                  required
                  placeholder="تکرار کلمه عبور جدید"
                  class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                />
              </div>
            </div>

            <div class="pt-2 flex justify-end">
              <button
                type="submit"
                :disabled="changingPassword"
                class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-xs font-bold shadow-md shadow-amber-600/20 transition-all flex items-center gap-2"
              >
                <div v-if="changingPassword" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                <span>بروزرسانی کلمه عبور</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';

const authStore = useAuthStore();

const successMsg = ref(null);
const errorMsg = ref(null);

const savingProfile = ref(false);
const changingPassword = ref(false);
const avatarUploading = ref(false);

const avatarFile = ref(null);
const avatarPreview = ref(null);

const profileForm = reactive({
  first_name: '',
  last_name: '',
  email: '',
});

const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

const userInitial = computed(() => {
  const name = authStore.user?.first_name || authStore.user?.username || 'U';
  return name.charAt(0).toUpperCase();
});

const isAdmin = computed(() => {
  return authStore.user?.roles?.some(r => {
    const slug = (r.slug || r.name || '').toLowerCase();
    return slug === 'admin' || slug === 'administrator';
  });
});

const syncUserData = () => {
  if (authStore.user) {
    profileForm.first_name = authStore.user.first_name || '';
    profileForm.last_name = authStore.user.last_name || '';
    profileForm.email = authStore.user.email || '';
  }
};

const handleAvatarFile = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  if (file.size > 2 * 1024 * 1024) {
    errorMsg.value = 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.';
    return;
  }

  const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
  if (!validTypes.includes(file.type)) {
    errorMsg.value = 'فرمت تصویر نامعتبر است. فرمت‌های مجاز: JPG, PNG, WEBP';
    return;
  }

  avatarFile.value = file;
  avatarPreview.value = URL.createObjectURL(file);
};

const cancelAvatarUpload = () => {
  avatarFile.value = null;
  avatarPreview.value = null;
};

const uploadAvatar = async () => {
  if (!avatarFile.value) return;
  avatarUploading.value = true;
  errorMsg.value = null;
  successMsg.value = null;

  try {
    const formData = new FormData();
    formData.append('avatar', avatarFile.value);

    const res = await apiClient.post('/me/avatar', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (res.data?.user) {
      authStore.user = res.data.user;
    }
    avatarFile.value = null;
    avatarPreview.value = null;
    successMsg.value = 'تصویر نمایه با موفقیت بروزرسانی شد.';
  } catch (err) {
    errorMsg.value = err.message || 'خطا در بارگذاری تصویر نمایه';
  } finally {
    avatarUploading.value = false;
  }
};

const updateProfile = async () => {
  savingProfile.value = true;
  errorMsg.value = null;
  successMsg.value = null;

  try {
    const res = await apiClient.patch('/me/profile', {
      first_name: profileForm.first_name,
      last_name: profileForm.last_name,
      email: profileForm.email,
    });

    if (res.data?.user) {
      authStore.user = res.data.user;
    }
    successMsg.value = 'مشخصات فردی با موفقیت ذخیره شد.';
  } catch (err) {
    errorMsg.value = err.message || 'خطا در ذخیره مشخصات';
  } finally {
    savingProfile.value = false;
  }
};

const changePassword = async () => {
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    errorMsg.value = 'کلمه عبور جدید با تکرار آن همخوانی ندارد.';
    return;
  }

  changingPassword.value = true;
  errorMsg.value = null;
  successMsg.value = null;

  try {
    await apiClient.post('/me/password', {
      current_password: passwordForm.current_password,
      new_password: passwordForm.new_password,
      new_password_confirmation: passwordForm.new_password_confirmation,
    });

    passwordForm.current_password = '';
    passwordForm.new_password = '';
    passwordForm.new_password_confirmation = '';
    successMsg.value = 'کلمه عبور شما با موفقیت تغییر یافت.';
  } catch (err) {
    errorMsg.value = err.message || 'خطا در تغییر کلمه عبور';
  } finally {
    changingPassword.value = false;
  }
};

onMounted(async () => {
  if (!authStore.user) {
    await authStore.fetchMe();
  }
  syncUserData();
});
</script>
