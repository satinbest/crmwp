// Persian Digits array
const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

const PERSIAN_MONTH_NAMES = [
  'فروردین', 'اردیبهشت', 'خرداد',
  'تیر', 'مرداد', 'شهریور',
  'مهر', 'آبان', 'آذر',
  'دی', 'بهمن', 'اسفند'
];

/**
 * Converts English digits (0-9) to Persian digits (۰-۹).
 * Preserves non-digit characters.
 */
export function toPersianDigits(val) {
  if (val === null || val === undefined) return '';
  return String(val).replace(/[0-9]/g, (d) => PERSIAN_DIGITS[Number(d)]);
}

/**
 * Parses any date format (ISO, MySQL YYYY-MM-DD HH:MM:SS, timestamp, Date object) safely.
 */
function parseDate(dateInput) {
  if (!dateInput) return null;
  if (dateInput instanceof Date) {
    return isNaN(dateInput.getTime()) ? null : dateInput;
  }
  if (typeof dateInput === 'number') {
    const d = new Date(dateInput);
    return isNaN(d.getTime()) ? null : d;
  }

  let str = String(dateInput).trim();
  if (str === '' || str === '0000-00-00 00:00:00' || str === '0000-00-00') {
    return null;
  }

  // Normalize MySQL datetime "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM:SS"
  if (/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}(:\d{2})?/.test(str)) {
    str = str.replace(' ', 'T');
  }

  const parsed = new Date(str);
  return isNaN(parsed.getTime()) ? null : parsed;
}

/**
 * Fallback Gregorian to Jalali (Persian) conversion algorithm.
 */
function gregorianToJalali(gy, gm, gd) {
  const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
  let jy = (gy <= 1600) ? 0 : 979;
  gy -= (gy <= 1600) ? 621 : 1600;
  const gy2 = (gm > 2) ? (gy + 1) : gy;
  let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100)
    + Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
  jy += 33 * Math.floor(days / 12053);
  days %= 12053;
  jy += 4 * Math.floor(days / 1461);
  days %= 1461;
  jy += Math.floor((days - 1) / 365);
  if (days > 0) days = (days - 1) % 365;
  let jm, jd;
  if (days < 186) {
    jm = 1 + Math.floor(days / 31);
    jd = 1 + (days % 31);
  } else {
    jm = 7 + Math.floor((days - 186) / 30);
    jd = 1 + ((days - 186) % 30);
  }
  return [jy, jm, jd];
}

/**
 * Formats a date string (ISO / MySQL / timestamp) to Persian (Jalali / شمسی) date.
 * Default output: "۱۴۰۵/۰۷/۱۳"
 * If format is 'text' or 'long': "۱۳ مهر ۱۴۰۵"
 *
 * @param {string|Date|number} dateStr
 * @param {string} format 'numeric' | 'text' | 'long'
 * @param {string} fallback
 * @returns {string}
 */
export function formatDate(dateStr, format = 'numeric', fallback = '—') {
  const d = parseDate(dateStr);
  if (!d) return fallback;

  try {
    if (Intl && Intl.DateTimeFormat) {
      if (format === 'text' || format === 'long') {
        const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
          timeZone: 'Asia/Tehran',
          year: 'numeric',
          month: 'long',
          day: 'numeric',
        });
        return formatter.format(d);
      } else {
        // Standard numeric YYYY/MM/DD
        const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
          timeZone: 'Asia/Tehran',
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
        });
        return formatter.format(d);
      }
    }
  } catch (e) {
    // Fallback to pure algorithmic calculation
  }

  // Pure mathematical fallback
  const gy = d.getFullYear();
  const gm = d.getMonth() + 1;
  const gd = d.getDate();
  const [jy, jm, jd] = gregorianToJalali(gy, gm, gd);

  if (format === 'text' || format === 'long') {
    const monthName = PERSIAN_MONTH_NAMES[jm - 1] || '';
    return toPersianDigits(`${jd} ${monthName} ${jy}`);
  }

  const pad = (n) => (n < 10 ? `0${n}` : `${n}`);
  return toPersianDigits(`${jy}/${pad(jm)}/${pad(jd)}`);
}

/**
 * Formats time (hour and minute) in Tehran timezone with Persian digits.
 * Example: "۱۴:۳۵"
 */
export function formatTime(dateStr, fallback = '—') {
  const d = parseDate(dateStr);
  if (!d) return fallback;

  try {
    if (Intl && Intl.DateTimeFormat) {
      const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'Asia/Tehran',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      });
      return formatter.format(d);
    }
  } catch (e) {
    // Fallback
  }

  const h = String(d.getHours()).padStart(2, '0');
  const m = String(d.getMinutes()).padStart(2, '0');
  return toPersianDigits(`${h}:${m}`);
}

/**
 * Formats full date and time in Persian (Jalali) digits.
 * Example: "۱۴۰۵/۰۷/۱۳ - ۱۴:۳۵"
 */
export function formatDateTime(dateStr, fallback = '—') {
  const d = parseDate(dateStr);
  if (!d) return fallback;

  try {
    if (Intl && Intl.DateTimeFormat) {
      const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        timeZone: 'Asia/Tehran',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      });
      return formatter.format(d);
    }
  } catch (e) {
    // Fallback
  }

  const datePart = formatDate(d, 'numeric', fallback);
  const timePart = formatTime(d, fallback);
  return `${datePart} ${timePart}`;
}

/**
 * Formats a relative time string (e.g. "۵ دقیقه پیش", "۲ ساعت پیش", "دیروز").
 */
export function formatRelativeTime(dateStr, fallback = '—') {
  const d = parseDate(dateStr);
  if (!d) return fallback;

  const now = new Date();
  const diffSec = Math.floor((now.getTime() - d.getTime()) / 1000);

  if (diffSec < 60) return 'چند لحظه پیش';
  if (diffSec < 3600) {
    const mins = Math.floor(diffSec / 60);
    return `${toPersianDigits(mins)} دقیقه پیش`;
  }
  if (diffSec < 86400) {
    const hours = Math.floor(diffSec / 3600);
    return `${toPersianDigits(hours)} ساعت پیش`;
  }
  if (diffSec < 172800) return 'دیروز';
  if (diffSec < 2592000) {
    const days = Math.floor(diffSec / 86400);
    return `${toPersianDigits(days)} روز پیش`;
  }

  return formatDate(d, 'numeric', fallback);
}

/**
 * Formats a number with Persian thousands separators and Persian digits.
 * Example: "۱,۵۰۰,۰۰۰"
 */
export function formatNumber(val, fallback = '۰') {
  if (val === null || val === undefined || val === '') return fallback;
  const num = Number(val);
  if (isNaN(num)) return toPersianDigits(val);
  try {
    return new Intl.NumberFormat('fa-IR').format(num);
  } catch (e) {
    return toPersianDigits(String(num).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
  }
}

/**
 * Formats a price/currency amount in Persian digits with currency suffix.
 * Example: "۱,۵۰۰,۰۰۰ تومان"
 */
export function formatCurrency(val, currency = 'تومان', fallback = '۰ تومان') {
  if (val === null || val === undefined || val === '') return fallback;
  const num = Number(val);
  if (isNaN(num)) return `${toPersianDigits(val)} ${currency}`;
  const formatted = formatNumber(Math.round(num));
  return `${formatted} ${currency}`;
}

export const formatPrice = formatCurrency;

/**
 * Formats a percentage in Persian digits with the Persian percent symbol '٪'.
 */
export function formatPercent(val, decimals = 0, fallback = '۰٪') {
  if (val === null || val === undefined || val === '') return fallback;
  const num = Number(val);
  if (isNaN(num)) return `${toPersianDigits(val)}٪`;
  const fixed = decimals > 0 ? num.toFixed(decimals) : Math.round(num);
  return `${formatNumber(fixed)}٪`;
}

export default {
  toPersianDigits,
  formatDate,
  formatTime,
  formatDateTime,
  formatRelativeTime,
  formatNumber,
  formatCurrency,
  formatPrice,
  formatPercent,
};
