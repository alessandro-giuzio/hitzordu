<script setup lang="ts">
import { computed, onMounted, ref, useId, watch } from 'vue';
import CalendarGrid from './CalendarGrid.vue';
import { parseIsoDate, weekStartForLocale } from './lib/calendar';
import { useBookingFlow } from './useBookingFlow';

const props = defineProps<{ apiUrl: string; meetingType: string }>();

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
  load,
  showMonth,
  chooseDay,
  chooseSlot,
  back,
  submit,
} = useBookingFlow({ apiUrl: props.apiUrl, meetingType: props.meetingType });

const weekStart = weekStartForLocale(locale);
const timesId = useId();

const dateFormat = new Intl.DateTimeFormat(locale, {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  timeZone: 'UTC',
});
const timeFormat = new Intl.DateTimeFormat(locale, {
  hour: '2-digit',
  minute: '2-digit',
  timeZone,
});

function dateLabel(day: string): string {
  return day ? dateFormat.format(parseIsoDate(day)) : '';
}

function timeLabel(slot: string): string {
  return timeFormat.format(new Date(slot));
}

const selectedTime = computed(() =>
  selectedSlot.value ? timeLabel(selectedSlot.value) : '',
);

// Each step's heading and the error message can receive focus from script.
const heading = ref<HTMLElement | null>(null);
const alert = ref<HTMLElement | null>(null);

watch(
  step,
  (current, previous) => {
    if (current === 'submitting') return;
    if (previous === 'submitting' && current === 'details') {
      alert.value?.focus();
      return;
    }
    heading.value?.focus();
  },
  { flush: 'post' },
);

onMounted(load);
</script>

<template>
  <section aria-label="Book a call">
    <p v-if="message" ref="alert" role="alert" tabindex="-1">{{ message }}</p>

    <div v-if="step === 'pickDate'">
      <h3 ref="heading" tabindex="-1">Choose a day</h3>
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
      <h3 :id="timesId" ref="heading" tabindex="-1">
        {{ dateLabel(selectedDay) }}
      </h3>
      <p>Times are shown in {{ timeZone }}.</p>
      <ul role="list" :aria-labelledby="timesId">
        <li v-for="slot in days.get(selectedDay)" :key="slot">
          <button type="button" @click="chooseSlot(slot)">
            {{ timeLabel(slot) }}
          </button>
        </li>
      </ul>
      <button type="button" @click="back">Back to days</button>
    </div>

    <form
      v-else-if="step === 'details' || step === 'submitting'"
      @submit.prevent="submit">
      <h3 ref="heading" tabindex="-1">
        {{ dateLabel(selectedDay) }}, {{ selectedTime }}
      </h3>
      <p>
        <label
          >Name
          <input v-model="name" required maxlength="190" autocomplete="name"
        /></label>
      </p>
      <p>
        <label
          >Email
          <input v-model="email" type="email" required autocomplete="email"
        /></label>
      </p>
      <p>
        <label>Notes (optional) <textarea v-model="notes"></textarea></label>
      </p>
      <button type="submit" :disabled="step === 'submitting'">
        {{ step === 'submitting' ? 'Booking…' : 'Confirm booking' }}
      </button>
      <button type="button" :disabled="step === 'submitting'" @click="back">
        Back to times
      </button>
    </form>

    <div v-else>
      <h3 ref="heading" tabindex="-1">You're booked</h3>
      <p>{{ dateLabel(selectedDay) }}, {{ selectedTime }} ({{ timeZone }}).</p>
      <p>A confirmation email is on its way to {{ email }}.</p>
    </div>
  </section>
</template>
