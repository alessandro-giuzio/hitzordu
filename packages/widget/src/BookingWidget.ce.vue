<script setup lang="ts">
import { computed, onMounted, ref, useId, watch } from 'vue'
import CalendarGrid from './CalendarGrid.vue'
import { parseIsoDate, weekStartForLocale } from './lib/calendar'
import { useBookingFlow } from './useBookingFlow'

const props = defineProps<{ apiUrl: string; meetingType: string; locale?: string }>()

// The page's language: WordPress sets <html lang> from the site language.
const pageLocale =
  props.locale || document.documentElement.lang || navigator.language || 'en'

const {
  step,
  loading,
  message,
  days,
  visibleMonth,
  selectedDay,
  selectedSlot,
  name,
  email,
  notes,
  timeZone,
  locale,
  today,
  minDate,
  maxDate,
  canGoPrev,
  canGoNext,
  t,
  load,
  showMonth,
  chooseDay,
  chooseSlot,
  back,
  submit,
} = useBookingFlow({ apiUrl: props.apiUrl, meetingType: props.meetingType, locale: pageLocale })

const weekStart = weekStartForLocale(locale)
const timesId = useId()

const dateFormat = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' })
const timeFormat = new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: '2-digit', timeZone })

function dateLabel(day: string): string {
  return day ? dateFormat.format(parseIsoDate(day)) : ''
}

function timeLabel(slot: string): string {
  return timeFormat.format(new Date(slot))
}

const selectedTime = computed(() => (selectedSlot.value ? timeLabel(selectedSlot.value) : ''))

// Each step's heading and the error message can receive focus from script.
const heading = ref<HTMLElement | null>(null)
const alert = ref<HTMLElement | null>(null)

watch(
  step,
  (current, previous) => {
    if (current === 'submitting') return
    if (previous === 'submitting' && current === 'details') {
      alert.value?.focus()
      return
    }
    heading.value?.focus()
  },
  { flush: 'post' },
)

onMounted(load)
</script>

<template>
  <section class="hz" :aria-label="t('bookACall')">
    <p v-if="message" ref="alert" class="hz-alert" role="alert" tabindex="-1">
      {{ message }}
    </p>

    <div v-if="step === 'pickDate'">
      <h3 ref="heading" class="hz-heading" tabindex="-1">{{ t('chooseDay') }}</h3>
      <CalendarGrid
        :month="visibleMonth"
        :days="days"
        :selected="selectedDay"
        :today="today"
        :min-date="minDate"
        :max-date="maxDate"
        :week-start="weekStart"
        :locale="locale"
        :loading="loading"
        :can-go-prev="canGoPrev"
        :can-go-next="canGoNext"
        @select="chooseDay"
        @change-month="showMonth" />
    </div>

    <div v-else-if="step === 'pickTime'">
      <h3 :id="timesId" ref="heading" class="hz-heading" tabindex="-1">
        {{ dateLabel(selectedDay) }}
      </h3>
      <p class="hz-muted">{{ t('timesShownIn', { zone: timeZone }) }}</p>
      <ul class="hz-times" role="list" :aria-labelledby="timesId">
        <li v-for="slot in days.get(selectedDay)" :key="slot">
          <button type="button" class="hz-button hz-time" part="button" @click="chooseSlot(slot)">
            {{ timeLabel(slot) }}
          </button>
        </li>
      </ul>
      <div class="hz-actions">
        <button type="button" class="hz-button" part="button" @click="back">
          {{ t('backToDays') }}
        </button>
      </div>
    </div>

    <form v-else-if="step === 'details' || step === 'submitting'" @submit.prevent="submit">
      <h3 ref="heading" class="hz-heading" tabindex="-1">
        {{ dateLabel(selectedDay) }}, {{ selectedTime }}
      </h3>

      <label class="hz-field">
        <span>{{ t('name') }}</span>
        <input v-model="name" class="hz-input" part="input" required maxlength="190" autocomplete="name" />
      </label>

      <label class="hz-field">
        <span>{{ t('email') }}</span>
        <input v-model="email" class="hz-input" part="input" type="email" required autocomplete="email" />
      </label>

      <label class="hz-field">
        <span>{{ t('notes') }} <span class="hz-muted">{{ t('optional') }}</span></span>
        <textarea v-model="notes" class="hz-input" part="input" rows="3"></textarea>
      </label>

      <div class="hz-actions">
        <button
          type="submit"
          class="hz-button hz-button--primary"
          part="button"
          :disabled="step === 'submitting'">
          {{ step === 'submitting' ? t('booking') : t('confirmBooking') }}
        </button>
        <button
          type="button"
          class="hz-button"
          part="button"
          :disabled="step === 'submitting'"
          @click="back">
          {{ t('backToTimes') }}
        </button>
      </div>
    </form>

    <div v-else>
      <h3 ref="heading" class="hz-heading" tabindex="-1">{{ t('booked') }}</h3>
      <p>{{ dateLabel(selectedDay) }}, {{ selectedTime }} ({{ timeZone }}).</p>
      <p class="hz-muted">{{ t('emailOnItsWay', { email }) }}</p>
    </div>
  </section>
</template>

<style>
/*
 * Public theming hooks. Set any of these on the element from the site's CSS:
 *   hitzordu-widget { --hitzordu-accent: #0f766e; }
 *
 *   --hitzordu-accent           buttons, selected day, available days
 *   --hitzordu-accent-contrast  text on the accent colour
 *   --hitzordu-background       card background (default: transparent)
 *   --hitzordu-border           card and input borders
 *   --hitzordu-muted            secondary text
 *   --hitzordu-radius           corner radius
 *   --hitzordu-max-width        card width
 *   --hitzordu-font-size        base text size
 *   --hitzordu-font             font family (default: the page's)
 *   --hitzordu-text             text colour (default: the page's)
 */
:host {
  --_accent: var(--hitzordu-accent, #1d4ed8);
  --_on-accent: var(--hitzordu-accent-contrast, #ffffff);
  --_radius: var(--hitzordu-radius, 0.75em);
  --_border: var(
    --hitzordu-border,
    color-mix(in srgb, currentColor 20%, transparent)
  );
  --_muted: var(
    --hitzordu-muted,
    color-mix(in srgb, currentColor 65%, transparent)
  );
  --_tint: color-mix(in srgb, var(--_accent) 12%, transparent);

  display: block;
  max-width: var(--hitzordu-max-width, 26em);
  container-type: inline-size;
  /* Never smaller than 16px, even on themes that set the root font size to 62.5%,
     but still larger when the visitor has raised their browser's text size. */
  font-size: var(--hitzordu-font-size, max(1rem, 16px));
  font-family: var(--hitzordu-font, inherit);
  line-height: 1.5;
  color: var(--hitzordu-text, inherit);
}

*,
*::before,
*::after {
  box-sizing: border-box;
}

:focus-visible {
  outline: 3px solid var(--_accent);
  outline-offset: 2px;
}

/* When the script focuses a heading inside this shadow tree, the browser also
   treats the host as focused, and many themes draw a square outline on it. The
   real controls inside keep their own focus rings. */
:host(:focus),
:host(:focus-visible) {
  outline: none !important;
}

p {
  margin: 0 0 1em;
}

/* The card */

.hz {
  padding: 1.5em;
  border: 1px solid var(--_border);
  border-radius: calc(var(--_radius) * 1.5);
  background: var(--hitzordu-background, transparent);
  box-shadow:
    0 1px 3px color-mix(in srgb, currentColor 10%, transparent),
    0 8px 24px color-mix(in srgb, currentColor 6%, transparent);
}

.hz-heading {
  margin: 0 0 0.5em;
  font-size: 1.125em;
  font-weight: 700;
  line-height: 1.3;
}

/* Headings only receive focus from script, to announce a new step. */
.hz-heading:focus {
  outline: none;
}

.hz-muted {
  color: var(--_muted);
  font-size: 0.875em;
}

.hz-alert {
  margin-bottom: 1em;
  padding: 0.75em 1em;
  border: 1px solid color-mix(in srgb, currentColor 30%, transparent);
  border-left: 0.3em solid currentColor;
  border-radius: var(--_radius);
  background: color-mix(in srgb, currentColor 6%, transparent);
}

/* Buttons */

.hz-button {
  min-height: 2.75em;
  padding: 0.5em 1.25em;
  border: 1px solid var(--_border);
  border-radius: var(--_radius);
  background: transparent;
  color: inherit;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.hz-button:hover:not(:disabled) {
  border-color: var(--_accent);
  background: var(--_tint);
}

.hz-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.hz-button--primary {
  border-color: var(--_accent);
  background: var(--_accent);
  color: var(--_on-accent);
}

.hz-button--primary:hover:not(:disabled) {
  border-color: var(--_accent);
  background: var(--_accent);
  filter: brightness(1.1);
}

.hz-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5em;
  margin-top: 0.5em;
}

.hz-actions .hz-button {
  flex: 1 1 auto;
}

/* Calendar */

.hz-month {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5em;
  margin-bottom: 0.25em;
}

.hz-month-title {
  margin: 0;
  font-size: 1em;
  font-weight: 700;
  text-align: center;
  text-transform: capitalize;
}

.hz-nav {
  flex: none;
  width: 2.2em;
  min-height: 2.2em;
  padding: 0;
  border-color: transparent;
  border-radius: 50%;
  background: var(--_tint);
  color: var(--_accent);
  font-size: 1.25em;
  line-height: 1;
}

.hz-nav:hover:not(:disabled) {
  background: var(--_accent);
  color: var(--_on-accent);
}

.hz-grid {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
}

.hz-grid th {
  padding: 0.5em 0;
  color: var(--_muted);
  font-size: 0.75em;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-align: center;
  text-transform: uppercase;
}

.hz-grid abbr {
  text-decoration: none;
}

.hz-grid td {
  padding: 2px 0;
  text-align: center;
}

.hz-day {
  position: relative;
  display: block;
  width: 100%;
  max-width: 2.75em;
  min-height: 2.75em;
  margin: 0 auto;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 50%;
  background: transparent;
  color: var(--_muted);
  font: inherit;
  cursor: default;
}

/* Available days: a tinted circle and bold accent text, so it is not colour alone. */
.hz-day[data-available] {
  background: var(--_tint);
  color: var(--_accent);
  font-weight: 700;
  cursor: pointer;
}

.hz-day[data-available]:hover {
  border-color: var(--_accent);
}

/* Today: a small dot under the number. */
.hz-day[aria-current='date']::after {
  content: '';
  position: absolute;
  bottom: 0.3em;
  left: 50%;
  width: 0.3em;
  height: 0.3em;
  margin-left: -0.15em;
  border-radius: 50%;
  background: currentColor;
}

.hz-day[data-selected] {
  border-color: var(--_accent);
  background: var(--_accent);
  color: var(--_on-accent);
}

/* Times */

/* One scrolling column of times, like Calendly. The padding and negative margin
   keep the focus rings from being clipped by the scroll area. */
.hz-times {
  display: grid;
  gap: 0.5em;
  max-width: 16em;
  max-height: 20em;
  margin: 0 auto 1em;
  padding: 4px;
  overflow-y: auto;
  list-style: none;
}

.hz-time {
  width: 100%;
  border-color: var(--_accent);
  color: var(--_accent);
}

.hz-time:hover:not(:disabled) {
  background: var(--_accent);
  color: var(--_on-accent);
}

/* Form */

.hz-field {
  display: grid;
  gap: 0.25em;
  margin-bottom: 1em;
  font-weight: 600;
}

.hz-input {
  width: 100%;
  min-height: 2.75em;
  padding: 0.5em 0.75em;
  border: 1px solid var(--_border);
  border-radius: var(--_radius);
  background: transparent;
  color: inherit;
  font: inherit;
  font-weight: 400;
}

.hz-input:focus-visible {
  border-color: var(--_accent);
  outline-offset: 0;
}

/* Layout responds to the widget's own width, not the window's. */
@container (max-width: 20em) {
  .hz {
    padding: 1em;
  }
}

@media (prefers-reduced-motion: no-preference) {
  .hz-button,
  .hz-day {
    transition:
      border-color 0.15s,
      background-color 0.15s,
      color 0.15s;
  }
}

@media (forced-colors: active) {
  .hz-day[data-selected] {
    forced-color-adjust: none;
    border-color: Highlight;
    background: Highlight;
    color: HighlightText;
  }

  .hz-day[aria-disabled='true'] {
    text-decoration: line-through;
  }
}
</style>
