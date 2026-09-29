/* ===========================================================================
 * cards.js - Meetoo's cards in the browser (the twin of template-parts/carte.php).
 *
 * The skeleton and the tools are the shared ones: your-theme/js/cards.js
 * (WS.cards), drawn by your-theme/css/cards.css. Here only what is Meetoo's:
 * the lead block (a date, an icon), the facts of an event or a place, and what
 * "interest" means on Meetoo.
 *
 *   Meetoo.eventCard(ev, opts)   an event from the index, its date on the left
 *   Meetoo.tileCard(opts)        a card with an icon (collections, groups, sections)
 *   Meetoo.placeCard(place)      a place: type, address, rating
 *
 *   .card > .card-date|.card-icon + .card-body(.card-title > a.card-link,
 *           .card-meta) + .card-tools | .card-actions | .card-arrow
 * =========================================================================== */
(function () {
  var MESI = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
    });
  }
  function icon(name) { return '<span class="material-symbols-outlined">' + esc(name) + '</span>'; }
  function metaItem(ico, text) { return '<span>' + (ico ? icon(ico) : '') + esc(text) + '</span>'; }
  function C() { return window.WS && window.WS.cards; }

  /* ---- Interest ------------------------------------------------------------
   * The shared cards ask ('ws:card-interest'); Meetoo answers:
   *   an event  -> on the server (meetoo:interestedIn): one has to be signed in;
   *   a place   -> in the browser of whoever looks (places have no public
   *                register of favourites yet), under the key the waterfront
   *                has always used, so what was marked stays marked. */
  var FAV_KEY = 'meetoo:favorites';
  function favSet() {
    try { return new Set(JSON.parse(localStorage.getItem(FAV_KEY) || '[]')); } catch (e) { return new Set(); }
  }
  function favSalva(set) {
    // Array.from, not slice: a Set has no indices and slice would give [].
    try { localStorage.setItem(FAV_KEY, JSON.stringify(Array.from(set))); } catch (e) {}
  }
  function toast(msg, ico) { if (C()) C().toast(msg, ico); }
  function share(url, titolo) { if (C()) C().share(url, titolo); }
  function segna(btn, acceso) {
    btn.classList.toggle('on', !!acceso);
    btn.setAttribute('aria-pressed', acceso ? 'true' : 'false');
  }

  document.addEventListener('ws:card-interest', function (e) {
    var d = e.detail || {};
    var btn = d.button;
    e.preventDefault();                                  // Meetoo answers
    if (d.kind === 'event') {
      var S = window.meetooSession;
      if (!(S && S.getUser && S.getUser())) { toast('Accedi per segnare gli eventi che ti interessano.', 'info'); return; }
      var prima = btn.classList.contains('on');
      segna(btn, !prima);                                // at once; the server confirms
      S.api('like', { path: d.id }).then(function (r) {
        var ok = r.status === 200 && r.body && typeof r.body.liked === 'boolean';
        if (ok) segna(btn, r.body.liked);
        else { segna(btn, prima); toast('Non sono riuscito a registrare il tuo interesse.', 'error'); }
      });
      return;
    }
    var set = favSet();
    if (set.has(d.id)) set.delete(d.id); else set.add(d.id);
    favSalva(set);
    segna(btn, set.has(d.id));
  });

  /* The tools of a card: share + interest ('social') and the pen ('edit'). */
  function strumenti(o) {
    var t = {};
    if (o.social) {
      t.share = o.social.url || true;
      t.interest = { kind: o.social.kind || 'place', id: o.social.id || '', on: o.social.kind !== 'event' && favSet().has(o.social.id) };
    }
    if (o.edit) t.edit = o.edit;
    return (t.share || t.edit) ? t : null;
  }
  function social(o) { return C() ? C().tools(strumenti({ social: o || {} }), (o && o.url) || '') : ''; }

  // One skeleton: only the lead block (date or icon) and the tail change.
  // With opts.actions (the admin) the card holds labelled buttons and its link
  // is the title alone; otherwise the title's link covers the card.
  function card(href, head, title, metas, opts) {
    opts = opts || {};
    return C().card({
      href: href, external: opts.external, className: opts.className,
      head: head, title: title, meta: metas,
      tools: opts.actions ? null : strumenti(opts),
      actions: opts.actions || null
    });
  }

  // Icona di chi organizza/anima: distingue un GRUPPO o un'organizzazione da
  // un'ATTIVITÀ LOCALE, e riconosce i casi che meritano un segno proprio
  // (biblioteca, libreria, associazione di volontariato). Il tipo comanda; il nome
  // interviene solo quando il tipo è generico (es. una biblioteca è LocalBusiness).
  function orgIcon(type, name) {
    var t = [].concat(type || []).join(' ').toLowerCase();
    var n = String(name || '').toLowerCase();

    // ATTIVITÀ LOCALE: il nome serve solo a precisare di che attività si tratta
    // (una biblioteca resta un LocalBusiness, ma non è un negozio).
    if (/localbusiness|store|shop|restaurant|cafe|bar\b/.test(t)) {
      if (/library|biblioteca/.test(t + ' ' + n)) return 'local_library';
      if (/bookstore|libreria/.test(t + ' ' + n)) return 'menu_book';
      return 'storefront';
    }
    // GRUPPI E ORGANIZZAZIONI: decide il tipo, non come si chiamano — un'APS o un
    // comitato sono Organization tanto quanto un club.
    if (/ngo|nonprofit|charit/.test(t)) return 'volunteer_activism';
    if (/organization|group|club|association/.test(t)) return 'groups';

    // Tipo assente o sconosciuto: ultima risorsa, si guarda il nome.
    if (/biblioteca|library/.test(n)) return 'local_library';
    if (/onlus|\baps\b|associazion|comitato|volontar/.test(n)) return 'volunteer_activism';
    return 'groups';
  }

  // Stato dell'evento (schema.org eventStatus) → badge accanto al titolo.
  function statusBadge(status) {
    var s = String(status || '');
    if (/Cancelled/i.test(s)) return '<span class="badge cancelled">' + icon('cancel') + 'Annullato</span>';
    if (/Postponed/i.test(s)) return '<span class="badge postponed">' + icon('update') + 'Rinviato</span>';
    if (/Rescheduled/i.test(s)) return '<span class="badge rescheduled">' + icon('update') + 'Riprogrammato</span>';
    return '';
  }

  // "Nome del luogo, Località" da un luogo {name, address:{addressLocality}}.
  // Si mostra sempre il `name` (l'eventuale alternateName resta un'alternativa,
  // non un sostituto) e sempre la località; il CAP no, è un dato tecnico.
  function placeText(p) {
    if (!p) return '';
    var loc = p.address && p.address.addressLocality;
    return p.name ? (loc ? p.name + ', ' + loc : p.name) : (loc || '');
  }
  // Come sopra, ma partendo da una voce dell'indice eventi ({place:{…}}).
  function placeLabel(ev) { return placeText(ev && ev.place); }

  // Risolve un RIFERIMENTO a luogo ({@id,name} come lo scrive l'evento) leggendo
  // il file del luogo: stessa regola dell'indicizzatore (nome canonico + località),
  // così indice e pagine dicono la stessa cosa. Ripiega sul riferimento se il file
  // non c'è. Una richiesta per luogo, memorizzata.
  var placeCache = {};
  function resolvePlace(contentBase, ref) {
    if (typeof ref === 'string') ref = { '@id': ref };
    if (!ref || typeof ref !== 'object') return Promise.resolve(null);
    if (Array.isArray(ref)) ref = ref[0] || {};
    var id = String(ref['@id'] || '').replace(/^\/+/, '');
    var fallback = { id: id, name: ref.name || id.split('/').pop(), address: ref.address };
    if (!id || !contentBase) return Promise.resolve(fallback);
    var key = contentBase + id;
    if (!placeCache[key]) {
      placeCache[key] = fetch(contentBase + id + '/index.json', { headers: { Accept: 'application/json' } })
        .then(function (r) {
          var ct = r.headers.get('content-type') || '';
          return (r.ok && ct.indexOf('json') !== -1) ? r.json() : null;
        })
        .then(function (j) {
          var e = j && (j.mainEntity || j);
          if (!e) return fallback;
          return { id: id, name: e.name || fallback.name, address: e.address || ref.address, geo: e.geo, hasMap: e.hasMap, '@type': e['@type'] };
        })
        .catch(function () { return fallback; });
    }
    return placeCache[key];
  }

  window.Meetoo = window.Meetoo || {};

  /* ---- Card evento (indice eventi) ----------------------------------------
   * ev: {path, name, startDate, status, organizer, place{…}} dall'indice.
   * opts.base   query ?base= da propagare (anteprime su un'altra base contenuti)
   * opts.organizer  false = non mostrare l'organizzatore (pagine già "sue")
   * opts.actions    azioni in coda [{href,icon,label,title,primary,external}]
   *                 (pagine di gestione: Visualizza / Modifica / Duplica…)
   * opts.extraMeta  voci meta aggiuntive [{icon,text}] (es. ultima modifica)
   * opts.badge      etichetta accanto al titolo (es. avviso riferimenti rotti) */
  Meetoo.eventCard = function (ev, opts) {
    opts = opts || {};
    var baseQ = opts.base || '';
    var dt = ev.startDate ? new Date(ev.startDate) : null;
    var ok = dt && !isNaN(dt);
    var head = '<div class="card-date"><span class="d">' + esc(ok ? dt.getDate() : '·') + '</span>' +
      '<span class="m">' + esc(ok ? MESI[dt.getMonth()] : '') + '</span>' +
      '<span class="y">' + esc(ok ? dt.getFullYear() : '') + '</span></div>';

    var metas = [];
    if (ok && /T\d/.test(ev.startDate)) {
      metas.push(metaItem('schedule', dt.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })));
    }
    if (opts.organizer !== false && ev.organizer) metas.push(metaItem(orgIcon(ev.organizerType, ev.organizer), ev.organizer));
    var place = placeLabel(ev);
    if (place) metas.push(metaItem('location_on', place));
    (opts.extraMeta || []).forEach(function (m) { if (m && m.text) metas.push(metaItem(m.icon, m.text)); });

    var title = esc(ev.name || '(senza titolo)') + statusBadge(ev.status) + (opts.badge || '');
    /* Dove porta il titolo. Era `event.html?id=…`, il prototipo del tema: adesso
     * quel file è in archivio, e il titolo di ogni evento portava a un 404. Chi
     * disegna la card sa dove vuole mandare e lo dice con `viewUrl`; chi non lo
     * dice non ottiene un link sbagliato, ottiene un titolo che non è un link. */
    var href = opts.viewUrl || '';
    // Condividi + «mi interessa» di serie; le pagine di gestione, dove servono
    // altre azioni, le tolgono con social: false.
    if (opts.social !== false && !opts.actions) {
      opts = Object.assign({}, opts, { social: { kind: 'event', id: ev.path, url: href } });
    }
    return card(href, head, title, metas, opts);
  };

  /* ---- Card con icona (collezioni, gruppi, voci di sezione) ---------------- */
  Meetoo.tileCard = function (o) {
    var head = '<div class="card-icon' + (o.accent ? ' accent' : '') + '">' + icon(o.icon || 'chevron_right') + '</div>';
    var metas = o.meta ? [metaItem(o.metaIcon || '', o.meta)] : [];
    return card(o.href, head, esc(o.title) + (o.badge || ''), metas, o);
  };

  /* ---- Card luogo (collezioni di luoghi) ----------------------------------
   * p: documento/riferimento del luogo. Se manca hasMap, il link mappa si
   * costruisce dalle coordinate (nessuna chiamata a Google). */
  Meetoo.placeCard = function (p, opts) {
    opts = opts || {};
    var types = [].concat(p['@type'] || []).join(' ').toLowerCase();
    var ico = /park|playground|beach/.test(types) ? 'park'
      : /library|book/.test(types) ? 'local_library'
      : /localbusiness|store|restaurant|cafe|bar/.test(types) ? 'storefront' : 'place';
    var name = p.name || String(p['@id'] || '').split('/').pop();
    var addr = (p.address && (p.address.streetAddress || p.address)) || '';
    var loc = (p.address && p.address.addressLocality) || '';
    var geo = p.geo || {};
    var href = opts.href || (typeof p.hasMap === 'string' ? p.hasMap : (p.hasMap && p.hasMap.url))
      || (geo.latitude ? 'https://www.google.com/maps/search/?api=1&query=' + geo.latitude + ',' + geo.longitude : '');

    var metas = [];
    if (typeof addr === 'string' && addr) metas.push(metaItem('', loc ? addr + ', ' + loc : addr));
    else if (loc) metas.push(metaItem('', loc));
    var rating = p.aggregateRating && p.aggregateRating.ratingValue;
    if (rating) metas.push(metaItem('star', rating));

    var opzioni = { className: opts.className, external: opts.external !== false };
    if (opts.social !== false) {
      opzioni.social = { kind: 'place', id: String(p['@id'] || name), url: opts.shareUrl || href };
    }
    return card(href, '<div class="card-icon">' + icon(ico) + '</div>', esc(name), metas, opzioni);
  };

  // Luogo: risoluzione del riferimento + testo "Nome, Località" (regola unica,
  // uguale a quella dell'indice eventi).
  Meetoo.social = social;
  Meetoo.share = share;
  Meetoo.toast = toast;
  Meetoo.orgIcon = orgIcon;
  Meetoo.resolvePlace = resolvePlace;
  Meetoo.placeText = placeText;

  // Utilità condivise, così le pagine non le riscrivono.
  Meetoo.cardUtils = { esc: esc, icon: icon, metaItem: metaItem, statusBadge: statusBadge, orgIcon: orgIcon, placeLabel: placeLabel, placeText: placeText, MESI: MESI };
})();
