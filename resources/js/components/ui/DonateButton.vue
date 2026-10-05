<template>
  <div class="relative inline-flex items-center">
    <!-- Donate Trigger Button (Sleek SaaS Minimalist Style) -->
    <button
      type="button"
      @click="openModal"
      class="group inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl border border-rose-200/80 dark:border-rose-900/50 bg-rose-50/60 dark:bg-rose-950/30 hover:bg-rose-100/70 dark:hover:bg-rose-900/50 text-rose-600 dark:text-rose-400 text-xs font-semibold transition-all shadow-2xs hover:shadow-xs focus-visible:ring-2 focus-visible:ring-rose-500 cursor-pointer"
      title="حمایت از توسعه پروژه"
      aria-label="حمایت از توسعه پروژه"
    >
      <Iconsax
        name="heart"
        size="16"
        class="text-rose-500 dark:text-rose-400 group-hover:scale-110 transition-transform duration-200"
      />
      <span class="hidden md:inline text-[11px] font-bold">
        حمایت
      </span>
    </button>

    <!-- Professional Modern Donate Modal -->
    <teleport to="body">
      <transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="isOpen"
          class="fixed inset-0 z-modal-layer flex items-center justify-center p-4 sm:p-6 bg-slate-950/50 backdrop-blur-xs select-none overflow-y-auto"
          @click.self="closeModal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="donate-modal-title"
          aria-describedby="donate-modal-desc"
        >
          <div
            class="relative w-full max-w-[480px] bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl p-6 sm:p-7 shadow-2xl shadow-slate-900/10 dark:shadow-black/50 text-right transform transition-all my-auto"
            @click.stop
          >
            <!-- Close Button -->
            <button
              type="button"
              @click="closeModal"
              class="absolute top-4 left-4 p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/80 transition-colors cursor-pointer focus-visible:ring-2 focus-visible:ring-indigo-500"
              aria-label="بستن"
              title="بستن"
            >
              <Iconsax name="close" size="18" />
            </button>

            <!-- Header Section -->
            <div class="flex flex-col items-center text-center pt-1 mb-5">
              <!-- Heart Icon Badge Container -->
              <div class="w-13 h-13 rounded-2xl bg-rose-50/90 dark:bg-rose-950/40 border border-rose-200/70 dark:border-rose-900/50 text-rose-500 dark:text-rose-400 flex items-center justify-center shadow-xs mb-3.5 transition-transform duration-300 hover:scale-105">
                <Iconsax name="heart" size="26" class="stroke-[1.6]" />
              </div>

              <!-- Title -->
              <h3
                id="donate-modal-title"
                class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight"
              >
                حمایت از توسعه پروژه
              </h3>

              <!-- Description -->
              <p
                id="donate-modal-desc"
                class="text-xs sm:text-[13px] text-slate-500 dark:text-slate-400 leading-relaxed mt-2 max-w-[380px]"
              >
                اگر این پروژه برای شما مفید بوده، می‌توانید با حمایت خود به توسعه و نگهداری آن کمک کنید.
              </p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="py-10 flex flex-col items-center justify-center gap-3">
              <div class="w-7 h-7 border-2 border-rose-500 border-t-transparent rounded-full animate-spin"></div>
              <span class="text-xs text-slate-400 font-medium">در حال دریافت اطلاعات...</span>
            </div>

            <!-- Main Content -->
            <div v-else class="space-y-4">
              <!-- Donation Information Card -->
              <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-850/60 border border-slate-200/80 dark:border-slate-800 space-y-4">
                <!-- Card Header -->
                <div class="flex items-center gap-2 pb-3 border-b border-slate-200/70 dark:border-slate-800/80">
                  <div class="w-6 h-6 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-500 dark:text-rose-400 flex items-center justify-center">
                    <Iconsax name="card" size="14" />
                  </div>
                  <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                    اطلاعات حمایت
                  </span>
                </div>

                <!-- Recipient Info -->
                <div class="flex items-center justify-between text-xs">
                  <span class="text-slate-400 dark:text-slate-500 font-medium">گیرنده</span>
                  <span class="font-bold text-slate-800 dark:text-slate-100">
                    {{ donateInfo.recipient_name || 'کمک مالی به حسین محمدپور' }}
                  </span>
                </div>

                <!-- Card Number with Persian Formatting & Copy Action -->
                <div v-if="donateInfo.has_card || donateInfo.card_number" class="space-y-1.5 pt-0.5">
                  <div class="flex items-center justify-between text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                    <span>شماره کارت</span>
                    <span
                      v-if="copied"
                      class="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1 transition-opacity duration-200"
                    >
                      <Iconsax name="tick-circle" size="13" />
                      کپی شد
                    </span>
                  </div>

                  <!-- Formatted Card Number Box -->
                  <div class="flex items-center justify-between gap-2.5 p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-750 shadow-2xs">
                    <div class="min-w-0 flex-1 text-center">
                      <span class="text-base sm:text-[17px] font-bold tracking-wider text-slate-900 dark:text-white select-all dir-ltr inline-block">
                        {{ formattedCardPersian }}
                      </span>
                    </div>

                    <!-- Copy Button -->
                    <button
                      type="button"
                      @click="copyCardNumber"
                      class="h-9 px-3 rounded-lg text-xs font-semibold transition-all inline-flex items-center justify-center gap-1.5 shrink-0 cursor-pointer focus-visible:ring-2 focus-visible:ring-offset-1 select-none"
                      :class="copied
                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800 focus-visible:ring-emerald-500'
                        : 'bg-slate-100 hover:bg-slate-200/80 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-750 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 focus-visible:ring-indigo-500'"
                      aria-label="کپی شماره کارت"
                      title="کپی شماره کارت"
                    >
                      <Iconsax :name="copied ? 'copy-success' : 'copy'" size="15" />
                      <span>{{ copied ? 'کپی شد' : 'کپی' }}</span>
                    </button>
                  </div>
                </div>

                <!-- Optional Online Donate Link if set in backend -->
                <div v-if="donateInfo.url" class="pt-1">
                  <a
                    :href="donateInfo.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-md shadow-indigo-600/20 transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-indigo-500"
                  >
                    <Iconsax name="heart" size="15" />
                    <span>پرداخت آنلاین مستقیم</span>
                  </a>
                </div>
              </div>

              <!-- Contact & Social Details -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                <!-- Email -->
                <a
                  :href="`mailto:${donateInfo.email || 'info@hosseinmohammadpour.ir'}`"
                  class="group flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 hover:bg-slate-100/90 dark:bg-slate-850/60 dark:hover:bg-slate-800/80 border border-slate-200/70 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-all text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400"
                  title="ارسال ایمیل"
                >
                  <div class="flex items-center gap-2">
                    <Iconsax name="sms" size="15" class="text-slate-400 group-hover:text-indigo-500 transition-colors" />
                    <span class="text-slate-500 dark:text-slate-400 text-[11px] font-medium">ایمیل</span>
                  </div>
                  <span class="dir-ltr text-[11px] font-medium truncate max-w-[140px] text-slate-700 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                    {{ donateInfo.email || 'info@hosseinmohammadpour.ir' }}
                  </span>
                </a>

                <!-- GitHub -->
                <a
                  :href="`https://github.com/${donateInfo.github || 'satinbest/crmwp'}`"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 hover:bg-slate-100/90 dark:bg-slate-850/60 dark:hover:bg-slate-800/80 border border-slate-200/70 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-all text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400"
                  title="مشاهده گیت‌هاب پروژه"
                >
                  <div class="flex items-center gap-2">
                    <Iconsax name="github" size="15" class="text-slate-400 group-hover:text-indigo-500 transition-colors" />
                    <span class="text-slate-500 dark:text-slate-400 text-[11px] font-medium">GitHub</span>
                  </div>
                  <span class="dir-ltr text-[11px] font-medium truncate max-w-[140px] text-slate-700 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                    {{ donateInfo.github || 'satinbest/crmwp' }}
                  </span>
                </a>
              </div>

              <!-- Footer Section -->
              <div class="pt-2 flex flex-col items-center gap-2.5">
                <p class="text-[11px] text-slate-400 dark:text-slate-500 text-center">
                  این حمایت به توسعه و نگهداری پروژه کمک می‌کند.
                </p>

                <button
                  type="button"
                  @click="closeModal"
                  class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100/80 hover:bg-slate-200/80 dark:bg-slate-800 dark:hover:bg-slate-750 border border-slate-200/80 dark:border-slate-700/80 transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-indigo-500"
                  aria-label="بستن"
                >
                  بستن
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import apiClient from '@/api/client';
import { toPersianDigits } from '@/utils/formatters';
import { useNotificationStore } from '@/stores/notification';

const notificationStore = useNotificationStore();

const isOpen = ref(false);
const loading = ref(false);
const copied = ref(false);
let copyTimeout = null;

const donateInfo = ref({
  recipient_name: 'کمک مالی به حسین محمدپور',
  card_number: '',
  email: 'info@hosseinmohammadpour.ir',
  github: 'satinbest/crmwp',
  url: '',
  has_card: false,
});

// Format card number into 4-digit groups with Persian digits
const formattedCardPersian = computed(() => {
  const raw = donateInfo.value.card_number || '';
  if (!raw) return '—';
  const parts = raw.match(/.{1,4}/g) || [raw];
  const spaced = parts.join('   ');
  return toPersianDigits(spaced);
});

// Fetch donate configuration from backend source of truth
const fetchDonateInfo = async () => {
  if (donateInfo.value.card_number) {
    return; // Already loaded
  }
  loading.value = true;
  try {
    const res = await apiClient.get('/system/donate');
    if (res.data?.success && res.data?.data) {
      donateInfo.value = {
        ...donateInfo.value,
        ...res.data.data,
      };
    }
  } catch (err) {
    console.debug('Failed to load donate info from backend', err);
  } finally {
    loading.value = false;
  }
};

const copyCardNumber = async () => {
  const text = donateInfo.value.card_number;
  if (!text) return;

  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
    } else {
      // Fallback for non-secure context or older browsers
      const textArea = document.createElement('textarea');
      textArea.value = text;
      textArea.style.position = 'fixed';
      textArea.style.opacity = '0';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
    }

    copied.value = true;
    notificationStore.success('شماره کارت با موفقیت کپی شد.', 'کپی شد');

    if (copyTimeout) clearTimeout(copyTimeout);
    copyTimeout = setTimeout(() => {
      copied.value = false;
    }, 2500);
  } catch (err) {
    console.error('Failed to copy card number', err);
  }
};

const openModal = () => {
  isOpen.value = true;
  fetchDonateInfo();
};

const closeModal = () => {
  isOpen.value = false;
  copied.value = false;
};

const handleKeyDown = (e) => {
  if (e.key === 'Escape' && isOpen.value) {
    closeModal();
  }
};

// Manage body overflow during modal open state
watch(isOpen, (val) => {
  if (val) {
    document.body.classList.add('overflow-hidden');
  } else {
    document.body.classList.remove('overflow-hidden');
  }
});

onMounted(() => {
  document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyDown);
  document.body.classList.remove('overflow-hidden');
  if (copyTimeout) clearTimeout(copyTimeout);
});
</script>
