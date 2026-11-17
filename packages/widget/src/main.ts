import { defineCustomElement } from 'vue';
import BookingWidget from './BookingWidget.ce.vue';

const HitzorduWidget = defineCustomElement(BookingWidget);

// Guard against the script being loaded twice on one page.
if (!customElements.get('hitzordu-widget')) {
  customElements.define('hitzordu-widget', HitzorduWidget);
}
