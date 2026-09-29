import { defineStore } from 'pinia';

function getInitialDarkState() {
  if (typeof window === 'undefined') return false;
  try {
    const stored = localStorage.getItem('crmwp_theme');
    if (stored === 'dark') return true;
    if (stored === 'light') return false;
    return Boolean(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  } catch (e) {
    return false;
  }
}

export const useThemeStore = defineStore('theme', {
  state: () => ({
    isDark: getInitialDarkState(),
    mediaListenerAttached: false,
  }),

  actions: {
    init() {
      if (typeof window === 'undefined') return;

      try {
        const stored = localStorage.getItem('crmwp_theme');
        if (stored === 'dark') {
          this.isDark = true;
        } else if (stored === 'light') {
          this.isDark = false;
        } else if (window.matchMedia) {
          this.isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
      } catch (e) {
        // Fallback gracefully if localStorage is restricted
      }

      this.apply();

      // Listen for OS theme changes if user hasn't overridden theme explicitly
      if (!this.mediaListenerAttached && window.matchMedia) {
        try {
          const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
          const handler = (e) => {
            const hasStoredPreference = localStorage.getItem('crmwp_theme');
            if (!hasStoredPreference) {
              this.isDark = e.matches;
              this.apply();
            }
          };
          if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', handler);
          } else if (mediaQuery.addListener) {
            mediaQuery.addListener(handler);
          }
          this.mediaListenerAttached = true;
        } catch (e) {
          // ignore
        }
      }
    },

    toggle() {
      this.isDark = !this.isDark;
      try {
        localStorage.setItem('crmwp_theme', this.isDark ? 'dark' : 'light');
      } catch (e) {
        // ignore
      }
      this.apply();
    },

    setTheme(dark) {
      this.isDark = Boolean(dark);
      try {
        localStorage.setItem('crmwp_theme', this.isDark ? 'dark' : 'light');
      } catch (e) {
        // ignore
      }
      this.apply();
    },

    apply() {
      if (typeof document === 'undefined') return;
      const el = document.documentElement;
      if (this.isDark) {
        el.classList.add('dark');
        el.setAttribute('data-theme', 'dark');
        el.style.colorScheme = 'dark';
      } else {
        el.classList.remove('dark');
        el.setAttribute('data-theme', 'light');
        el.style.colorScheme = 'light';
      }
    },
  },
});
