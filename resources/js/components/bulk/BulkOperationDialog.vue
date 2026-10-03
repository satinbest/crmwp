<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div
      class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh] animate-fadeIn"
      dir="rtl"
    >
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0 bg-slate-50/50 dark:bg-slate-850/50">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
            <Iconsax name="bulk" size="20" />
          </div>
          <div>
            <h3 class="font-bold text-base text-slate-800 dark:text-slate-100 flex items-center gap-2">
              <span>عملیات گروهی</span>
              <span class="text-xs px-2.5 py-0.5 rounded-full font-medium bg-indigo-50 dark:bg-indigo-950/80 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
                {{ entityLabel }}
              </span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
              دامنه اثر: <strong class="text-indigo-600 dark:text-indigo-400">{{ scopeSummary }}</strong>
            </p>
          </div>
        </div>

        <button
          @click="closeModal"
          :disabled="isProcessing"
          class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors disabled:opacity-50"
        >
          <Iconsax name="close" size="18" />
        </button>
      </div>

      <!-- Stepper Indicator -->
      <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0">
        <div class="flex items-center justify-between text-xs">
          <div class="flex items-center gap-2" :class="step >= 1 ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-400'">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]" :class="step >= 1 ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800'">1</span>
            <span>انتخاب عملیات</span>
          </div>
          <div class="h-0.5 flex-1 mx-3" :class="step >= 2 ? 'bg-indigo-500' : 'bg-slate-100 dark:bg-slate-800'"></div>
          <div class="flex items-center gap-2" :class="step >= 2 ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-400'">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]" :class="step >= 2 ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800'">2</span>
            <span>پیش‌نمایش و هشدارها</span>
          </div>
          <div class="h-0.5 flex-1 mx-3" :class="step >= 3 ? 'bg-indigo-500' : 'bg-slate-100 dark:bg-slate-800'"></div>
          <div class="flex items-center gap-2" :class="step >= 3 ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-400'">
            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]" :class="step >= 3 ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800'">3</span>
            <span>تایید و اجرا</span>
          </div>
        </div>
      </div>

      <!-- Modal Body (Scrollable) -->
      <div class="p-6 overflow-y-auto flex-1 space-y-6">
        <!-- STEP 1: Action Selection & Parameters -->
        <div v-if="step === 1" class="space-y-5">
          <!-- Action Group / Type Selector -->
          <div class="space-y-1.5">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
              نوع عملیات مورد نظر را انتخاب کنید:
            </label>
            <select
              v-model="selectedActionType"
              @change="handleActionTypeChange"
              class="w-full py-2.5 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option disabled value="">-- انتخاب کنید --</option>
              <optgroup v-for="(group, gName) in actionOptionsGrouped" :key="gName" :label="gName">
                <option v-for="act in group" :key="act.key" :value="act.key">
                  {{ act.name }}
                </option>
              </optgroup>
            </select>
            <p v-if="currentActionDefinition" class="text-[11px] text-slate-500 dark:text-slate-400">
              {{ currentActionDefinition.description }}
            </p>
          </div>

          <!-- Product Target Selector (Parent / Variations / Both) -->
          <div v-if="entity === 'products'" class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200/80 dark:border-slate-800 space-y-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
              دامنه هدف عملیات (Target):
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <label
                class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs transition-colors"
                :class="actionParams.target === 'parent' ? 'border-indigo-500 bg-indigo-50/60 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-bold' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                <input type="radio" v-model="actionParams.target" value="parent" class="text-indigo-600 focus:ring-0" />
                <span>محصولات اصلی (Parent)</span>
              </label>

              <label
                class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs transition-colors"
                :class="actionParams.target === 'variations' ? 'border-indigo-500 bg-indigo-50/60 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-bold' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                <input type="radio" v-model="actionParams.target" value="variations" class="text-indigo-600 focus:ring-0" />
                <span>متغیرها (Variations)</span>
              </label>

              <label
                class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs transition-colors"
                :class="actionParams.target === 'both' ? 'border-indigo-500 bg-indigo-50/60 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-bold' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'"
              >
                <input type="radio" v-model="actionParams.target" value="both" class="text-indigo-600 focus:ring-0" />
                <span>محصولات اصلی + متغیرها</span>
              </label>
            </div>
            <p class="text-[11px] text-slate-400">
              در حالت متغیرها، عملیات از طریق Endpoint رسمی WooCommerce Variation Batch اجرا می‌گردد.
            </p>
          </div>

          <!-- Dynamic Action Parameters Form -->
          <div v-if="selectedActionType" class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200/80 dark:border-slate-800 space-y-4">
            <!-- Numeric Value input (Percentage or Amount or Stock) -->
            <div v-if="needsValueInput" class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                {{ valueInputLabel }}
              </label>
              <div class="relative">
                <input
                  v-model.number="actionParams.value"
                  type="number"
                  :min="selectedActionType.includes('percent') ? 1 : 0"
                  :max="selectedActionType === 'decrease_price_percent' ? 100 : null"
                  :placeholder="valueInputPlaceholder"
                  class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">
                  {{ valueInputUnit }}
                </span>
              </div>
            </div>

            <!-- Product Stock Status -->
            <div v-if="selectedActionType === 'set_stock_status'" class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">وضعیت انبار:</label>
              <select
                v-model="actionParams.status"
                class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="instock">موجود در انبار (instock)</option>
                <option value="outofstock">ناموجود (outofstock)</option>
                <option value="onbackorder">در پیش‌خرید (onbackorder)</option>
              </select>
            </div>

            <!-- Product Category Select -->
            <div v-if="selectedActionType === 'add_category' || selectedActionType === 'remove_category'" class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">انتخاب دسته‌بندی:</label>
              <select
                v-model.number="actionParams.category_id"
                class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option disabled :value="0">-- انتخاب دسته‌بندی --</option>
                <option v-for="c in categoriesList" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <!-- Product Tag Select -->
            <div v-if="selectedActionType === 'add_tag' || selectedActionType === 'remove_tag'" class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">انتخاب برچسب کالا:</label>
              <select
                v-model.number="actionParams.tag_id"
                class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option disabled :value="0">-- انتخاب برچسب --</option>
                <option v-for="t in tagsList" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>

            <!-- Product Status Select -->
            <div v-if="selectedActionType === 'set_status'" class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">وضعیت انتشار:</label>
              <select
                v-model="actionParams.status"
                class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="publish">منتشر شده (Publish)</option>
                <option value="draft">پیش‌نویس (Draft)</option>
                <option value="pending">در انتظار بررسی (Pending)</option>
                <option value="private">خصوصی (Private)</option>
              </select>
            </div>

            <!-- Order Status Select -->
            <div v-if="entity === 'orders' && selectedActionType === 'change_status'" class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">وضعیت جدید سفارش:</label>
              <select
                v-model="actionParams.status"
                class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option disabled value="">-- انتخاب وضعیت --</option>
                <option v-for="st in orderStatusesList" :key="st.slug" :value="st.slug">
                  {{ st.name }} ({{ st.slug }})
                </option>
              </select>
            </div>

            <!-- Order Note Input -->
            <div v-if="entity === 'orders' && selectedActionType === 'add_note'" class="space-y-3">
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">متن یادداشت:</label>
                <textarea
                  v-model="actionParams.note"
                  rows="3"
                  placeholder="متن یادداشت را وارد کنید..."
                  class="w-full p-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                ></textarea>
              </div>
              <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-600 dark:text-slate-300">
                <input type="checkbox" v-model="actionParams.customer_note" class="rounded text-indigo-600 focus:ring-0" />
                <span>یادداشت برای مشتری نیز قابل مشاهده باشد (Customer Note)</span>
              </label>
            </div>

            <!-- Customer Tag Input -->
            <div v-if="entity === 'customers' && (selectedActionType === 'add_tag' || selectedActionType === 'remove_tag')" class="space-y-3">
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">نام برچسب (CRM):</label>
                <input
                  v-model="actionParams.tag_name"
                  type="text"
                  placeholder="مثلاً: مشتری VIP، پیگیری مجدد..."
                  class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>
              <div v-if="selectedActionType === 'add_tag'" class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">رنگ برچسب:</label>
                <div class="flex items-center gap-2">
                  <input type="color" v-model="actionParams.color" class="w-8 h-8 rounded-lg cursor-pointer border border-slate-200 dark:border-slate-700" />
                  <span class="text-xs text-slate-500">{{ actionParams.color }}</span>
                </div>
              </div>
            </div>

            <!-- Customer Task Input -->
            <div v-if="entity === 'customers' && selectedActionType === 'create_task'" class="space-y-3">
              <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">عنوان وظیفه (تسک):</label>
                <input
                  v-model="actionParams.title"
                  type="text"
                  placeholder="مثلاً: تماس تلفنی جهت ارائه پیشنهاد ویژه..."
                  class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">اولویت:</label>
                  <select
                    v-model="actionParams.priority"
                    class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100"
                  >
                    <option value="low">کم</option>
                    <option value="medium">متوسط</option>
                    <option value="high">زیاد</option>
                    <option value="urgent">فوری</option>
                  </select>
                </div>
                <div class="space-y-1.5">
                  <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">موعد انجام:</label>
                  <input
                    v-model="actionParams.due_date"
                    type="date"
                    class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- STEP 2: Preview & Warnings -->
        <div v-else-if="step === 2" class="space-y-5">
          <!-- Summary Box -->
          <div class="p-4 rounded-2xl bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200/80 dark:border-indigo-900/60 space-y-3">
            <div class="flex items-center justify-between">
              <div>
                <span class="text-xs text-indigo-700 dark:text-indigo-300 font-medium">کل رکوردهای واجد شرایط:</span>
                <div class="text-2xl font-black text-indigo-800 dark:text-indigo-200 mt-0.5">
                  {{ toPersianDigits(previewData?.affected_count ?? 0) }} <span class="text-xs font-normal">مورد</span>
                </div>
              </div>
              <div class="text-right">
                <span class="text-xs text-indigo-700 dark:text-indigo-300 font-medium">عملیات:</span>
                <div class="text-xs font-bold text-indigo-900 dark:text-indigo-100 mt-0.5">
                  {{ currentActionDefinition?.name }}
                </div>
              </div>
            </div>

            <!-- Target Breakdown (if products) -->
            <div v-if="entity === 'products'" class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-2.5 border-t border-indigo-200/50 dark:border-indigo-800/50 text-xs">
              <div class="bg-white/60 dark:bg-slate-900/60 p-2 rounded-xl border border-indigo-100 dark:border-indigo-950">
                <span class="text-slate-500 dark:text-slate-400 block text-[11px]">محصولات اصلی:</span>
                <span class="font-bold text-slate-800 dark:text-slate-200">{{ toPersianDigits(previewData?.parent_count ?? previewData?.affected_count ?? 0) }} مورد</span>
              </div>
              <div class="bg-white/60 dark:bg-slate-900/60 p-2 rounded-xl border border-indigo-100 dark:border-indigo-950">
                <span class="text-slate-500 dark:text-slate-400 block text-[11px]">متغیرها (Variations):</span>
                <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ toPersianDigits(previewData?.variation_count ?? 0) }} مورد</span>
              </div>
              <div class="bg-white/60 dark:bg-slate-900/60 p-2 rounded-xl border border-indigo-100 dark:border-indigo-950 col-span-2 sm:col-span-1">
                <span class="text-slate-500 dark:text-slate-400 block text-[11px]">دامنه هدف:</span>
                <span class="font-bold text-emerald-600 dark:text-emerald-400">
                  {{ previewData?.target === 'variations' ? 'فقط متغیرها' : (previewData?.target === 'both' ? 'اصلی + متغیرها' : 'محصولات اصلی') }}
                </span>
              </div>
            </div>
          </div>

          <!-- Warnings Box -->
          <div v-if="previewData?.warnings && previewData.warnings.length > 0" class="space-y-2">
            <div
              v-for="(w, idx) in previewData.warnings"
              :key="idx"
              class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-900/60 text-amber-800 dark:text-amber-200 flex items-start gap-2.5 text-xs"
            >
              <Iconsax name="warning" size="16" class="shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
              <span>{{ w }}</span>
            </div>
          </div>

          <!-- Sample Items Before / After Table -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <h4 class="text-xs font-bold text-slate-700 dark:text-slate-200">
                پیش‌نمایش تغییرات (نمونه {{ previewData?.sample?.length || 0 }} رکورد اولیه):
              </h4>
              <span class="text-[10px] text-slate-400">بدون تغییر در ووکامرس</span>
            </div>

            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden max-h-60 overflow-y-auto">
              <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 dark:bg-slate-850 border-b border-slate-200 dark:border-slate-800 text-slate-500 sticky top-0">
                  <tr>
                    <th class="p-2.5">شناسه</th>
                    <th class="p-2.5">عنوان / نام</th>
                    <th v-if="entity === 'products'" class="p-2.5">نوع</th>
                    <th class="p-2.5">مقدار قبلی</th>
                    <th class="p-2.5">مقدار جدید</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="item in previewData?.sample || []" :key="item.entity_id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                    <td class="p-2.5 text-slate-400 font-semibold">#{{ toPersianDigits(item.entity_id) }}</td>
                    <td class="p-2.5 font-medium text-slate-800 dark:text-slate-100 max-w-[150px] truncate">{{ item.name }}</td>
                    <td v-if="entity === 'products'" class="p-2.5">
                      <span v-if="item.type === 'variation'" class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                        متغیر (#{{ toPersianDigits(item.parent_id) }})
                      </span>
                      <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                        محصول اصلی
                      </span>
                    </td>
                    <td class="p-2.5 text-slate-500 text-[11px] max-w-[140px] truncate dir-ltr text-right">
                      {{ formatValue(item.old_value) }}
                    </td>
                    <td class="p-2.5 text-emerald-600 dark:text-emerald-400 font-bold text-[11px] max-w-[140px] truncate dir-ltr text-right">
                      {{ formatValue(item.new_value) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- STEP 3: Final Confirmation & Progress Execution -->
        <div v-else-if="step === 3" class="space-y-6">
          <!-- Confirmation Checkbox (Before processing) -->
          <div v-if="!isProcessing && !operationResult" class="p-5 rounded-2xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200/80 dark:border-rose-900/60 space-y-4">
            <div class="flex items-start gap-3">
              <Iconsax name="warning" size="24" class="text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
              <div class="space-y-1">
                <h4 class="font-bold text-sm text-rose-900 dark:text-rose-100">تایید نهایی اجرای عملیات گروهی</h4>
                <p class="text-xs text-rose-700 dark:text-rose-300 leading-relaxed">
                  تعداد <strong>{{ toPersianDigits(previewData?.affected_count ?? 0) }}</strong> رکورد از موجودیت «{{ entityLabel }}» به صورت مستقیم تغییر خواهند کرد. این فرآیند قابل بازگردانی خودکار نیست.
                </p>
              </div>
            </div>

            <label class="flex items-center gap-2.5 pt-2 border-t border-rose-200/60 dark:border-rose-900/60 cursor-pointer">
              <input type="checkbox" v-model="userConfirmed" class="rounded text-rose-600 focus:ring-0" />
              <span class="text-xs font-bold text-slate-800 dark:text-slate-100">از اجرای این تغییرات اطمینان دارم و عواقب آن را می‌پذیرم.</span>
            </label>
          </div>

          <!-- Progress Bar & Status (While processing) -->
          <div v-if="isProcessing || operationResult" class="space-y-4">
            <div class="flex items-center justify-between text-xs">
              <span class="font-bold text-slate-800 dark:text-slate-100">
                وضعیت: {{ getStatusLabel(currentProgress?.status || 'processing') }}
              </span>
              <span class="font-bold text-indigo-600 dark:text-indigo-400">
                {{ formatPercent(currentProgress?.percent ?? 100) }}
              </span>
            </div>

            <!-- Progress Track -->
            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden">
              <div
                class="bg-indigo-600 h-full transition-all duration-300 rounded-full"
                :style="{ width: `${currentProgress?.percent ?? 100}%` }"
              ></div>
            </div>

            <!-- Progress Stats -->
            <div class="grid grid-cols-4 gap-2 text-center text-xs">
              <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                <div class="text-slate-400 text-[10px]">کل</div>
                <div class="font-bold text-slate-800 dark:text-slate-100 mt-0.5">{{ formatNumber(currentProgress?.total_items ?? previewData?.affected_count ?? 0) }}</div>
              </div>
              <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/60">
                <div class="text-emerald-600 dark:text-emerald-400 text-[10px]">موفق</div>
                <div class="font-bold text-emerald-700 dark:text-emerald-300 mt-0.5">{{ formatNumber(currentProgress?.success_items ?? 0) }}</div>
              </div>
              <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/60">
                <div class="text-amber-600 dark:text-amber-400 text-[10px]">رد شده</div>
                <div class="font-bold text-amber-700 dark:text-amber-300 mt-0.5">{{ formatNumber(currentProgress?.skipped_items ?? 0) }}</div>
              </div>
              <div class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60">
                <div class="text-rose-600 dark:text-rose-400 text-[10px]">ناموفق</div>
                <div class="font-bold text-rose-700 dark:text-rose-300 mt-0.5">{{ formatNumber(currentProgress?.failed_items ?? 0) }}</div>
              </div>
            </div>
          </div>

          <!-- Final Success Alert -->
          <div v-if="operationResult && !isProcessing" class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-xs space-y-1">
            <h4 class="font-bold text-emerald-800 dark:text-emerald-200">عملیات گروهی با موفقیت به پایان رسید</h4>
            <p class="text-emerald-700 dark:text-emerald-300">
              تغییرات با موفقیت پردازش شدند و اطلاعات در سیستم و ووکامرس ثبت گردید.
            </p>
          </div>
        </div>
      </div>

      <!-- Modal Footer Controls -->
      <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0 bg-slate-50/50 dark:bg-slate-850/50">
        <!-- Back Button -->
        <div>
          <button
            v-if="step > 1 && !isProcessing && !operationResult"
            @click="step--"
            class="py-2 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
          >
            بازگشت
          </button>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2">
          <!-- Cancel execution if running -->
          <button
            v-if="isProcessing && currentProgress?.id"
            @click="cancelCurrentOperation"
            class="py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-colors"
          >
            لغو عملیات
          </button>

          <!-- Step 1 -> Step 2 (Preview) -->
          <button
            v-if="step === 1"
            @click="loadPreview"
            :disabled="!canProceedStep1 || loadingPreview"
            class="py-2.5 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20 disabled:opacity-50 flex items-center gap-2"
          >
            <span v-if="loadingPreview" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>مشاهده پیش‌نمایش و ارزیابی</span>
          </button>

          <!-- Step 2 -> Step 3 (Go to Confirm) -->
          <button
            v-if="step === 2"
            @click="step = 3"
            class="py-2.5 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all shadow-md shadow-indigo-600/20"
          >
            مرحله بعد (تایید نهایی)
          </button>

          <!-- Step 3 (Execute) -->
          <button
            v-if="step === 3 && !isProcessing && !operationResult"
            @click="executeBulkOperation"
            :disabled="!userConfirmed"
            class="py-2.5 px-6 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-all shadow-md shadow-rose-600/20 disabled:opacity-50"
          >
            اجرای قطعی عملیات
          </button>

          <!-- Finished Close -->
          <button
            v-if="operationResult && !isProcessing"
            @click="finishAndClose"
            class="py-2.5 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all"
          >
            بستن و بازخوانی جدول
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import Iconsax from '@/components/icons/Iconsax.vue';
import api from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import { toPersianDigits, formatNumber, formatPercent } from '@/utils/formatters';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  entity: { type: String, required: true }, // 'products' | 'orders' | 'customers'
  selectedIds: { type: Array, default: () => [] },
  filter: { type: Object, default: () => ({}) },
  selectionMode: { type: String, default: 'ids' }, // 'ids' | 'filter'
  initialActionType: { type: String, default: '' },
  categoriesList: { type: Array, default: () => [] },
  tagsList: { type: Array, default: () => [] },
  orderStatusesList: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'completed']);
const notification = useNotificationStore();

const step = ref(1);
const selectedActionType = ref('');
const actionParams = reactive({
  target: 'parent',
  value: null,
  status: '',
  category_id: 0,
  tag_id: 0,
  tag_name: '',
  color: '#4F46E5',
  note: '',
  customer_note: false,
  title: '',
  priority: 'medium',
  due_date: '',
});

const loadingPreview = ref(false);
const previewData = ref(null);
const userConfirmed = ref(false);
const isProcessing = ref(false);
const currentProgress = ref(null);
const operationResult = ref(null);

const entityLabel = computed(() => {
  switch (props.entity) {
    case 'products': return 'محصولات';
    case 'orders': return 'سفارش‌ها';
    case 'customers': return 'مشتریان';
    default: return props.entity;
  }
});

const scopeSummary = computed(() => {
  if (props.selectionMode === 'ids') {
    return `${props.selectedIds.length} رکورد به صورت دستی انتخاب شده`;
  }
  return 'تمام رکوردهای مطابق با فیلترهای فعال در سامانه';
});

// Action definitions grouped
const actionOptionsGrouped = computed(() => {
  if (props.entity === 'products') {
    return {
      'تغییرات قیمت': [
        { key: 'increase_price_percent', name: 'افزایش درصد قیمت (+%)', description: 'افزایش قیمت عادی بر اساس درصد مشخص' },
        { key: 'decrease_price_percent', name: 'کاهش درصد قیمت (-%)', description: 'کاهش قیمت عادی بر اساس درصد مشخص' },
        { key: 'increase_price_amount', name: 'افزایش مبلغ ثابت (+)', description: 'افزایش مبلغ ثابت به قیمت عادی' },
        { key: 'decrease_price_amount', name: 'کاهش مبلغ ثابت (-)', description: 'کاهش مبلغ ثابت از قیمت عادی' },
        { key: 'set_regular_price', name: 'تنظیم قیمت عادی مشخص', description: 'تعیین قیمت عادی مشخص برای کالاها' },
        { key: 'set_sale_price', name: 'تنظیم قیمت ویژه (تخفیف)', description: 'تعیین قیمت حراجی برای کالاها' },
        { key: 'clear_sale_price', name: 'حذف قیمت ویژه (لغو تخفیف)', description: 'حذف تخفیف و بازگردانی قیمت به عادی' },
      ],
      'مدیریت موجودی و انبار': [
        { key: 'set_stock', name: 'تنظیم موجودی انبار', description: 'تعیین تعداد مشخص موجودی کالا' },
        { key: 'increase_stock', name: 'افزایش موجودی (+)', description: 'افزایش تعداد موجودی کالا' },
        { key: 'decrease_stock', name: 'کاهش موجودی (-)', description: 'کاهش تعداد موجودی کالا' },
        { key: 'set_stock_status', name: 'تنظیم وضعیت موجودی انبار', description: 'تغییر وضعیت به موجود یا ناموجود' },
      ],
      'دسته‌بندی و برچسب': [
        { key: 'add_category', name: 'افزودن دسته‌بندی', description: 'افزودن دسته‌بندی با حفظ دسته‌های قبلی' },
        { key: 'remove_category', name: 'حذف دسته‌بندی', description: 'حذف یک دسته‌بندی از کالاها' },
        { key: 'add_tag', name: 'افزودن برچسب', description: 'افزودن برچسب با حفظ برچسب‌های قبلی' },
        { key: 'remove_tag', name: 'حذف برچسب', description: 'حذف یک برچسب مشخص از کالاها' },
      ],
      'وضعیت انتشار': [
        { key: 'set_status', name: 'تغییر وضعیت انتشار', description: 'تغییر وضعیت به منتشر شده، پیش‌نویس یا خصوصی' },
      ],
    };
  }

  if (props.entity === 'orders') {
    return {
      'عملیات سفارش': [
        { key: 'change_status', name: 'تغییر وضعیت سفارش‌ها', description: 'تغییر وضعیت سفارشات بر اساس وضعیت‌های معتبر ووکامرس' },
        { key: 'add_note', name: 'افزودن یادداشت گروهی', description: 'ثبت یک یادداشت مشترک برای سفارش‌ها' },
      ],
    };
  }

  if (props.entity === 'customers') {
    return {
      'مدیریت مشتریان (CRM)': [
        { key: 'add_tag', name: 'افزودن برچسب CRM', description: 'افزودن برچسب به مشتریان در پایگاه داده CRM' },
        { key: 'remove_tag', name: 'حذف برچسب CRM', description: 'حذف برچسب از مشتریان' },
        { key: 'create_task', name: 'ایجاد وظیفه پیگیری (تسک)', description: 'تعریف تسک برای تیم فروش/پشتیبانی' },
      ],
    };
  }

  return {};
});

const currentActionDefinition = computed(() => {
  for (const group of Object.values(actionOptionsGrouped.value)) {
    const found = group.find(a => a.key === selectedActionType.value);
    if (found) return found;
  }
  return null;
});

const needsValueInput = computed(() => {
  const act = selectedActionType.value;
  return [
    'increase_price_percent', 'decrease_price_percent',
    'increase_price_amount', 'decrease_price_amount',
    'set_regular_price', 'set_sale_price',
    'set_stock', 'increase_stock', 'decrease_stock',
  ].includes(act);
});

const valueInputLabel = computed(() => {
  switch (selectedActionType.value) {
    case 'increase_price_percent': return 'درصد افزایش:';
    case 'decrease_price_percent': return 'درصد کاهش:';
    case 'increase_price_amount': return 'مبلغ افزایش:';
    case 'decrease_price_amount': return 'مبلغ کاهش:';
    case 'set_regular_price': return 'قیمت عادی جدید:';
    case 'set_sale_price': return 'قیمت ویژه جدید:';
    case 'set_stock': return 'تعداد جدید موجودی:';
    case 'increase_stock': return 'تعداد افزایش موجودی:';
    case 'decrease_stock': return 'تعداد کاهش موجودی:';
    default: return 'مقدار:';
  }
});

const valueInputUnit = computed(() => {
  if (selectedActionType.value.includes('percent')) return '٪';
  if (selectedActionType.value.includes('stock')) return 'عدد';
  return 'ریال';
});

const valueInputPlaceholder = computed(() => {
  if (selectedActionType.value.includes('percent')) return 'مثلاً 10';
  if (selectedActionType.value.includes('stock')) return 'مثلاً 50';
  return 'مثلاً 100000';
});

const canProceedStep1 = computed(() => {
  if (!selectedActionType.value) return false;
  if (needsValueInput.value) {
    return actionParams.value !== null && actionParams.value !== '';
  }
  if (selectedActionType.value === 'set_stock_status') return !!actionParams.status;
  if (selectedActionType.value === 'add_category' || selectedActionType.value === 'remove_category') return actionParams.category_id > 0;
  if (selectedActionType.value === 'add_tag' || selectedActionType.value === 'remove_tag') {
    if (props.entity === 'products') return actionParams.tag_id > 0;
    return !!actionParams.tag_name;
  }
  if (selectedActionType.value === 'change_status') return !!actionParams.status;
  if (selectedActionType.value === 'add_note') return !!actionParams.note;
  if (selectedActionType.value === 'create_task') return !!actionParams.title;
  return true;
});

const handleActionTypeChange = () => {
  actionParams.value = null;
  actionParams.status = (props.entity === 'orders') ? (props.orderStatusesList[0]?.slug || 'processing') : 'publish';
  actionParams.category_id = props.categoriesList[0]?.id || 0;
  actionParams.tag_id = props.tagsList[0]?.id || 0;
  actionParams.tag_name = '';
  actionParams.note = '';
  actionParams.title = '';
};

// Reset on open
watch(() => props.isOpen, (open) => {
  if (open) {
    step.value = 1;
    previewData.value = null;
    userConfirmed.value = false;
    isProcessing.value = false;
    operationResult.value = null;
    currentProgress.value = null;
    if (props.initialActionType) {
      selectedActionType.value = props.initialActionType;
      handleActionTypeChange();
    } else {
      selectedActionType.value = '';
    }
  }
});

const buildPayload = () => {
  const selection = props.selectionMode === 'ids'
    ? { mode: 'ids', ids: props.selectedIds }
    : { mode: 'filter', filter: props.filter };

  return {
    entity: props.entity,
    selection,
    filter: props.filter,
    action: {
      type: selectedActionType.value,
      ...actionParams,
    },
  };
};

const loadPreview = async () => {
  loadingPreview.value = true;
  try {
    const res = await api.post('/bulk-operations/preview', buildPayload());
    if (res?.success !== false) {
      previewData.value = res.data;
      step.value = 2;
    } else {
      notification.error(res.error?.message || 'خطا در دریافت پیش‌نمایش');
    }
  } catch (err) {
    notification.error(err.message || 'خطا در دریافت پیش‌نمایش');
  } finally {
    loadingPreview.value = false;
  }
};

const executeBulkOperation = async () => {
  isProcessing.value = true;
  try {
    const res = await api.post('/bulk-operations', buildPayload());
    if (res?.success !== false) {
      operationResult.value = res.data;
      currentProgress.value = res.data;
      notification.success('عملیات گروهی با موفقیت اجرا شد.');
      emit('completed', res.data);
    } else {
      notification.error(res.error?.message || 'خطا در اجرای عملیات');
    }
  } catch (err) {
    notification.error(err.message || 'خطا در اجرای عملیات');
  } finally {
    isProcessing.value = false;
  }
};

const cancelCurrentOperation = async () => {
  if (!currentProgress.value?.id) return;
  try {
    const res = await api.post(`/bulk-operations/${currentProgress.value.id}/cancel`);
    if (res?.success !== false) {
      notification.info('دستور لغو ارسال شد.');
      currentProgress.value = res.data;
      isProcessing.value = false;
    }
  } catch (err) {
    notification.error(err.message || 'خطا در لغو عملیات');
  }
};

const finishAndClose = () => {
  emit('close');
};

const closeModal = () => {
  if (!isProcessing.value) {
    emit('close');
  }
};

const formatValue = (val) => {
  if (val === null || val === undefined) return '-';
  if (typeof val === 'object') {
    return Object.entries(val)
      .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`)
      .join(' | ');
  }
  return String(val);
};

const getStatusLabel = (status) => {
  switch (status) {
    case 'completed': return 'تکمیل شده';
    case 'partial': return 'تکمیل جزئی';
    case 'processing': return 'در حال پردازش';
    case 'pending': return 'در انتظار';
    case 'cancelled': return 'لغو شده';
    case 'failed': return 'ناموفق';
    default: return status;
  }
};
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}
.animate-fadeIn {
  animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
