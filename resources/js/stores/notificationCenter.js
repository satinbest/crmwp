import { defineStore } from 'pinia';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';

export const useNotificationCenterStore = defineStore('notificationCenter', {
  state: () => ({
    notifications: [],
    unreadCount: 0,
    loading: false,
    markingRead: false,
    meta: {
      current_page: 1,
      per_page: 20,
      total: 0,
      total_pages: 1,
    },
    preferences: {
      task_notifications: true,
      order_notifications: true,
      inventory_notifications: true,
      bulk_notifications: true,
      system_notifications: true,
      retention_days: 30,
    },
    prefsLoading: false,
  }),

  actions: {
    async fetchUnreadCount(storeId = null) {
      try {
        const params = {};
        if (storeId) params.store_id = storeId;
        const res = await apiClient.get('/notifications/unread-count', { params });
        this.unreadCount = res?.data?.unread_count ?? (typeof res?.unread_count === 'number' ? res.unread_count : 0);
        return this.unreadCount;
      } catch (err) {
        console.error('Failed to fetch unread count:', err);
        return 0;
      }
    },

    async fetchNotifications(params = {}) {
      this.loading = true;
      try {
        const res = await apiClient.get('/notifications', { params });
        this.notifications = Array.isArray(res?.data) ? res.data : (res?.data?.data || []);
        this.meta = res?.meta || {
          current_page: 1,
          per_page: 20,
          total: this.notifications.length,
          total_pages: 1,
        };
        if (typeof res?.meta?.unread_count === 'number') {
          this.unreadCount = res.meta.unread_count;
        }
        return this.notifications;
      } catch (err) {
        console.error('Failed to fetch notifications:', err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async markAsRead(id) {
      try {
        await apiClient.post(`/notifications/${id}/read`);
        const item = this.notifications.find(n => n.id === id);
        if (item && !item.is_read) {
          item.is_read = true;
          item.read_at = new Date().toISOString();
          if (this.unreadCount > 0) {
            this.unreadCount--;
          }
        }
      } catch (err) {
        console.error('Failed to mark notification as read:', err);
        throw err;
      }
    },

    async markAllAsRead(storeId = null) {
      this.markingRead = true;
      const toast = useNotificationStore();
      try {
        const params = {};
        if (storeId) params.store_id = storeId;
        const res = await apiClient.post('/notifications/read-all', null, { params });
        this.notifications.forEach(n => {
          n.is_read = true;
          n.read_at = new Date().toISOString();
        });
        this.unreadCount = 0;
        toast.success('تمامی اعلان‌ها به عنوان خوانده‌شده علامت‌گذاری شدند.');
        return res.data;
      } catch (err) {
        toast.error('خطا در علامت‌گذاری اعلان‌ها.');
        throw err;
      } finally {
        this.markingRead = false;
      }
    },

    async deleteNotification(id) {
      const toast = useNotificationStore();
      try {
        await apiClient.delete(`/notifications/${id}`);
        const idx = this.notifications.findIndex(n => n.id === id);
        if (idx !== -1) {
          if (!this.notifications[idx].is_read && this.unreadCount > 0) {
            this.unreadCount--;
          }
          this.notifications.splice(idx, 1);
          this.meta.total = Math.max(0, this.meta.total - 1);
        }
        toast.success('اعلان با موفقیت حذف شد.');
      } catch (err) {
        toast.error('خطا در حذف اعلان.');
        throw err;
      }
    },

    async fetchPreferences() {
      this.prefsLoading = true;
      try {
        const res = await apiClient.get('/notifications/preferences');
        if (res?.data) {
          this.preferences = { ...this.preferences, ...(res.data.preferences || res.data) };
        }
        return this.preferences;
      } catch (err) {
        console.error('Failed to fetch preferences:', err);
      } finally {
        this.prefsLoading = false;
      }
    },

    async updatePreferences(data) {
      this.prefsLoading = true;
      const toast = useNotificationStore();
      try {
        const res = await apiClient.put('/notifications/preferences', data);
        if (res?.data) {
          this.preferences = { ...this.preferences, ...(res.data.preferences || res.data) };
        }
        toast.success('تنظیمات اعلان‌ها با موفقیت بروزرسانی شد.');
        return this.preferences;
      } catch (err) {
        toast.error('خطا در ذخیره تنظیمات اعلان‌ها.');
        throw err;
      } finally {
        this.prefsLoading = false;
      }
    },
  },
});
