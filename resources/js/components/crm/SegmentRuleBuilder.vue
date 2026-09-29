<template>
  <div class="space-y-4">
    <!-- Combinator Header -->
    <div class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-800">
      <div class="flex items-center gap-2">
        <span class="text-xs font-bold text-slate-700 dark:text-slate-300">منطق اعمال شرایط:</span>
        <select
          v-model="modelValue.combinator"
          class="text-xs font-semibold rounded-xl px-3 py-1.5 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 focus:ring-2 focus:ring-indigo-500"
        >
          <option value="AND">مطابقت با همه شروط (AND)</option>
          <option value="OR">مطابقت با حداقل یک شرط (OR)</option>
        </select>
      </div>

      <button
        type="button"
        @click="addRule"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-xs font-bold transition-colors"
      >
        <span>+ افزودن شرط</span>
      </button>
    </div>

    <!-- Rules List -->
    <div v-if="!modelValue.rules || modelValue.rules.length === 0" class="p-8 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl">
      هنوز هیچ شرطی برای این بخش‌بندی تعریف نشده است. با کلیک بر روی «افزودن شرط» قوانین فیلتر را تعیین نمایید.
    </div>

    <div v-else class="space-y-2.5">
      <div
        v-for="(rule, index) in modelValue.rules"
        :key="index"
        class="flex flex-wrap items-center gap-2.5 p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-xs shadow-xs"
      >
        <!-- Field -->
        <select
          v-model="rule.field"
          class="min-w-[150px] rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-indigo-500"
        >
          <optgroup label="🛒 معیارهای فروشگاهی ووکامرس">
            <option value="order_count">تعداد کل سفارش‌ها</option>
            <option value="total_spent">مجموع مبالغ خرید (تومان)</option>
            <option value="aov">میانگین ارزش سفارش (AOV)</option>
            <option value="city">شهر خریدار</option>
            <option value="role">نقش در وردپرس</option>
            <option value="last_order_date">تاریخ آخرین سفارش</option>
            <option value="registration_date">تاریخ عضویت</option>
          </optgroup>
          <optgroup label="👥 معیارهای اختصاصی CRM">
            <option value="tag">دارای برچسب</option>
            <option value="tasks_count">تعداد کل وظایف</option>
            <option value="open_tasks">تعداد وظایف باز</option>
            <option value="overdue_tasks">تعداد وظایف معوقه</option>
            <option value="has_notes">دارای یادداشت</option>
            <option value="last_activity">تاریخ آخرین فعالیت</option>
          </optgroup>
        </select>

        <!-- Operator -->
        <select
          v-model="rule.operator"
          class="min-w-[130px] rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-indigo-500"
        >
          <option value="equals">برابر با (=)</option>
          <option value="not_equals">مخالف با (≠)</option>
          <option value="greater_than">بزرگتر از (&gt;)</option>
          <option value="less_than">کوچکتر از (&lt;)</option>
          <option value="greater_equal">بزرگتر یا مساوی (≥)</option>
          <option value="less_equal">کوچکتر یا مساوی (≤)</option>
          <option value="contains">شامل عبارت</option>
          <option value="starts_with">شروع با</option>
          <option value="before">قبل از تاریخ</option>
          <option value="after">بعد از تاریخ</option>
          <option value="is_empty">خالی باشد</option>
          <option value="is_not_empty">خالی نباشد</option>
        </select>

        <!-- Value Input -->
        <div v-if="!['is_empty', 'is_not_empty'].includes(rule.operator)" class="flex-1 min-w-[150px]">
          <input
            v-if="['order_count', 'total_spent', 'aov', 'tasks_count', 'open_tasks', 'overdue_tasks'].includes(rule.field)"
            v-model.number="rule.value"
            type="number"
            placeholder="مقدار عددی..."
            class="w-full rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-indigo-500"
          />
          <input
            v-else-if="['last_order_date', 'registration_date', 'last_activity'].includes(rule.field)"
            v-model="rule.value"
            type="date"
            class="w-full rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-indigo-500"
          />
          <input
            v-else
            v-model="rule.value"
            type="text"
            placeholder="مقدار مورد نظر..."
            class="w-full rounded-xl px-3 py-2 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-indigo-500"
          />
        </div>

        <!-- Remove Rule Button -->
        <button
          type="button"
          @click="removeRule(index)"
          class="p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition-colors shrink-0"
          title="حذف شرط"
        >
          ✕
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ combinator: 'AND', rules: [] }),
  },
});

const emit = defineEmits(['update:modelValue']);

const addRule = () => {
  if (!props.modelValue.rules) {
    props.modelValue.rules = [];
  }
  props.modelValue.rules.push({
    field: 'order_count',
    operator: 'greater_than',
    value: 0,
  });
};

const removeRule = (index) => {
  props.modelValue.rules.splice(index, 1);
};
</script>
