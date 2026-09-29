<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
          <span>اتوماسیون و گردش‌کار هوشمند</span>
          <span class="text-xs bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-bold px-2 py-0.5 rounded-full border border-indigo-200/50 dark:border-indigo-800/50">
            فاز ۱۵
          </span>
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
          تعریف سناریوهای خودکار بر اساس رویدادهای ووکامرس، شروط پیشرفته و اقدامات بلادرنگ CRM
        </p>
      </div>

      <div class="flex items-center gap-2">
        <RefreshButton
          @click="fetchAutomations"
          :loading="loading"
          label="به‌روزرسانی"
          title="به‌روزرسانی داده‌ها"
        />

        <button
          v-if="authStore.hasPermission('automations.create')"
          @click="openCreateWizard"
          class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/20"
        >
          <Iconsax name="add" size="18" />
          <span>ایجاد اتوماسیون جدید</span>
        </button>
      </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-slate-400">کل اتوماسیون‌ها</span>
          <span class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
            <Iconsax name="activity" size="18" />
          </span>
        </div>
        <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">
          {{ toPersianDigits(automations.length) }}
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-slate-400">فعال در چرخه کاری</span>
          <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
            <Iconsax name="check" size="18" />
          </span>
        </div>
        <div class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">
          {{ toPersianDigits(activeCount) }}
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-slate-400">مجموع اجراهای موفق</span>
          <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
            <Iconsax name="flash" size="18" />
          </span>
        </div>
        <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">
          {{ formatNumber(totalRunsCount) }}
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-slate-400">فروشگاه فعال</span>
          <span class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
            <Iconsax name="shop" size="18" />
          </span>
        </div>
        <div class="mt-2 text-sm font-bold text-slate-900 dark:text-white truncate">
          {{ storeContext.currentStoreName }}
        </div>
      </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
      <div class="w-full md:w-80 relative">
        <Iconsax name="search" size="16" class="absolute right-3.5 top-3 text-slate-400" />
        <input
          v-model="filters.search"
          @input="debounceFetch"
          type="text"
          placeholder="جستجو بر اساس نام یا توضیح اتوماسیون..."
          class="w-full pr-10 pl-4 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 rounded-xl text-xs text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <div class="flex items-center gap-3 w-full md:w-auto">
        <select
          v-model="filters.status"
          @change="fetchAutomations"
          class="px-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="all">همه وضعیت‌ها</option>
          <option value="active">فقط فعال</option>
          <option value="inactive">غیرفعال</option>
          <option value="draft">پیش‌نویس</option>
        </select>

        <select
          v-model="filters.trigger_type"
          @change="fetchAutomations"
          class="px-3 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="">همه تریگرها</option>
          <option value="order.created">ثبت سفارش جدید</option>
          <option value="order.status_changed">تغییر وضعیت سفارش</option>
          <option value="inventory.low_stock">کسری موجودی کالا</option>
          <option value="task.overdue">سررسید وظیفه</option>
          <option value="scheduled">زمان‌بندی‌شده دوره‌ای</option>
        </select>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="h-28 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl animate-pulse"></div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="automations.length === 0"
      class="bg-white dark:bg-slate-900 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center"
    >
      <div class="w-16 h-16 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-4">
        <Iconsax name="flash" size="32" />
      </div>
      <h3 class="text-base font-bold text-slate-900 dark:text-white">هنوز هیچ اتوماسیونی ثبت نشده است</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mt-2">
        با موتور اتوماسیون می‌توانید وظایف تکراری، ارسال اعلان‌ها و برچسب‌گذاری مشتریان را به صورت خودکار برنامه‌ریزی کنید.
      </p>
      <button
        v-if="authStore.hasPermission('automations.create')"
        @click="openCreateWizard"
        class="mt-5 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold inline-flex items-center gap-2 shadow-md shadow-indigo-600/20"
      >
        <Iconsax name="add" size="18" />
        <span>ایجاد اولین اتوماسیون</span>
      </button>
    </div>

    <!-- Automations List -->
    <div v-else class="space-y-4">
      <div
        v-for="auto in automations"
        :key="auto.id"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm hover:border-indigo-500/30 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4"
      >
        <div class="space-y-2 min-w-0 flex-1">
          <div class="flex items-center gap-3 flex-wrap">
            <!-- Active Toggle -->
            <button
              v-if="authStore.hasPermission('automations.enable') || authStore.hasPermission('automations.disable')"
              @click="toggleStatus(auto)"
              :disabled="togglingId === auto.id"
              class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
              :class="auto.status === 'active' ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700'"
              title="تغییر وضعیت فعال/غیرفعال"
            >
              <span
                class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                :class="auto.status === 'active' ? '-translate-x-4' : 'translate-x-0'"
              />
            </button>

            <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">
              {{ auto.name }}
            </h3>

            <!-- Trigger Badge -->
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200/50 dark:border-indigo-800/50">
              <Iconsax name="flash" size="12" />
              <span>{{ getTriggerLabel(auto.trigger_type) }}</span>
            </span>

            <!-- Store Badge -->
            <span v-if="auto.store_name" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
              <Iconsax name="shop" size="11" />
              <span>{{ auto.store_name }}</span>
            </span>
          </div>

          <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-1">
            {{ auto.description || 'بدون توضیحات' }}
          </p>

          <!-- Conditions & Actions Summary -->
          <div class="flex items-center gap-2 flex-wrap pt-1">
            <span class="text-[11px] text-slate-400 font-medium">اقدامات:</span>
            <span
              v-for="(act, idx) in auto.actions"
              :key="idx"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-50 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60"
            >
              <Iconsax name="tick-circle" size="11" class="text-emerald-500" />
              <span>{{ getActionLabel(act.type || act.action) }}</span>
            </span>
          </div>
        </div>

        <!-- Meta & Action Buttons -->
        <div class="flex items-center gap-2 flex-wrap md:flex-nowrap shrink-0 border-t md:border-t-0 pt-3 md:pt-0 border-slate-100 dark:border-slate-800/60">
          <div class="text-left ml-3 hidden sm:block">
            <div class="text-[11px] text-slate-600 dark:text-slate-300 font-bold">
              {{ toPersianDigits(auto.run_count) }} اجرا
            </div>
            <div class="text-[10px] text-slate-400">
              {{ auto.last_run_at ? formatDate(auto.last_run_at) : 'بدون اجرا' }}
            </div>
          </div>

          <!-- Dry Run Test -->
          <button
            @click="openDryRunModal(auto)"
            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5"
            title="تست و شبیه‌سازی شروط بدون اجرای واقعی"
          >
            <Iconsax name="play" size="14" />
            <span>تست</span>
          </button>

          <!-- Manual Run -->
          <button
            v-if="authStore.hasPermission('automations.run')"
            @click="confirmManualRun(auto)"
            class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:hover:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5"
            title="اجرای فوری و دستی"
          >
            <Iconsax name="flash" size="14" />
            <span>اجرا</span>
          </button>

          <!-- History / Runs Link -->
          <router-link
            v-if="authStore.hasPermission('automations.view_runs')"
            :to="`/automations/${auto.id}/runs`"
            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5"
            title="مشاهده تاریخچه و سوابق اجرا"
          >
            <Iconsax name="clock" size="14" />
            <span>تاریخچه</span>
          </router-link>

          <!-- Edit -->
          <button
            v-if="authStore.hasPermission('automations.update')"
            @click="openEditWizard(auto)"
            class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors"
            title="ویرایش اتوماسیون"
          >
            <Iconsax name="edit" size="16" />
          </button>

          <!-- Delete -->
          <button
            v-if="authStore.hasPermission('automations.delete')"
            @click="confirmDelete(auto)"
            class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-xl transition-colors"
            title="حذف اتوماسیون"
          >
            <Iconsax name="trash" size="16" />
          </button>
        </div>
      </div>
    </div>

    <!-- Wizard / Builder Modal -->
    <div
      v-if="isWizardOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div>
            <h2 class="text-base font-extrabold text-slate-900 dark:text-white">
              {{ editingId ? 'ویرایش اتوماسیون' : 'ایجاد اتوماسیون جدید' }}
            </h2>
            <div class="text-xs text-slate-400 mt-0.5">
              مرحله {{ wizardStep }} از ۴: {{ stepTitle }}
            </div>
          </div>
          <button
            @click="isWizardOpen = false"
            class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <!-- Wizard Progress Bar -->
        <div class="w-full bg-slate-100 dark:bg-slate-800 h-1">
          <div
            class="bg-indigo-600 h-1 transition-all duration-300"
            :style="{ width: `${(wizardStep / 4) * 100}%` }"
          ></div>
        </div>

        <!-- Modal Body Content -->
        <div class="p-6 overflow-y-auto flex-1 space-y-6">
          <!-- Step 1: Trigger & Basic Info -->
          <div v-if="wizardStep === 1" class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">نام اتوماسیون *</label>
              <input
                v-model="wizardForm.name"
                type="text"
                placeholder="مثلاً: ارتقای مشتریان پرخرید به VIP"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">توضیحات (اختیاری)</label>
              <textarea
                v-model="wizardForm.description"
                rows="2"
                placeholder="شرح عملکرد این سناریو برای همکاران..."
                class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              ></textarea>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1.5">رویداد محرک (Trigger) *</label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto p-1">
                <div
                  v-for="trig in allTriggers"
                  :key="trig.type"
                  @click="wizardForm.trigger_type = trig.type"
                  class="p-3 border rounded-xl cursor-pointer transition-all flex items-center justify-between"
                  :class="wizardForm.trigger_type === trig.type ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-bold' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'"
                >
                  <div class="flex items-center gap-2">
                    <Iconsax name="flash" size="16" :class="wizardForm.trigger_type === trig.type ? 'text-indigo-600' : 'text-slate-400'" />
                    <span class="text-xs">{{ trig.label }}</span>
                  </div>
                  <span class="text-[10px] text-slate-400 font-mono">{{ trig.type }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Step 2: Conditions Builder -->
          <div v-if="wizardStep === 2" class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">شروط منطقی اجرای اقدامات</h4>
                <p class="text-[11px] text-slate-400">اقدامات فقط در صورتی که این شروط محقق شوند اجرا خواهند شد.</p>
              </div>

              <!-- AND / OR Toggle -->
              <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl">
                <button
                  type="button"
                  @click="wizardForm.conditions.operator = 'AND'"
                  class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all"
                  :class="wizardForm.conditions.operator === 'AND' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500'"
                >
                  همه (AND)
                </button>
                <button
                  type="button"
                  @click="wizardForm.conditions.operator = 'OR'"
                  class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all"
                  :class="wizardForm.conditions.operator === 'OR' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-slate-500'"
                >
                  یا (OR)
                </button>
              </div>
            </div>

            <div class="space-y-2">
              <div
                v-for="(cond, idx) in wizardForm.conditions.conditions"
                :key="idx"
                class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-700/60"
              >
                <!-- Field -->
                <select
                  v-model="cond.field"
                  class="flex-1 px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium text-slate-800 dark:text-slate-200"
                >
                  <option value="order.total">مبلغ کل سفارش (order.total)</option>
                  <option value="order.status">وضعیت سفارش (order.status)</option>
                  <option value="product.stock_quantity">موجودی انبار (product.stock_quantity)</option>
                  <option value="customer.order_count">تعداد سفارش مشتری (customer.order_count)</option>
                  <option value="customer.total_spent">مجموع خرید مشتری (customer.total_spent)</option>
                  <option value="task.status">وضعیت وظیفه (task.status)</option>
                </select>

                <!-- Operator -->
                <select
                  v-model="cond.operator"
                  class="w-36 px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium text-slate-800 dark:text-slate-200"
                >
                  <option value="greater_than">بزرگتر از (&gt;)</option>
                  <option value="greater_or_equal">بزرگتر یا مساوی (&gt;=)</option>
                  <option value="less_than">کوچکتر از (&lt;)</option>
                  <option value="less_or_equal">کوچکتر یا مساوی (&lt;=)</option>
                  <option value="equals">برابر با (==)</option>
                  <option value="not_equals">نابرابر (!=)</option>
                  <option value="contains">شامل باشد</option>
                  <option value="in">عضو لیست باشد</option>
                </select>

                <!-- Value -->
                <input
                  v-model="cond.value"
                  type="text"
                  placeholder="مقدار..."
                  class="w-36 px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200"
                />

                <button
                  type="button"
                  @click="removeCondition(idx)"
                  class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg"
                  title="حذف شرط"
                >
                  <Iconsax name="close" size="14" />
                </button>
              </div>

              <button
                type="button"
                @click="addCondition"
                class="px-3 py-1.5 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 flex items-center gap-1.5 w-full justify-center transition-colors"
              >
                <Iconsax name="add" size="14" />
                <span>افزودن شرط جدید</span>
              </button>
            </div>
          </div>

          <!-- Step 3: Actions Builder -->
          <div v-if="wizardStep === 3" class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">اقدامات خودکار (Actions)</h4>
                <p class="text-[11px] text-slate-400">پس از تحقق شروط، این عملیات به ترتیب اجرا خواهند شد.</p>
              </div>
            </div>

            <!-- Available Variable Tags for Easy Click-to-Insert -->
            <div class="bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 rounded-xl p-3">
              <span class="text-[10px] font-bold text-indigo-700 dark:text-indigo-300 block mb-1.5">
                متغیرهای قابل استفاده (کلیک جهت کپی):
              </span>
              <div class="flex flex-wrap gap-1.5">
                <span
                  v-for="(lbl, tag) in availableVariables"
                  :key="tag"
                  @click="copyTag(tag)"
                  class="px-2 py-0.5 rounded text-[10px] font-mono bg-white dark:bg-slate-800 border border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400 cursor-pointer hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition-colors"
                  :title="lbl"
                >
                  &#123;&#123;{{ tag }}&#125;&#125;
                </span>
              </div>
            </div>

            <!-- Actions List -->
            <div class="space-y-3">
              <div
                v-for="(act, idx) in wizardForm.actions"
                :key="idx"
                class="bg-slate-50 dark:bg-slate-800/50 p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-700/60 space-y-2.5"
              >
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                    اقدام #{{ idx + 1 }}: {{ getActionLabel(act.type) }}
                  </span>
                  <button
                    type="button"
                    @click="removeAction(idx)"
                    class="p-1 text-slate-400 hover:text-rose-600 rounded"
                  >
                    <Iconsax name="close" size="14" />
                  </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <select
                    v-model="act.type"
                    class="px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-medium text-slate-800 dark:text-slate-200"
                  >
                    <option value="add_customer_tag">افزودن برچسب به مشتری</option>
                    <option value="create_task">ایجاد وظیفه جدید</option>
                    <option value="create_notification">ارسال اعلان درون‌برنامه‌ای</option>
                    <option value="create_activity">ثبت فعالیت تعامل</option>
                    <option value="change_order_status">تغییر وضعیت سفارش ووکامرس</option>
                    <option value="add_order_note">ثبت یادداشت روی سفارش</option>
                    <option value="update_product_stock">بروزرسانی موجودی انبار</option>
                  </select>

                  <!-- Dynamic inputs depending on action type -->
                  <template v-if="act.type === 'add_customer_tag'">
                    <input
                      v-model="act.config.tag_name"
                      type="text"
                      placeholder="نام برچسب (مثلاً VIP)"
                      class="px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs"
                    />
                  </template>

                  <template v-else-if="act.type === 'create_task'">
                    <input
                      v-model="act.config.title"
                      type="text"
                      placeholder="عنوان وظیفه (مثلاً پیگیری سفارش &#123;&#123;order.number&#125;&#125;)"
                      class="px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs"
                    />
                  </template>

                  <template v-else-if="act.type === 'create_notification'">
                    <input
                      v-model="act.config.title"
                      type="text"
                      placeholder="عنوان اعلان"
                      class="px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs"
                    />
                  </template>

                  <template v-else-if="act.type === 'change_order_status'">
                    <select
                      v-model="act.config.new_status"
                      class="px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs"
                    >
                      <option value="processing">در حال انجام (processing)</option>
                      <option value="completed">تکمیل شده (completed)</option>
                      <option value="on-hold">در انتظار بررسی (on-hold)</option>
                      <option value="cancelled">لغو شده (cancelled)</option>
                    </select>
                  </template>

                  <template v-else-if="act.type === 'add_order_note'">
                    <input
                      v-model="act.config.note"
                      type="text"
                      placeholder="متن یادداشت سفارش"
                      class="px-2.5 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs"
                    />
                  </template>
                </div>
              </div>

              <button
                type="button"
                @click="addAction"
                class="px-3 py-1.5 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 flex items-center gap-1.5 w-full justify-center transition-colors"
              >
                <Iconsax name="add" size="14" />
                <span>افزودن اقدام جدید</span>
              </button>
            </div>
          </div>

          <!-- Step 4: Review & Save -->
          <div v-if="wizardStep === 4" class="space-y-4">
            <div class="bg-slate-50 dark:bg-slate-800/60 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/60 space-y-3">
              <div>
                <span class="text-[11px] text-slate-400">نام اتوماسیون:</span>
                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ wizardForm.name }}</div>
              </div>

              <div>
                <span class="text-[11px] text-slate-400">رویداد محرک:</span>
                <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ getTriggerLabel(wizardForm.trigger_type) }}</div>
              </div>

              <div>
                <span class="text-[11px] text-slate-400">تعداد شروط:</span>
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                  {{ wizardForm.conditions.conditions.length }} شرط با عملگر {{ wizardForm.conditions.operator }}
                </div>
              </div>

              <div>
                <span class="text-[11px] text-slate-400">اقدامات برنامه‌ریزی‌شده:</span>
                <div class="flex items-center gap-1.5 flex-wrap mt-1">
                  <span
                    v-for="(act, idx) in wizardForm.actions"
                    :key="idx"
                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                  >
                    {{ getActionLabel(act.type) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
          <button
            v-if="wizardStep > 1"
            @click="wizardStep--"
            class="px-4 py-2 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition-colors"
          >
            مرحله قبل
          </button>
          <div v-else></div>

          <button
            v-if="wizardStep < 4"
            @click="wizardStep++"
            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/20"
          >
            مرحله بعد
          </button>
          <button
            v-else
            @click="saveAutomation"
            :disabled="saving"
            class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-emerald-600/20 disabled:opacity-50 flex items-center gap-2"
          >
            <Iconsax v-if="saving" name="refresh" size="14" class="animate-spin" />
            <span>ذخیره و فعال‌سازی اتوماسیون</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Dry Run Simulation Modal -->
    <div
      v-if="isDryRunOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-2xl overflow-hidden shadow-2xl flex flex-col max-h-[85vh]">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <Iconsax name="play" size="18" />
            </span>
            <div>
              <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">شبیه‌سازی و تست Dry Run</h2>
              <div class="text-[11px] text-slate-400">{{ activeDryRunAuto?.name }}</div>
            </div>
          </div>
          <button
            @click="isDryRunOpen = false"
            class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            <Iconsax name="close" size="18" />
          </button>
        </div>

        <div class="p-6 overflow-y-auto space-y-4">
          <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 rounded-xl text-xs text-amber-800 dark:text-amber-300">
            حالت Dry Run بدون اعمال هرگونه تغییر بر روی پایگاه داده یا ووکامرس، شروط را بررسی و خروجی فرضی اقدامات را نمایش می‌دهد.
          </div>

          <button
            @click="executeDryRun"
            :disabled="dryRunning"
            class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/20 disabled:opacity-50 flex items-center justify-center gap-2"
          >
            <Iconsax name="flash" size="16" :class="{ 'animate-spin': dryRunning }" />
            <span>اجرای تست شبیه‌سازی</span>
          </button>

          <!-- Simulation Results -->
          <div v-if="dryRunResult" class="space-y-3 pt-2">
            <div
              class="p-3 rounded-xl border flex items-center gap-2"
              :class="dryRunResult.would_run ? 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300'"
            >
              <Iconsax :name="dryRunResult.would_run ? 'tick-circle' : 'warning-2'" size="18" />
              <span class="text-xs font-bold">{{ dryRunResult.summary }}</span>
            </div>

            <!-- Planned Actions Simulation -->
            <div>
              <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">نتیجه اقدامات شبیه‌سازی‌شده:</h4>
              <div class="space-y-2">
                <div
                  v-for="(pAct, idx) in dryRunResult.planned_actions"
                  :key="idx"
                  class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200/80 dark:border-slate-700/60 text-xs"
                >
                  <div class="flex items-center justify-between mb-1">
                    <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ pAct.label || pAct.type }}</span>
                    <span
                      class="px-2 py-0.5 rounded text-[10px] font-bold"
                      :class="pAct.status === 'would_execute' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                    >
                      {{ pAct.status === 'would_execute' ? 'آماده اجرا' : 'نادیده گرفته شد' }}
                    </span>
                  </div>
                  <pre class="text-[11px] font-mono text-slate-600 dark:text-slate-300 overflow-x-auto p-2 bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800">{{ JSON.stringify(pAct.simulated_config, null, 2) }}</pre>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useStoreContext } from '@/stores/storeContext';
import apiClient from '@/api/client';
import Iconsax from '@/components/icons/Iconsax.vue';
import { toPersianDigits, formatNumber, formatDate } from '@/utils/formatters';

const authStore = useAuthStore();
const storeContext = useStoreContext();

const automations = ref([]);
const loading = ref(false);
const saving = ref(false);
const togglingId = ref(null);

const filters = ref({
  search: '',
  status: 'all',
  trigger_type: '',
});

let debounceTimer = null;
const debounceFetch = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchAutomations();
  }, 350);
};

const activeCount = computed(() => automations.value.filter(a => a.status === 'active').length);
const totalRunsCount = computed(() => automations.value.reduce((acc, curr) => acc + (curr.run_count || 0), 0));

// Schema metadata
const allTriggers = [
  { type: 'order.created', label: 'ثبت سفارش جدید ووکامرس' },
  { type: 'order.status_changed', label: 'تغییر وضعیت سفارش' },
  { type: 'inventory.low_stock', label: 'کمبود موجودی کالا در انبار' },
  { type: 'inventory.out_of_stock', label: 'اتمام موجودی کالا' },
  { type: 'customer.created', label: 'ثبت‌نام مشتری جدید' },
  { type: 'task.overdue', label: 'فرارسیدن موعد سررسید وظیفه' },
  { type: 'scheduled', label: 'زمان‌بندی‌شده دوره‌ای (Cron)' },
];

const availableVariables = {
  'order.number': 'شماره سفارش',
  'order.total': 'مبلغ کل سفارش',
  'customer.name': 'نام مشتری',
  'customer.id': 'شناسه مشتری',
  'product.name': 'نام کالا',
  'product.stock_quantity': 'موجودی انبار',
  'task.title': 'عنوان وظیفه',
  'store.name': 'نام فروشگاه',
};

const getTriggerLabel = (type) => {
  const found = allTriggers.find(t => t.type === type);
  return found ? found.label : type;
};

const getActionLabel = (type) => {
  const map = {
    add_customer_tag: 'افزودن برچسب مشتری',
    remove_customer_tag: 'حذف برچسب مشتری',
    create_task: 'ایجاد وظیفه CRM',
    update_task: 'ویرایش وظیفه',
    create_activity: 'ثبت فعالیت تعامل',
    create_notification: 'ارسال اعلان',
    change_order_status: 'تغییر وضعیت سفارش',
    add_order_note: 'ثبت یادداشت سفارش',
    update_product_stock: 'بروزرسانی موجودی انبار',
  };
  return map[type] || type;
};

const copyTag = (tag) => {
  navigator.clipboard.writeText(`{{${tag}}}`);
};

// Wizard State
const isWizardOpen = ref(false);
const wizardStep = ref(1);
const editingId = ref(null);
const wizardForm = ref({
  name: '',
  description: '',
  trigger_type: 'order.created',
  conditions: {
    operator: 'AND',
    conditions: [
      { field: 'order.total', operator: 'greater_than', value: '5000000' }
    ]
  },
  actions: [
    { type: 'add_customer_tag', config: { tag_name: 'VIP', tag_color: '#E11D48' } }
  ]
});

const stepTitle = computed(() => {
  switch (wizardStep.value) {
    case 1: return 'انتخاب نام و رویداد محرک';
    case 2: return 'تنظیم شروط منطقی (Conditions)';
    case 3: return 'تعریف اقدامات خودکار (Actions)';
    case 4: return 'بازبینی نهایی و ذخیره';
    default: return '';
  }
});

const openCreateWizard = () => {
  editingId.value = null;
  wizardForm.value = {
    name: '',
    description: '',
    trigger_type: 'order.created',
    conditions: {
      operator: 'AND',
      conditions: [
        { field: 'order.total', operator: 'greater_than', value: '5000000' }
      ]
    },
    actions: [
      { type: 'add_customer_tag', config: { tag_name: 'VIP', tag_color: '#E11D48' } }
    ]
  };
  wizardStep.value = 1;
  isWizardOpen.value = true;
};

const openEditWizard = (auto) => {
  editingId.value = auto.id;
  wizardForm.value = {
    name: auto.name,
    description: auto.description || '',
    trigger_type: auto.trigger_type,
    conditions: auto.conditions && auto.conditions.conditions ? auto.conditions : { operator: 'AND', conditions: [] },
    actions: Array.isArray(auto.actions) ? JSON.parse(JSON.stringify(auto.actions)) : []
  };
  wizardStep.value = 1;
  isWizardOpen.value = true;
};

const addCondition = () => {
  wizardForm.value.conditions.conditions.push({
    field: 'order.total',
    operator: 'greater_than',
    value: ''
  });
};

const removeCondition = (idx) => {
  wizardForm.value.conditions.conditions.splice(idx, 1);
};

const addAction = () => {
  wizardForm.value.actions.push({
    type: 'create_notification',
    config: { title: 'اعلان خودکار', message: 'عملیات با موفقیت انجام شد.' }
  });
};

const removeAction = (idx) => {
  wizardForm.value.actions.splice(idx, 1);
};

// Dry Run Modal State
const isDryRunOpen = ref(false);
const activeDryRunAuto = ref(null);
const dryRunning = ref(false);
const dryRunResult = ref(null);

const openDryRunModal = (auto) => {
  activeDryRunAuto.value = auto;
  dryRunResult.value = null;
  isDryRunOpen.value = true;
};

const executeDryRun = async () => {
  if (!activeDryRunAuto.value) return;
  dryRunning.value = true;
  try {
    const res = await apiClient.post(`/automations/${activeDryRunAuto.value.id}/test`);
    dryRunResult.value = res.data;
  } catch (err) {
    alert(err.message || 'خطا در اجرای تست شبیه‌سازی');
  } finally {
    dryRunning.value = false;
  }
};

// API Actions
const fetchAutomations = async () => {
  loading.value = true;
  try {
    const params = {
      status: filters.value.status,
      trigger_type: filters.value.trigger_type || undefined,
      search: filters.value.search || undefined,
    };
    const res = await apiClient.get('/automations', { params });
    automations.value = res.data || [];
  } catch (err) {
    console.error('Fetch automations error:', err);
  } finally {
    loading.value = false;
  }
};

const toggleStatus = async (auto) => {
  togglingId.value = auto.id;
  try {
    const endpoint = auto.status === 'active' ? `/automations/${auto.id}/disable` : `/automations/${auto.id}/enable`;
    await apiClient.post(endpoint);
    auto.status = auto.status === 'active' ? 'inactive' : 'active';
  } catch (err) {
    alert(err.message || 'خطا در تغییر وضعیت اتوماسیون');
  } finally {
    togglingId.value = null;
  }
};

const saveAutomation = async () => {
  if (!wizardForm.value.name.trim()) {
    alert('نام اتوماسیون الزامی است.');
    return;
  }
  if (!wizardForm.value.actions.length) {
    alert('حداقل یک اقدام باید تعریف شود.');
    return;
  }

  saving.value = true;
  try {
    if (editingId.value) {
      await apiClient.patch(`/automations/${editingId.value}`, wizardForm.value);
    } else {
      await apiClient.post('/automations', wizardForm.value);
    }
    isWizardOpen.value = false;
    await fetchAutomations();
  } catch (err) {
    alert(err.message || 'خطا در ذخیره‌سازی اتوماسیون');
  } finally {
    saving.value = false;
  }
};

const confirmManualRun = async (auto) => {
  if (!confirm(`آیا از اجرای دستی اتوماسیون «${auto.name}» اطمینان دارید؟ این عملیات ممکن است روی داده‌های واقعی اثر بگذارد.`)) {
    return;
  }

  try {
    const res = await apiClient.post(`/automations/${auto.id}/run`);
    alert(`اتوماسیون با موفقیت اجرا شد. وضعیت: ${res.data?.status || 'تکمیل شد'}`);
    await fetchAutomations();
  } catch (err) {
    alert(err.message || 'خطا در اجرای دستی اتوماسیون');
  }
};

const confirmDelete = async (auto) => {
  if (!confirm(`آیا از حذف اتوماسیون «${auto.name}» اطمینان دارید؟`)) {
    return;
  }

  try {
    await apiClient.delete(`/automations/${auto.id}`);
    await fetchAutomations();
  } catch (err) {
    alert(err.message || 'خطا در حذف اتوماسیون');
  }
};

onMounted(() => {
  fetchAutomations();
});
</script>
