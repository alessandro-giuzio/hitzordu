/** A calendar date as YYYY-MM-DD, using the date's local fields. */
export function isoDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

const dateFormats = new Map<string, Intl.DateTimeFormat>();

/** The calendar date (YYYY-MM-DD) on which a UTC instant falls in the given zone. */
export function localDateKey(instant: string, timeZone: string): string {
  let format = dateFormats.get(timeZone);
  if (!format) {
    format = new Intl.DateTimeFormat('en', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    });
    dateFormats.set(timeZone, format);
  }
  const parts = Object.fromEntries(
    format.formatToParts(new Date(instant)).map(p => [p.type, p.value]),
  );
  return `${parts.year}-${parts.month}-${parts.day}`;
}

/** Group UTC slot instants by the visitor's local date, keeping their order. */
export function groupByDay(
  slots: string[],
  timeZone: string,
): Map<string, string[]> {
  const grouped = new Map<string, string[]>();
  for (const slot of slots) {
    const key = localDateKey(slot, timeZone);
    const list = grouped.get(key);
    if (list) list.push(slot);
    else grouped.set(key, [slot]);
  }
  return grouped;
}

/** Build an API URL for pretty (/wp-json/...) and plain (?rest_route=...) permalinks. */
export function endpoint(
  apiUrl: string,
  path: string,
  params: Record<string, string> = {},
): string {
  const url = new URL(apiUrl);
  const route = url.searchParams.get('rest_route');
  if (route !== null) {
    url.searchParams.set('rest_route', route.replace(/\/$/, '') + path);
  } else {
    url.pathname = url.pathname.replace(/\/$/, '') + path;
  }
  for (const [key, value] of Object.entries(params)) {
    url.searchParams.set(key, value);
  }
  return url.toString();
}
