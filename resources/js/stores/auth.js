import { defineStore } from 'pinia';
import apiClient, { setCsrfToken } from '@/api/client';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    loading: false,
    initialized: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    permissions: (state) => state.user?.permissions || [],
    roles: (state) => state.user?.roles || [],
    hasPermission: (state) => (permission) => {
      if (!state.user) return false;
      // Admin role has all permissions
      if (state.user.roles?.some(r => {
        const name = (r.name || '').toLowerCase();
        const slug = (r.slug || '').toLowerCase();
        return name === 'admin' || name === 'administrator' || slug === 'admin' || slug === 'administrator';
      })) return true;
      return state.user.permissions?.includes(permission) || false;
    },
    hasRole: (state) => (roleIdentifier) => {
      if (!state.user) return false;
      const target = (roleIdentifier || '').toLowerCase();
      return state.user?.roles?.some(r => 
        (r.name || '').toLowerCase() === target || (r.slug || '').toLowerCase() === target
      ) || false;
    },
  },

  actions: {
    async init() {
      if (this.initialized) return;
      try {
        await this.fetchMe();
      } catch (e) {
        this.user = null;
      } finally {
        this.initialized = true;
      }
    },

    async fetchMe() {
      this.loading = true;
      try {
        const res = await apiClient.get('/auth/me');
        this.user = res.data.user;
        if (res.data.csrf_token) {
          setCsrfToken(res.data.csrf_token);
        }
        return this.user;
      } catch (err) {
        this.user = null;
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async login(username, password) {
      this.loading = true;
      try {
        const res = await apiClient.post('/auth/login', { username, password });
        this.user = res.data.user;
        if (res.data.csrf_token) {
          setCsrfToken(res.data.csrf_token);
        }
        return this.user;
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      this.loading = true;
      try {
        await apiClient.post('/auth/logout');
      } catch (e) {
        // Ignore logout errors
      } finally {
        this.user = null;
        this.loading = false;
      }
    },
  },
});
