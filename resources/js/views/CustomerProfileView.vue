<template>
  <div class="space-y-6">
    <!-- Breadcrumb & Back Button -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <router-link to="/customers" class="hover:text-indigo-600 transition-colors flex items-center gap-1 font-medium">
          <Iconsax name="customers" size="14" />
          <span>مشتریان</span>
        </router-link>
        <span>/</span>
        <span class="text-slate-800 dark:text-slate-200 font-bold truncate max-w-xs">
          {{ customer?.full_name || 'پرونده مشتری' }}
        </span>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="fetchCustomerDetails"
          :disabled="loading"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold shadow-2xs transition-colors disabled:opacity-50"
        >
          <Iconsax name="refresh" size="15" :class="{ 'animate-spin': loading }" />
          <span>به‌روزرسانی</span>
        </button>

        <router-link
          to="/customers"
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
        <h4 class="font-bold text-sm text-rose-900 dark:text-rose-200">خطا در بارگذاری اطلاعات مشتری</h4>
        <p class="text-xs text-rose-700 dark:text-rose-400 mt-1 max-w-md mx-auto">{{ errorMessage }}</p>
      </div>
      <button
        @click="fetchCustomerDetails"
        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs transition-colors"
      >
        تلاش مجدد
      </button>
    </div>

    <div v-else class="space-y-6">
      <!-- Profile Hero Card -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-6 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <!-- Left: Identity -->
          <div class="flex items-start sm:items-center gap-4 min-w-0">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-black text-2xl shadow-lg shadow-indigo-500/25 shrink-0">
              {{ customerInitials }}
            </div>

            <div class="min-w-0 space-y-1">
              <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-lg md:text-xl font-black text-slate-900 dark:text-slate-100 truncate">
                  {{ customer?.full_name || 'در حال بارگذاری...' }}
                </h1>
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                  :class="customer?.customer_type === 'guest'
                    ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800'
                    : 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'"
                >
                  {{ customer?.customer_type === 'guest' ? 'مهمان' : 'مشتری ثبت‌نامی' }}
                </span>
                <span v-if="customer?.role" class="px-2 py-0.5 rounded-md text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                  {{ customer.role }}
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-500 dark:text-slate-400">
                <span>شناسه ووکامرس: #{{ toPersianDigits(customer?.id) }}</span>
                <span v-if="customer?.username">• نام‌کاربری: @{{ customer.username }}</span>
                <span v-if="customer?.email">• {{ customer.email }}</span>
                <span v-if="customer?.phone" dir="ltr">• {{ customer.phone }}</span>
              </div>
            </div>
          </div>

          <!-- Right: Key Financial Indicators -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 border-t lg:border-t-0 lg:border-r border-slate-100 dark:border-slate-800 pt-4 lg:pt-0 lg:pr-6">
            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800/80">
              <div class="text-[11px] font-medium text-slate-400">تعداد سفارش‌ها</div>
              <div class="text-base font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                {{ formatNumber(customer?.orders_count || 0) }}
              </div>
            </div>

            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800/80">
              <div class="text-[11px] font-medium text-slate-400">مجموع خرید</div>
              <div class="text-base font-bold text-indigo-600 dark:text-indigo-400 mt-0.5">
                {{ formatPrice(customer?.total_spent) }}
              </div>
            </div>

            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800/80">
              <div class="text-[11px] font-medium text-slate-400">میانگین هر سفارش</div>
              <div class="text-base font-bold text-slate-800 dark:text-slate-200 mt-0.5">
                {{ formatPrice(customer?.average_order_value) }}
              </div>
            </div>

            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800/80">
              <div class="text-[11px] font-medium text-slate-400">آخرین سفارش</div>
              <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mt-1">
                {{ formatDate(customer?.last_order_date) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="border-b border-slate-200 dark:border-slate-800 flex items-center gap-1 overflow-x-auto select-none">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          class="flex items-center gap-2 px-4 py-3 text-xs md:text-sm font-semibold border-b-2 transition-all shrink-0 -mb-px"
          :class="activeTab === tab.id
            ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 font-black'
            : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
        >
          <Iconsax :name="tab.icon" size="17" />
          <span>{{ tab.label }}</span>
          <span
            v-if="tab.badge !== undefined && tab.badge > 0"
            class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400"
          >
            {{ formatNumber(tab.badge) }}
          </span>
        </button>
      </div>

      <!-- Tab 1: Overview -->
      <div v-if="activeTab === 'overview'" class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-fadeIn">
        <!-- Billing Address Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
              <Iconsax name="shop" size="18" class="text-indigo-600" />
              <span>نشانی صورتحساب (Billing)</span>
            </h3>
          </div>

          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between py-1 border-b border-slate-50 dark:border-slate-800/40">
              <span class="text-slate-400">نام کامل:</span>
              <span class="font-medium text-slate-800 dark:text-slate-200">
                {{ customer?.billing?.first_name }} {{ customer?.billing?.last_name }}
              </span>
            </div>

            <div class="flex items-center justify-between py-1 border-b border-slate-50 dark:border-slate-800/40">
              <span class="text-slate-400">شرکت / سازمان:</span>
              <span class="font-medium text-slate-800 dark:text-slate-200">
                {{ customer?.billing?.company || '—' }}
              </span>
            </div>

            <div class="flex items-center justify-between py-1 border-b border-slate-50 dark:border-slate-800/40">
              <span class="text-slate-400">استان و شهر:</span>
              <span class="font-medium text-slate-800 dark:text-slate-200">
                {{ customer?.billing?.state || '' }} {{ customer?.billing?.city || '—' }}
              </span>
            </div>

            <div class="flex items-center justify-between py-1 border-b border-slate-50 dark:border-slate-800/40">
              <span class="text-slate-400">کد پستی:</span>
              <span class="text-slate-800 dark:text-slate-200">
                {{ toPersianDigits(customer?.billing?.postcode) || '—' }}
              </span>
            </div>

            <div class="flex items-center justify-between py-1 border-b border-slate-50 dark:border-slate-800/40">
              <span class="text-slate-400">تلفن صورتحساب:</span>
              <span class="text-slate-800 dark:text-slate-200" dir="ltr">
                {{ customer?.billing?.phone || '—' }}
              </span>
            </div>

            <div class="py-1">
              <span class="text-slate-400 block mb-1">آدرس پستی:</span>
              <p class="font-medium text-slate-800 dark:text-slate-200 leading-relaxed bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                {{ customer?.billing?.address_1 }}
                <span v-if="customer?.billing?.address_2"> - {{ customer.billing.address_2 }}</span>
                <span v-if="!customer?.billing?.address_1" class="text-slate-400">آدرسی ثبت نشده است.</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Shipping Address Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
              <Iconsax name="inventory" size="18" class="text-indigo-600" />
              <span>نشانی تحویل گیرنده (Shipping)</span>
            </h3>
          </div>

          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between py-1 border-b border-slate-50 dark:border-slate-800/40">
              <span class="text-slate-400">تحویل گیرنده:</span>
              <span class="font-medium text-slate-800 dark:text-slate-200">
                {{ customer?.shipping?.first_name }} {{ customer?.shipping?.last_name || '—' }}
              </span>
            </div>

            <div class="flex items-center justify-between py-1 border-b border-slate-50 dark:border-slate-800/40">
              <span class="text-slate-400">استان و شهر تحویل:</span>
              <span class="font-medium text-slate-800 dark:text-slate-200">
                {{ customer?.shipping?.state || '' }} {{ customer?.shipping?.city || '—' }}
              </span>
            </div>

            <div class="flex items-center justify-between py-1 border-b border-slate-50 dark:border-slate-800/40">
              <span class="text-slate-400">کد پستی تحویل:</span>
              <span class="text-slate-800 dark:text-slate-200">
                {{ toPersianDigits(customer?.shipping?.postcode) || '—' }}
              </span>
            </div>

            <div class="py-1">
              <span class="text-slate-400 block mb-1">آدرس تحویل کالا:</span>
              <p class="font-medium text-slate-800 dark:text-slate-200 leading-relaxed bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                {{ customer?.shipping?.address_1 || 'نشانی حمل و نقل ثبت نشده یا با نشانی صورتحساب یکسان است.' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: Orders -->
      <div v-if="activeTab === 'orders'" class="space-y-4 animate-fadeIn">
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl shadow-2xs overflow-hidden">
          <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
              سفارش‌های ثبت شده در ووکامرس
            </h3>
            <span class="text-xs text-slate-400">منبع داده: REST API ووکامرس</span>
          </div>

          <div v-if="loadingOrders" class="p-8 text-center text-xs text-slate-400">
            در حال دریافت سفارش‌ها...
          </div>
          <div v-else-if="orders.length === 0" class="p-8 text-center text-xs text-slate-400">
            هیچ سفارشی برای این مشتری در ووکامرس ثبت نشده است.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-right text-xs">
              <thead>
                <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-400 font-semibold">
                  <th class="py-3 px-4">شماره سفارش</th>
                  <th class="py-3 px-4">تاریخ ثبت</th>
                  <th class="py-3 px-4">وضعیت</th>
                  <th class="py-3 px-4">روش پرداخت</th>
                  <th class="py-3 px-4">تعداد اقلام</th>
                  <th class="py-3 px-4">هزینه ارسال</th>
                  <th class="py-3 px-4">مبلغ کل</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                <tr v-for="order in orders" :key="order.id" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                  <td class="py-3 px-4 font-bold text-slate-900 dark:text-slate-100">
                    #{{ toPersianDigits(order.number) }}
                  </td>
                  <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                    {{ formatDate(order.date_created) }}
                  </td>
                  <td class="py-3 px-4">
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                      {{ order.status_label }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                    {{ order.payment_method }}
                  </td>
                  <td class="py-3 px-4 text-slate-700 dark:text-slate-300">
                    {{ formatNumber(order.items_count) }} آیتم
                  </td>
                  <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                    {{ formatPrice(order.shipping_total) }}
                  </td>
                  <td class="py-3 px-4 font-bold text-indigo-600 dark:text-indigo-400">
                    {{ formatPrice(order.total) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tab 3: Notes -->
      <div v-if="activeTab === 'notes'" class="space-y-4 animate-fadeIn">
        <!-- New Note Form Card -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-3">
          <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">ثبت یادداشت جدید برای مشتری</h3>
          <textarea
            v-model="newNoteContent"
            rows="3"
            placeholder="متن یادداشت داخلی، نکات مربوط به مشتری، یا مکالمات قبلی..."
            class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-800 dark:text-slate-100 p-3 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          ></textarea>
          <div class="flex justify-end">
            <button
              @click="saveNote"
              :disabled="submittingNote || !newNoteContent.trim()"
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
            هنوز یادداشتی برای این مشتری ثبت نشده است.
          </div>
          <div
            v-for="note in notes"
            :key="note.id"
            class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-2"
          >
            <div class="flex items-center justify-between text-xs text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-800">
              <span class="font-bold text-slate-700 dark:text-slate-300">{{ note.author_name || 'کاربر سیستم' }}</span>
              <div class="flex items-center gap-3">
                <span>{{ formatDate(note.created_at) }}</span>
                <button
                  @click="deleteNote(note.id)"
                  class="text-rose-500 hover:text-rose-700 transition-colors"
                  title="حذف یادداشت"
                >
                  <Iconsax name="trash" size="14" />
                </button>
              </div>
            </div>
            <div class="text-xs text-slate-700 dark:text-slate-200 whitespace-pre-line leading-relaxed pt-1">
              {{ note.content }}
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 4: Tasks -->
      <div v-if="activeTab === 'tasks'" class="space-y-4 animate-fadeIn">
        <!-- New Task Form -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-5 shadow-2xs space-y-3">
          <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">ایجاد وظیفه جدید برای این مشتری</h3>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input
              v-model="newTaskTitle"
              type="text"
              placeholder="عنوان وظیفه (مثلاً: پیگیری سفارش، تماس تلفنی...)"
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
            وظیفه‌ای برای این مشتری تعریف نشده است.
          </div>
          <div
            v-for="task in tasks"
            :key="task.id"
            class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-2xs flex items-center justify-between"
          >
            <div class="flex items-center gap-3">
              <input
                type="checkbox"
                :checked="task.status === 'completed'"
                @change="toggleTaskStatus(task)"
                class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer"
              />
              <div>
                <div
                  class="text-xs font-bold"
                  :class="task.status === 'completed' ? 'line-through text-slate-400' : 'text-slate-900 dark:text-slate-100'"
                >
                  {{ task.title }}
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                  توسط {{ task.creator_user_name || 'کاربر سیستم' }} • {{ formatDate(task.created_at) }}
                </div>
              </div>
            </div>

            <span
              class="text-xs px-2.5 py-1 rounded-lg font-bold"
              :class="{
                'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300': task.priority === 'low',
                'bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400': task.priority === 'medium',
                'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400': task.priority === 'high',
                'bg-rose-50 text-rose-600 dark:bg-rose-950 dark:text-rose-400': task.priority === 'urgent',
              }"
            >
              {{ formatPriority(task.priority) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Tab 5: Tags -->
      <div v-if="activeTab === 'tags'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-6 shadow-2xs space-y-6 animate-fadeIn">
        <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">مدیریت برچسب‌های CRM این مشتری</h3>

        <!-- Add Tag Input -->
        <div class="flex items-center gap-2 max-w-md">
          <input
            v-model="newTagName"
            type="text"
            placeholder="عنوان برچسب جدید..."
            class="flex-1 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-800 dark:text-slate-100 p-2.5 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden"
          />
          <input
            v-model="newTagColor"
            type="color"
            class="w-10 h-10 rounded-xl border-0 cursor-pointer p-0.5"
            title="رنگ برچسب"
          />
          <button
            @click="saveTag"
            :disabled="submittingTag || !newTagName.trim()"
            class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-semibold shadow-xs transition-colors shrink-0"
          >
            افزودن برچسب
          </button>
        </div>

        <!-- Tags Display -->
        <div class="pt-2">
          <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-3">برچسب‌های تخصیص‌داده شده:</div>
          <div v-if="tags.length === 0" class="text-xs text-slate-400">
            هنوز برچسبی به این مشتری اختصاص نیافته است.
          </div>
          <div v-else class="flex flex-wrap gap-2.5">
            <div
              v-for="tag in tags"
              :key="tag.id"
              class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold text-white shadow-2xs"
              :style="{ backgroundColor: tag.color || '#4F46E5' }"
            >
              <span>{{ tag.name }}</span>
              <button
                @click="removeTag(tag.id)"
                class="hover:opacity-75 transition-opacity"
                title="حذف برچسب"
              >
                <Iconsax name="close" size="14" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 6: Activities -->
      <div v-if="activeTab === 'activities'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl p-6 shadow-2xs space-y-4 animate-fadeIn">
        <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 pb-2 border-b border-slate-100 dark:border-slate-800">
          تایم‌لاین رخدادها و فعالیت‌های CRM
        </h3>

        <div v-if="loadingActivities" class="py-8 text-center text-xs text-slate-400">در حال دریافت فعالیت‌ها...</div>
        <div v-else-if="activities.length === 0" class="py-8 text-center text-xs text-slate-400">
          هنوز رخدادی برای این مشتری در سامانه ثبت نشده است.
        </div>
        <div v-else class="relative border-r-2 border-slate-100 dark:border-slate-800 mr-3 pr-6 space-y-6">
          <div
            v-for="act in activities"
            :key="act.id"
            class="relative"
          >
            <!-- Timeline dot -->
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
const customerId = computed(() => route.params.id);

const customer = ref(null);
const loading = ref(false);
const errorMessage = ref(null);

const activeTab = ref('overview');
const tabs = computed(() => [
  { id: 'overview', label: 'نمای کلی', icon: 'dashboard' },
  { id: 'orders', label: 'سفارش‌ها', icon: 'orders', badge: customer.value?.orders_count },
  { id: 'notes', label: 'یادداشت‌های داخلی', icon: 'edit', badge: notes.value.length },
  { id: 'tasks', label: 'وظایف و پیگیری‌ها', icon: 'task', badge: tasks.value.length },
  { id: 'tags', label: 'برچسب‌ها', icon: 'tag', badge: tags.value.length },
  { id: 'activities', label: 'فعالیت‌ها', icon: 'activity' },
]);

const customerInitials = computed(() => {
  const name = customer.value?.full_name || customer.value?.first_name || 'U';
  return name.charAt(0).toUpperCase();
});

// Orders Tab
const orders = ref([]);
const loadingOrders = ref(false);

// Notes Tab
const notes = ref([]);
const loadingNotes = ref(false);
const newNoteContent = ref('');
const submittingNote = ref(false);

// Tasks Tab
const tasks = ref([]);
const loadingTasks = ref(false);
const newTaskTitle = ref('');
const newTaskPriority = ref('medium');
const submittingTask = ref(false);

// Tags Tab
const tags = ref([]);
const newTagName = ref('');
const newTagColor = ref('#4F46E5');
const submittingTag = ref(false);

// Activities Tab
const activities = ref([]);
const loadingActivities = ref(false);

onMounted(() => {
  fetchCustomerDetails();
});

const fetchCustomerDetails = async () => {
  if (!customerId.value) return;
  loading.value = true;
  errorMessage.value = null;

  try {
    const res = await apiClient.get(`/customers/${customerId.value}`);
    customer.value = res.data;
    loadOrders();
    loadNotes();
    loadTasks();
    loadTags();
    loadActivities();
  } catch (err) {
    errorMessage.value = err.message || 'خطا در بارگذاری اطلاعات مشتری.';
  } finally {
    loading.value = false;
  }
};

const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    const res = await apiClient.get(`/customers/${customerId.value}/orders`);
    orders.value = res.data || [];
  } catch (e) {
    orders.value = [];
  } finally {
    loadingOrders.value = false;
  }
};

const loadNotes = async () => {
  loadingNotes.value = true;
  try {
    const res = await apiClient.get(`/customers/${customerId.value}/notes`);
    notes.value = res.data || [];
  } catch (e) {
    notes.value = [];
  } finally {
    loadingNotes.value = false;
  }
};

const saveNote = async () => {
  if (!newNoteContent.value.trim()) return;
  submittingNote.value = true;
  try {
    await apiClient.post(`/customers/${customerId.value}/notes`, {
      content: newNoteContent.value.trim(),
    });
    newNoteContent.value = '';
    notification.success('یادداشت داخلی با موفقیت ثبت شد.');
    loadNotes();
    loadActivities();
  } catch (e) {
    notification.error(e.message || 'خطا در ثبت یادداشت.');
  } finally {
    submittingNote.value = false;
  }
};

const deleteNote = async (noteId) => {
  try {
    await apiClient.delete(`/customers/${customerId.value}/notes/${noteId}`);
    notification.success('یادداشت با موفقیت حذف گردید.');
    loadNotes();
    loadActivities();
  } catch (e) {
    notification.error(e.message || 'خطا در حذف یادداشت.');
  }
};

const loadTasks = async () => {
  loadingTasks.value = true;
  try {
    const res = await apiClient.get(`/customers/${customerId.value}/tasks`);
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
    await apiClient.post(`/customers/${customerId.value}/tasks`, {
      title: newTaskTitle.value.trim(),
      priority: newTaskPriority.value,
    });
    newTaskTitle.value = '';
    notification.success('وظیفه جدید با موفقیت ایجاد شد.');
    loadTasks();
    loadActivities();
  } catch (e) {
    notification.error(e.message || 'خطا در ایجاد وظیفه.');
  } finally {
    submittingTask.value = false;
  }
};

const toggleTaskStatus = async (task) => {
  const newStatus = task.status === 'completed' ? 'pending' : 'completed';
  try {
    await apiClient.patch(`/customers/${customerId.value}/tasks/${task.id}`, {
      status: newStatus,
    });
    task.status = newStatus;
    notification.success('وضعیت وظیفه به‌روزرسانی شد.');
    loadActivities();
  } catch (e) {
    notification.error(e.message || 'خطا در تغییر وضعیت وظیفه.');
  }
};

const loadTags = async () => {
  try {
    const res = await apiClient.get(`/customers/${customerId.value}/tags`);
    tags.value = res.data || [];
  } catch (e) {
    tags.value = [];
  }
};

const saveTag = async () => {
  if (!newTagName.value.trim()) return;
  submittingTag.value = true;
  try {
    await apiClient.post(`/customers/${customerId.value}/tags`, {
      name: newTagName.value.trim(),
      color: newTagColor.value,
    });
    newTagName.value = '';
    notification.success('برچسب با موفقیت افزوده شد.');
    loadTags();
    loadActivities();
  } catch (e) {
    notification.error(e.message || 'خطا در افزودن برچسب.');
  } finally {
    submittingTag.value = false;
  }
};

const removeTag = async (tagId) => {
  try {
    await apiClient.delete(`/customers/${customerId.value}/tags/${tagId}`);
    notification.success('برچسب با موفقیت حذف شد.');
    loadTags();
    loadActivities();
  } catch (e) {
    notification.error(e.message || 'خطا در حذف برچسب.');
  }
};

const loadActivities = async () => {
  loadingActivities.value = true;
  try {
    const res = await apiClient.get(`/customers/${customerId.value}/activities`);
    activities.value = res.data || [];
  } catch (e) {
    activities.value = [];
  } finally {
    loadingActivities.value = false;
  }
};

const formatActivityTitle = (type) => {
  return {
    note_created: 'ثبت یادداشت جدید',
    note_edited: 'ویرایش یادداشت',
    note_deleted: 'حذف یادداشت',
    tag_added: 'تخصیص برچسب',
    tag_removed: 'حذف برچسب',
    task_created: 'ایجاد وظیفه جدید',
    task_updated: 'به‌روزرسانی وظیفه',
    task_completed: 'تکمیل وظیفه',
  }[type] || type;
};

const formatActivityDetails = (type, details) => {
  if (!details) return '';
  if (details.excerpt) return details.excerpt;
  if (details.tag_name) return `برچسب: ${details.tag_name}`;
  if (details.title) return `عنوان: ${details.title}`;
  return JSON.stringify(details);
};


const formatPriority = (p) => {
  return {
    low: 'کم',
    medium: 'متوسط',
    high: 'زیاد',
    urgent: 'فوری',
  }[p] || p;
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
