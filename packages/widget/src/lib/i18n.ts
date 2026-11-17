type Message = string | { one: string; other: string };

const en = {
  bookACall: 'Book a call',
  chooseDay: 'Choose a day',
  timesShownIn: 'Times are shown in {zone}.',
  backToDays: 'Back to days',
  name: 'Name',
  email: 'Email',
  notes: 'Notes',
  optional: '(optional)',
  booking: 'Booking…',
  confirmBooking: 'Confirm booking',
  backToTimes: 'Back to times',
  booked: "You're booked",
  emailOnItsWay: 'A confirmation email is on its way to {email}.',
  previousMonth: 'Previous month',
  nextMonth: 'Next month',
  loadingTimes: 'Loading available times…',
  monthNone: '{month}: no available times.',
  monthDays: {
    one: '{month}: {count} day with available times.',
    other: '{month}: {count} days with available times.',
  },
  dayUnavailable: '{date}, not available',
  dayTimes: {
    one: '{date}, {count} time available',
    other: '{date}, {count} times available',
  },
  loadFailed: 'Could not load available times. Please try again later.',
  slotTaken: 'Sorry, that time was just taken. Please choose another.',
  genericError: 'Something went wrong. Please try again.',
  networkError: 'Could not reach the server. Please try again.',
} satisfies Record<string, Message>;

export type MessageKey = keyof typeof en;
type Catalogue = Record<MessageKey, Message>;

const es: Catalogue = {
  bookACall: 'Reservar una llamada',
  chooseDay: 'Elige un día',
  timesShownIn: 'Las horas se muestran en {zone}.',
  backToDays: 'Volver a los días',
  name: 'Nombre',
  email: 'Correo electrónico',
  notes: 'Notas',
  optional: '(opcional)',
  booking: 'Reservando…',
  confirmBooking: 'Confirmar reserva',
  backToTimes: 'Volver a las horas',
  booked: 'Reserva confirmada',
  emailOnItsWay: 'Te hemos enviado un correo de confirmación a {email}.',
  previousMonth: 'Mes anterior',
  nextMonth: 'Mes siguiente',
  loadingTimes: 'Cargando horas disponibles…',
  monthNone: '{month}: no hay horas disponibles.',
  monthDays: {
    one: '{month}: {count} día con horas disponibles.',
    other: '{month}: {count} días con horas disponibles.',
  },
  dayUnavailable: '{date}, no disponible',
  dayTimes: {
    one: '{date}, {count} hora disponible',
    other: '{date}, {count} horas disponibles',
  },
  loadFailed:
    'No se han podido cargar las horas disponibles. Inténtalo más tarde.',
  slotTaken: 'Lo sentimos, alguien acaba de reservar esa hora. Elige otra.',
  genericError: 'Algo ha fallado. Inténtalo de nuevo.',
  networkError: 'No se ha podido conectar con el servidor. Inténtalo de nuevo.',
};

const it: Catalogue = {
  bookACall: 'Prenota una chiamata',
  chooseDay: 'Scegli un giorno',
  timesShownIn: 'Gli orari sono indicati nel fuso orario {zone}.',
  backToDays: 'Torna ai giorni',
  name: 'Nome',
  email: 'Email',
  notes: 'Note',
  optional: '(facoltativo)',
  booking: 'Prenotazione in corso…',
  confirmBooking: 'Conferma prenotazione',
  backToTimes: 'Torna agli orari',
  booked: 'Prenotazione confermata',
  emailOnItsWay: "Ti abbiamo inviato un'email di conferma a {email}.",
  previousMonth: 'Mese precedente',
  nextMonth: 'Mese successivo',
  loadingTimes: 'Caricamento degli orari disponibili…',
  monthNone: '{month}: nessun orario disponibile.',
  monthDays: {
    one: '{month}: {count} giorno con orari disponibili.',
    other: '{month}: {count} giorni con orari disponibili.',
  },
  dayUnavailable: '{date}, non disponibile',
  dayTimes: {
    one: '{date}, {count} orario disponibile',
    other: '{date}, {count} orari disponibili',
  },
  loadFailed: 'Impossibile caricare gli orari disponibili. Riprova più tardi.',
  slotTaken:
    "Ci dispiace, qualcuno ha appena prenotato quell'orario. Scegline un altro.",
  genericError: 'Qualcosa è andato storto. Riprova.',
  networkError: 'Impossibile contattare il server. Riprova.',
};

const eu: Catalogue = {
  bookACall: 'Dei bat erreserbatu',
  chooseDay: 'Aukeratu egun bat',
  timesShownIn: 'Orduak {zone} ordu-zonan erakusten dira.',
  backToDays: 'Itzuli egunetara',
  name: 'Izena',
  email: 'Helbide elektronikoa',
  notes: 'Oharrak',
  optional: '(aukerakoa)',
  booking: 'Erreserbatzen…',
  confirmBooking: 'Baieztatu erreserba',
  backToTimes: 'Itzuli orduetara',
  booked: 'Erreserba baieztatuta',
  emailOnItsWay: 'Baieztapen-mezu bat bidali dugu helbide honetara: {email}.',
  previousMonth: 'Aurreko hilabetea',
  nextMonth: 'Hurrengo hilabetea',
  loadingTimes: 'Ordu libreak kargatzen…',
  monthNone: '{month}: ez dago ordu librerik.',
  monthDays: {
    one: '{month}: {count} egun ordu libreekin.',
    other: '{month}: {count} egun ordu libreekin.',
  },
  dayUnavailable: '{date}, ez dago libre',
  dayTimes: {
    one: '{date}, {count} ordu libre',
    other: '{date}, {count} ordu libre',
  },
  loadFailed: 'Ezin izan dira ordu libreak kargatu. Saiatu berriro geroago.',
  slotTaken:
    'Barkatu, norbaitek ordu hori erreserbatu berri du. Aukeratu beste bat.',
  genericError: 'Zerbait gaizki joan da. Saiatu berriro.',
  networkError: 'Ezin izan da zerbitzariarekin konektatu. Saiatu berriro.',
};

const catalogues: Record<string, Catalogue> = { en, es, it, eu };

/** The catalogue language for a locale such as es-ES or es_ES, falling back to English. */
export function languageOf(locale: string): string {
  const base = locale.toLowerCase().split(/[-_]/)[0];
  return base in catalogues ? base : 'en';
}

export type Translate = (
  key: MessageKey,
  params?: Record<string, string | number>,
) => string;

/** A t() function for one locale: picks the plural form and fills in {placeholders}. */
export function createTranslator(locale: string): Translate {
  const language = languageOf(locale);
  const catalogue = catalogues[language];
  const plurals = new Intl.PluralRules(language);

  return (key, params = {}) => {
    const message = catalogue[key];
    const text =
      typeof message === 'string'
        ? message
        : plurals.select(Number(params.count ?? 0)) === 'one'
          ? message.one
          : message.other;

    return text.replace(/\{(\w+)\}/g, (match, name: string) =>
      name in params ? String(params[name]) : match,
    );
  };
}

/** Every language must define every key; used by the tests. */
export function catalogueKeys(): Record<string, string[]> {
  return Object.fromEntries(
    Object.entries(catalogues).map(([lang, cat]) => [
      lang,
      Object.keys(cat).sort(),
    ]),
  );
}
