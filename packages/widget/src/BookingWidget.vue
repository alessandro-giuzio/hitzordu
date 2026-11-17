<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useBookingFlow } from './useBookingFlow'

const props = defineProps<{ apiUrl: string; meetingType: string }>()

const {
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
} = useBookingFlow({ apiUrl: props.apiUrl, meetingType: props.meetingType })

const dayFormat = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long', timeZone })
const timeFormat = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', timeZone })

function dayLabel(day: string): string {
  const first = days.value.get(day)?.[0]
  return first ? dayFormat.format(new Date(first)) : day
}

function timeLabel(slot: string): string {
  return timeFormat.format(new Date(slot))
}

const selectedTime = computed(() => (selectedSlot.value ? timeLabel(selectedSlot.value) : ''))

onMounted(load)
</script>

<template>
  <section aria-label="Book a call">
    <p v-if="message" role="alert">{{ message }}</p>

    <div v-if="step === 'pickDate'">
      <h3>Choose a day</h3>
      <p v-if="loading">Loading available times…</p>
      <p v-else-if="days.size === 0">No times available in the next two weeks.</p>
      <ul v-else>
        <li v-for="day in days.keys()" :key="day">
          <button type="button" @click="chooseDay(day)">{{ dayLabel(day) }}</button>
        </li>
      </ul>
    </div>

    <div v-else-if="step === 'pickTime'">
      <h3>{{ dayLabel(selectedDay) }}</h3>
      <p>Times are shown in {{ timeZone }}.</p>
      <ul>
        <li v-for="slot in days.get(selectedDay)" :key="slot">
          <button type="button" @click="chooseSlot(slot)">{{ timeLabel(slot) }}</button>
        </li>
      </ul>
      <button type="button" @click="back">Back</button>
    </div>

    <form v-else-if="step === 'details' || step === 'submitting'" @submit.prevent="submit">
      <h3>{{ dayLabel(selectedDay) }}, {{ selectedTime }}</h3>
      <p>
        <label>Name <input v-model="name" required maxlength="190" autocomplete="name" /></label>
      </p>
      <p>
        <label>Email <input v-model="email" type="email" required autocomplete="email" /></label>
      </p>
      <p>
        <label>Notes (optional) <textarea v-model="notes"></textarea></label>
      </p>
      <button type="submit" :disabled="step === 'submitting'">
        {{ step === 'submitting' ? 'Booking…' : 'Confirm booking' }}
      </button>
      <button type="button" :disabled="step === 'submitting'" @click="back">Back</button>
    </form>

    <div v-else>
      <h3>You're booked</h3>
      <p>{{ dayLabel(selectedDay) }}, {{ selectedTime }} ({{ timeZone }}).</p>
      <p>A confirmation email is on its way to {{ email }}.</p>
    </div>
  </section>
</template>
