<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue';
import {
  addDays,
  addMonths,
  clampDate,
  monthGrid,
  moveByKey,
  parseIsoDate,
} from './lib/calendar';

const props = defineProps<{
  month: string;
  days: Map<string, string[]>;
  selected: string;
  today: string;
  minDate: string;
  maxDate: string;
  weekStart: number;
  locale: string;
  loading: boolean;
  canGoPrev: boolean;
  canGoNext: boolean;
}>();

const emit = defineEmits<{
  select: [day: string];
  changeMonth: [date: string];
}>();

const titleId = useId();
const grid = ref<HTMLTableElement | null>(null);

// Dates here are UTC midnights, so they are always formatted in UTC.
const monthFormat = computed(
  () =>
    new Intl.DateTimeFormat(props.locale, {
      month: 'long',
      year: 'numeric',
      timeZone: 'UTC',
    }),
);
const fullDateFormat = computed(
  () =>
    new Intl.DateTimeFormat(props.locale, {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      timeZone: 'UTC',
    }),
);

const monthTitle = computed(() =>
  monthFormat.value.format(parseIsoDate(props.month)),
);
const weeks = computed(() => monthGrid(props.month, props.weekStart));

const weekdays = computed(() => {
  const short = new Intl.DateTimeFormat(props.locale, {
    weekday: 'short',
    timeZone: 'UTC',
  });
  const long = new Intl.DateTimeFormat(props.locale, {
    weekday: 'long',
    timeZone: 'UTC',
  });
  // 3 January 2027 is a Sunday; add the weekday number to get each day's name.
  return Array.from({ length: 7 }, (_, i) => {
    const date = parseIsoDate(addDays('2027-01-03', (props.weekStart + i) % 7));
    return { short: short.format(date), long: long.format(date) };
  });
});

function isAvailable(day: string): boolean {
  return (
    day >= props.minDate &&
    day <= props.maxDate &&
    (props.days.get(day)?.length ?? 0) > 0
  );
}

const statusText = computed(() => {
  if (props.loading) return 'Loading available times…';
  const prefix = props.month.slice(0, 7);
  const count = [...props.days.keys()].filter(
    day => day.startsWith(prefix) && isAvailable(day),
  ).length;
  if (count === 0) return `${monthTitle.value}: no available times.`;
  return `${monthTitle.value}: ${count} ${count === 1 ? 'day' : 'days'} with available times.`;
});

function label(day: string): string {
  const date = fullDateFormat.value.format(parseIsoDate(day));
  if (!isAvailable(day)) return `${date}, not available`;
  const count = props.days.get(day)?.length ?? 0;
  return `${date}, ${count} ${count === 1 ? 'time' : 'times'} available`;
}

/** Which day gets the single tab stop when the month is shown. */
function initialFocus(): string {
  const prefix = props.month.slice(0, 7);
  if (props.selected.startsWith(prefix)) return props.selected;
  const firstAvailable = [...props.days.keys()].find(
    day => day.startsWith(prefix) && isAvailable(day),
  );
  return firstAvailable ?? clampDate(props.month, props.minDate, props.maxDate);
}

const focused = ref(initialFocus());
let restoreFocus = false;

function focusDay(day: string): void {
  grid.value
    ?.querySelector<HTMLButtonElement>(`button[data-date="${day}"]`)
    ?.focus();
}

/** Whether focus is inside the grid. Asks the shadow root, which knows the real focused element. */
function gridHasFocus(): boolean {
  const root = grid.value?.getRootNode() as Document | ShadowRoot | undefined;
  return Boolean(root && grid.value?.contains(root.activeElement));
}

watch(
  () => props.month,
  async () => {
    if (restoreFocus) {
      // The month changed because of a key press: keep focus on the target day.
      restoreFocus = false;
      await nextTick();
      focusDay(focused.value);
      return;
    }
    focused.value = initialFocus();
  },
);

watch(
  () => props.days,
  () => {
    // New availability arrived; only move the tab stop if the visitor is not in the grid.
    if (!gridHasFocus()) focused.value = initialFocus();
  },
);

async function onKeydown(event: KeyboardEvent): Promise<void> {
  const next = moveByKey(
    focused.value,
    event.key,
    event.shiftKey,
    props.weekStart,
  );
  if (next === null) return;

  event.preventDefault();
  const target = clampDate(next, props.minDate, props.maxDate);
  focused.value = target;

  if (!target.startsWith(props.month.slice(0, 7))) {
    restoreFocus = true;
    emit('changeMonth', target);
    return;
  }

  await nextTick();
  focusDay(target);
}

function pick(day: string): void {
  focused.value = day;
  if (isAvailable(day)) emit('select', day);
}
</script>

<template>
  <div>
    <div class="hz-month">
      <button
        type="button"
        class="hz-button hz-nav"
        part="button"
        aria-label="Previous month"
        :disabled="!canGoPrev"
        @click="emit('changeMonth', addMonths(month, -1))">
        ‹
      </button>
      <h4 :id="titleId" class="hz-month-title">{{ monthTitle }}</h4>
      <button
        type="button"
        class="hz-button hz-nav"
        part="button"
        aria-label="Next month"
        :disabled="!canGoNext"
        @click="emit('changeMonth', addMonths(month, 1))">
        ›
      </button>
    </div>

    <p class="hz-muted" role="status">{{ statusText }}</p>

    <table
      ref="grid"
      class="hz-grid"
      role="grid"
      :aria-labelledby="titleId"
      @keydown="onKeydown">
      <thead>
        <tr>
          <th v-for="weekday in weekdays" :key="weekday.long" scope="col">
            <abbr :title="weekday.long">{{ weekday.short }}</abbr>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(week, w) in weeks" :key="w">
          <td
            v-for="(day, i) in week"
            :key="day ?? `empty-${w}-${i}`"
            :aria-selected="day ? day === selected : undefined">
            <button
              v-if="day"
              type="button"
              class="hz-day"
              part="day"
              :data-date="day"
              :data-available="isAvailable(day) || undefined"
              :data-selected="day === selected || undefined"
              :tabindex="day === focused ? 0 : -1"
              :aria-label="label(day)"
              :aria-disabled="isAvailable(day) ? undefined : 'true'"
              :aria-current="day === today ? 'date' : undefined"
              @click="pick(day)">
              {{ Number(day.slice(8)) }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
