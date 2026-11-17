import { computed, ref } from 'vue';
import { endpoint, groupByDay, isoDate } from './lib/slots';

export type Step =
  | 'pickDate'
  | 'pickTime'
  | 'details'
  | 'submitting'
  | 'confirmed';

export interface BookingFlowOptions {
  apiUrl: string;
  meetingType: string;
  /** Visitor's time zone. Defaults to the browser's. */
  timeZone?: string;
  /** Visitor's locale. Defaults to the browser's. */
  locale?: string;
  /** Injected in tests; defaults to the real fetch. */
  fetchFn?: typeof fetch;
  /** Injected in tests; defaults to the real clock. */
  now?: () => Date;
}

function newKey(): string {
  return Array.from(crypto.getRandomValues(new Uint8Array(16)), byte =>
    byte.toString(16).padStart(2, '0'),
  ).join('');
}

export function useBookingFlow(options: BookingFlowOptions) {
  const fetchFn: typeof fetch =
    options.fetchFn ?? ((input, init) => fetch(input, init));
  const now = options.now ?? (() => new Date());
  const timeZone =
    options.timeZone ?? Intl.DateTimeFormat().resolvedOptions().timeZone;
  const locale =
    options.locale ??
    (typeof navigator !== 'undefined' ? navigator.language : 'en');

  const step = ref<Step>('pickDate');
  const loading = ref(false);
  const message = ref('');
  const slots = ref<string[]>([]);
  const selectedDay = ref('');
  const selectedSlot = ref('');
  const name = ref('');
  const email = ref('');
  const notes = ref('');

  // One key per widget load: a retried submit can never book twice.
  const idempotencyKey = newKey();

  const days = computed(() => groupByDay(slots.value, timeZone));

  async function load(): Promise<void> {
    loading.value = true;
    try {
      const from = now();
      const to = new Date(from);
      to.setDate(to.getDate() + 14);
      const response = await fetchFn(
        endpoint(options.apiUrl, '/availability', {
          type: options.meetingType,
          from: isoDate(from),
          to: isoDate(to),
        }),
      );
      if (!response.ok) throw new Error('Request failed');
      slots.value = (await response.json()).slots;
    } catch {
      message.value = 'Could not load available times. Please try again later.';
    } finally {
      loading.value = false;
    }
  }

  function chooseDay(day: string): void {
    message.value = '';
    selectedDay.value = day;
    step.value = 'pickTime';
  }

  function chooseSlot(slot: string): void {
    selectedSlot.value = slot;
    step.value = 'details';
  }

  function back(): void {
    if (step.value === 'pickTime') step.value = 'pickDate';
    else if (step.value === 'details') step.value = 'pickTime';
  }

  async function submit(): Promise<void> {
    step.value = 'submitting';
    message.value = '';
    try {
      const response = await fetchFn(endpoint(options.apiUrl, '/bookings'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          type: options.meetingType,
          start: selectedSlot.value,
          name: name.value,
          email: email.value,
          notes: notes.value,
          timezone: timeZone,
          locale,
          idempotencyKey,
        }),
      });
      const data = await response.json();

      if (response.ok && data.status === 'confirmed') {
        step.value = 'confirmed';
        return;
      }

      if (response.status === 409) {
        message.value =
          'Sorry, that time was just taken. Please choose another.';
        await load();
        step.value = 'pickDate';
        return;
      }

      message.value = data.message ?? 'Something went wrong. Please try again.';
      step.value = 'details';
    } catch {
      message.value = 'Could not reach the server. Please try again.';
      step.value = 'details';
    }
  }

  return {
    step,
    loading,
    message,
    days,
    selectedDay,
    selectedSlot,
    name,
    email,
    notes,
    timeZone,
    load,
    chooseDay,
    chooseSlot,
    back,
    submit,
  };
}
