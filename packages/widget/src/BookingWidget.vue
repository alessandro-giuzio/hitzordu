<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

const props = defineProps<{ apiUrl: string; meetingType: string }>()

type Step = 'pickDate' | 'pickTime' | 'details' | 'submitting' | 'confirmed'

const step = ref<Step>('pickDate')
const loading = ref(true)
const message = ref('')
const slots = ref<string[]>([])
const selectedDay = ref('')
const selectedSlot = ref('')
const name = ref('')
const email = ref('')
const notes = ref('')

const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone
const dayFormat = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long' })
const timeFormat = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' })

// One key per widget load, so a double click or retry never books twice.
const idempotencyKey = Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) =>
  byte.toString(16).padStart(2, '0'),
).join('')

// Slots arrive as UTC instants; group them by the visitor's local day.
const days = computed(() => {
  const grouped = new Map<string, string[]>()
  for (const slot of slots.value) {
    const label = dayFormat.format(new Date(slot))
    grouped.set(label, [...(grouped.get(label) ?? []), slot])
  }
  return grouped
})

const selectedTime = computed(() =>
  selectedSlot.value ? timeFormat.format(new Date(selectedSlot.value)) : '',
)

// Works with pretty permalinks (/wp-json/...) and plain ones (?rest_route=...).
function endpoint(path: string, params: Record<string, string> = {}): string {
  const url = new URL(props.apiUrl)
  const route = url.searchParams.get('rest_route')
  if (route !== null) {
    url.searchParams.set('rest_route', route.replace(/\/$/, '') + path)
  } else {
    url.pathname = url.pathname.replace(/\/$/, '') + path
  }
  for (const [key, value] of Object.entries(params)) {
    url.searchParams.set(key, value)
  }
  return url.toString()
}

function isoDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

async function load() {
  loading.value = true
  try {
    const from = new Date()
    const to = new Date()
    to.setDate(to.getDate() + 14)
    const response = await fetch(
      endpoint('/availability', { type: props.meetingType, from: isoDate(from), to: isoDate(to) }),
    )
    if (!response.ok) throw new Error('Request failed')
    slots.value = (await response.json()).slots
  } catch {
    message.value = 'Could not load available times. Please try again later.'
  } finally {
    loading.value = false
  }
}

function chooseDay(label: string) {
  message.value = ''
  selectedDay.value = label
  step.value = 'pickTime'
}

function chooseSlot(slot: string) {
  selectedSlot.value = slot
  step.value = 'details'
}

async function submit() {
  step.value = 'submitting'
  message.value = ''
  try {
    const response = await fetch(endpoint('/bookings'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        type: props.meetingType,
        start: selectedSlot.value,
        name: name.value,
        email: email.value,
        notes: notes.value,
        timezone: timeZone,
        locale: navigator.language,
        idempotencyKey,
      }),
    })
    const data = await response.json()

    if (response.ok && data.status === 'confirmed') {
      step.value = 'confirmed'
      return
    }

    if (response.status === 409) {
      message.value = 'Sorry, that time was just taken. Please choose another.'
      await load()
      step.value = 'pickDate'
      return
    }

    message.value = data.message ?? 'Something went wrong. Please try again.'
    step.value = 'details'
  } catch {
    message.value = 'Could not reach the server. Please try again.'
    step.value = 'details'
  }
}

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
        <li v-for="label in days.keys()" :key="label">
          <button type="button" @click="chooseDay(label)">{{ label }}</button>
        </li>
      </ul>
    </div>

    <div v-else-if="step === 'pickTime'">
      <h3>{{ selectedDay }}</h3>
      <p>Times are shown in {{ timeZone }}.</p>
      <ul>
        <li v-for="slot in days.get(selectedDay)" :key="slot">
          <button type="button" @click="chooseSlot(slot)">{{ timeFormat.format(new Date(slot)) }}</button>
        </li>
      </ul>
      <button type="button" @click="step = 'pickDate'">Back</button>
    </div>

    <form v-else-if="step === 'details' || step === 'submitting'" @submit.prevent="submit">
      <h3>{{ selectedDay }}, {{ selectedTime }}</h3>
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
      <button type="button" :disabled="step === 'submitting'" @click="step = 'pickTime'">Back</button>
    </form>

    <div v-else>
      <h3>You're booked</h3>
      <p>{{ selectedDay }}, {{ selectedTime }} ({{ timeZone }}).</p>
      <p>A confirmation email is on its way to {{ email }}.</p>
    </div>
  </section>
</template>
