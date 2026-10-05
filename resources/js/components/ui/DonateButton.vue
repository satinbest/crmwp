<template>
  <div class="relative inline-flex items-center">
    <!-- Donate Trigger Button (Sleek SaaS Minimalist Style) -->
    <button
      type="button"
      @click="openModal"
      class="group inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl border border-rose-200/70 dark:border-rose-900/60 bg-rose-50/50 dark:bg-rose-950/30 hover:bg-rose-100/70 dark:hover:bg-rose-900/50 text-rose-600 dark:text-rose-400 text-xs font-bold transition-all shadow-xs focus:outline-none focus:ring-2 focus:ring-rose-500/20 cursor-pointer"
      title="حمایت از توسعه پروژه"
      aria-label="حمایت از توسعه پروژه"
    >
      <Iconsax
        name="heart"
        size="17"
        class="text-rose-500 group-hover:scale-110 transition-transform duration-200"
      />
      <span class="hidden md:inline text-[11px] font-bold">
        حمایت
      </span>
    </button>

    <!-- Professional Clean Modal -->
    <teleport to="body">
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="isOpen"
          class="fixed inset-0 z-modal-layer flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs select-none"
          @click.self="closeModal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="donate-modal-title"
        >
          <div
            class="w-full max-w-md bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-3xl p-6 sm:p-7 shadow-2xl space-y-5 transform transition-all text-right"
            @click.stop
          >
            <!-- Header with Heart Badge -->
            <div class="flex items-center gap-3.5 pb-1 border-b border-slate-100 dark:border-slate-800/80">
              <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/80 border border-rose-200/80 dark:border-rose-900/80 text-rose-500 flex items-center justify-center shadow-inner shrink-0">
                <Iconsax name="heart" size="24" class="animate-pulse" />
              </div>
              <div>
                <h3 id="donate-modal-title" class="text-base sm:text-lg font-black text-slate-900 dark:text-white">
                  حمایت از توسعه پروژه
                </h3>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                  توسعه و نگهداری مستقل سامانه مدیریت ووکامرس
                </p>
              </div>
            </div>

            <!-- Polite Description -->
            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
              اگر این پروژه برای شما مفید بوده است، می‌توانید با حمایت مالی به توسعه و نگهداری آن کمک کنید.
            </p>

            <!-- Loading State -->
            <div v-if="loading" class="py-6 flex flex-col items-center justify-center gap-2">
              <div class="w-7 h-7 border-2 border-rose-500 border-t-transparent rounded-full animate-spin"></div>
              <span class="text-xs text-slate-400">در حال دریافت اطلاعات...</span>
            </div>

            <!-- Loaded Content -->
            <div v-else class="space-y-3.5">
              <!-- Recipient & Bank Card Box -->
              <div class="p-4 rounded-2xl bg-gradient-to-br from-rose-50/70 via-rose-50/30 to-amber-50/40 dark:from-rose-950/30 dark:via-slate-850 dark:to-slate-850 border border-rose-200/70 dark:border-rose-900/50 space-y-3">
                <!-- Recipient Name -->
                <div class="flex items-center justify-between text-xs">
                  <span class="text-slate-500 dark:text-slate-400 font-medium">گیرنده:</span>
                  <span class="font-black text-slate-900 dark:text-white">
                    {{ donateInfo.recipient_name || 'کمک مالی به حسین محمدپور' }}
                  </span>
                </div>

                <!-- Card Number with Partitioning -->
                <div v-if="donateInfo.has_card || donateInfo.card_number" class="pt-1">
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 mb-1.5 flex items-center justify-between">
                    <span>شماره کارت بانکی:</span>
                    <span v-if="copied" class="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1 text-[11px] animate-fade-in">
                      <Iconsax name="tick-circle" size="14" />
                      شماره کارت کپی شد
                    </span>
                  </div>

                  <!-- Formatted Card Display -->
                  <div class="flex items-center justify-between p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                    <span class="text-base sm:text-lg font-black tracking-widest text-slate-900 dark:text-white select-all font-mono-none dir-ltr text-center flex-1">
                      {{ formattedCardPersian }}
                    </span>

                    <button
                      type="button"
                      @click="copyCardNumber"
                      class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shrink-0 cursor-pointer"
                      :class="copied
                        ? 'bg-emerald-500 text-white shadow-xs'
                        : 'bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:hover:bg-rose-900/80 dark:text-rose-300'"
                      title="کپی شماره کارت"
                      aria-label="کپی شماره کارت"
                    >
                      <Iconsax :name="copied ? 'tick-circle' : 'copy'" size="15" />
                      <span>{{ copied ? 'کپی شد' : 'کپی' }}</span>
                    </button>
                  </div>
                </div>

                <!-- Optional External Donate Link if set -->
                <div v-if="donateInfo.url" class="pt-1">
                  <a
                    :href="donateInfo.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-700 hover:to-pink-700 text-white font-bold text-xs shadow-md shadow-rose-600/20 transition-all cursor-pointer"
                  >
                    <Iconsax name="heart" size="16" />
                    <span>پرداخت آنلاین مستقیم</span>
                  </a>
                </div>
              </div>

              <!-- Contact & Social Details -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                <!-- Email -->
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-200/70 dark:border-slate-800 flex items-center justify-between">
                  <span class="text-slate-400">ایمیل:</span>
                  <a
                    :href="`mailto:${donateInfo.email || 'info@hosseinmohammadpour.ir'}`"
                    class="font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 dir-ltr text-xs transition-colors"
                  >
                    {{ donateInfo.email || 'info@hosseinmohammadpour.ir' }}
                  </a>
                </div>

                <!-- GitHub -->
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-200/70 dark:border-slate-800 flex items-center justify-between">
                  <span class="text-slate-400">GitHub:</span>
                  <a
                    :href="`https://github.com/${donateInfo.github || 'satinbest/crmwp'}`"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 dir-ltr text-xs transition-colors"
                  >
                    {{ donateInfo.github || 'satinbest/crmwp' }}
                  </a>
                </div>
              </div>
            </div>

            <!-- Footer Action -->
            <div class="pt-2 flex items-center justify-end">
              <button
                type="button"
                @click="closeModal"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              >
                شاید بعداً
              </button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import apiClient from '@/api/client';
import { toPersianDigits } from '@/utils/formatters';

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

// Format card number with spaces for Persian UI display
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
    // Graceful fallback without crashing
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
      // Fallback for non-https or older browsers
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

onMounted(() => {
  document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyDown);
  if (copyTimeout) clearTimeout(copyTimeout);
});
</script>
