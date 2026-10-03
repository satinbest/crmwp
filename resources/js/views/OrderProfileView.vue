<template>
  <div class="space-y-6">
    <!-- Breadcrumb & Top Actions -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <router-link to="/orders" class="hover:text-indigo-600 transition-colors flex items-center gap-1 font-medium">
          <Iconsax name="receipt" size="14" />
          <span>سفارش‌ها</span>
        </router-link>
        <span>/</span>
        <span class="text-slate-800 dark:text-slate-200 font-bold truncate max-w-xs">
          سفارش #{{ order?.number || order?.id || '...' }}
        </span>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchOrderDetails"
          :disabled="loading"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold shadow-2xs transition-colors disabled:opacity-50"
        >
          <Iconsax name="refresh" size="15" :class="{ 'animate-spin': loading }" />
          <span>به‌روزرسانی</span>
        </button>

        <router-link
          to="/orders"
          class="flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors"
        >
          <Iconsax name="chevron-right" size="15" />
          <span>بازگشت به فهرست</span>
        </router-link>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="errorMessage && !loading"
      class="p-6 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-center space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto">
        <Iconsax name="close" size="24" />
      </div>
      <div>
        <h4 class="font-bold text-sm text-rose-900 dark:text-rose-200">خطا در دریافت اطلاعات سفارش</h4>
        <p class="text-xs text-rose-700 dark:text-rose-400 mt-1 max-w-md mx-auto">{{ errorMessage }}</p>
      </div>
      <button
        @click="fetchOrderDetails"
        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs transition-colors"
      >
        تلاش مجدد
      </button>
    </div>

    <div v-else class="space-y-6">
      <!-- Top Order Header Card -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-6 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <!-- Order Title and Status -->
          <div class="flex items-start sm:items-center gap-4 min-w-0">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-black text-2xl shadow-lg shadow-indigo-500/25 shrink-0">
              <Iconsax name="receipt" size="30" />
            </div>

            <div class="min-w-0 space-y-1">
              <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-lg md:text-xl font-black text-slate-900 dark:text-slate-100 truncate">
                  سفارش #{{ toPersianDigits(order?.number || order?.id) }}
                </h1>
                <span
                  class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold tracking-wide"
                  :class="getStatusBadgeClass(order?.status)"
                >
                  {{ order?.status_label || order?.status }}
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-y-1 gap-x-3 text-xs text-slate-500 dark:text-slate-400">
                <span>ثبت شده در: {{ formatDate(order?.date_created) }}</span>
                <span>•</span>
                <span>روش پرداخت: {{ order?.payment_method_title || order?.payment_method || 'نامشخص' }}</span>
                <span v-if="order?.transaction_id">• تراکنش: {{ order.transaction_id }}</span>
              </div>
            </div>
          </div>

          <!-- Top Consequential Action Buttons -->
          <div class="flex items-center gap-3 flex-wrap">
            <button
              @click="openStatusModal"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold shadow-2xs transition-colors"
            >
              <Iconsax name="edit" size="15" />
              <span>تغییر وضعیت</span>
            </button>

            <button
              @click="openRefundModal"
              :disabled="isFullyRefunded"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900/60 hover:bg-rose-100 text-rose-700 dark:text-rose-300 text-xs font-semibold shadow-2xs transition-colors disabled:opacity-40"
            >
              <Iconsax name="refresh" size="15" />
              <span>استرداد وجه (Refund)</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Financial Metrics Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-2xs">
          <div class="text-xs font-medium text-slate-400">جمع جزء اقلام</div>
          <div class="text-base font-bold text-slate-900 dark:text-slate-100 mt-1">
            {{ formatPrice(order?.subtotal) }}
          </div>
        </div>

        <div class="p-4 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-2xs">
          <div class="text-xs font-medium text-slate-400">هزینه ارسال</div>
          <div class="text-base font-bold text-slate-900 dark:text-slate-100 mt-1">
            {{ formatPrice(order?.shipping_total) }}
          </div>
        </div>

        <div class="p-4 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-2xs">
          <div class="text-xs font-medium text-slate-400">مجموع استرداد شده</div>
          <div class="text-base font-bold text-rose-600 dark:text-rose-400 mt-1">
            {{ formatPrice(order?.refunded_total) }}
          </div>
        </div>

        <div class="p-4 rounded-3xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60 shadow-2xs">
          <div class="text-xs font-bold text-indigo-700 dark:text-indigo-300">مبلغ نهایی سفارش</div>
          <div class="text-lg font-black text-indigo-700 dark:text-indigo-400 mt-1">
            {{ formatPrice(order?.total) }}
          </div>
        </div>
      </div>

      <!-- Customer & Addresses Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Customer Info -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-3">
          <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-bold text-xs text-slate-900 dark:text-slate-100 flex items-center gap-2">
              <Iconsax name="customers" size="16" class="text-indigo-600" />
              <span>مشخصات خریدار</span>
            </h3>
            <router-link
              v-if="order?.customer_id && !order?.customer?.is_guest"
              :to="'/customers/' + order.customer_id"
              class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline font-bold"
            >
              مشاهده پرونده
            </router-link>
            <span v-else class="text-[10px] px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
              کاربر مهمان
            </span>
          </div>

          <div class="space-y-2 text-xs">
            <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">
              {{ order?.customer?.name || order?.billing?.full_name || 'نامشخص' }}
            </div>
            <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
              <Iconsax name="mail" size="14" />
              <span class="truncate">{{ order?.customer?.email || order?.billing?.email || 'بدون ایمیل' }}</span>
            </div>
            <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
              <Iconsax name="phone" size="14" />
              <span dir="ltr">{{ order?.customer?.phone || order?.billing?.phone || 'بدون تلفن' }}</span>
            </div>
          </div>
        </div>

        <!-- Billing Address -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-3">
          <div class="pb-2 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-bold text-xs text-slate-900 dark:text-slate-100 flex items-center gap-2">
              <Iconsax name="shop" size="16" class="text-indigo-600" />
              <span>نشانی صورتحساب</span>
            </h3>
          </div>
          <div class="text-xs text-slate-600 dark:text-slate-300 space-y-1 leading-relaxed">
            <div class="font-semibold text-slate-800 dark:text-slate-200">
              {{ order?.billing?.full_name }}
              <span v-if="order?.billing?.company" class="font-normal text-slate-400">({{ order.billing.company }})</span>
            </div>
            <div>
              {{ order?.billing?.state ? order.billing.state + '، ' : '' }}
              {{ order?.billing?.city ? order.billing.city + '، ' : '' }}
              {{ order?.billing?.address_1 }}
              <span v-if="order?.billing?.address_2"> - {{ order.billing.address_2 }}</span>
            </div>
            <div v-if="order?.billing?.postcode" class="text-slate-400">
              کد پستی: {{ toPersianDigits(order.billing.postcode) }}
            </div>
          </div>
        </div>

        <!-- Shipping Address -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-3">
          <div class="pb-2 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-bold text-xs text-slate-900 dark:text-slate-100 flex items-center gap-2">
              <Iconsax name="inventory" size="16" class="text-indigo-600" />
              <span>نشانی تحویل کالا</span>
            </h3>
          </div>
          <div class="text-xs text-slate-600 dark:text-slate-300 space-y-1 leading-relaxed">
            <div class="font-semibold text-slate-800 dark:text-slate-200">
              {{ order?.shipping?.full_name || order?.shipping?.first_name || order?.billing?.full_name }}
            </div>
            <div>
              {{ order?.shipping?.address_1 ? (order.shipping.city + '، ' + order.shipping.address_1) : 'با نشانی صورتحساب یکسان است.' }}
            </div>
            <div v-if="order?.shipping?.postcode" class="text-slate-400">
              کد پستی: {{ toPersianDigits(order.shipping.postcode) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Line Items Table -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl shadow-2xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
            اقلام ثبت شده در سفارش
          </h3>
          <span class="text-xs text-slate-400">{{ formatNumber(order?.items_count || 0) }} قلم کالا</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-right text-xs">
            <thead>
              <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-400 font-semibold">
                <th class="py-3 px-4">محصول / ویژگی</th>
                <th class="py-3 px-4">کد کالا (SKU)</th>
                <th class="py-3 px-4">قیمت واحد</th>
                <th class="py-3 px-4">تعداد</th>
                <th class="py-3 px-4">مالیات</th>
                <th class="py-3 px-4 text-left">مجموع</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
              <tr v-for="item in (order?.items || [])" :key="item.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-3.5 px-4">
                  <div class="font-bold text-slate-900 dark:text-slate-100 text-xs">
                    {{ item.name }}
                  </div>
                  <!-- Variation Metadata -->
                  <div v-if="item.meta_data && item.meta_data.length > 0" class="flex flex-wrap gap-2 mt-1">
                    <span
                      v-for="(meta, mIdx) in item.meta_data"
                      :key="mIdx"
                      class="text-[10px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300"
                    >
                      {{ meta.key }}: {{ meta.value }}
                    </span>
                  </div>
                </td>
                <td class="py-3.5 px-4 text-slate-500 dir-ltr text-right">
                  {{ item.sku || '—' }}
                </td>
                <td class="py-3.5 px-4 text-slate-700 dark:text-slate-300">
                  {{ formatPrice(item.price) }}
                </td>
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-slate-100">
                  × {{ formatNumber(item.quantity) }}
                </td>
                <td class="py-3.5 px-4 text-slate-500">
                  {{ formatPrice(item.tax) }}
                </td>
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-slate-100 text-left">
                  {{ formatPrice(item.total) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Navigation Tabs: Notes, Refunds, Activities, Tasks -->
      <div class="border-b border-slate-200 dark:border-slate-800 flex items-center gap-1 overflow-x-auto select-none">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          class="flex items-center gap-2 px-4 py-3 text-xs md:text-sm font-semibold border-b-2 transition-all shrink-0 -mb-px"
          :class="activeTab === tab.id ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
        >
          <Iconsax :name="tab.icon" size="17" />
          <span>{{ tab.label }}</span>
          <span
            v-if="tab.badge !== undefined && tab.badge > 0"
            class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400"
          >
            {{ tab.badge }}
          </span>
        </button>
      </div>

      <!-- Tab 1: Notes -->
      <div v-if="activeTab === 'notes'" class="space-y-4 animate-fadeIn">
        <!-- New Note Form Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-3">
          <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">افزودن یادداشت به سفارش</h3>
          <textarea
            v-model="newNoteText"
            rows="3"
            placeholder="متن یادداشت سفارش..."
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-800 dark:text-slate-100 p-3 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          ></textarea>
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" v-model="isCustomerNote" class="rounded text-indigo-600 w-4 h-4" />
              <span>ارسال برای خریدار (یادداشت به مشتری)</span>
            </label>
            <button
              @click="saveNote"
              :disabled="submittingNote || !newNoteText.trim()"
              class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-semibold shadow-xs transition-colors"
            >
              {{ submittingNote ? 'در حال ثبت...' : 'ثبت یادداشت' }}
            </button>
          </div>
        </div>

        <!-- Notes List -->
        <div class="space-y-3">
          <div v-if="loadingNotes" class="p-8 text-center text-xs text-slate-400">در حال دریافت یادداشت‌ها...</div>
          <div v-else-if="notes.length === 0" class="p-8 text-center text-xs text-slate-400 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl">
            هنوز یادداشتی برای این سفارش ثبت نشده است.
          </div>
          <div
            v-for="note in notes"
            :key="note.id"
            class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-2"
          >
            <div class="flex items-center justify-between text-xs text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-800">
              <span class="font-bold text-slate-700 dark:text-slate-300">{{ note.author }}</span>
              <div class="flex items-center gap-3">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-semibold"
                  :class="note.customer_note ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'"
                >
                  {{ note.customer_note ? 'یادداشت به مشتری' : 'یادداشت خصوصی داخلی' }}
                </span>
                <span>{{ formatDate(note.date_created) }}</span>
              </div>
            </div>
            <div class="text-xs text-slate-700 dark:text-slate-200 whitespace-pre-line leading-relaxed pt-1">
              {{ note.note }}
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: Refunds -->
      <div v-if="activeTab === 'refunds'" class="space-y-4 animate-fadeIn">
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-6 shadow-2xs space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">تاریخچه مبالغ استرداد شده (Refunds)</h3>
            <button
              @click="openRefundModal"
              :disabled="isFullyRefunded"
              class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold disabled:opacity-40"
            >
              استرداد جدید
            </button>
          </div>

          <div v-if="order?.refunds && order.refunds.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800">
            <div
              v-for="refItem in order.refunds"
              :key="refItem.id"
              class="py-3 flex items-center justify-between text-xs"
            >
              <div>
                <div class="font-bold text-slate-800 dark:text-slate-200">
                  استرداد وجه #{{ toPersianDigits(refItem.id) }}
                </div>
                <div class="text-slate-400 text-[11px] mt-0.5">
                  دلیل: {{ refItem.reason || 'بدون ذکر دلیل' }}
                </div>
              </div>
              <div class="font-black text-rose-600 dark:text-rose-400 text-sm">
                - {{ formatPrice(refItem.total) }}
              </div>
            </div>
          </div>
          <div v-else class="py-6 text-center text-xs text-slate-400">
            تاکنون وجهی برای این سفارش مسترد نشده است.
          </div>
        </div>
      </div>

      <!-- Tab 3: Activities -->
      <div v-if="activeTab === 'activities'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-6 shadow-2xs space-y-4 animate-fadeIn">
        <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-2 border-b border-slate-100 dark:border-slate-800">
          تایم‌لاین رخدادها و فعالیت‌های سفارش
        </h3>

        <div v-if="loadingActivities" class="py-8 text-center text-xs text-slate-400">در حال دریافت فعالیت‌ها...</div>
        <div v-else-if="activities.length === 0" class="py-8 text-center text-xs text-slate-400">
          هنوز رخدادی برای این سفارش در سامانه ثبت نشده است.
        </div>
        <div v-else class="relative border-r-2 border-slate-100 dark:border-slate-800 mr-3 pr-6 space-y-6">
          <div
            v-for="act in activities"
            :key="act.id"
            class="relative"
          >
            <div class="absolute -right-[31px] top-1 w-3 h-3 rounded-full bg-indigo-600 ring-4 ring-indigo-100 dark:ring-indigo-950"></div>
            <div class="space-y-1">
              <div class="flex items-center gap-2 text-xs">
                <span class="font-bold text-slate-900 dark:text-slate-100">{{ formatActivityTitle(act.action_type) }}</span>
                <span class="text-slate-400">• {{ act.user_name || 'کاربر سیستم' }}</span>
                <span class="text-slate-400">• {{ formatDate(act.created_at) }}</span>
              </div>
              <div v-if="act.details" class="text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800 inline-block">
                {{ formatActivityDetails(act.action_type, act.details) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 4: Tasks -->
      <div v-if="activeTab === 'tasks'" class="space-y-4 animate-fadeIn">
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-3">
          <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">ایجاد وظیفه جدید برای این سفارش</h3>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input
              v-model="newTaskTitle"
              type="text"
              placeholder="عنوان وظیفه (مثلاً: پیگیری بسته پستی، هماهنگی با انبار...)"
              class="sm:col-span-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-800 dark:text-slate-100 p-2.5 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
            />
            <select
              v-model="newTaskPriority"
              class="text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-200 p-2.5 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
            >
              <option value="low">اولویت کم</option>
              <option value="medium">اولویت متوسط</option>
              <option value="high">اولویت زیاد</option>
              <option value="urgent">فوری</option>
            </select>
          </div>
          <div class="flex justify-end">
            <button
              @click="saveTask"
              :disabled="submittingTask || !newTaskTitle.trim()"
              class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-semibold shadow-xs transition-colors"
            >
              {{ submittingTask ? 'در حال ثبت...' : 'ایجاد وظیفه' }}
            </button>
          </div>
        </div>

        <!-- Task List -->
        <div class="space-y-3">
          <div v-if="loadingTasks" class="p-8 text-center text-xs text-slate-400">در حال دریافت وظایف...</div>
          <div v-else-if="tasks.length === 0" class="p-8 text-center text-xs text-slate-400 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl">
            وظیفه‌ای برای این سفارش تعریف نشده است.
          </div>
          <div
            v-for="t in tasks"
            :key="t.id"
            class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-2xs flex items-center justify-between"
          >
            <div>
              <div class="text-xs font-bold text-slate-900 dark:text-slate-100">{{ t.title }}</div>
              <div class="text-[10px] text-slate-400 mt-0.5">توسط: {{ t.creator_user_name || 'کاربر سیستم' }}</div>
            </div>
            <span class="text-xs px-2.5 py-1 rounded-lg font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
              {{ t.priority }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Status Change Modal (Section 15) -->
    <div v-if="showStatusModal" class="fixed inset-0 z-60 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 animate-fadeIn">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 max-w-sm w-full space-y-4 shadow-xl">
        <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
          تأیید تغییر وضعیت سفارش #{{ order?.number || order?.id }}
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          وضعیت فعلی: <span class="font-bold text-slate-700 dark:text-slate-200">{{ order?.status_label || order?.status }}</span>
        </p>

        <div>
          <label class="block text-xs font-medium text-slate-600 dark:text-slate-300 mb-1.5">انتخاب وضعیت جدید:</label>
          <select
            v-model="targetStatus"
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 p-2.5 focus:ring-2 focus:ring-indigo-500"
          >
            <option v-for="st in availableStatuses" :key="st.slug" :value="st.slug">
              {{ st.name }}
            </option>
          </select>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            @click="showStatusModal = false"
            class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200"
          >
            انصراف
          </button>
          <button
            @click="confirmStatusChange"
            :disabled="changingStatus || targetStatus === order?.status"
            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs disabled:opacity-50"
          >
            {{ changingStatus ? 'در حال اعمال...' : 'تأیید تغییر وضعیت' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Refund Safety Modal (Section 17, 18, 19) -->
    <div v-if="showRefundModal" class="fixed inset-0 z-60 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 animate-fadeIn">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 max-w-md w-full space-y-4 shadow-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">
            <Iconsax name="refresh" size="20" />
          </div>
          <div>
            <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
              استرداد وجه سفارش #{{ order?.number || order?.id }}
            </h3>
            <p class="text-[11px] text-slate-400">عملیات مالی حساس در ووکامرس</p>
          </div>
        </div>

        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs space-y-1.5">
          <div class="flex justify-between">
            <span class="text-slate-400">مبلغ کل سفارش:</span>
            <span class="font-bold">{{ formatPrice(order?.total) }}</span>
          </div>
          <div class="flex justify-between text-rose-600 dark:text-rose-400">
            <span>مبلغ قابل استرداد:</span>
            <span class="font-black">{{ formatPrice(refundableAmount) }}</span>
          </div>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">مبلغ استرداد (تومان/ریال):</label>
            <input
              v-model.number="refundAmount"
              type="number"
              :max="refundableAmount"
              min="1000"
              class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 p-2.5 focus:ring-2 focus:ring-rose-500 font-bold"
            />
          </div>

          <div>
            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">علت استرداد وجه:</label>
            <input
              v-model="refundReason"
              type="text"
              placeholder="مثلاً: انصراف خریدار، نقص فنی در کالا..."
              class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 p-2.5 focus:ring-2 focus:ring-rose-500"
            />
          </div>

          <label class="flex items-center gap-2 text-slate-600 dark:text-slate-300 cursor-pointer pt-1">
            <input type="checkbox" v-model="apiRefund" class="rounded text-rose-600 w-4 h-4" />
            <span>پردازش استرداد از طریق درگاه پرداخت متصل (API Refund)</span>
          </label>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
          <button
            @click="showRefundModal = false"
            class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold hover:bg-slate-200"
          >
            انصراف
          </button>
          <button
            @click="confirmRefund"
            :disabled="processingRefund || refundAmount <= 0 || refundAmount > refundableAmount"
            class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs disabled:opacity-50"
          >
            {{ processingRefund ? 'در حال پردازش...' : 'تأیید و اجرای استرداد وجه' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import apiClient from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import Iconsax from '@/components/icons/Iconsax.vue';
import { formatNumber, formatPrice, formatDate, toPersianDigits } from '@/utils/formatters';

const route = useRoute();
const notification = useNotificationStore();
const orderId = computed(() => route.params.id);

const order = ref(null);
const loading = ref(false);
const errorMessage = ref(null);

const activeTab = ref('notes');
const tabs = computed(() => [
  { id: 'notes', label: 'یادداشت‌های سفارش', icon: 'edit', badge: notes.value.length },
  { id: 'refunds', label: 'استردادها (Refunds)', icon: 'refresh', badge: order.value?.refunds?.length },
  { id: 'activities', label: 'تایم‌لاین فعالیت‌ها', icon: 'activity' },
  { id: 'tasks', label: 'وظایف پیگیری', icon: 'task', badge: tasks.value.length },
]);

// Notes State
const notes = ref([]);
const loadingNotes = ref(false);
const newNoteText = ref('');
const isCustomerNote = ref(false);
const submittingNote = ref(false);

// Activities State
const activities = ref([]);
const loadingActivities = ref(false);

// Tasks State
const tasks = ref([]);
const loadingTasks = ref(false);
const newTaskTitle = ref('');
const newTaskPriority = ref('medium');
const submittingTask = ref(false);

// Status Change Modal State
const showStatusModal = ref(false);
const targetStatus = ref('processing');
const changingStatus = ref(false);
const availableStatuses = ref([
  { slug: 'pending', name: 'در انتظار پرداخت' },
  { slug: 'processing', name: 'در حال پردازش' },
  { slug: 'on-hold', name: 'در انتظار بررسی' },
  { slug: 'completed', name: 'تکمیل شده' },
  { slug: 'cancelled', name: 'لغو شده' },
  { slug: 'refunded', name: 'مسترد شده' },
  { slug: 'failed', name: 'ناموفق' },
]);

// Refund Modal State
const showRefundModal = ref(false);
const refundAmount = ref(0);
const refundReason = ref('');
const apiRefund = ref(true);
const processingRefund = ref(false);

const refundableAmount = computed(() => {
  if (!order.value) return 0;
  return Math.max(0, order.value.total - (order.value.refunded_total || 0));
});

const isFullyRefunded = computed(() => {
  return refundableAmount.value <= 0;
});

onMounted(() => {
  fetchStatuses();
  fetchOrderDetails();
});

const fetchStatuses = async () => {
  try {
    const res = await apiClient.get('/orders/statuses');
    if (res.data && res.data.length > 0) {
      availableStatuses.value = res.data;
    }
  } catch (e) {
    // defaults
  }
};

const fetchOrderDetails = async () => {
  if (!orderId.value) return;
  loading.value = true;
  errorMessage.value = null;

  try {
    const res = await apiClient.get(`/orders/${orderId.value}`);
    order.value = res.data;
    loadNotes();
    loadActivities();
    loadTasks();
  } catch (err) {
    errorMessage.value = err.message || 'خطا در بارگذاری اطلاعات سفارش.';
  } finally {
    loading.value = false;
  }
};

const loadNotes = async () => {
  loadingNotes.value = true;
  try {
    const res = await apiClient.get(`/orders/${orderId.value}/notes`);
    notes.value = res.data || [];
  } catch (e) {
    notes.value = [];
  } finally {
    loadingNotes.value = false;
  }
};

const saveNote = async () => {
  if (!newNoteText.value.trim()) return;
  submittingNote.value = true;
  try {
    await apiClient.post(`/orders/${orderId.value}/notes`, {
      note: newNoteText.value.trim(),
      customer_note: isCustomerNote.value,
    });
    newNoteText.value = '';
    isCustomerNote.value = false;
    notification.success('یادداشت سفارش با موفقیت ثبت شد.');
    loadNotes();
    loadActivities();
  } catch (e) {
    notification.error(e.message || 'خطا در ثبت یادداشت.');
  } finally {
    submittingNote.value = false;
  }
};

const loadActivities = async () => {
  loadingActivities.value = true;
  try {
    const res = await apiClient.get(`/orders/${orderId.value}/activities`);
    activities.value = res.data || [];
  } catch (e) {
    activities.value = [];
  } finally {
    loadingActivities.value = false;
  }
};

const loadTasks = async () => {
  loadingTasks.value = true;
  try {
    const res = await apiClient.get(`/orders/${orderId.value}/tasks`);
    tasks.value = res.data || [];
  } catch (e) {
    tasks.value = [];
  } finally {
    loadingTasks.value = false;
  }
};

const saveTask = async () => {
  if (!newTaskTitle.value.trim()) return;
  submittingTask.value = true;
  try {
    await apiClient.post(`/orders/${orderId.value}/tasks`, {
      title: newTaskTitle.value.trim(),
      priority: newTaskPriority.value,
    });
    newTaskTitle.value = '';
    notification.success('وظیفه برای این سفارش ایجاد شد.');
    loadTasks();
  } catch (e) {
    notification.error(e.message || 'خطا در ایجاد وظیفه.');
  } finally {
    submittingTask.value = false;
  }
};

const openStatusModal = () => {
  targetStatus.value = order.value?.status || 'processing';
  showStatusModal.value = true;
};

const confirmStatusChange = async () => {
  changingStatus.value = true;
  try {
    await apiClient.patch(`/orders/${orderId.value}/status`, {
      status: targetStatus.value,
    });
    notification.success('وضعیت سفارش با موفقیت در ووکامرس به‌روزرسانی شد.');
    showStatusModal.value = false;
    fetchOrderDetails();
  } catch (e) {
    notification.error(e.message || 'خطا در تغییر وضعیت سفارش.');
  } finally {
    changingStatus.value = false;
  }
};

const openRefundModal = () => {
  refundAmount.value = refundableAmount.value;
  refundReason.value = '';
  apiRefund.value = true;
  showRefundModal.value = true;
};

const confirmRefund = async () => {
  processingRefund.value = true;
  try {
    await apiClient.post(`/orders/${orderId.value}/refund`, {
      amount: refundAmount.value,
      reason: refundReason.value.trim(),
      api_refund: apiRefund.value,
    });
    notification.success('استرداد وجه با موفقیت در ووکامرس ثبت گردید.');
    showRefundModal.value = false;
    fetchOrderDetails();
  } catch (e) {
    notification.error(e.message || 'خطا در پردازش استرداد وجه.');
  } finally {
    processingRefund.value = false;
  }
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'completed':
      return 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800';
    case 'processing':
      return 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800';
    case 'on-hold':
      return 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800';
    case 'pending':
      return 'bg-violet-50 dark:bg-violet-950/60 text-violet-700 dark:text-violet-400 border border-violet-200 dark:border-violet-800';
    case 'cancelled':
    case 'failed':
      return 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800';
    case 'refunded':
      return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700';
    default:
      return 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800';
  }
};


const formatActivityTitle = (type) => {
  return {
    order_status_changed: 'تغییر وضعیت سفارش',
    order_note_added: 'ثبت یادداشت سفارش',
    order_refunded: 'استرداد وجه سفارش',
    order_task_created: 'ایجاد وظیفه جدید',
  }[type] || type;
};

const formatActivityDetails = (type, details) => {
  if (!details) return '';
  if (details.new_label) return `تغییر به وضعیت: ${details.new_label}`;
  if (details.amount) return `مبلغ استرداد: ${formatPrice(details.amount)}`;
  if (details.excerpt) return details.excerpt;
  return JSON.stringify(details);
};
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.2s ease-out forwards;
}
</style>
