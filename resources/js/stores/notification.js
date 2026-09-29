import { defineStore } from 'pinia';

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    toasts: [],
  }),

  actions: {
    notify(title, message, type = 'info', duration = 4000) {
      const id = Date.now() + Math.random().toString(36).substring(2, 6);
      const toast = { id, title, message, type };
      this.toasts.push(toast);

      if (duration > 0) {
        setTimeout(() => {
          this.remove(id);
        }, duration);
      }
      return id;
    },

    success(message, title = 'عملیات موفق') {
      return this.notify(title, message, 'success');
    },

    error(message, title = 'خطا') {
      return this.notify(title, message, 'error', 6000);
    },

    info(message, title = 'اطلاع') {
      return this.notify(title, message, 'info');
    },

    warning(message, title = 'هشدار') {
      return this.notify(title, message, 'warning');
    },

    remove(id) {
      this.toasts = this.toasts.filter(t => t.id !== id);
    },
  },
});
