import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

import AppLayout from '@/layouts/AppLayout.vue';
import LoginView from '@/views/LoginView.vue';
import DashboardView from '@/views/DashboardView.vue';
import StoresView from '@/views/StoresView.vue';
import SettingsView from '@/views/SettingsView.vue';
import UnauthorizedView from '@/views/UnauthorizedView.vue';

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guestOnly: true },
  },
  {
    path: '/403',
    name: 'unauthorized',
    component: UnauthorizedView,
    meta: { requiresAuth: true },
  },
  {
    path: '/',
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/dashboard',
      },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: DashboardView,
        meta: { permission: 'dashboard.view' },
      },
      {
        path: 'users',
        redirect: '/settings/users',
      },
      {
        path: 'stores',
        name: 'stores',
        component: StoresView,
        meta: { permission: 'stores.view' },
      },
      {
        path: 'customers',
        name: 'customers',
        component: () => import('@/views/CustomersView.vue'),
        meta: { permission: 'customers.view' },
      },
      {
        path: 'customers/:id',
        name: 'customer-profile',
        component: () => import('@/views/CustomerProfileView.vue'),
        meta: { permission: 'customers.view' },
      },
      {
        path: 'orders',
        name: 'orders',
        component: () => import('@/views/OrdersView.vue'),
        meta: { permission: 'orders.view' },
      },
      {
        path: 'orders/:id',
        name: 'order-profile',
        component: () => import('@/views/OrderProfileView.vue'),
        meta: { permission: 'orders.view' },
      },
      {
        path: 'products',
        name: 'products',
        component: () => import('@/views/ProductsView.vue'),
        meta: { permission: 'products.view' },
      },
      {
        path: 'products/create',
        name: 'product-create',
        component: () => import('@/views/ProductCreateView.vue'),
        meta: { permission: 'products.create' },
      },
      {
        path: 'products/:id',
        name: 'product-detail',
        component: () => import('@/views/ProductDetailView.vue'),
        meta: { permission: 'products.view' },
      },
      {
        path: 'inventory',
        name: 'inventory',
        component: () => import('@/views/InventoryView.vue'),
        meta: { permission: 'inventory.view' },
      },
      {
        path: 'bulk-operations',
        name: 'bulk-operations',
        component: () => import('@/views/BulkOperationsView.vue'),
        meta: { permission: 'bulk.view' },
      },
      {
        path: 'crm',
        name: 'crm',
        component: () => import('@/views/crm/CrmDashboardView.vue'),
        meta: { permission: 'crm.view' },
      },
      {
        path: 'crm/segments',
        name: 'crm-segments',
        component: () => import('@/views/crm/SegmentsView.vue'),
        meta: { permission: 'segments.view' },
      },
      {
        path: 'crm/tags',
        name: 'crm-tags',
        component: () => import('@/views/crm/TagsView.vue'),
        meta: { permission: 'tags.view' },
      },
      {
        path: 'crm/tasks',
        name: 'crm-tasks',
        component: () => import('@/views/crm/TasksView.vue'),
        meta: { permission: 'tasks.view' },
      },
      {
        path: 'crm/activities',
        name: 'crm-activities',
        component: () => import('@/views/crm/ActivitiesView.vue'),
        meta: { permission: 'activities.view' },
      },
      {
        path: 'notifications',
        name: 'notifications',
        component: () => import('@/views/NotificationsView.vue'),
      },
      {
        path: 'automations',
        name: 'automations',
        component: () => import('@/views/automations/AutomationsView.vue'),
        meta: { permission: 'automations.view' },
      },
      {
        path: 'automations/:id/runs',
        name: 'automation-runs',
        component: () => import('@/views/automations/AutomationRunsView.vue'),
        meta: { permission: 'automations.view_runs' },
      },
      {
        path: 'settings',
        name: 'settings',
        component: SettingsView,
      },
      {
        path: 'settings/stores',
        name: 'settings-stores',
        component: StoresView,
        meta: { permission: 'stores.view' },
      },
      {
        path: 'settings/users',
        name: 'settings-users',
        component: () => import('@/views/settings/UsersManagementView.vue'),
        meta: { permission: 'users.view' },
      },
      {
        path: 'settings/roles',
        name: 'settings-roles',
        component: () => import('@/views/settings/RolesManagementView.vue'),
        meta: { permission: 'roles.view' },
      },
      {
        path: 'settings/profile',
        name: 'settings-profile',
        component: () => import('@/views/settings/SelfProfileView.vue'),
      },
      {
        path: 'settings/notifications',
        name: 'settings-notifications',
        component: () => import('@/views/settings/NotificationPreferencesView.vue'),
      },
      {
        path: 'settings/webhooks',
        name: 'settings-webhooks',
        component: () => import('@/views/settings/WebhooksManagementView.vue'),
        meta: { permission: 'webhooks.view' },
      },
      {
        path: 'settings/cache',
        name: 'settings-cache',
        component: () => import('@/views/settings/ObjectCacheView.vue'),
      },
      {
        path: 'settings/about',
        name: 'settings-about',
        component: () => import('@/views/settings/AboutView.vue'),
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, from) => {
  const authStore = useAuthStore();

  if (!authStore.initialized) {
    await authStore.init();
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login' };
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    return { name: 'dashboard' };
  }

  // Check granular permission requirement
  if (to.meta.permission && authStore.isAuthenticated) {
    if (!authStore.hasPermission(to.meta.permission)) {
      return { name: 'unauthorized' };
    }
  }
});

export default router;
