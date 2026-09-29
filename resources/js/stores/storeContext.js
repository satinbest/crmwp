import { defineStore } from 'pinia';
import apiClient from '@/api/client';

export const useStoreContext = defineStore('storeContext', {
  state: () => ({
    stores: [],
    activeStore: null,
    loading: false,
    error: null,
  }),

  getters: {
    hasStores: (state) => state.stores && state.stores.length > 0,
    currentStoreName: (state) => state.activeStore ? state.activeStore.name : 'فروشگاهی انتخاب نشده',
    activeStoreId: (state) => state.activeStore ? state.activeStore.id : null,
    currency: (state) => state.activeStore?.currency || 'IRR',
    currencySymbol: (state) => {
      const c = state.activeStore?.currency || 'IRR';
      switch (c) {
        case 'USD': return '$';
        case 'EUR': return '€';
        case 'GBP': return '£';
        case 'AED': return 'AED';
        default: return 'تومان';
      }
    },
    timezone: (state) => state.activeStore?.timezone || 'Asia/Tehran',
    isDemo: (state) => Boolean(state.activeStore?.is_demo),
    isConnected: (state) => state.activeStore?.status === 'active',
  },

  actions: {
    async fetchStores() {
      this.loading = true;
      this.error = null;
      try {
        const res = await apiClient.get('/stores');
        this.stores = Array.isArray(res.data) ? res.data : (res.data?.data || []);
        
        if (this.stores.length > 0) {
          const storedId = localStorage.getItem('crmwp_active_store_id');
          const found = this.stores.find(s => String(s.id) === String(storedId));
          // If stored store is found in accessible stores list, keep it; otherwise switch to the first accessible store
          this.activeStore = found || this.stores[0];
          localStorage.setItem('crmwp_active_store_id', this.activeStore.id);
        } else {
          this.activeStore = null;
          localStorage.removeItem('crmwp_active_store_id');
        }
      } catch (e) {
        this.stores = [];
        this.activeStore = null;
        this.error = e.message || 'خطا در بارگذاری فروشگاه‌ها';
      } finally {
        this.loading = false;
      }
    },

    setActiveStore(store) {
      if (!store) {
        this.activeStore = null;
        localStorage.removeItem('crmwp_active_store_id');
        return;
      }

      const prevId = this.activeStore?.id;
      this.activeStore = store;
      localStorage.setItem('crmwp_active_store_id', store.id);

      if (prevId !== store.id) {
        window.dispatchEvent(new CustomEvent('store:changed', { detail: store }));
      }
    },
  },
});

