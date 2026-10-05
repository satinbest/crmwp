/**
 * Centralized Help & Guidance Directory for Dashboard & System Modules
 * Designed for easy extensibility across all CRM & WooCommerce views.
 */

export const helpContent = {
  // --- Dashboard Primary KPIs ---
  dashboard_sales: {
    title: 'فروش کل',
    description: 'مجموع مبلغ فروش ثبت‌شده در بازه زمانی انتخابی را بر اساس سفارش‌های موفق ووکامرس نمایش می‌دهد.',
    source: 'WooCommerce Reports / Orders API',
    usage: 'برای ارزیابی عملکرد درآمدی فروشگاه و مقایسه دوره‌ای مبالغ کل دریافتی.',
  },
  dashboard_orders: {
    title: 'تعداد سفارش‌ها',
    description: 'تعداد کل سفارش‌های ثبت‌شده در بازه زمانی انتخابی را بر اساس داده‌های زنده ووکامرس نمایش می‌دهد.',
    source: 'WooCommerce Orders API',
    usage: 'نمایانگر حجم تراکنش‌های خرید ثبت‌شده در فروشگاه.',
  },
  dashboard_customers: {
    title: 'مشتریان',
    description: 'اطلاعات مشتریان و روند ثبت و همگام‌سازی خریداران را بر اساس داده‌های واقعی فروشگاه نمایش می‌دهد.',
    source: 'CRM Customer Database & WooCommerce Customers',
    usage: 'برای مشاهده پایگاه کاربران فعال و دسترسی سریع به پرونده‌های مشتریان.',
  },
  dashboard_products: {
    title: 'تنوع محصولات',
    description: 'وضعیت تعداد کل محصولات فعال، تنوع کاتالوگ و گستره کالایی فروشگاه را نمایش می‌دهد.',
    source: 'WooCommerce Products Catalog',
    usage: 'کنترل سریع وضعیت کلی انبار و تنوع اقلام موجود در فروشگاه.',
  },

  // --- Dashboard Secondary Statuses ---
  dashboard_pending_orders: {
    title: 'در انتظار پرداخت و بررسی',
    description: 'سفارش‌هایی که ثبت شده‌اند اما منتظر نهایی‌سازی پرداخت مشتری یا تایید اولیه مدیریت هستند.',
    source: 'سفارش‌های دارای وضعیت Pending / On-Hold',
    usage: 'برای پیگیری خریدهای ناتمام و تبدیل آن‌ها به سفارش قطعی.',
  },
  dashboard_processing_orders: {
    title: 'در حال پردازش و آماده‌سازی',
    description: 'سفارش‌هایی که هزینه آن‌ها با موفقیت پرداخت شده و در مرحله جمع‌آوری و بسته‌بندی انبار قرار دارند.',
    source: 'سفارش‌های دارای وضعیت Processing',
    usage: 'اقدام جهت آماده‌سازی، صدور فاکتور و تحویل به شرکت پست/پیک.',
  },
  dashboard_completed_orders: {
    title: 'سفارش‌های تکمیل‌شده',
    description: 'سفارش‌هایی که با موفقیت تحویل خریدار شده و چرخه خرید آن‌ها پایان یافته است.',
    source: 'سفارش‌های دارای وضعیت Completed',
    usage: 'شاخص موفقیت پردازش نهایی سفارش‌ها در بازه جاری.',
  },
  dashboard_low_stock_kpi: {
    title: 'کالاهای نیازمند تأمین',
    description: 'محصولاتی که موجودی انبار آن‌ها به کمتر از آستانه هشدار تعریف‌شده رسیده یا به صفر رسیده است.',
    source: 'انبارداری ووکامرس (Low Stock / Out of Stock)',
    usage: 'اطلاع‌رسانی سریع به واحد تأمین و انبارداری جهت شارژ موجودی.',
  },

  // --- Dashboard Charts & Widgets ---
  dashboard_sales_chart: {
    title: 'نمودار روند فروش',
    description: 'روند تغییرات فروش روزانه و حجم مبالغ دریافتی فروشگاه را در طول بازه زمانی انتخاب‌شده نمایش می‌دهد.',
    source: 'محاسبه زنده بر اساس سفارش‌های ثبت‌شده در ووکامرس',
    usage: 'شناسایی روزهای اوج فروش، سنجش کمپین‌ها و ارزیابی نوسانات مالی.',
  },
  dashboard_orders_status: {
    title: 'خلاصه وضعیت سفارش‌ها',
    description: 'سهم و توزیع درصدی وضعیت‌های مختلف سفارش‌ها (تکمیل، جاری، لغوشده و معلق) را ترسیم می‌کند.',
    source: 'گزارش تجمیعی گزارشات سفارشات ووکامرس',
    usage: 'بررسی سلامت فرآیند فروش و نظارت بر نرخ سفارش‌های لغوشده یا معلق.',
  },
  dashboard_low_stock_widget: {
    title: 'محصولات کم‌موجودی و ناموجود',
    description: 'فهرست دقیق اقلامی که موجودی آن‌ها به سطح بحرانی رسیده را با نمایش SKU، موجودی و تصویر ارائه می‌دهد.',
    source: 'موجودی لحظه‌ای انبار محصولات (ساده و متغیر)',
    usage: 'ورود مستقیم به صفحه ویرایش محصول جهت به‌روزرسانی آنی تعداد موجودی.',
  },
  dashboard_customers_widget: {
    title: 'مشتریان جدید و برتر',
    description: 'نمایش تازه‌ترین خریداران ثبت‌نامی و همچنین مشتریان با بیشترین حجم خرید و تکرار سفارش.',
    source: 'پایگاه داده ارتباط با مشتریان (CRM) و تاریخچه سفارش‌ها',
    usage: 'شناسایی مشتریان وفادار (VIP) برای اعمال تخفیف‌های ویژه یا کمپین‌های هدفمند.',
  },
  dashboard_recent_activities: {
    title: 'فعالیت‌های اخیر',
    description: 'آخرین رخدادها و لاگ‌های ثبت‌شده در CRM شامل تغییر وضعیت سفارش‌ها، ثبت مشتری، وظایف و عملیات گروهی.',
    source: 'لاگ رویدادها و Audit Trail سامانه',
    usage: 'نظارت بر عملکرد تیم و ردگیری تغییرات سیستم به ترتیب زمان وقوع.',
  },

  // --- Future Extensibility Placeholders ---
  orders_module: {
    title: 'مدیریت سفارش‌ها',
    description: 'مرکز کنترل و پردازش سفارش‌های فروشگاه با امکان فیلتر، تغییر وضعیت و چاپ برچسب.',
  },
  products_module: {
    title: 'مدیریت محصولات',
    description: 'فهرست کامل کالاها با پشتیبانی از قیمت، موجودی، تصاویر و محصولات متغیر.',
  },
  inventory_module: {
    title: 'انبار و موجودی',
    description: 'مدیریت متمرکز موجودی کالاها با امکان ویرایش دسته‌ای و هشدار کسری انبار.',
  },
  crm_module: {
    title: 'مدیریت ارتباط با مشتریان (CRM)',
    description: 'پرونده‌های جامع خریداران، سگمنت‌بندی هوشمند، برچسب‌ها و مدیریت وظایف پشتیبانی.',
  },
  bulk_module: {
    title: 'عملیات گروهی',
    description: 'به‌روزرسانی سریع قیمت‌ها، موجودی انبار و وضعیت محصولات به صورت دسته‌ای و با سرعت بالا.',
  },
  object_cache: {
    title: 'لایه‌بندی Object Cache',
    description: 'کش اشیاء توزیع‌شده با Memcached برای تسریع پاسخ‌دهی و حذف کوئری‌های تکراری.',
  },
};

export function getHelp(key) {
  return helpContent[key] || {
    title: 'راهنما',
    description: 'توضیحات تکمیلی برای این بخش در دسترس نیست.',
  };
}

export default helpContent;
