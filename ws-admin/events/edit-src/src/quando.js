// Il tempo di un evento: fuso, orari, e le incoerenze che vale la pena dire.
//
// Un'ora senza fuso è ambigua. «17:30» del 7 giugno a Ostia e «17:30» a Berlino
// sono due istanti diversi, e chi legge il JSON da fuori (Google, un feed, un'app)
// non ha modo di saperlo. Perciò le date salvate portano lo scarto da UTC
// (`2026-06-07T17:30:00+02:00`), mentre il form continua a mostrare l'ora di
// parete — quella che sta scritta sulla locandina.
//
// Il NOME del fuso («Europe/Rome») non si ricava dallo scarto: +02:00 d'estate lo
// hanno mezza Europa e mezza Africa. Perciò viaggia a parte, in `meetoo:timezone`,
// e serve solo a far ricordare al campo che cosa si era scelto.

/** Il fuso del browser, che per il redattore è quasi sempre quello giusto. */
export function fusoDelBrowser() {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Rome';
  } catch {
    return 'Europe/Rome';
  }
}

/** Elenco dei fusi noti al browser (poche centinaia); se non li espone, i nostri. */
export function fusiDisponibili() {
  try {
    const tutti = Intl.supportedValuesOf?.('timeZone');
    if (Array.isArray(tutti) && tutti.length) return tutti;
  } catch { /* browser vecchio */ }
  return ['Europe/Rome', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Madrid', 'UTC'];
}

/** Scarto da UTC, in minuti, per un istante in una zona. */
function scartoMinuti(zona, istante) {
  const f = new Intl.DateTimeFormat('en-US', { timeZone: zona, timeZoneName: 'longOffset' });
  const nome = f.formatToParts(istante).find((x) => x.type === 'timeZoneName')?.value || 'GMT+00:00';
  const m = /GMT([+-])(\d{2}):?(\d{2})?/.exec(nome);
  if (!m) return 0;
  return (m[1] === '-' ? -1 : 1) * (Number(m[2]) * 60 + Number(m[3] || 0));
}

/**
 * Scarto da UTC («+02:00») di un'ora di parete in una zona.
 *
 * Due passaggi, non uno: per sapere lo scarto serve l'istante, ma per avere
 * l'istante serve lo scarto. Si parte leggendo l'ora come se fosse UTC, si vede
 * quanto scarto avrebbe, si corregge e si richiede. Basta, tranne che nell'ora
 * che si ripete o che non esiste al cambio dell'ora legale.
 */
export function offsetDi(zona, oraLocale) {
  if (!zona) return '';
  const m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(String(oraLocale ?? '').trim());
  if (!m) return '';
  const comeUtc = Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]);
  try {
    const primo = scartoMinuti(zona, new Date(comeUtc));
    const vero = scartoMinuti(zona, new Date(comeUtc - primo * 60000));
    const segno = vero < 0 ? '-' : '+';
    const abs = Math.abs(vero);
    return `${segno}${String(Math.floor(abs / 60)).padStart(2, '0')}:${String(abs % 60).padStart(2, '0')}`;
  } catch {
    return ''; // zona sconosciuta al browser: meglio nessuno scarto che uno inventato
  }
}

/** Aggiunge (o rifà) lo scarto a una data con ora. Le date senza ora restano nude:
 *  «2026-06-07» è un giorno, non un istante, e uno scarto non le riguarda. */
export function conOffset(iso, zona) {
  const s = String(iso ?? '').trim();
  if (!s || !s.includes('T')) return s;
  const nudo = senzaOffset(s);
  const off = offsetDi(zona, nudo);
  if (!off) return s;
  const conSecondi = /T\d{2}:\d{2}:\d{2}/.test(nudo) ? nudo : nudo + ':00';
  return conSecondi + off;
}

/** Toglie scarto e secondi: la forma che vogliono i campi del form. */
export function senzaOffset(iso) {
  const s = String(iso ?? '').trim();
  const m = /^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/.exec(s);
  return m ? m[1] : s.replace(/(Z|[+-]\d{2}:?\d{2})$/, '');
}

export const dataDi = (iso) => String(iso ?? '').slice(0, 10);
export const oraDi = (iso) => {
  const m = /T(\d{2}:\d{2})/.exec(String(iso ?? ''));
  return m ? m[1] : '';
};
/** Data + ora → datetime locale. Senza uno dei due non c'è niente da comporre. */
export const componi = (data, ora) => (data && ora ? `${dataDi(data)}T${ora}` : '');

const minuti = (iso) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(String(iso ?? ''));
  return m ? Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) / 60000 : null;
};

const giorno = (iso) => dataDi(senzaOffset(iso));
const ITA = { weekday: 'long', day: 'numeric', month: 'long' };
const quandoLeggibile = (iso) => {
  const d = new Date(senzaOffset(iso));
  return isNaN(d) ? iso : d.toLocaleDateString('it-IT', ITA) + ' ' + oraDi(iso);
};

/** Da una ricorrenza del form alla domanda «questa data ci sta dentro?». */
function rispettaRicorrenza(ric, iso) {
  if (!ric || !ric.frequency) return true;
  if (ric.frequency !== 'weekly' || !ric.byDay?.length) return true; // solo il caso che sappiamo dire
  const SIGLE = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
  const d = new Date(senzaOffset(iso));
  return isNaN(d) ? true : ric.byDay.includes(SIGLE[d.getDay()]);
}

/**
 * Le incoerenze di «Quando». Non blocca niente: elenca quello che non torna, con
 * parole che dicano anche che cosa farne. `occorrenze` è una mappa @id → startDate
 * (dall'indice degli eventi), assente finché non è stata caricata.
 */
export function avvisiQuando(d, occorrenze = null) {
  const out = [];
  const serie = d?.primaryType === 'EventSeries';
  const inizio = minuti(d?.startDate);
  const fine = minuti(d?.endDate);

  if (inizio !== null && fine !== null && fine < inizio) {
    out.push({ tipo: 'errore', testo: 'La fine viene prima dell’inizio.' });
  }

  if (!serie) {
    if (d?.startDate && d?.endDate && giorno(d.startDate) !== giorno(d.endDate)) {
      out.push({
        tipo: 'avviso',
        testo:
          'L’evento comincia un giorno e finisce un altro. Se sono più giornate distinte, ' +
          'di solito conviene una Collezione con un’occorrenza per giorno.',
      });
    }
    // Programma: gli orari stanno dentro la giornata dell'evento?
    for (const [i, s] of (d?.subEvent ?? []).entries()) {
      const a = minuti(s?.startDate);
      const b = minuti(s?.endDate);
      const eti = s?.name ? `«${s.name}»` : `la voce ${i + 1} del programma`;
      if (a !== null && b !== null && b < a) {
        out.push({ tipo: 'errore', testo: `Nel programma, ${eti} finisce prima di cominciare.` });
      }
      if (a !== null && inizio !== null && a < inizio) {
        out.push({ tipo: 'avviso', testo: `Nel programma, ${eti} comincia prima dell’evento.` });
      }
      if (b !== null && fine !== null && b > fine) {
        out.push({ tipo: 'avviso', testo: `Nel programma, ${eti} finisce dopo l’evento.` });
      }
    }
    return out;
  }

  // ---- Collezione: le occorrenze devono stare dentro quello che dice «Quando».
  const elenco = d?.occurrences ?? [];
  if (!elenco.length) return out;
  if (!occorrenze) {
    out.push({ tipo: 'nota', testo: 'Le date delle occorrenze non sono ancora state lette dall’indice.' });
    return out;
  }
  const senzaData = [];
  for (const o of elenco) {
    const id = (o?.id || '').trim();
    if (!id) continue;
    const quando = occorrenze[id] || occorrenze[id.replace(/^events\//, '')];
    if (!quando) {
      senzaData.push(id);
      continue;
    }
    const q = minuti(quando);
    if (inizio !== null && q !== null && q < inizio) {
      out.push({ tipo: 'avviso', testo: `L’occorrenza del ${quandoLeggibile(quando)} è prima dell’inizio della collezione.` });
    }
    if (fine !== null && q !== null && q > fine) {
      out.push({ tipo: 'avviso', testo: `L’occorrenza del ${quandoLeggibile(quando)} è dopo la fine della collezione.` });
    }
    if (!rispettaRicorrenza(d?.eventSchedule, quando)) {
      out.push({ tipo: 'avviso', testo: `L’occorrenza del ${quandoLeggibile(quando)} non cade in un giorno previsto dalla ricorrenza.` });
    }
  }
  if (senzaData.length) {
    out.push({
      tipo: 'nota',
      testo:
        `${senzaData.length} occorrenz${senzaData.length > 1 ? 'e non risultano' : 'a non risulta'} nell’indice ` +
        `(${senzaData.slice(0, 3).join(', ')}${senzaData.length > 3 ? '…' : ''}): rigenera l’indice, o l’@id non esiste.`,
    });
  }
  // Una ricorrenza mensile con date sparse non descrive niente: meglio dirlo.
  const ric = d?.eventSchedule;
  if (ric?.frequency === 'monthly' && elenco.length > 2) {
    const date = elenco
      .map((o) => occorrenze[(o?.id || '').trim()] || occorrenze[(o?.id || '').replace(/^events\//, '')])
      .filter(Boolean)
      .map((x) => giorno(x))
      .sort();
    const mesi = new Set(date.map((x) => x.slice(0, 7)));
    if (date.length > mesi.size) {
      out.push({
        tipo: 'avviso',
        testo: 'La ricorrenza dice «ogni mese», ma ci sono più occorrenze nello stesso mese: la ricorrenza non descrive le date reali.',
      });
    }
  }
  return out;
}
