import { createApp } from 'vue'
import BookingWidget from './BookingWidget.vue'

document.querySelectorAll<HTMLElement>('[data-hitzordu-widget]').forEach((el) => {
  createApp(BookingWidget, {
    apiUrl: el.dataset.apiUrl ?? '',
    meetingType: el.dataset.meetingType ?? '',
  }).mount(el)
})
