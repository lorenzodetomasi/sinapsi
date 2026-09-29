// THE SHAPE OF AN EVENT IN TIME: six cases, one list (decided on 29 Sep 2026).
//
// The same list opens /ws-admin/events/add/ and heads the form, in the order of
// how often they happen on Meetoo. Choosing one sets what the form needs and
// shows only what serves it:
//
//   singolo    one appointment              Event, «Quando» on one date
//   incontri   several meetings, each its   a container: EventSeries with its
//              own (a course, a book club)  occurrences, each an event
//   periodo    days in a row (an            one file: «Quando» from… to…
//              exhibition, a festival)
//   repliche   the same event in several    one file: «Quando» with several
//              dates (a show's replicas)    dates
//   regola     every week or month, always  one file: «Quando» with a rule
//              the same (a workshop)
//   giornata   a day with several           Event with its programme (the rows
//              appointments (a conference)  of the day, without pages of their own)
//
// periodo, repliche and regola are written as EventSeries in one file
// (jsonld-adapter.js); in the form they are events with their «Quando».

export const FORME = [
  { const: 'singolo', title: 'Un appuntamento' },
  { const: 'incontri', title: 'Più incontri, ognuno diverso' },
  { const: 'periodo', title: 'Più giorni di fila' },
  { const: 'repliche', title: 'Lo stesso evento in più date' },
  { const: 'regola', title: 'Ogni settimana o mese, sempre uguale' },
  { const: 'giornata', title: 'Una giornata con più appuntamenti' },
];

const MODO = { singolo: 'una', repliche: 'piu', regola: 'regola', periodo: 'periodo', giornata: 'una' };

/** The shape of form data already built (primaryType, quando, subEvent). */
export function formaDi(d) {
  if (d?.primaryType === 'EventSeries') return 'incontri';
  const programma = (d?.subEvent ?? []).some((s) => s && (s.name || s.startDate || s.description));
  const modo = d?.quando?.modo || 'una';
  if (modo === 'piu') return 'repliche';
  if (modo === 'regola') return 'regola';
  if (modo === 'periodo') return 'periodo';
  return programma ? 'giornata' : 'singolo';
}

/** The form data dressed for a shape: the type, the mode of «Quando», a programme row. */
export function conForma(d, forma) {
  const f = FORME.some((x) => x.const === forma) ? forma : 'singolo';
  const out = { ...d, forma: f };
  if (f === 'incontri') {
    out.primaryType = 'EventSeries';
    return out;
  }
  out.primaryType = 'Event';
  out.quando = { ...(d.quando || {}), modo: MODO[f] };
  if (f === 'giornata' && !(d.subEvent ?? []).length) {
    out.subEvent = [{ name: '', description: '', startDate: '', endDate: '' }];
  }
  return out;
}

/** ?tipo= of the chooser (and of the older chooser) -> a shape. */
export function formaDaTipo(tipo) {
  const vecchi = { 'serie-regolare': 'incontri', 'serie-variabile': 'incontri' };
  const t = vecchi[tipo] || tipo;
  return FORME.some((x) => x.const === t) ? t : 'singolo';
}
