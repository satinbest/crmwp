<template>
  <div class="min-h-screen flex bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 font-sans">
    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 right-0 z-30 w-64 bg-white dark:bg-slate-900 border-l border-slate-200/80 dark:border-slate-800/80 flex flex-col transition-transform duration-300 md:translate-x-0"
      :class="isMobileMenuOpen ? 'translate-x-0' : 'translate-x-full md:translate-x-0'"
    >
      <!-- Brand Header -->
      <div class="h-16 flex items-center justify-between px-5 border-b border-slate-100 dark:border-slate-800/60">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 font-bold text-lg">
            W
          </div>
          <div>
            <div class="font-bold text-sm tracking-tight">پنل مدیریت ووکامرس</div>
            <div class="text-[11px] text-slate-400 font-medium">WooCommerce & CRM</div>
          </div>
        </div>
        <button
          @click="isMobileMenuOpen = false"
          class="md:hidden p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
        >
          <Iconsax name="close" size="18" />
        </button>
      </div>

      <!-- Store Switcher Context -->
      <div class="p-3 border-b border-slate-100 dark:border-slate-800/60">
        <div class="bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40 rounded-xl p-2.5 flex items-center justify-between">
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-500/20 shrink-0"></span>
            <div class="truncate">
              <div class="text-xs font-semibold truncate">{{ storeContext.currentStoreName }}</div>
              <div class="text-[10px] text-slate-400">وضعیت اتصال: آماده</div>
            </div>
          </div>
          <router-link
            v-if="authStore.hasPermission('stores.view')"
            to="/stores"
            class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium hover:underline shrink-0"
          >
            تغییر
          </router-link>
        </div>
      </div>

      <!-- Navigation Links -->
      <nav class="flex-1 overflow-y-auto p-3 space-y-6">
        <!-- Main Section -->
        <div v-if="authStore.hasPermission('dashboard.view')">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2">اصلی</div>
          <div class="space-y-1">
            <router-link
              to="/dashboard"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/dashboard') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <Iconsax name="dashboard" size="19" />
              <span>داشبورد</span>
            </router-link>
          </div>
        </div>

        <!-- Store Section -->
        <div v-if="canViewAnyStoreModule">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2">فروشگاه</div>
          <div class="space-y-1">
            <router-link
              v-if="authStore.hasPermission('orders.view')"
              to="/orders"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/orders') || route.path.startsWith('/orders/') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="orders" size="19" />
                <span>سفارش‌ها</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('products.view')"
              to="/products"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/products') || route.path.startsWith('/products/') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="products" size="19" />
                <span>محصولات</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('inventory.view')"
              to="/inventory"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/inventory') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="inventory" size="19" />
                <span>انبار و موجودی</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>
          </div>
        </div>

        <!-- CRM Section -->
        <div v-if="canViewAnyCrmModule">
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2">مدیریت مشتریان (CRM)</div>
          <div class="space-y-1">
            <router-link
              v-if="authStore.hasPermission('crm.view')"
              to="/crm"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/crm') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="dashboard" size="19" />
                <span>پیشخوان CRM</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('customers.view')"
              to="/customers"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/customers') || route.path.startsWith('/customers/') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="customers" size="19" />
                <span>مشتریان</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('segments.view')"
              to="/crm/segments"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/crm/segments') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="segments" size="19" />
                <span>بخش‌بندی‌ها (Segments)</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('tags.view')"
              to="/crm/tags"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/crm/tags') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="tag" size="19" />
                <span>برچسب‌ها (Tags)</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('tasks.view')"
              to="/crm/tasks"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/crm/tasks') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="task" size="19" />
                <span>وظایف (Tasks)</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('activities.view')"
              to="/crm/activities"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/crm/activities') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="activity" size="19" />
                <span>فعالیت‌ها (Activities)</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>
          </div>
        </div>

        <!-- Management & Settings -->
        <div>
          <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-3 mb-2">مدیریت و پیکربندی</div>
          <div class="space-y-1">
            <router-link
              v-if="authStore.hasPermission('users.view') || authStore.hasPermission('roles.view')"
              to="/settings/users"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="route.path.startsWith('/settings/users') || route.path.startsWith('/settings/roles') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <Iconsax name="users" size="19" />
              <span>کاربران و نقش‌ها (RBAC)</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('stores.view')"
              to="/stores"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/stores') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <Iconsax name="shop" size="19" />
              <span>اتصال ووکامرس</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('bulk.view')"
              to="/bulk-operations"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/bulk-operations') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="bulk" size="19" />
                <span>عملیات گروهی</span>
              </div>
              <span class="text-[10px] bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 px-1.5 py-0.5 rounded font-medium">فعال</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('automations.view')"
              to="/automations"
              class="flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/automations') || route.path.startsWith('/automations/') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <div class="flex items-center gap-3">
                <Iconsax name="flash" size="19" />
                <span>اتوماسیون و گردش‌کار</span>
              </div>
              <span class="text-[10px] bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400 px-1.5 py-0.5 rounded font-medium">هوشمند</span>
            </router-link>

            <router-link
              v-if="authStore.hasPermission('webhooks.view')"
              to="/settings/webhooks"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/settings/webhooks') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <Iconsax name="activity" size="19" />
              <span>وب‌هوک‌ها و تطبیق</span>
            </router-link>

            <router-link
              to="/settings"
              class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors"
              :class="isCurrentRoute('/settings') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/60'"
            >
              <Iconsax name="settings" size="19" />
              <span>تنظیمات سامانه</span>
            </router-link>
          </div>
        </div>
      </nav>

      <!-- User Profile Card -->
      <div class="p-3 border-t border-slate-100 dark:border-slate-800/60">
        <div class="flex items-center justify-between p-2 rounded-xl hover:bg-slate-100/60 dark:hover:bg-slate-800/40 transition-colors">
          <router-link
            to="/settings/profile"
            class="flex items-center gap-3 min-w-0 flex-1 hover:opacity-90 transition-opacity"
            title="مشاهده و ویرایش پروفایل"
          >
            <div
              v-if="authStore.user?.avatar"
              class="w-9 h-9 rounded-full overflow-hidden border border-slate-200 shrink-0"
            >
              <img :src="authStore.user.avatar" alt="Avatar" class="w-full h-full object-cover" />
            </div>
            <div
              v-else
              class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-violet-600 text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-sm"
            >
              {{ userInitial }}
            </div>
            <div class="truncate">
              <div class="text-xs font-bold truncate text-slate-900 dark:text-white">
                {{ authStore.user?.full_name || authStore.user?.username }}
              </div>
              <div class="text-[10px] text-indigo-600 dark:text-indigo-400 font-medium">
                {{ primaryRoleName }}
              </div>
            </div>
          </router-link>

          <button
            @click="logout"
            title="خروج از سامانه"
            class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors shrink-0"
          >
            <Iconsax name="logout" size="18" />
          </button>
        </div>
      </div>
    </aside>

    <!-- Mobile Overlay -->
    <div
      v-if="isMobileMenuOpen"
      @click="isMobileMenuOpen = false"
      class="fixed inset-0 z-20 bg-slate-900/50 backdrop-blur-sm md:hidden"
    ></div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col md:mr-64 transition-all">
      <!-- Top Bar -->
      <header class="h-16 sticky top-0 z-10 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800/80 px-4 md:px-6 flex items-center justify-between">
        <div class="flex items-center gap-2 sm:gap-3">
          <button
            @click="isMobileMenuOpen = true"
            class="md:hidden p-2 rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            <Iconsax name="menu" size="20" />
          </button>

          <!-- Central Header Store Switcher -->
          <StoreSwitcher />

          <!-- Search / Command Palette Trigger -->
          <button
            @click="openPalette"
            class="hidden sm:flex items-center gap-3 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-850 hover:border-slate-300 dark:hover:border-slate-700 text-slate-400 text-xs transition-colors"
          >
            <Iconsax name="search" size="16" class="text-slate-400" />
            <span>جستجو یا دستور سریع...</span>
            <kbd class="font-mono bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-1.5 py-0.5 rounded text-[10px] text-slate-500">Ctrl + K</kbd>
          </button>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
          <!-- Command palette mobile icon -->
          <button
            @click="openPalette"
            class="sm:hidden p-2 rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
            title="دستور سریع"
          >
            <Iconsax name="search" size="19" />
          </button>

          <!-- Theme Toggle -->
          <button
            @click="themeStore.toggle"
            class="p-2 rounded-xl text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            :title="themeStore.isDark ? 'حالت روز' : 'حالت شب'"
          >
            <Iconsax :name="themeStore.isDark ? 'sun' : 'moon'" size="20" />
          </button>

          <!-- Notification Bell & Center Dropdown -->
          <NotificationDropdown />

          <!-- User Role Badge -->
          <div class="hidden sm:flex items-center gap-2 pl-2 border-r border-slate-200 dark:border-slate-800 mr-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-800/50">
              {{ primaryRoleName }}
            </span>
          </div>

          <!-- Header Profile Quick Link -->
          <router-link
            to="/settings/profile"
            class="flex items-center gap-2 p-1 rounded-full hover:ring-2 hover:ring-indigo-500/20 transition-all"
            title="پروفایل من"
          >
            <div
              v-if="authStore.user?.avatar"
              class="w-8 h-8 rounded-full overflow-hidden border border-slate-200"
            >
              <img :src="authStore.user.avatar" alt="Avatar" class="w-full h-full object-cover" />
            </div>
            <div
              v-else
              class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-xs"
            >
              {{ userInitial }}
            </div>
          </router-link>
        </div>
      </header>

      <!-- Main View Content (Keyed by activeStore ID to guarantee complete view re-mount on store switch) -->
      <main class="flex-1 p-4 md:p-6 max-w-7xl w-full mx-auto">
        <router-view :key="(storeContext.activeStore?.id || 'none') + '-' + route.fullPath" />
      </main>
    </div>

    <!-- UI Overlay Components -->
    <CommandPalette ref="commandPaletteRef" />
    <ToastContainer />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import { useStoreContext } from '@/stores/storeContext';
import { useNotificationStore } from '@/stores/notification';
import Iconsax from '@/components/icons/Iconsax.vue';
import CommandPalette from '@/components/ui/CommandPalette.vue';
import ToastContainer from '@/components/ui/ToastContainer.vue';
import NotificationDropdown from '@/components/ui/NotificationDropdown.vue';
import StoreSwitcher from '@/components/StoreSwitcher.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();
const storeContext = useStoreContext();
const notification = useNotificationStore();

const isMobileMenuOpen = ref(false);
const commandPaletteRef = ref(null);

onMounted(() => {
  storeContext.fetchStores();
});

const userInitial = computed(() => {
  const name = authStore.user?.first_name || authStore.user?.username || 'U';
  return name.charAt(0).toUpperCase();
});

const primaryRoleName = computed(() => {
  const role = authStore.user?.roles?.[0];
  return role ? (role.display_name || role.name) : 'کاربر';
});

const canViewAnyStoreModule = computed(() => {
  return (
    authStore.hasPermission('orders.view') ||
    authStore.hasPermission('products.view') ||
    authStore.hasPermission('inventory.view')
  );
});

const canViewAnyCrmModule = computed(() => {
  return (
    authStore.hasPermission('crm.view') ||
    authStore.hasPermission('customers.view') ||
    authStore.hasPermission('segments.view') ||
    authStore.hasPermission('tags.view') ||
    authStore.hasPermission('tasks.view') ||
    authStore.hasPermission('activities.view')
  );
});

const isCurrentRoute = (path) => {
  return route.path === path;
};

const openPalette = () => {
  commandPaletteRef.value?.open();
};

const logout = async () => {
  await authStore.logout();
  notification.info('شما با موفقیت خارج شدید.');
  router.push('/login');
};
</script>
