const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

/**
 * Converts English digits (0-9) to Persian digits (۰-۹).
 * Preserves non-digit characters.
 */
export function toPersianDigits(val) {
  if (val === null || val === undefined) return '';
  return String(val).replace(/[0-9]/g, (d) => PERSIAN_DIGITS[Number(d)]);
}

/**
 * Formats a number with Persian thousands separators and Persian digits.
 */
export function formatNumber(val, fallback = '۰') {
  if (val === null || val === undefined || val === '') return fallback;
  const num = Number(val);
  if (isNaN(num)) return toPersianDigits(val);
  try {
    return new Intl.NumberFormat('fa-IR').format(num);
  } catch (e) {
    return toPersianDigits(num);
  }
}

/**
 * Formats a price/currency amount in Persian digits with currency suffix.
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

/**
 * Formats a date string (ISO / MySQL / timestamp) to Shamsi (Jalali) date.
 */
export function formatDate(dateStr, fallback = '—') {
  if (!dateStr) return fallback;
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return toPersianDigits(dateStr);
    return d.toLocaleDateString('fa-IR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  } catch (e) {
    return toPersianDigits(dateStr);
  }
}

/**
 * Formats time (hour and minute) in Persian digits.
 */
export function formatTime(dateStr, fallback = '—') {
  if (!dateStr) return fallback;
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return toPersianDigits(dateStr);
    return d.toLocaleTimeString('fa-IR', {
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return toPersianDigits(dateStr);
  }
}

/**
 * Formats full date and time in Persian digits.
 */
export function formatDateTime(dateStr, fallback = '—') {
  if (!dateStr) return fallback;
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return toPersianDigits(dateStr);
    return d.toLocaleDateString('fa-IR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return toPersianDigits(dateStr);
  }
}

export default {
  toPersianDigits,
  formatNumber,
  formatCurrency,
  formatPrice,
  formatPercent,
  formatDate,
  formatTime,
  formatDateTime,
};
