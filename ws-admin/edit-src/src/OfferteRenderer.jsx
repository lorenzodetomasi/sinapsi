import { useEffect, useMemo, useState } from 'react';
import { rankWith, and, uiTypeIs, optionIs } from '@jsonforms/core';
import { withJsonFormsControlProps, useJsonForms } from '@jsonforms/react';
import { CONTENT_BASE } from './config.js';
import { dateDi } from './quandoModello.js';

/* Tickets and sign-ups: one row per price.
 *
 * A theatre has full and reduced, under 12, a school matinée, a second child
 * at half price, a workshop fee to be paid on sign-up by email. Each is a row:
 * name, price, where to buy or sign up (a web address, or just an email), and
 * - when needed - for whom, on which condition, from which ticket, on which
 * dates. The model is in ws-admin/lib/event-offers.php.
 *
 * THE PRICE LISTS of the organizer (its `makesOffer`, grouped by `category`)
 * are buttons: a click COPIES that list into the event, where it can be
 * changed. A copy, not a link: a ticket price is a fact of that evening, and a
 * new season's prices must not rewrite it. */

const NOMI = ['Intero', 'Ridotto', 'Ridotto under 12', 'Matinée per le scuole', '2° figlio', 'Quota di partecipazione', 'Ingresso gratuito'];
const MESI = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
const breve = (g) => {
  const d = new Date(g + 'T12:00:00');
  return `${['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'][d.getDay()]} ${d.getDate()} ${MESI[d.getMonth()]}`;
};
const VUOTA = { name: '', price: '', priceCurrency: 'EUR', url: '', availability: '', condizione: '', eta: '', pubblico: '', minimo: '', date: [], iscrizione: false };

/** The organizer's price lists: { category: [Offer, ...] }, from its own file. */
const cache = new Map();
function listini(orgId) {
  const id = String(orgId || '').replace(/^\/+|\/+$/g, '');
  if (!id) return Promise.resolve({});
  if (!cache.has(id)) {
    cache.set(id, fetch(CONTENT_BASE + id + '/index.json', { cache: 'no-store' })
      .then((r) => (r.ok && (r.headers.get('content-type') || '').includes('json') ? r.json() : null))
      .then((j) => {
        const e = j?.mainEntity || j || {};
        const out = {};
        (Array.isArray(e.makesOffer) ? e.makesOffer : e.makesOffer ? [e.makesOffer] : []).forEach((o) => {
          const c = o.category || 'Listino';
          (out[c] = out[c] || []).push(o);
        });
        return out;
      })
      .catch(() => ({})));
  }
  return cache.get(id);
}

/** An Offer of a price list as a row of the form (the same shape the adapter makes). */
const riga = (o) => ({
  ...VUOTA,
  name: o.name || '', price: o.price ?? '', priceCurrency: o.priceCurrency || 'EUR', url: o.url || '',
  condizione: o.description || '', eta: o['meetoo:eligibleAge'] || '', pubblico: o['meetoo:audience'] || '',
  minimo: o.eligibleQuantity?.minValue || '', iscrizione: o['meetoo:registration'] === 'required',
});

function OfferteRenderer({ data, handleChange, path, visible }) {
  const ctx = useJsonForms();
  const ev = ctx?.core?.data || {};
  const orgId = ev.organizer?.[0]?.id || '';
  const [liste, setListe] = useState({});
  const [aperte, setAperte] = useState({});
  useEffect(() => { let vivo = true; listini(orgId).then((l) => vivo && setListe(l)); return () => { vivo = false; }; }, [orgId]);
  const giorni = useMemo(() => (ev.quando?.modo === 'piu' ? dateDi(ev.quando) : []), [JSON.stringify(ev.quando)]); // eslint-disable-line react-hooks/exhaustive-deps
  if (visible === false) return null;

  const righe = (Array.isArray(data) ? data : []).map((r) => ({ ...VUOTA, ...r }));
  const scrivi = (r) => handleChange(path, r);
  const setR = (i, x) => scrivi(righe.map((r, j) => (j === i ? { ...r, ...x } : r)));

  return (
    <div className="offerte">
      <div className="occ-azioni">
        {Object.entries(liste).map(([nome, offerte]) => (
          <button key={nome} type="button" className="btn-ghost" title={offerte.map((o) => `${o.name}: ${o.price} €`).join(' · ')}
            onClick={() => scrivi([...righe, ...offerte.map(riga)])}>
            <span className="material-symbols-outlined">playlist_add</span>{nome}
          </button>
        ))}
        <button type="button" className="btn-ghost" onClick={() => scrivi([...righe, { ...VUOTA }])}>
          <span className="material-symbols-outlined">add</span>Un prezzo
        </button>
      </div>
      {!Object.keys(liste).length && orgId ? (
        <p className="quando-aiuto">L’organizzatore non ha listini: si scrivono nel suo file, come <code>makesOffer</code>.</p>
      ) : null}

      <datalist id="offerte-nomi">{NOMI.map((n) => <option key={n} value={n} />)}</datalist>
      {righe.map((r, i) => (
        <div className="offerta" key={i}>
          <div className="quando-linea">
            <input type="text" list="offerte-nomi" placeholder="Intero, Ridotto…" value={r.name} onChange={(e) => setR(i, { name: e.target.value })} />
            <span className="quando-orari">
              <input type="number" min="0" step="0.5" className="quando-num" placeholder="€" value={r.price}
                onChange={(e) => setR(i, { price: e.target.value === '' ? '' : Number(e.target.value) })} />€
            </span>
            <input type="text" className="offerta-url" placeholder="Link ai biglietti, o email per le iscrizioni" value={String(r.url || '').replace(/^mailto:/, '')}
              onChange={(e) => setR(i, { url: e.target.value })} />
            <label className="quando-orari" title="Senza iscrizione o prenotazione non si entra">
              <input type="checkbox" checked={!!r.iscrizione} onChange={(e) => setR(i, { iscrizione: e.target.checked })} />iscrizione
            </label>
            <button type="button" className="btn-icona" title="Dettagli: per chi, a che condizione, in quali date"
              onClick={() => setAperte({ ...aperte, [i]: !aperte[i] })}>
              <span className="material-symbols-outlined">{aperte[i] ? 'expand_less' : 'tune'}</span>
            </button>
            <button type="button" className="btn-icona" title="Togli" onClick={() => scrivi(righe.filter((_, j) => j !== i))}>
              <span className="material-symbols-outlined">close</span>
            </button>
          </div>
          {(aperte[i] || r.eta || r.pubblico || r.condizione || r.minimo || r.date?.length) ? (
            <div className="quando-linea offerta-dettagli">
              età <input type="text" className="quando-num" placeholder="0-11" value={r.eta} onChange={(e) => setR(i, { eta: e.target.value })} />
              per <input type="text" placeholder="scuole" value={r.pubblico} onChange={(e) => setR(i, { pubblico: e.target.value })} />
              dal <input type="number" min="1" className="quando-num" placeholder="1" value={r.minimo} onChange={(e) => setR(i, { minimo: e.target.value })} />° biglietto
              <input type="text" className="offerta-url" placeholder="condizione, es. «dal secondo figlio»" value={r.condizione} onChange={(e) => setR(i, { condizione: e.target.value })} />
              {giorni.length > 1 ? (
                <span className="quando-giorni-testo">
                  solo il
                  {giorni.map((g) => (
                    <button key={g} type="button" className={'quando-chip' + (r.date?.includes(g) ? ' on' : '')}
                      onClick={() => setR(i, { date: r.date?.includes(g) ? r.date.filter((x) => x !== g) : [...(r.date || []), g] })}>
                      {breve(g)}
                    </button>
                  ))}
                  <small>(nessuna scelta = tutte le date)</small>
                </span>
              ) : null}
            </div>
          ) : null}
        </div>
      ))}
    </div>
  );
}

export const offerteTester = rankWith(18, and(uiTypeIs('Control'), optionIs('offerte', true)));
export default withJsonFormsControlProps(OfferteRenderer);
