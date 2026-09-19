/** Locale-aware formatting helpers (Persian digits, dates, reading time). */

const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

export function digits(value, locale = 'en') {
  const text = String(value ?? '');

  if (locale !== 'fa') return text;

  return text.replace(/\d/g, (digit) => FA_DIGITS[Number(digit)]);
}

export function formatNumber(value, locale = 'en') {
  const number = Number(value ?? 0);

  if (Number.isNaN(number)) return digits(value, locale);

  const formatted = new Intl.NumberFormat(locale === 'fa' ? 'fa-IR' : 'en-US').format(number);

  return locale === 'fa' ? formatted : formatted;
}

export function formatDate(value, locale = 'en', options = { dateStyle: 'medium' }) {
  if (!value) return '';

  const date = new Date(String(value).replace(' ', 'T') + (String(value).length === 10 ? 'T00:00:00' : ''));

  if (Number.isNaN(date.getTime())) return String(value);

  return new Intl.DateTimeFormat(locale === 'fa' ? 'fa-IR' : 'en-GB', options).format(date);
}

export function formatRange(start, end, isCurrent, locale = 'en') {
  const format = (value) =>
    value
      ? new Intl.DateTimeFormat(locale === 'fa' ? 'fa-IR' : 'en-GB', { month: 'short', year: 'numeric' }).format(
          new Date(`${String(value).slice(0, 10)}T00:00:00`)
        )
      : '';

  if (isCurrent) return `${format(start)} — ${locale === 'fa' ? 'تاکنون' : 'present'}`;

  return `${format(start)}${end ? ` — ${format(end)}` : ''}`;
}

export function readingTime(minutes, locale = 'en') {
  const value = Math.max(1, Number(minutes) || 1);

  return locale === 'fa' ? `${digits(value, 'fa')} دقیقه مطالعه` : `${value} min read`;
}

export function excerpt(value, limit = 180) {
  const text = String(value ?? '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

  return text.length > limit ? `${text.slice(0, limit - 1)}…` : text;
}

export function initials(name = '') {
  return String(name)
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase();
}

export function humanSize(bytes) {
  const value = Number(bytes) || 0;
  const units = ['B', 'KB', 'MB', 'GB'];
  let index = 0;
  let size = value;

  while (size >= 1024 && index < units.length - 1) {
    size /= 1024;
    index += 1;
  }

  return `${size.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

/** Build a per-language href that keeps the current section. */
export function withLocale(path, locale) {
  const clean = `/${String(path || '').replace(/^\/+/, '')}`;
  const segments = clean.split('/').filter(Boolean);
  const reserved = ['fa', 'en'];

  if (segments.length && reserved.includes(segments[0])) {
    segments[0] = locale;
  } else {
    segments.unshift(locale);
  }

  return `/${segments.join('/')}`;
}
