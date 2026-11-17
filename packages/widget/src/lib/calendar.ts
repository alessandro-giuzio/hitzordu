/** Parse YYYY-MM-DD as midnight UTC, so date maths never meets a clock change. */
export function parseIsoDate(iso: string): Date {
  const [year, month, day] = iso.split('-').map(Number);
  return new Date(Date.UTC(year, month - 1, day));
}

function formatIsoDate(date: Date): string {
  return date.toISOString().slice(0, 10);
}

export function addDays(iso: string, days: number): string {
  const date = parseIsoDate(iso);
  date.setUTCDate(date.getUTCDate() + days);
  return formatIsoDate(date);
}

/** Add months, clamping to the last day of a shorter month (31 Jan + 1 = 28 Feb). */
export function addMonths(iso: string, months: number): string {
  const date = parseIsoDate(iso);
  const day = date.getUTCDate();
  date.setUTCDate(1);
  date.setUTCMonth(date.getUTCMonth() + months);
  const lastDay = new Date(
    Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0),
  ).getUTCDate();
  date.setUTCDate(Math.min(day, lastDay));
  return formatIsoDate(date);
}

export function firstOfMonth(iso: string): string {
  return `${iso.slice(0, 7)}-01`;
}

export function lastOfMonth(iso: string): string {
  return addDays(addMonths(firstOfMonth(iso), 1), -1);
}

/** 0 = Sunday ... 6 = Saturday, as in Date.getUTCDay(). */
export function weekdayOf(iso: string): number {
  return parseIsoDate(iso).getUTCDay();
}

export function startOfWeek(iso: string, weekStart: number): string {
  return addDays(iso, -((weekdayOf(iso) - weekStart + 7) % 7));
}

export function endOfWeek(iso: string, weekStart: number): string {
  return addDays(startOfWeek(iso, weekStart), 6);
}

export function clampDate(iso: string, min: string, max: string): string {
  return iso < min ? min : iso > max ? max : iso;
}

/** The month as weeks of seven cells; days outside the month are null. */
export function monthGrid(
  month: string,
  weekStart: number,
): (string | null)[][] {
  const prefix = month.slice(0, 7);
  const last = lastOfMonth(month);
  const weeks: (string | null)[][] = [];
  let day = startOfWeek(firstOfMonth(month), weekStart);

  while (day <= last) {
    const week: (string | null)[] = [];
    for (let i = 0; i < 7; i++) {
      week.push(day.startsWith(prefix) ? day : null);
      day = addDays(day, 1);
    }
    weeks.push(week);
  }

  return weeks;
}

/** Where a key press moves focus in the grid, or null for keys the grid ignores. */
export function moveByKey(
  iso: string,
  key: string,
  shiftKey: boolean,
  weekStart: number,
): string | null {
  switch (key) {
    case 'ArrowLeft':
      return addDays(iso, -1);
    case 'ArrowRight':
      return addDays(iso, 1);
    case 'ArrowUp':
      return addDays(iso, -7);
    case 'ArrowDown':
      return addDays(iso, 7);
    case 'Home':
      return startOfWeek(iso, weekStart);
    case 'End':
      return endOfWeek(iso, weekStart);
    case 'PageUp':
      return addMonths(iso, shiftKey ? -12 : -1);
    case 'PageDown':
      return addMonths(iso, shiftKey ? 12 : 1);
    default:
      return null;
  }
}

// Fallback for browsers without week info: regions where weeks usually start on Sunday.
const SUNDAY_FIRST_REGIONS = new Set([
  'US',
  'CA',
  'MX',
  'BR',
  'JP',
  'KR',
  'IL',
  'PH',
  'IN',
  'ZA',
  'TW',
  'HK',
]);

/** First day of the week for a locale: 0 = Sunday, 1 = Monday. */
export function weekStartForLocale(locale: string): number {
  try {
    const intlLocale = new Intl.Locale(locale) as Intl.Locale & {
      getWeekInfo?: () => { firstDay: number };
      weekInfo?: { firstDay: number };
    };
    const info =
      typeof intlLocale.getWeekInfo === 'function'
        ? intlLocale.getWeekInfo()
        : intlLocale.weekInfo;
    if (info) return info.firstDay % 7; // Intl counts 1 = Monday ... 7 = Sunday.
    const region = intlLocale.maximize().region ?? '';
    return SUNDAY_FIRST_REGIONS.has(region) ? 0 : 1;
  } catch {
    return 1;
  }
}
