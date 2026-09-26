// "Quando" of a single event: four ways an event can sit in time, one field.
//
//   una      one date: startDate / endDate, as always
//   piu      several dates of the same event - the replicas of a show:
//            one schema.org Schedule per date, without a repeatFrequency
//   regola   it repeats: every Monday 20:30-22, from ... to ..., except ...:
//            one Schedule with repeatFrequency and byDay
//   periodo  from one day to another - an exhibition, a festival, a workshop
//            week; optionally with its opening hours (Fri-Sun 17-20), which
//            are a weekly Schedule marked meetoo:kind = opening-hours
//
// The server expands all of them the same way (ws-admin/lib/event-dates.php);
// this module only translates between the file and the form. A series
// (EventSeries) does not use it: its dates are its occurrences.

const GIORNI = { MO: 'Monday', TU: 'Tuesday', WE: 'Wednesday', TH: 'Thursday', FR: 'Friday', SA: 'Saturday', SU: 'Sunday' };
const DA_NOME = Object.fromEntries(Object.entries(GIORNI).map(([k, v]) => [v.toUpperCase(), k]));

const asArray = (v) => (Array.isArray(v) ? v : v === undefined || v === null || v === '' ? [] : [v]);
export const giornoDi = (iso) => (/^(\d{4}-\d{2}-\d{2})/.exec(String(iso || '')) || [])[1] || '';
export const oraDi = (iso) => (/T(\d{2}:\d{2})/.exec(String(iso || '')) || [])[1] || '';

/** byDay in any spelling ("MO", "Monday", "https://schema.org/Monday", lists) -> ["MO", ...]. */
export function codiciGiorni(byDay) {
  const parti = Array.isArray(byDay) ? byDay : String(byDay || '').split(/[\s,;]+/);
  const out = [];
  parti.forEach((p) => {
    let x = String((p && typeof p === 'object' ? p['@id'] : p) || '').trim().toUpperCase();
    x = x.replace(/^HTTPS?:\/\/SCHEMA\.ORG\//, '');
    const c = GIORNI[x] ? x : DA_NOME[x];
    if (c && !out.includes(c)) out.push(c);
  });
  return Object.keys(GIORNI).filter((k) => out.includes(k));
}

export const QUANDO_VUOTO = {
  modo: 'una',
  date: [],
  regola: { freq: 'W', interval: 1, byDay: [], dal: '', al: '', dalle: '', alle: '', tranne: [] },
  periodo: { dal: '', al: '', dalle: '', alle: '', orari: false, byDay: [], apertoDalle: '', apertoAlle: '' },
};

/** File -> form. */
export function quandoDa(doc) {
  const q = JSON.parse(JSON.stringify(QUANDO_VUOTO));
  const s0 = giornoDi(doc?.startDate);
  const s1 = giornoDi(doc?.endDate);
  const sched = asArray(doc?.eventSchedule).filter((x) => x && typeof x === 'object');
  if (!sched.length) {
    if (s0 && s1 && s1 > s0) {
      q.modo = 'periodo';
      Object.assign(q.periodo, { dal: s0, al: s1, dalle: oraDi(doc.startDate), alle: oraDi(doc.endDate) });
    }
    return q;
  }
  const ripete = sched.find((x) => /^P\d+[DWMY]$/i.test(String(x.repeatFrequency || '')));
  if (ripete && ripete['meetoo:kind'] === 'opening-hours') {
    q.modo = 'periodo';
    Object.assign(q.periodo, {
      dal: s0 || giornoDi(ripete.startDate), al: s1 || giornoDi(ripete.endDate),
      orari: true, byDay: codiciGiorni(ripete.byDay),
      apertoDalle: String(ripete.startTime || '').slice(0, 5), apertoAlle: String(ripete.endTime || '').slice(0, 5),
    });
    return q;
  }
  if (ripete) {
    const m = /^P(\d+)([DWMY])$/i.exec(ripete.repeatFrequency);
    q.modo = 'regola';
    Object.assign(q.regola, {
      freq: m[2].toUpperCase(), interval: Number(m[1]) || 1, byDay: codiciGiorni(ripete.byDay),
      dal: giornoDi(ripete.startDate) || s0, al: giornoDi(ripete.endDate) || '',
      dalle: String(ripete.startTime || '').slice(0, 5) || oraDi(doc.startDate),
      alle: String(ripete.endTime || '').slice(0, 5),
      tranne: asArray(ripete.exceptDate).map(giornoDi).filter(Boolean),
    });
    return q;
  }
  q.modo = 'piu';
  q.date = sched
    .map((x) => ({ giorno: giornoDi(x.startDate), dalle: String(x.startTime || '').slice(0, 5), alle: String(x.endTime || '').slice(0, 5) }))
    .filter((d) => d.giorno)
    .sort((a, b) => (a.giorno + a.dalle).localeCompare(b.giorno + b.dalle));
  return q;
}

const conOra = (g, o) => (g ? (o ? `${g}T${o}` : g) : '');

/**
 * Form -> the date fields of the file: { startDate, endDate, eventSchedule }
 * as wall-clock values (the adapter adds the offset). null means "mode una":
 * the plain startDate/endDate of the form are used as they are.
 */
export function quandoPer(q, fuso) {
  if (!q || q.modo === 'una' || !q.modo) return null;
  const tz = fuso ? { scheduleTimezone: fuso } : {};
  if (q.modo === 'piu') {
    const date = (q.date || []).filter((d) => d.giorno).sort((a, b) => (a.giorno + a.dalle).localeCompare(b.giorno + b.dalle));
    if (!date.length) return { startDate: '', endDate: '', eventSchedule: undefined };
    const ultima = date[date.length - 1];
    return {
      startDate: conOra(date[0].giorno, date[0].dalle),
      endDate: date.length > 1 || ultima.alle ? conOra(ultima.giorno, ultima.alle) : '',
      eventSchedule: date.map((d) => ({
        '@type': 'Schedule', startDate: d.giorno,
        ...(d.dalle ? { startTime: d.dalle } : {}), ...(d.alle ? { endTime: d.alle } : {}), ...tz,
      })),
    };
  }
  if (q.modo === 'regola') {
    const r = q.regola || {};
    const freq = ['D', 'W', 'M', 'Y'].includes(r.freq) ? r.freq : 'W';
    return {
      startDate: conOra(r.dal, r.dalle),
      endDate: r.al ? conOra(r.al, r.alle) : '',
      eventSchedule: [{
        '@type': 'Schedule',
        repeatFrequency: `P${Math.max(1, Number(r.interval) || 1)}${freq}`,
        ...(freq === 'W' && r.byDay?.length ? { byDay: r.byDay.map((c) => `https://schema.org/${GIORNI[c]}`) } : {}),
        ...(r.dal ? { startDate: r.dal } : {}), ...(r.al ? { endDate: r.al } : {}),
        ...(r.dalle ? { startTime: r.dalle } : {}), ...(r.alle ? { endTime: r.alle } : {}),
        ...(r.tranne?.length ? { exceptDate: [...r.tranne].sort() } : {}),
        ...tz,
      }],
    };
  }
  // periodo
  const p = q.periodo || {};
  return {
    startDate: conOra(p.dal, p.dalle),
    endDate: conOra(p.al, p.alle),
    eventSchedule: p.orari && p.byDay?.length
      ? [{
          '@type': 'Schedule', repeatFrequency: 'P1W',
          byDay: p.byDay.map((c) => `https://schema.org/${GIORNI[c]}`),
          ...(p.dal ? { startDate: p.dal } : {}), ...(p.al ? { endDate: p.al } : {}),
          ...(p.apertoDalle ? { startTime: p.apertoDalle } : {}), ...(p.apertoAlle ? { endTime: p.apertoAlle } : {}),
          'meetoo:kind': 'opening-hours', ...tz,
        }]
      : undefined,
  };
}

/** The dates a "Quando" produces, for the preview: ['YYYY-MM-DD', ...], at most `max`. */
export function dateDi(q, max = 400) {
  if (!q) return [];
  if (q.modo === 'piu') return (q.date || []).map((d) => d.giorno).filter(Boolean).sort();
  if (q.modo !== 'regola') return [];
  const r = q.regola || {};
  if (!r.dal) return [];
  const passo = Math.max(1, Number(r.interval) || 1);
  const fine = r.al || '';
  const tranne = new Set(r.tranne || []);
  const SIG = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
  const [y, m, d] = r.dal.split('-').map(Number);
  const primo = new Date(Date.UTC(y, m - 1, d));
  const giorni = r.freq === 'W' ? (r.byDay?.length ? r.byDay : [SIG[primo.getUTCDay()]]) : null;
  const out = [];
  const iso = (x) => x.toISOString().slice(0, 10);
  const c = new Date(primo);
  for (let i = 0; i < 3000 && out.length < max; i++) {
    const g = iso(c);
    if (fine && g > fine) break;
    if (!fine && out.length >= 60) break;   // open-ended: a preview, not a year
    let prendi = true;
    if (r.freq === 'W') prendi = Math.floor((c - primo) / 604800000) % passo === 0 && giorni.includes(SIG[c.getUTCDay()]);
    if (prendi && !tranne.has(g)) out.push(g);
    if (r.freq === 'W' || r.freq === 'D') c.setUTCDate(c.getUTCDate() + (r.freq === 'D' ? passo : 1));
    else if (r.freq === 'M') c.setUTCMonth(c.getUTCMonth() + passo);
    else c.setUTCFullYear(c.getUTCFullYear() + passo);
  }
  return out;
}
