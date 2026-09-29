import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';

import '@/../css/app.css';

import Iconsax from '@/components/icons/Iconsax.vue';
import Button from '@/components/ui/Button.vue';
import RefreshButton from '@/components/ui/RefreshButton.vue';

import * as formatters from '@/utils/formatters';

const app = createApp(App);
const pinia = createPinia();

app.config.globalProperties.$fmt = formatters;
app.config.globalProperties.toPersianDigits = formatters.toPersianDigits;
app.config.globalProperties.formatNumber = formatters.formatNumber;
app.config.globalProperties.formatCurrency = formatters.formatCurrency;
app.config.globalProperties.formatPrice = formatters.formatPrice;
app.config.globalProperties.formatPercent = formatters.formatPercent;
app.config.globalProperties.formatDate = formatters.formatDate;
app.config.globalProperties.formatTime = formatters.formatTime;
app.config.globalProperties.formatDateTime = formatters.formatDateTime;

app.component('Iconsax', Iconsax);
app.component('AppButton', Button);
app.component('RefreshButton', RefreshButton);

app.use(pinia);
app.use(router);

app.config.errorHandler = (err, instance, info) => {
  console.error('[Vue Global Error Handler]:', err, info);
};

app.mount('#app');
