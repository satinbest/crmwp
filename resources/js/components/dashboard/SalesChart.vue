<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col justify-between relative overflow-hidden transition-all">
    <!-- Card Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 shadow-sm shadow-indigo-500/50"></span>
          <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">روند فروش</h3>
          <HelpButton help-key="dashboard_sales_chart" size="14" />
          <span v-if="periodLabel" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/40">
            {{ periodLabel }}
          </span>
        </div>
        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">نمودار پایش روزانه مبالغ فروش و تعداد سفارش‌ها</p>
      </div>

      <!-- Quick Summary Stats in Header -->
      <div v-if="!loading && chartPoints.length > 0" class="flex items-center gap-3">
        <div class="text-left">
          <div class="text-[11px] text-slate-400">مجموع فروش بازه</div>
          <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white">
            {{ formatCurrency(totalPeriodSales, currencySymbol) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Skeleton Loading State -->
    <div v-if="loading" class="h-64 flex flex-col justify-between py-4 animate-pulse">
      <div class="flex items-end gap-2 h-44 border-b border-slate-100 dark:border-slate-800 pb-2">
        <div
          v-for="i in 12"
          :key="i"
          class="flex-1 bg-slate-100 dark:bg-slate-800 rounded-t-lg"
          :style="{ height: `${20 + (i * 7) % 75}%` }"
        ></div>
      </div>
      <div class="flex justify-between text-xs text-slate-300 dark:text-slate-700 pt-2">
        <span class="w-12 h-3 bg-slate-100 dark:bg-slate-800 rounded"></span>
        <span class="w-12 h-3 bg-slate-100 dark:bg-slate-800 rounded"></span>
        <span class="w-12 h-3 bg-slate-100 dark:bg-slate-800 rounded"></span>
        <span class="w-12 h-3 bg-slate-100 dark:bg-slate-800 rounded"></span>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="chartPoints.length === 0"
      class="h-64 flex flex-col items-center justify-center text-center p-6 bg-slate-50/50 dark:bg-slate-850/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-800"
    >
      <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-500 flex items-center justify-center mb-3 shadow-inner">
        <Iconsax name="chart-2" size="24" />
      </div>
      <h4 class="text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-300">اطلاعات فروشی یافت نشد</h4>
      <p class="text-xs text-slate-400 mt-1 max-w-xs">
        در بازه زمانی انتخابی، هیچ سفارشی در ووکامرس به ثبت نرسیده است.
      </p>
    </div>

    <!-- Interactive SVG Chart Area -->
    <div
      v-else
      class="relative h-64 sm:h-72 w-full select-none"
      ref="chartContainer"
      @mousemove="onMouseMove"
      @mouseleave="hoverIndex = null"
    >
      <!-- SVG Drawing -->
      <svg
        class="w-full h-full overflow-visible"
        :viewBox="`0 0 ${svgWidth} ${svgHeight}`"
        preserveAspectRatio="none"
      >
        <defs>
          <!-- Area Gradient -->
          <linearGradient id="salesGradient" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#6366f1" stop-opacity="0.35" />
            <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0" />
          </linearGradient>

          <!-- Glow Filter -->
          <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#4f46e5" flood-opacity="0.3" />
          </filter>
        </defs>

        <!-- Horizontal Grid Lines -->
        <g class="grid-lines opacity-20 dark:opacity-10 stroke-slate-400" stroke-dasharray="4 4">
          <line
            v-for="(gridY, idx) in gridLinesY"
            :key="idx"
            :x1="padding.left"
            :y1="gridY"
            :x2="svgWidth - padding.right"
            :y2="gridY"
            stroke-width="1"
          />
        </g>

        <!-- Gradient Fill Path -->
        <path
          v-if="areaPathD"
          :d="areaPathD"
          fill="url(#salesGradient)"
        />

        <!-- Smooth Line Path -->
        <path
          v-if="linePathD"
          :d="linePathD"
          fill="none"
          stroke="#4f46e5"
          stroke-width="3"
          stroke-linecap="round"
          stroke-linejoin="round"
          filter="url(#glow)"
        />

        <!-- Active Hover Guide Line -->
        <line
          v-if="hoverPoint"
          :x1="hoverPoint.x"
          :y1="padding.top"
          :x2="hoverPoint.x"
          :y2="svgHeight - padding.bottom"
          stroke="#818cf8"
          stroke-width="1.5"
          stroke-dasharray="3 3"
        />

        <!-- Active Hover Dot -->
        <g v-if="hoverPoint">
          <circle
            :cx="hoverPoint.x"
            :cy="hoverPoint.y"
            r="6"
            fill="#4f46e5"
            stroke="#ffffff"
            stroke-width="2.5"
            class="transition-all duration-75"
          />
          <circle
            :cx="hoverPoint.x"
            :cy="hoverPoint.y"
            r="12"
            fill="#6366f1"
            fill-opacity="0.25"
            class="animate-ping"
          />
        </g>

        <!-- Data Point Circles (shown if <= 14 points) -->
        <g v-if="chartPoints.length <= 14 && !hoverPoint">
          <circle
            v-for="(pt, idx) in chartPoints"
            :key="idx"
            :cx="pt.x"
            :cy="pt.y"
            r="3.5"
            class="fill-indigo-600 dark:fill-indigo-400 stroke-white dark:stroke-slate-900"
            stroke-width="1.5"
          />
        </g>
      </svg>

      <!-- Y-Axis Labels (Left) -->
      <div class="absolute left-1 inset-y-0 flex flex-col justify-between pointer-events-none text-[10px] text-slate-400 font-medium pb-8 pt-2">
        <span>{{ formatNumber(maxSalesValue) }}</span>
        <span>{{ formatNumber(Math.round(maxSalesValue / 2)) }}</span>
        <span>۰</span>
      </div>

      <!-- X-Axis Labels (Bottom) -->
      <div class="absolute bottom-1 inset-x-0 flex justify-between pointer-events-none text-[11px] text-slate-400 px-4">
        <span>{{ formatJalaliShort(firstDate) }}</span>
        <span v-if="midDate">{{ formatJalaliShort(midDate) }}</span>
        <span>{{ formatJalaliShort(lastDate) }}</span>
      </div>

      <!-- Floating Interactive Tooltip -->
      <transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="hoverPoint"
          class="absolute z-tooltip pointer-events-none bg-slate-900/95 dark:bg-slate-800/95 text-white backdrop-blur-md px-3.5 py-2.5 rounded-xl shadow-xl border border-slate-700/60 text-xs min-w-[150px] transition-transform duration-75"
          :style="tooltipStyle"
        >
          <div class="flex items-center justify-between gap-3 text-[11px] text-slate-300 border-b border-slate-700/60 pb-1.5 mb-1.5">
            <span class="font-bold">{{ formatJalaliText(hoverPoint.date) }}</span>
            <span class="text-indigo-400 font-semibold">{{ toPersianDigits(hoverPoint.orders) }} سفارش</span>
          </div>
          <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] text-slate-400">مبلغ فروش:</span>
            <span class="font-black text-emerald-400 dir-ltr text-right">
              {{ formatCurrency(hoverPoint.sales, currencySymbol) }}
            </span>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import HelpButton from '@/components/ui/HelpButton.vue';
import { formatNumber, formatCurrency, toPersianDigits, formatDate } from '@/utils/formatters';

const props = defineProps({
  data: {
    type: Array,
    default: () => [],
  },
  currencySymbol: {
    type: String,
    default: 'تومان',
  },
  periodLabel: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const chartContainer = ref(null);
const hoverIndex = ref(null);

const svgWidth = 600;
const svgHeight = 220;
const padding = { top: 20, right: 25, bottom: 35, left: 35 };

const totalPeriodSales = computed(() => {
  return props.data.reduce((sum, item) => sum + (Number(item.sales) || 0), 0);
});

const maxSalesValue = computed(() => {
  if (!props.data || props.data.length === 0) return 1000;
  const max = Math.max(...props.data.map(d => Number(d.sales) || 0));
  return max > 0 ? max * 1.15 : 1000;
});

const chartPoints = computed(() => {
  if (!props.data || props.data.length === 0) return [];

  const count = props.data.length;
  const usableWidth = svgWidth - padding.left - padding.right;
  const usableHeight = svgHeight - padding.top - padding.bottom;
  const maxVal = maxSalesValue.value;

  return props.data.map((item, index) => {
    const x = count > 1 ? padding.left + (index / (count - 1)) * usableWidth : svgWidth / 2;
    const yVal = Number(item.sales) || 0;
    const y = svgHeight - padding.bottom - (yVal / maxVal) * usableHeight;

    return {
      x,
      y,
      sales: yVal,
      orders: Number(item.orders) || 0,
      date: item.date,
    };
  });
});

const gridLinesY = computed(() => {
  const usableHeight = svgHeight - padding.top - padding.bottom;
  return [
    padding.top,
    padding.top + usableHeight / 2,
    svgHeight - padding.bottom,
  ];
});

// Build smooth curved path (Cardinal / Bezier spline)
const linePathD = computed(() => {
  const pts = chartPoints.value;
  if (pts.length === 0) return '';
  if (pts.length === 1) return `M ${pts[0].x} ${pts[0].y}`;

  let d = `M ${pts[0].x},${pts[0].y}`;
  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = pts[i === 0 ? 0 : i - 1];
    const p1 = pts[i];
    const p2 = pts[i + 1];
    const p3 = pts[i + 2] || p2;

    const cp1x = p1.x + (p2.x - p0.x) / 6;
    const cp1y = p1.y + (p2.y - p0.y) / 6;
    const cp2x = p2.x - (p3.x - p1.x) / 6;
    const cp2y = p2.y - (p3.y - p1.y) / 6;

    d += ` C ${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`;
  }
  return d;
});

const areaPathD = computed(() => {
  const pts = chartPoints.value;
  if (pts.length < 2) return '';
  const lineD = linePathD.value;
  const bottomY = svgHeight - padding.bottom;
  const lastX = pts[pts.length - 1].x;
  const firstX = pts[0].x;

  return `${lineD} L ${lastX},${bottomY} L ${firstX},${bottomY} Z`;
});

const firstDate = computed(() => props.data[0]?.date || '');
const lastDate = computed(() => props.data[props.data.length - 1]?.date || '');
const midDate = computed(() => {
  if (props.data.length < 3) return '';
  const midIdx = Math.floor(props.data.length / 2);
  return props.data[midIdx]?.date || '';
});

const hoverPoint = computed(() => {
  if (hoverIndex.value === null) return null;
  return chartPoints.value[hoverIndex.value] || null;
});

const onMouseMove = (event) => {
  if (!chartContainer.value || chartPoints.value.length === 0) return;
  const rect = chartContainer.value.getBoundingClientRect();
  const mouseX = event.clientX - rect.left;
  const ratio = mouseX / rect.width;
  const targetX = ratio * svgWidth;

  // Find nearest data point
  let nearestIdx = 0;
  let minDist = Infinity;
  chartPoints.value.forEach((pt, idx) => {
    const dist = Math.abs(pt.x - targetX);
    if (dist < minDist) {
      minDist = dist;
      nearestIdx = idx;
    }
  });

  hoverIndex.value = nearestIdx;
};

const tooltipStyle = computed(() => {
  if (!hoverPoint.value || !chartContainer.value) return {};
  const rect = chartContainer.value.getBoundingClientRect();
  const normX = (hoverPoint.value.x / svgWidth) * rect.width;
  const normY = (hoverPoint.value.y / svgHeight) * rect.height;

  // Prevent tooltip from overflowing edges
  const isLeftHalf = normX < rect.width / 2;
  const leftPos = isLeftHalf ? normX + 15 : normX - 165;
  const topPos = Math.max(10, normY - 60);

  return {
    left: `${leftPos}px`,
    top: `${topPos}px`,
  };
});

const formatJalaliShort = (dateStr) => {
  if (!dateStr) return '';
  return formatDate(dateStr, 'numeric');
};

const formatJalaliText = (dateStr) => {
  if (!dateStr) return '';
  return formatDate(dateStr, 'text');
};
</script>
