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
          <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">کاربران و تیم</span>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">مدیریت کاربران و تیم</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          مدیریت حساب‌ها، تخصیص نقش‌های سازمانی، محدودسازی دسترسی فروشگاه‌ها و رصد امنیت
        </p>
      </div>

      <div class="flex items-center gap-3">
        <router-link
          to="/settings/roles"
          class="flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-sm"
        >
          <Iconsax name="shield-tick" size="16" />
          <span>مدیریت نقش‌ها ({{ roles.length }})</span>
        </router-link>

        <button
          v-if="canCreateUser"
          @click="openCreateModal"
          class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-600/20 transition-all"
        >
          <Iconsax name="add" size="18" />
          <span>کاربر جدید</span>
        </button>
      </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm">
      <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
        <!-- Search Input -->
        <div class="sm:col-span-6 relative">
          <Iconsax name="search" size="16" customClass="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            v-model="filters.search"
            @input="debounceFetch"
            type="text"
            placeholder="جستجو در نام، نام کاربری یا ایمیل..."
            class="w-full pr-10 pl-4 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
          />
        </div>

        <!-- Role Filter -->
        <div class="sm:col-span-3">
          <select
            v-model="filters.role"
            @change="fetchUsers"
            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
          >
            <option value="">همه نقش‌ها</option>
            <option v-for="r in roles" :key="r.id" :value="r.slug || r.name">
              {{ r.display_name || r.name }}
            </option>
          </select>
        </div>

        <!-- Status Filter -->
        <div class="sm:col-span-3">
          <select
            v-model="filters.status"
            @change="fetchUsers"
            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
          >
            <option value="">همه وضعیت‌ها</option>
            <option value="active">فعال</option>
            <option value="inactive">غیرفعال</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Users Table Container -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
      <!-- Loading State -->
      <div v-if="loading" class="p-16 text-center">
        <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <div class="text-xs text-slate-400">در حال دریافت فهرست کاربران...</div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="p-8 text-center bg-rose-50 dark:bg-rose-950/40 border-b border-rose-200 dark:border-rose-900">
        <p class="text-xs text-rose-600 dark:text-rose-400 mb-3">{{ error }}</p>
        <button
          @click="fetchUsers"
          class="px-4 py-1.5 bg-rose-600 text-white rounded-xl text-xs font-semibold hover:bg-rose-500"
        >
          تلاش دوباره
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="users.length === 0" class="p-16 text-center">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-3 text-slate-400">
          <Iconsax name="customers" size="24" />
        </div>
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">کاربری یافت نشد</h4>
        <p class="text-xs text-slate-400 mt-1">با معیارهای جستجوی فعلی کاربری پیدا نشد.</p>
      </div>

      <!-- Table View -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-right text-xs">
          <thead class="bg-slate-50/75 dark:bg-slate-800/50 text-slate-500 font-semibold border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th class="p-4">کاربر</th>
              <th class="p-4">شناسه / ایمیل</th>
              <th class="p-4">نقش‌های سازمانی</th>
              <th class="p-4">دسترسی فروشگاه‌ها</th>
              <th class="p-4">وضعیت</th>
              <th class="p-4">آخرین ورود</th>
              <th class="p-4 text-left">عملیات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
            <tr
              v-for="user in users"
              :key="user.id"
              class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors"
            >
              <!-- Avatar & Name -->
              <td class="p-4">
                <div class="flex items-center gap-3">
                  <div
                    v-if="user.avatar"
                    class="w-9 h-9 rounded-full overflow-hidden bg-slate-100 border border-slate-200 shrink-0"
                  >
                    <img :src="user.avatar" :alt="user.full_name" class="w-full h-full object-cover" />
                  </div>
                  <div
                    v-else
                    class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-violet-600 text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-sm"
                  >
                    {{ getUserInitial(user) }}
                  </div>
                  <div>
                    <div class="font-bold text-slate-900 dark:text-white text-xs">
                      {{ user.full_name || user.username }}
                    </div>
                    <div class="text-[10px] text-slate-400">شناسه: #{{ toPersianDigits(user.id) }}</div>
                  </div>
                </div>
              </td>

              <!-- Username & Email -->
              <td class="p-4 text-slate-600 dark:text-slate-300">
                <div class="text-xs font-semibold dir-ltr text-right">{{ user.username }}</div>
                <div class="text-[10px] text-slate-400 dir-ltr text-right">{{ user.email }}</div>
              </td>

              <!-- Roles -->
              <td class="p-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="r in user.roles"
                    :key="r.id"
                    class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-800/50"
                  >
                    {{ r.display_name || r.name }}
                  </span>
                  <span v-if="!user.roles || user.roles.length === 0" class="text-slate-400 text-[10px]">
                    بدون نقش
                  </span>
                </div>
              </td>

              <!-- Stores -->
              <td class="p-4">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-if="isUserAdmin(user)"
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200/50"
                  >
                    همه فروشگاه‌ها (مدیر کل)
                  </span>
                  <template v-else>
                    <span
                      v-for="s in user.stores"
                      :key="s.id"
                      class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"
                    >
                      {{ s.name }}
                    </span>
                    <span v-if="!user.stores || user.stores.length === 0" class="text-rose-500 text-[10px]">
                      عدم دسترسی به فروشگاه
                    </span>
                  </template>
                </div>
              </td>

              <!-- Status -->
              <td class="p-4">
                <button
                  v-if="canEditUser"
                  @click="toggleUserStatus(user)"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold transition-all"
                  :class="user.is_active ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100' : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 hover:bg-rose-100'"
                  :title="user.is_active ? 'کلیک جهت غیرفعال‌سازی کاربر' : 'کلیک جهت فعال‌سازی کاربر'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="user.is_active ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                  {{ user.is_active ? 'فعال' : 'غیرفعال' }}
                </button>
                <span
                  v-else
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
                  :class="user.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                >
                  {{ user.is_active ? 'فعال' : 'غیرفعال' }}
                </span>
              </td>

              <!-- Last Login -->
              <td class="p-4 text-slate-500 dark:text-slate-400 text-[11px]">
                {{ user.last_login_at ? formatDateTime(user.last_login_at) : 'هرگز' }}
              </td>

              <!-- Actions -->
              <td class="p-4 text-left">
                <div class="flex items-center justify-end gap-1">
                  <button
                    @click="viewUserActivity(user)"
                    title="مشاهده ردپای امنیتی و فعالیت‌ها"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                  >
                    <Iconsax name="activity" size="16" />
                  </button>

                  <button
                    v-if="canEditUser"
                    @click="openResetPasswordModal(user)"
                    title="تغییر / ریست کلمه عبور"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition-colors"
                  >
                    <Iconsax name="key" size="16" />
                  </button>

                  <button
                    v-if="canEditUser"
                    @click="openEditModal(user)"
                    title="ویرایش کاربر"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-colors"
                  >
                    <Iconsax name="edit" size="16" />
                  </button>

                  <button
                    v-if="canDeleteUser"
                    @click="deleteUser(user)"
                    title="حذف کاربر"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                  >
                    <Iconsax name="trash" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <div class="text-slate-400">
          نمایش صفحه {{ toPersianDigits(pagination.page) }} از {{ toPersianDigits(pagination.totalPages) }} (مجموع {{ toPersianDigits(pagination.total) }} کاربر)
        </div>

        <div class="flex items-center gap-1">
          <button
            @click="goToPage(pagination.page - 1)"
            :disabled="pagination.page <= 1"
            class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            قبلی
          </button>
          <span class="px-3 py-1.5 font-bold text-indigo-600 dark:text-indigo-400">
            {{ toPersianDigits(pagination.page) }}
          </span>
          <button
            @click="goToPage(pagination.page + 1)"
            :disabled="pagination.page >= pagination.totalPages"
            class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            بعدی
          </button>
        </div>
      </div>
    </div>

    <!-- Create / Edit User Modal -->
    <div v-if="showUserModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
      <div class="w-full max-w-xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden my-8">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
            {{ isEditingUser ? 'ویرایش مشخصات کاربر' : 'افزودن کاربر جدید' }}
          </h3>
          <button @click="showUserModal = false" class="p-2 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <form @submit.prevent="saveUser" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
          <!-- Name Fields -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">نام</label>
              <input
                v-model="userForm.first_name"
                type="text"
                placeholder="مثلاً: علی"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">نام خانوادگی</label>
              <input
                v-model="userForm.last_name"
                type="text"
                placeholder="مثلاً: محمدی"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
              />
            </div>
          </div>

          <!-- Username & Email -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">نام کاربری *</label>
              <input
                v-model="userForm.username"
                type="text"
                required
                placeholder="مثلاً: ali_m"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ایمیل *</label>
              <input
                v-model="userForm.email"
                type="email"
                required
                placeholder="ali@company.local"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
              />
            </div>
          </div>

          <!-- Password fields for Create (or optional for Edit) -->
          <div v-if="!isEditingUser" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">کلمه عبور *</label>
              <input
                v-model="userForm.password"
                type="password"
                required
                placeholder="حداقل ۸ کاراکتر با حروف و اعداد"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">تکرار کلمه عبور *</label>
              <input
                v-model="userForm.password_confirmation"
                type="password"
                required
                placeholder="تکرار کلمه عبور"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
              />
            </div>
          </div>

          <!-- Roles Selection -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
              نقش‌های سازمانی *
            </label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-700/80">
              <label
                v-for="r in roles"
                :key="r.id"
                class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-200/60 dark:hover:bg-slate-700/60 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :value="r.slug || r.name"
                  v-model="userForm.roles"
                  class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                />
                <span class="text-xs text-slate-800 dark:text-slate-200">
                  {{ r.display_name || r.name }}
                </span>
              </label>
            </div>
          </div>

          <!-- Stores Selection -->
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
              دسترسی به فروشگاه‌ها (Store Isolation)
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-700/80">
              <label
                v-for="s in stores"
                :key="s.id"
                class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-200/60 dark:hover:bg-slate-700/60 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :value="s.id"
                  v-model="userForm.stores"
                  class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                />
                <div class="min-w-0">
                  <div class="text-xs font-medium text-slate-800 dark:text-slate-200">{{ s.name }}</div>
                  <div class="text-[10px] text-slate-400 truncate">{{ s.url }}</div>
                </div>
              </label>
            </div>
          </div>

          <!-- Status Toggle -->
          <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
            <div>
              <div class="text-xs font-bold text-slate-800 dark:text-slate-200">وضعیت حساب کاربری</div>
              <div class="text-[10px] text-slate-400">کاربران غیرفعال امکان ورود به سامانه را نخواهند داشت</div>
            </div>
            <button
              type="button"
              @click="userForm.is_active = !userForm.is_active"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
              :class="userForm.is_active ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-700'"
            >
              <span
                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                :class="userForm.is_active ? '-translate-x-6' : '-translate-x-1'"
              ></span>
            </button>
          </div>

          <div v-if="userFormError" class="p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 rounded-xl text-xs text-rose-600 dark:text-rose-400">
            {{ userFormError }}
          </div>

          <div class="pt-4 flex items-center justify-end gap-3">
            <button
              type="button"
              @click="showUserModal = false"
              class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
              انصراف
            </button>
            <button
              type="submit"
              :disabled="userSubmitting"
              class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-bold shadow-md shadow-indigo-600/20 flex items-center gap-2"
            >
              <div v-if="userSubmitting" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <span>{{ isEditingUser ? 'ذخیره تغییرات کاربر' : 'افزودن کاربر' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Reset Password Modal -->
    <div v-if="showResetModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">
            ریست کلمه عبور کاربر: {{ targetUser?.full_name || targetUser?.username }}
          </h3>
          <button @click="showResetModal = false" class="p-1 rounded-lg text-slate-400 hover:bg-slate-100">
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">کلمه عبور جدید *</label>
          <input
            v-model="resetPasswordVal"
            type="password"
            placeholder="حداقل ۸ کاراکتر با حروف و اعداد"
            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs dir-ltr text-right text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
          />
        </div>

        <div v-if="resetError" class="p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 text-xs text-rose-600 rounded-xl">
          {{ resetError }}
        </div>

        <div class="pt-2 flex items-center justify-end gap-3">
          <button
            type="button"
            @click="showResetModal = false"
            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100"
          >
            انصراف
          </button>
          <button
            type="button"
            @click="submitPasswordReset"
            :disabled="resetSubmitting"
            class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white text-xs font-bold shadow-md shadow-amber-600/20"
          >
            تغییر کلمه عبور
          </button>
        </div>
      </div>
    </div>

    <!-- User Security Activity Drawer -->
    <div v-if="showActivityDrawer" class="fixed inset-0 z-50 overflow-hidden">
      <div @click="showActivityDrawer = false" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
      <div class="fixed inset-y-0 left-0 max-w-full flex pl-0 md:pl-10">
        <div class="w-screen max-w-xl bg-white dark:bg-slate-900 shadow-2xl flex flex-col border-r border-slate-200 dark:border-slate-800">
          <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div>
              <h3 class="text-base font-extrabold text-slate-900 dark:text-white">
                ردپای امنیتی: {{ targetUser?.full_name || targetUser?.username }}
              </h3>
              <p class="text-xs text-slate-400 mt-0.5">ثبت تاریخچه تغییرات، تخصیص نقش‌ها و ورود کاربر</p>
            </div>
            <button @click="showActivityDrawer = false" class="p-2 rounded-xl text-slate-400 hover:bg-slate-100">
              <Iconsax name="close" size="20" />
            </button>
          </div>

          <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div v-if="activityLoading" class="p-12 text-center text-xs text-slate-400">
              در حال دریافت ردپای امنیتی...
            </div>
            <div v-else-if="userActivities.length === 0" class="p-12 text-center text-xs text-slate-400">
              هیچ ردپای امنیتی یا فعالیتی برای این کاربر ثبت نشده است.
            </div>
            <div
              v-else
              v-for="act in userActivities"
              :key="act.id"
              class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 space-y-2"
            >
              <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-slate-900 dark:text-white dir-ltr">{{ act.action }}</span>
                <span class="text-[10px] text-slate-400">{{ act.created_at ? formatDateTime(act.created_at) : '—' }}</span>
              </div>
              <p v-if="act.description" class="text-xs text-slate-600 dark:text-slate-300">
                {{ act.description }}
              </p>
              <div class="flex items-center gap-3 text-[10px] text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800">
                <span class="dir-ltr">IP: {{ act.ip_address || '—' }}</span>
                <span>بخش: <span class="dir-ltr">{{ act.entity_type || 'User' }}</span></span>
              </div>
            </div>
          </div>
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
import { formatDateTime, toPersianDigits } from '@/utils/formatters';

const authStore = useAuthStore();

const users = ref([]);
const roles = ref([]);
const stores = ref([]);
const loading = ref(true);
const error = ref(null);

const pagination = reactive({
  page: 1,
  perPage: 15,
  total: 0,
  totalPages: 1,
});

const filters = reactive({
  search: '',
  role: '',
  status: '',
});

let debounceTimer = null;
const debounceFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    pagination.page = 1;
    fetchUsers();
  }, 350);
};

const canCreateUser = computed(() => authStore.hasPermission('users.create'));
const canEditUser = computed(() => authStore.hasPermission('users.update'));
const canDeleteUser = computed(() => authStore.hasPermission('users.delete'));

const isUserAdmin = (user) => {
  if (!user || !user.roles) return false;
  return user.roles.some(r => {
    const slug = (r.slug || r.name || '').toLowerCase();
    return slug === 'admin' || slug === 'administrator';
  });
};

const getUserInitial = (user) => {
  const name = user.first_name || user.username || 'U';
  return name.charAt(0).toUpperCase();
};

const fetchInitialData = async () => {
  try {
    const [rolesRes, storesRes] = await Promise.all([
      apiClient.get('/roles'),
      apiClient.get('/stores'),
    ]);
    roles.value = Array.isArray(rolesRes.data) ? rolesRes.data : (rolesRes.data?.roles || []);
    stores.value = Array.isArray(storesRes.data) ? storesRes.data : (storesRes.data?.stores || []);
  } catch (err) {
    console.error('Failed to load roles or stores:', err);
  }
};

const fetchUsers = async () => {
  loading.value = true;
  error.value = null;
  try {
    const params = {
      page: pagination.page,
      per_page: pagination.perPage,
      search: filters.search || undefined,
      role: filters.role || undefined,
      status: filters.status || undefined,
    };
    const res = await apiClient.get('/users', { params });
    users.value = Array.isArray(res.data) ? res.data : (res.data?.users || []);
    if (res.meta) {
      pagination.total = res.meta.total || 0;
      pagination.totalPages = res.meta.total_pages || 1;
    }
  } catch (err) {
    error.value = err.message || 'خطا در بارگذاری فهرست کاربران';
  } finally {
    loading.value = false;
  }
};

const goToPage = (p) => {
  if (p < 1 || p > pagination.totalPages) return;
  pagination.page = p;
  fetchUsers();
};

// Modal State
const showUserModal = ref(false);
const isEditingUser = ref(false);
const userSubmitting = ref(false);
const userFormError = ref(null);
const targetUser = ref(null);

const userForm = reactive({
  first_name: '',
  last_name: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
  roles: [],
  stores: [],
  is_active: true,
});

const openCreateModal = () => {
  isEditingUser.value = false;
  targetUser.value = null;
  userForm.first_name = '';
  userForm.last_name = '';
  userForm.username = '';
  userForm.email = '';
  userForm.password = '';
  userForm.password_confirmation = '';
  userForm.roles = ['viewer'];
  userForm.stores = stores.value.map(s => s.id);
  userForm.is_active = true;
  userFormError.value = null;
  showUserModal.value = true;
};

const openEditModal = (user) => {
  isEditingUser.value = true;
  targetUser.value = user;
  userForm.first_name = user.first_name || '';
  userForm.last_name = user.last_name || '';
  userForm.username = user.username || '';
  userForm.email = user.email || '';
  userForm.password = '';
  userForm.password_confirmation = '';
  userForm.roles = (user.roles || []).map(r => r.slug || r.name);
  userForm.stores = (user.stores || []).map(s => s.id);
  userForm.is_active = user.is_active;
  userFormError.value = null;
  showUserModal.value = true;
};

const saveUser = async () => {
  userSubmitting.value = true;
  userFormError.value = null;

  try {
    if (isEditingUser.value) {
      await apiClient.patch(`/users/${targetUser.value.id}`, {
        first_name: userForm.first_name,
        last_name: userForm.last_name,
        username: userForm.username,
        email: userForm.email,
        roles: userForm.roles,
        stores: userForm.stores,
        is_active: userForm.is_active,
      });
    } else {
      if (userForm.password !== userForm.password_confirmation) {
        throw new Error('کلمه عبور با تکرار آن یکسان نیست.');
      }
      await apiClient.post('/users', {
        first_name: userForm.first_name,
        last_name: userForm.last_name,
        username: userForm.username,
        email: userForm.email,
        password: userForm.password,
        password_confirmation: userForm.password_confirmation,
        roles: userForm.roles,
        stores: userForm.stores,
        is_active: userForm.is_active,
      });
    }
    showUserModal.value = false;
    await fetchUsers();
  } catch (err) {
    userFormError.value = err.message || 'خطا در ثبت کاربر';
  } finally {
    userSubmitting.value = false;
  }
};

const toggleUserStatus = async (user) => {
  const action = user.is_active ? 'deactivate' : 'activate';
  const label = user.is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی';

  if (!confirm(`آیا از ${label} حساب کاربر "${user.full_name || user.username}" اطمینان دارید؟`)) return;

  try {
    await apiClient.post(`/users/${user.id}/${action}`);
    await fetchUsers();
  } catch (err) {
    alert(err.message || `خطا در ${label} حساب کاربر`);
  }
};

// Reset Password Modal
const showResetModal = ref(false);
const resetPasswordVal = ref('');
const resetSubmitting = ref(false);
const resetError = ref(null);

const openResetPasswordModal = (user) => {
  targetUser.value = user;
  resetPasswordVal.value = '';
  resetError.value = null;
  showResetModal.value = true;
};

const submitPasswordReset = async () => {
  if (!resetPasswordVal.value || resetPasswordVal.value.length < 8) {
    resetError.value = 'کلمه عبور باید حداقل ۸ کاراکتر باشد.';
    return;
  }
  resetSubmitting.value = true;
  resetError.value = null;

  try {
    await apiClient.post(`/users/${targetUser.value.id}/reset-password`, {
      new_password: resetPasswordVal.value,
    });
    alert('کلمه عبور با موفقیت تغییر یافت.');
    showResetModal.value = false;
  } catch (err) {
    resetError.value = err.message || 'خطا در ریست کلمه عبور';
  } finally {
    resetSubmitting.value = false;
  }
};

// Delete User
const deleteUser = async (user) => {
  if (!confirm(`آیا از حذف دائم کاربر "${user.full_name || user.username}" اطمینان دارید؟`)) return;

  try {
    await apiClient.delete(`/users/${user.id}`);
    await fetchUsers();
  } catch (err) {
    alert(err.message || 'خطا در حذف کاربر');
  }
};

// Activity Drawer
const showActivityDrawer = ref(false);
const activityLoading = ref(false);
const userActivities = ref([]);

const viewUserActivity = async (user) => {
  targetUser.value = user;
  showActivityDrawer.value = true;
  activityLoading.value = true;
  try {
    const res = await apiClient.get(`/users/${user.id}/activity`);
    userActivities.value = Array.isArray(res.data) ? res.data : (res.data?.activities || []);
  } catch (err) {
    console.error('Failed to load user activity:', err);
  } finally {
    activityLoading.value = false;
  }
};

onMounted(async () => {
  await fetchInitialData();
  await fetchUsers();
});
</script>
