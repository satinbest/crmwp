import { defineStore } from 'pinia';

export const useThemeStore = defineStore('theme', {
  state: () => ({
    isDark: false,
  }),

  actions: {
    init() {
      const stored = localStorage.getItem('crmwp_theme');
      if (stored) {
        this.isDark = stored === 'dark';
      } else {
        this.isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      }
      this.apply();
    },

    toggle() {
      this.isDark = !this.isDark;
      localStorage.setItem('crmwp_theme', this.isDark ? 'dark' : 'light');
      this.apply();
    },

    setTheme(dark) {
      this.isDark = dark;
      localStorage.setItem('crmwp_theme', this.isDark ? 'dark' : 'light');
      this.apply();
    },

    apply() {
      if (this.isDark) {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    },
  },
});
