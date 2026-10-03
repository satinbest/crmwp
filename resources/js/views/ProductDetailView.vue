<template>
  <div class="space-y-6">
    <!-- Breadcrumb & Back -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2 text-xs text-slate-400">
        <router-link to="/products" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
          محصولات
        </router-link>
        <span>/</span>
        <span class="text-slate-600 dark:text-slate-200 font-medium">جزئیات و ویرایش محصول</span>
      </div>

      <router-link
        to="/products"
        class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-100 flex items-center gap-1.5 transition-colors"
      >
        <Iconsax name="arrow-left" size="16" />
        <span>بازگشت به فهرست محصولات</span>
      </router-link>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 space-y-6 animate-pulse">
      <div class="h-8 bg-slate-200 dark:bg-slate-800 rounded w-1/3"></div>
      <div class="h-4 bg-slate-100 dark:bg-slate-850 rounded w-1/4"></div>
      <div class="grid grid-cols-3 gap-6 pt-6">
        <div class="h-40 bg-slate-100 dark:bg-slate-800 rounded-2xl"></div>
        <div class="h-40 bg-slate-100 dark:bg-slate-800 rounded-2xl"></div>
        <div class="h-40 bg-slate-100 dark:bg-slate-800 rounded-2xl"></div>
      </div>
    </div>

    <!-- Product Loaded -->
    <div v-else-if="product" class="space-y-6">
      <!-- Top Action Bar Card -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden flex items-center justify-center shrink-0">
            <img
              v-if="product.primary_image"
              :src="product.primary_image"
              :alt="product.name"
              class="w-full h-full object-cover"
            />
            <Iconsax v-else name="image" size="24" class="text-slate-400" />
          </div>

          <div>
            <div class="flex items-center gap-2 mb-1 flex-wrap">
              <span class="px-2 py-0.5 rounded-full text-[11px] font-medium" :class="getTypeBadgeClass(product.type)">
                {{ product.type_label }}
              </span>
              <span class="text-xs text-slate-400">#{{ toPersianDigits(product.id) }}</span>
              <span v-if="product.sku" class="text-xs text-slate-500 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">
                <span>کد کالا: </span><span class="dir-ltr inline-block">{{ product.sku }}</span>
              </span>
            </div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">
              {{ product.name }}
            </h1>
          </div>
        </div>

        <div class="flex items-center gap-3 self-end md:self-auto">
          <!-- Status Selector -->
          <div class="flex items-center gap-2">
            <label class="text-xs text-slate-400">وضعیت:</label>
            <select
              v-model="form.status"
              class="text-xs py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="publish">منتشر شده</option>
              <option value="draft">پیش‌نویس</option>
              <option value="pending">در انتظار بررسی</option>
              <option value="private">خصوصی</option>
            </select>
          </div>

          <!-- Save Button -->
          <button
            @click="saveProduct"
            :disabled="saving"
            class="py-2.5 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/20 transition-all disabled:opacity-50"
          >
            <Iconsax v-if="!saving" name="tick" size="18" />
            <div v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            <span>{{ saving ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}</span>
          </button>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex border-b border-slate-200 dark:border-slate-800 overflow-x-auto gap-2">
        <button
          v-for="tab in availableTabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          class="py-3 px-4 text-xs font-bold whitespace-nowrap border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === tab.id ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
        >
          <Iconsax :name="tab.icon" size="16" />
          <span>{{ tab.label }}</span>
          <span v-if="tab.id === 'variations' && product.type === 'variable'" class="px-1.5 py-0.5 rounded-full text-[10px] bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
            {{ variations.length }}
          </span>
        </button>
      </div>

      <!-- Tab 1: Overview -->
      <div v-show="activeTab === 'overview'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-6">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">اطلاعات اصلی و عنوان کالا</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">نام محصول *</label>
            <input
              v-model="form.name"
              type="text"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">نامک (Slug)</label>
            <input
              v-model="form.slug"
              type="text"
              dir="ltr"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">نوع محصول</label>
            <input
              type="text"
              :value="product.type_label"
              disabled
              class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800/50 text-slate-500 cursor-not-allowed"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">نمایش در کاتالوگ</label>
            <select
              v-model="form.catalog_visibility"
              class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="visible">فروشگاه و نتایج جستجو</option>
              <option value="catalog">فقط فروشگاه</option>
              <option value="search">فقط نتایج جستجو</option>
              <option value="hidden">مخفی</option>
            </select>
          </div>

          <div class="flex items-center gap-2 pt-6">
            <input type="checkbox" id="featured" v-model="form.featured" class="rounded text-indigo-600 focus:ring-0" />
            <label for="featured" class="text-xs text-slate-700 dark:text-slate-300 cursor-pointer font-medium">محصول ویژه (Featured)</label>
          </div>
        </div>

        <!-- Descriptions -->
        <div class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">توضیحات کوتاه محصول</label>
            <textarea
              v-model="form.short_description"
              rows="3"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 leading-relaxed"
              placeholder="توضیحات مختصر بالای صفحه محصول..."
            ></textarea>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">توضیحات کامل محصول</label>
            <textarea
              v-model="form.description"
              rows="6"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 leading-relaxed"
              placeholder="متن کامل یا کدهای HTML استاندارد محصول..."
            ></textarea>
          </div>
        </div>
      </div>

      <!-- Tab 2: Pricing -->
      <div v-show="activeTab === 'pricing'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-6">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">تنظیمات قیمت و حراجی</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">قیمت عادی (تومان)</label>
            <input
              v-model="form.regular_price"
              type="number"
              min="0"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <span class="text-[11px] text-slate-400 mt-1 block">
              معادل: {{ formatPrice(form.regular_price) }}
            </span>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">قیمت فروش ویژه (تومان)</label>
            <input
              v-model="form.sale_price"
              type="number"
              min="0"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <span class="text-[11px] text-slate-400 mt-1 block">
              معادل: {{ formatPrice(form.sale_price) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Tab 3: Inventory -->
      <div v-show="activeTab === 'inventory'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-6">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">مدیریت انبار و موجودی کالا</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">شناسه کالا (SKU)</label>
            <input
              v-model="form.sku"
              type="text"
              dir="ltr"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">وضعیت انبار</label>
            <select
              v-model="form.stock_status"
              class="w-full text-xs p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="instock">موجود در انبار</option>
              <option value="outofstock">ناموجود</option>
              <option value="onbackorder">در پیش‌خرید</option>
            </select>
          </div>
        </div>

        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-100 dark:border-slate-800 space-y-4">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.manage_stock" class="rounded text-indigo-600 focus:ring-0" />
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">ردیابی و مدیریت تعداد موجودی در انبار</span>
          </label>

          <div v-if="form.manage_stock" class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
            <div>
              <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">تعداد موجودی</label>
              <input
                v-model="form.stock_quantity"
                type="number"
                min="0"
                class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>

            <div>
              <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">پیش‌خرید (Backorders)</label>
              <select
                v-model="form.backorders"
                class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="no">مجاز نیست</option>
                <option value="notify">مجاز با اطلاع به مشتری</option>
                <option value="yes">مجاز بدون محدودیت</option>
              </select>
            </div>

            <div>
              <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">آستانه هشدار کمبود موجودی</label>
              <input
                v-model="form.low_stock_amount"
                type="number"
                min="0"
                class="w-full text-xs p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between p-4 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-100 dark:border-slate-800">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.sold_individually" class="rounded text-indigo-600 focus:ring-0" />
            <div>
              <span class="text-xs font-bold text-slate-700 dark:text-slate-200">فروش تکی کالا</span>
              <p class="text-[11px] text-slate-400">مشتری در هر سفارش فقط یک عدد از این کالا را می‌تواند خرید کند</p>
            </div>
          </label>

          <router-link
            :to="'/inventory?search=' + (product.sku || product.id)"
            class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
          >
            <span>مدیریت پیشرفته در انبارداری</span>
            <Iconsax name="arrow-left" size="14" />
          </router-link>
        </div>
      </div>

      <!-- Tab 4: Variations (Variable Products) -->
      <div v-show="activeTab === 'variations'" class="space-y-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">مدیریت تنوع‌های کالایی (Variations)</h3>
              <p class="text-xs text-slate-400 mt-0.5">قیمت، موجودی انبار و ویژگی‌های هر متغیر</p>
            </div>

            <button
              @click="openCreateVariationModal"
              class="py-2 px-3.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 text-xs font-bold hover:bg-indigo-100 transition-colors flex items-center gap-1.5"
            >
              <Iconsax name="add" size="16" />
              <span>افزودن متغیر جدید</span>
            </button>
          </div>

          <div v-if="loadingVariations" class="p-6 text-center text-xs text-slate-400">
            در حال بارگذاری متغیرها...
          </div>

          <div v-else-if="variations.length === 0" class="p-6 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
            هیچ متغیری برای این محصول تعریف نشده است.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-right text-xs">
              <thead class="bg-slate-50 dark:bg-slate-850/60 border-b border-slate-100 dark:border-slate-800 text-slate-400">
                <tr>
                  <th class="p-3">شناسه</th>
                  <th class="p-3">ویژگی‌ها</th>
                  <th class="p-3">SKU</th>
                  <th class="p-3">قیمت عادی</th>
                  <th class="p-3">قیمت حراج</th>
                  <th class="p-3">موجودی</th>
                  <th class="p-3">وضعیت</th>
                  <th class="p-3 text-center">عملیات</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="v in variations" :key="v.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                  <td class="p-3">#{{ toPersianDigits(v.id) }}</td>
                  <td class="p-3">
                    <span
                      v-for="a in v.attributes"
                      :key="a.name"
                      class="inline-block bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded text-[11px] font-medium mr-1"
                    >
                      {{ a.name }}: {{ a.option }}
                    </span>
                  </td>
                  <td class="p-3 text-slate-500 dir-ltr text-right">{{ v.sku || '—' }}</td>
                  <td class="p-3 font-bold">{{ formatPrice(v.regular_price) }}</td>
                  <td class="p-3 font-bold text-indigo-600">{{ v.sale_price ? formatPrice(v.sale_price) : '—' }}</td>
                  <td class="p-3">
                    <span :class="v.stock_status === 'instock' ? 'text-emerald-600' : 'text-rose-600'">
                      {{ v.stock_status_label }}
                    </span>
                    <span v-if="v.manage_stock && v.stock_quantity !== null" class="text-slate-400 text-[10px] mr-1">
                      ({{ formatNumber(v.stock_quantity) }})
                    </span>
                  </td>
                  <td class="p-3">
                    <span class="px-2 py-0.5 rounded-full text-[10px]" :class="getTypeBadgeClass(v.status)">
                      {{ v.status_label }}
                    </span>
                  </td>
                  <td class="p-3 text-center whitespace-nowrap">
                    <div class="flex items-center justify-center gap-1">
                      <button
                        @click="editVariation(v)"
                        class="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-indigo-600"
                        title="ویرایش متغیر"
                      >
                        <Iconsax name="edit" size="15" />
                      </button>
                      <button
                        @click="deleteVariation(v.id)"
                        class="p-1 rounded hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-500 hover:text-rose-600"
                        title="حذف متغیر"
                      >
                        <Iconsax name="trash" size="15" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tab 5: Categories & Taxonomies -->
      <div v-show="activeTab === 'taxonomies'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-6">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">دسته‌بندی‌ها و برچسب‌های کالا</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Categories list selection -->
          <div class="space-y-3">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">دسته‌بندی‌های کالا</label>
            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-850 max-h-60 overflow-y-auto space-y-2">
              <label
                v-for="cat in allCategories"
                :key="cat.id"
                class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer hover:bg-slate-200/60 dark:hover:bg-slate-800 p-1.5 rounded-lg"
              >
                <input
                  type="checkbox"
                  :value="cat.id"
                  v-model="selectedCategoryIds"
                  class="rounded text-indigo-600 focus:ring-0"
                />
                <span>{{ cat.name }}</span>
              </label>
            </div>
          </div>

          <!-- Tags list selection -->
          <div class="space-y-3">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">برچسب‌های کالا</label>
            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-850 max-h-60 overflow-y-auto space-y-2">
              <label
                v-for="tag in allTags"
                :key="tag.id"
                class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 cursor-pointer hover:bg-slate-200/60 dark:hover:bg-slate-800 p-1.5 rounded-lg"
              >
                <input
                  type="checkbox"
                  :value="tag.id"
                  v-model="selectedTagIds"
                  class="rounded text-indigo-600 focus:ring-0"
                />
                <span>{{ tag.name }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 6: Images -->
      <div v-show="activeTab === 'images'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-6">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">گالری تصاویر محصول</h3>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div
            v-for="(img, idx) in product.images"
            :key="idx"
            class="relative rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700 aspect-square bg-slate-50 dark:bg-slate-800 group"
          >
            <img :src="img.src" :alt="img.name" class="w-full h-full object-cover" />
            <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
              <span class="text-xs text-white bg-slate-900/80 px-2 py-1 rounded-lg">تصویر #{{ idx + 1 }}</span>
            </div>
          </div>

          <div v-if="!product.images?.length" class="col-span-full p-8 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-700 rounded-2xl">
            هیچ تصویری برای این کالا ثبت نشده است.
          </div>
        </div>
      </div>

      <!-- Tab 7: Additional Metadata -->
      <div v-show="activeTab === 'meta'" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">داده‌های تکمیلی و متادیتا (Extensions / Custom Fields)</h3>
        <p class="text-xs text-slate-400">فیلدهای سفارشی و متادیتای افزونه‌های ووکامرس بدون حذف داده‌های ناشناخته حفظ می‌شوند.</p>

        <div v-if="Object.keys(product.meta || {}).length === 0" class="p-6 text-center text-xs text-slate-400">
          متادیتای سفارشی موجود نیست.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="(val, key) in product.meta"
            :key="key"
            class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-850 border border-slate-100 dark:border-slate-800 text-xs"
          >
            <span class="text-slate-600 dark:text-slate-300 dir-ltr text-left">{{ key }}</span>
            <span class="text-slate-500 dir-ltr text-left">{{ typeof val === 'object' ? JSON.stringify(val) : val }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Variation Edit/Create Modal -->
    <div
      v-if="isVariationModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
        <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">
          {{ variationForm.id ? 'ویرایش تنوع کالایی' : 'ایجاد تنوع کالایی جدید' }}
        </h3>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-600 dark:text-slate-400 mb-1">شناسه SKU متغیر</label>
            <input
              v-model="variationForm.sku"
              type="text"
              dir="ltr"
              class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-600 dark:text-slate-400 mb-1">قیمت عادی (تومان) *</label>
              <input
                v-model="variationForm.regular_price"
                type="number"
                class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label class="block text-slate-600 dark:text-slate-400 mb-1">قیمت حراج (تومان)</label>
              <input
                v-model="variationForm.sale_price"
                type="number"
                class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-600 dark:text-slate-400 mb-1">وضعیت موجودی</label>
              <select
                v-model="variationForm.stock_status"
                class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="instock">موجود در انبار</option>
                <option value="outofstock">ناموجود</option>
                <option value="onbackorder">در پیش‌خرید</option>
              </select>
            </div>
            <div>
              <label class="block text-slate-600 dark:text-slate-400 mb-1">تعداد موجودی</label>
              <input
                v-model="variationForm.stock_quantity"
                type="number"
                class="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3 pt-3">
          <button
            @click="saveVariation"
            :disabled="savingVariation"
            class="flex-1 py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-colors disabled:opacity-50"
          >
            {{ savingVariation ? 'در حال ثبت...' : 'ذخیره تنوع کالایی' }}
          </button>
          <button
            @click="isVariationModalOpen = false"
            class="py-2.5 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
          >
            انصراف
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Iconsax from '@/components/icons/Iconsax.vue';
import api from '@/api/client';
import { useNotificationStore } from '@/stores/notification';
import { formatNumber, formatPrice, toPersianDigits } from '@/utils/formatters';

const route = useRoute();
const notification = useNotificationStore();

const productId = route.params.id;
const product = ref(null);
const variations = ref([]);
const allCategories = ref([]);
const allTags = ref([]);
const selectedCategoryIds = ref([]);
const selectedTagIds = ref([]);

const loading = ref(true);
const saving = ref(false);
const loadingVariations = ref(false);
const activeTab = ref('overview');

const isVariationModalOpen = ref(false);
const savingVariation = ref(false);
const variationForm = reactive({
  id: null,
  sku: '',
  regular_price: '',
  sale_price: '',
  stock_status: 'instock',
  stock_quantity: null,
});

const form = reactive({
  name: '',
  slug: '',
  status: 'publish',
  catalog_visibility: 'visible',
  featured: false,
  short_description: '',
  description: '',
  sku: '',
  regular_price: '',
  sale_price: '',
  manage_stock: false,
  stock_quantity: null,
  stock_status: 'instock',
  backorders: 'no',
  low_stock_amount: null,
  sold_individually: false,
});

const availableTabs = computed(() => {
  const tabs = [
    { id: 'overview', label: 'اطلاعات اصلی', icon: 'document' },
    { id: 'pricing', label: 'قیمت‌گذاری', icon: 'coin' },
    { id: 'inventory', label: 'انبارداری', icon: 'inventory' },
    { id: 'taxonomies', label: 'دسته‌بندی و برچسب', icon: 'tag' },
    { id: 'images', label: 'تصاویر کالا', icon: 'image' },
    { id: 'meta', label: 'داده‌های تکمیلی', icon: 'settings' },
  ];

  if (product.value?.type === 'variable') {
    tabs.splice(3, 0, { id: 'variations', label: 'متغیرها', icon: 'products' });
  }

  return tabs;
});

const fetchProduct = async () => {
  loading.value = true;
  try {
    const res = await api.get(`/products/${productId}`);
    if (res.data) {
      product.value = res.data;

      // Populate form
      form.name = product.value.name;
      form.slug = product.value.slug;
      form.status = product.value.status;
      form.catalog_visibility = product.value.catalog_visibility;
      form.featured = product.value.featured;
      form.short_description = product.value.short_description;
      form.description = product.value.description;
      form.sku = product.value.sku;
      form.regular_price = product.value.regular_price;
      form.sale_price = product.value.sale_price || '';
      form.manage_stock = product.value.manage_stock;
      form.stock_quantity = product.value.stock_quantity;
      form.stock_status = product.value.stock_status;
      form.backorders = product.value.backorders;
      form.low_stock_amount = product.value.low_stock_amount;
      form.sold_individually = !!product.value.sold_individually;

      selectedCategoryIds.value = (product.value.categories || []).map((c) => c.id);
      selectedTagIds.value = (product.value.tags || []).map((t) => t.id);

      if (product.value.type === 'variable') {
        fetchVariations();
      }
    }
  } catch (err) {
    notification.error(err.message || 'خطا در بارگذاری اطلاعات محصول');
  } finally {
    loading.value = false;
  }
};

const fetchVariations = async () => {
  loadingVariations.value = true;
  try {
    const res = await api.get(`/products/${productId}/variations`);
    variations.value = res.data || [];
  } catch (err) {
    console.error('Variations error:', err);
  } finally {
    loadingVariations.value = false;
  }
};

const fetchTaxonomies = async () => {
  try {
    const [catRes, tagRes] = await Promise.all([
      api.get('/product-categories'),
      api.get('/product-tags'),
    ]);
    allCategories.value = catRes.data || [];
    allTags.value = tagRes.data || [];
  } catch (err) {
    console.error('Taxonomies error:', err);
  }
};

const saveProduct = async () => {
  saving.value = true;
  try {
    const payload = {
      name: form.name,
      slug: form.slug,
      status: form.status,
      catalog_visibility: form.catalog_visibility,
      featured: form.featured,
      short_description: form.short_description,
      description: form.description,
      sku: form.sku,
      regular_price: String(form.regular_price),
      sale_price: String(form.sale_price),
      manage_stock: form.manage_stock,
      stock_quantity: form.manage_stock ? Number(form.stock_quantity) : null,
      stock_status: form.stock_status,
      backorders: form.backorders,
      low_stock_amount: form.low_stock_amount ? Number(form.low_stock_amount) : null,
      categories: selectedCategoryIds.value.map((id) => ({ id })),
      tags: selectedTagIds.value.map((id) => ({ id })),
    };

    const res = await api.patch(`/products/${productId}`, payload);
    if (res.data) {
      product.value = res.data;
      notification.success('تغییرات محصول با موفقیت در ووکامرس ذخیره شد.');
    }
  } catch (err) {
    notification.error(err.message || 'خطا در ذخیره تغییرات محصول');
  } finally {
    saving.value = false;
  }
};

const openCreateVariationModal = () => {
  variationForm.id = null;
  variationForm.sku = '';
  variationForm.regular_price = form.regular_price;
  variationForm.sale_price = '';
  variationForm.stock_status = 'instock';
  variationForm.stock_quantity = 5;
  isVariationModalOpen.value = true;
};

const editVariation = (v) => {
  variationForm.id = v.id;
  variationForm.sku = v.sku;
  variationForm.regular_price = v.regular_price;
  variationForm.sale_price = v.sale_price || '';
  variationForm.stock_status = v.stock_status;
  variationForm.stock_quantity = v.stock_quantity;
  isVariationModalOpen.value = true;
};

const saveVariation = async () => {
  savingVariation.value = true;
  try {
    const payload = {
      sku: variationForm.sku,
      regular_price: String(variationForm.regular_price),
      sale_price: String(variationForm.sale_price),
      stock_status: variationForm.stock_status,
      manage_stock: true,
      stock_quantity: Number(variationForm.stock_quantity),
    };

    if (variationForm.id) {
      const res = await api.patch(`/products/${productId}/variations/${variationForm.id}`, payload);
      if (res.success || res.data) {
        notification.success('متغیر کالا با موفقیت بروزرسانی شد.');
      }
    } else {
      const res = await api.post(`/products/${productId}/variations`, payload);
      if (res.success || res.data) {
        notification.success('متغیر جدید با موفقیت ایجاد شد.');
      }
    }

    isVariationModalOpen.value = false;
    fetchVariations();
  } catch (err) {
    notification.error(err.message || 'خطا در ثبت متغیر');
  } finally {
    savingVariation.value = false;
  }
};

const deleteVariation = async (varId) => {
  if (!confirm(`آیا از حذف متغیر #${varId} اطمینان دارید؟`)) return;
  try {
    const res = await api.delete(`/products/${productId}/variations/${varId}`);
    if (res.success || res.data) {
      notification.success('متغیر کالا با موفقیت حذف گردید.');
      fetchVariations();
    }
  } catch (err) {
    notification.error(err.message || 'خطا در حذف متغیر');
  }
};


const getTypeBadgeClass = (type) => {
  switch (type) {
    case 'variable':
      return 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400';
    case 'external':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';
    case 'grouped':
      return 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400';
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
  }
};

onMounted(() => {
  fetchTaxonomies();
  fetchProduct();
});
</script>
