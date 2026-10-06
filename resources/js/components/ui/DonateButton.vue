<template>
  <div class="relative inline-flex items-center">
    <!-- Donate Trigger Button (Sleek Header Pill) -->
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

    <!-- Professional Modern Bank Card Donate Modal -->
    <teleport to="body">
      <transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="isOpen"
          class="fixed inset-0 z-modal-layer flex items-center justify-center p-3 sm:p-5 bg-slate-950/60 backdrop-blur-xs overflow-y-auto"
          @click.self="closeModal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="donate-modal-title"
          aria-describedby="donate-modal-desc"
        >
          <div
            class="relative w-full max-w-[490px] bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl p-5 sm:p-7 shadow-2xl shadow-slate-950/20 text-right transform transition-all my-auto"
            @click.stop
          >
            <!-- Close Button -->
            <button
              ref="closeButtonRef"
              type="button"
              @click="closeModal"
              class="absolute top-4 left-4 p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer focus-visible:ring-2 focus-visible:ring-indigo-500"
              aria-label="بستن پنجره"
              title="بستن"
            >
              <Iconsax name="close" size="18" />
            </button>

            <!-- Modal Header -->
            <div class="flex flex-col items-center text-center mb-5 pt-1">
              <!-- Premium Heart Icon Container -->
              <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200/70 dark:border-rose-900/50 text-rose-500 dark:text-rose-400 flex items-center justify-center shadow-xs mb-3 transition-transform duration-300 hover:scale-105">
                <Iconsax name="heart" size="24" />
              </div>

              <h3
                id="donate-modal-title"
                class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white tracking-tight"
              >
                حمایت از توسعه پروژه
              </h3>

              <p
                id="donate-modal-desc"
                class="text-xs sm:text-[13px] text-slate-500 dark:text-slate-400 leading-relaxed mt-1.5 max-w-[380px]"
              >
                {{ donationConfig.description }}
              </p>
            </div>

            <!-- Central Digital Bank Card -->
            <div class="w-full">
              <div
                class="relative w-full aspect-[1.58/1] min-h-[195px] sm:min-h-[220px] rounded-2xl sm:rounded-3xl p-4.5 sm:p-6 overflow-hidden flex flex-col justify-between shadow-xl shadow-amber-500/15 dark:shadow-2xl dark:shadow-amber-500/10 border border-amber-300/80 dark:border-amber-400/50 select-none transition-transform duration-300 hover:scale-[1.01]"
                style="background: linear-gradient(135deg, #FEF7CD 0%, #FEE586 42%, #F6C844 100%);"
              >
                <!-- Card Sheen Overlays & Luxury Watermarks -->
                <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/25 to-white/45 pointer-events-none rounded-2xl sm:rounded-3xl"></div>
                <div class="absolute inset-x-0 top-0 h-px bg-white/70 pointer-events-none"></div>

                <!-- Subtle Decorative Rings Watermark -->
                <svg
                  class="absolute -left-12 -bottom-12 w-48 h-48 text-amber-950/6 pointer-events-none"
                  viewBox="0 0 200 200"
                  fill="none"
                  stroke="currentColor"
                >
                  <circle cx="100" cy="100" r="85" stroke-width="2" />
                  <circle cx="100" cy="100" r="65" stroke-width="1.5" />
                  <circle cx="100" cy="100" r="45" stroke-width="1.5" />
                  <circle cx="100" cy="100" r="25" stroke-width="1" />
                </svg>

                <!-- Card Top Row: EMV Chip, NFC Waves, & Badge -->
                <div class="relative z-10 flex items-center justify-between">
                  <!-- EMV Chip & NFC Waves -->
                  <div class="flex items-center gap-2 sm:gap-2.5">
                    <!-- High Fidelity EMV Banking Chip -->
                    <div class="relative w-11 h-8 sm:w-12 sm:h-8.5 rounded-md bg-gradient-to-br from-amber-200 via-amber-300 to-amber-500 p-[2px] shadow-xs border border-amber-600/35 overflow-hidden shrink-0">
                      <div class="w-full h-full rounded-[3px] border border-amber-700/35 grid grid-cols-3 grid-rows-3 relative bg-amber-300/40">
                        <div class="border-b border-r border-amber-700/25"></div>
                        <div class="border-b border-amber-700/25"></div>
                        <div class="border-b border-l border-amber-700/25"></div>
                        <div class="border-r border-amber-700/25"></div>
                        <div class="rounded-xs bg-amber-400/70 border border-amber-700/35"></div>
                        <div class="border-l border-amber-700/25"></div>
                        <div class="border-t border-r border-amber-700/25"></div>
                        <div class="border-t border-amber-700/25"></div>
                        <div class="border-t border-l border-amber-700/25"></div>
                      </div>
                    </div>

                    <!-- Contactless / NFC waves -->
                    <svg
                      class="w-4 h-4 sm:w-5 sm:h-5 text-amber-950/45 rotate-90"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2.2"
                      stroke-linecap="round"
                    >
                      <path d="M8.5 16.5a5 5 0 0 1 0-9" />
                      <path d="M12 19a8.5 8.5 0 0 1 0-14" />
                      <path d="M15.5 21.5a12 12 0 0 1 0-19" />
                    </svg>
                  </div>

                  <!-- Internal Card Icon & Minimalist Badge -->
                  <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-amber-950/10 backdrop-blur-xs border border-amber-900/10 shadow-2xs">
                    <Iconsax name="card" size="14" class="text-amber-950" />
                    <span class="text-[10px] font-bold text-amber-950 tracking-wider">CRMWP</span>
                  </div>
                </div>

                <!-- Card Middle Row: Card Number (High Contrast, Persian Digits, Standard Typography) -->
                <div class="relative z-10 my-auto py-1 sm:py-2">
                  <div class="text-[10px] sm:text-[11px] font-semibold text-slate-800/75 mb-1 sm:mb-1.5 flex items-center justify-between">
                    <span>شماره کارت بانکی</span>
                    <span class="text-[9px] font-bold tracking-widest text-slate-800/60 uppercase">IRAN SHETAB</span>
                  </div>
                  <div class="dir-ltr text-center sm:text-right">
                    <span class="text-[18px] sm:text-[22px] md:text-[24px] font-bold tracking-wider sm:tracking-widest text-slate-900 select-all leading-none inline-block whitespace-nowrap">
                      {{ formattedCardPersian }}
                    </span>
                  </div>
                </div>

                <!-- Card Bottom Row: Recipient Name & Support Label -->
                <div class="relative z-10 flex items-end justify-between pt-1">
                  <!-- Card Holder Name -->
                  <div>
                    <span class="block text-[10px] font-medium text-slate-800/70 leading-tight">
                      صاحب کارت
                    </span>
                    <span class="block text-xs sm:text-sm font-bold text-slate-900 mt-0.5 tracking-tight">
                      {{ donation.recipientName || donationConfig.recipientName }}
                    </span>
                  </div>

                  <!-- Support Badge -->
                  <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-amber-950/10 text-amber-950 text-[10px] sm:text-[11px] font-bold tracking-wider uppercase">
                      SUPPORT / DONATION
                    </span>
                  </div>
                </div>
              </div>

              <!-- Copy Card Number Button (Directly Beneath Bank Card) -->
              <button
                type="button"
                @click="copyCardNumber"
                class="w-full mt-3.5 py-3 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all duration-200 inline-flex items-center justify-center gap-2 cursor-pointer shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 select-none"
                :class="copied
                  ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/20 focus-visible:ring-emerald-500'
                  : 'bg-slate-900 hover:bg-slate-800 text-amber-300 dark:bg-amber-400 dark:hover:bg-amber-300 dark:text-slate-950 shadow-slate-900/10 focus-visible:ring-amber-500'"
                aria-label="کپی شماره کارت"
              >
                <Iconsax :name="copied ? 'copy-success' : 'copy'" size="18" />
                <span>{{ copied ? 'شماره کارت کپی شد' : 'کپی شماره کارت' }}</span>
              </button>
            </div>

            <!-- Contact & Social Details Outside the Card -->
            <div class="mt-4.5 space-y-2">
              <!-- Email Link -->
              <a
                :href="`mailto:${donationConfig.email}`"
                class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 transition-all text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 group"
                title="ارسال ایمیل به توسعه‌دهنده"
              >
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-lg bg-slate-200/70 dark:bg-slate-700/70 flex items-center justify-center text-slate-500 dark:text-slate-400 group-hover:text-indigo-500 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-950/50 transition-colors">
                    <Iconsax name="sms" size="16" />
                  </div>
                  <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">ایمیل ارتباطی</span>
                </div>
                <span class="dir-ltr text-xs font-medium text-slate-500 dark:text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                  {{ donationConfig.email }}
                </span>
              </a>

              <!-- GitHub Link -->
              <a
                :href="donationConfig.githubUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center justify-between p-2.5 sm:p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 transition-all text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 group"
                title="مشاهده مخزن پروژه در گیت‌هاب"
              >
                <div class="flex items-center gap-2.5">
                  <div class="w-7 h-7 rounded-lg bg-slate-200/70 dark:bg-slate-700/70 flex items-center justify-center text-slate-500 dark:text-slate-400 group-hover:text-indigo-500 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-950/50 transition-colors">
                    <Iconsax name="github" size="16" />
                  </div>
                  <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">مخزن گیت‌هاب</span>
                </div>
                <span class="dir-ltr text-xs font-medium text-slate-500 dark:text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                  {{ donationConfig.github }}
                </span>
              </a>
            </div>

            <!-- Modal Footer -->
            <div class="mt-4 pt-2 flex flex-col items-center gap-2.5">
              <p class="text-[11px] text-slate-400 dark:text-slate-500 text-center">
                این حمایت به توسعه و نگهداری رایگان پروژه کمک می‌کند.
              </p>

              <button
                type="button"
                @click="closeModal"
                class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-800 dark:hover:bg-slate-750 border border-slate-200/80 dark:border-slate-700/80 transition-all cursor-pointer focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="بستن"
              >
                بستن
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import apiClient from '@/api/client';
import { toPersianDigits } from '@/utils/formatters';
import { useNotificationStore } from '@/stores/notification';
import { donationConfig } from '@/config/donation';

const notificationStore = useNotificationStore();

const isOpen = ref(false);
const copied = ref(false);
const closeButtonRef = ref(null);
let copyTimeout = null;

// Initial state strictly seeded from local code configuration source of truth
const donation = ref({
  recipientName: donationConfig.recipientName,
  cardNumber: donationConfig.cardNumber,
  email: donationConfig.email,
  github: donationConfig.github,
  githubUrl: donationConfig.githubUrl,
  url: '',
});

// Format card number into 4-digit groups with Persian digits using standard project typography
const formattedCardPersian = computed(() => {
  const raw = donation.value.cardNumber || donationConfig.cardNumber;
  if (!raw) return '—';
  const parts = String(raw).match(/.{1,4}/g) || [raw];
  const spaced = parts.join('   ');
  return toPersianDigits(spaced);
});

// Optional background sync with backend endpoint if available
const syncDonateInfo = async () => {
  try {
    const res = await apiClient.get('/system/donate');
    if (res.data?.success && res.data?.data) {
      const data = res.data.data;
      if (data.card_number) {
        donation.value.cardNumber = data.card_number;
      }
      if (data.recipient_name) {
        donation.value.recipientName = data.recipient_name;
      }
      if (data.email) {
        donation.value.email = data.email;
      }
      if (data.github) {
        donation.value.github = data.github;
      }
      if (data.url) {
        donation.value.url = data.url;
      }
    }
  } catch (err) {
    // Keep local source of truth intact without any disruption
    console.debug('Using local donation source of truth', err);
  }
};

const copyCardNumber = async () => {
  const text = donation.value.cardNumber || donationConfig.cardNumber;
  if (!text) return;

  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
    } else {
      // Fallback for non-secure contexts or older browsers
      const textArea = document.createElement('textarea');
      textArea.value = text;
      textArea.style.position = 'fixed';
      textArea.style.left = '-9999px';
      textArea.style.top = '-9999px';
      textArea.setAttribute('readonly', '');
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
    notificationStore.error('امکان کپی شماره کارت وجود نداشت.', 'خطا');
  }
};

const openModal = () => {
  isOpen.value = true;
  nextTick(() => {
    closeButtonRef.value?.focus();
  });
  syncDonateInfo();
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
