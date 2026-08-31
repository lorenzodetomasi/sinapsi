/* ===========================================================================
 * cards.js — template CONDIVISI delle card di Meetoo (come meetoo.css per gli stili).
 *
 * Una card si scrive in UN posto solo: qui. Le pagine passano i dati e ottengono
 * il markup, così ordine dei meta, badge di stato e struttura restano identici
 * ovunque (home, organizer, collection, collezioni di luoghi).
 *
 * Uso:  <script src="cards.js"></script>   (dopo/insieme a header.js)
 *   Meetoo.eventCard(ev, opts)   evento dall'indice → card con data a sinistra
 *   Meetoo.tileCard(opts)        card generica con icona (collezioni, gruppi, sezioni)
 *   Meetoo.placeCard(place)      luogo → card con tipo, indirizzo, voto
 *
 * Struttura comune (invariata fra i tipi):
 *   a.card > .card-date|.card-icon + .card-body(.card-title + .card-meta) + .card-arrow
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

  /* ---- Condividi + «mi interessa» ------------------------------------------
   * La coppia di icone che il lungomare aveva in fondo alle sue card, ora per
   * tutte. Il MARKUP è uno solo; cambia dove finisce il «mi interessa»:
   *   kind 'event'  → sul server (meetoo:interestedIn), serve essere collegati;
   *   kind 'place'  → nel browser di chi guarda (i luoghi non hanno ancora un
   *                   registro pubblico dei preferiti).
   * Il click è intercettato una volta sola, sul documento: le card si creano e
   * si distruggono di continuo e attaccare un ascoltatore a ognuna è sprecato. */
  var FAV_KEY = 'meetoo:favorites';   // la stessa che usava il lungomare: i preferiti già segnati restano
  function favSet() {
    try { return new Set(JSON.parse(localStorage.getItem(FAV_KEY) || '[]')); } catch (e) { return new Set(); }
  }
  function favSalva(set) {
    // Array.from, non slice: un Set non ha indici e slice restituirebbe [].
    try { localStorage.setItem(FAV_KEY, JSON.stringify(Array.from(set))); } catch (e) {}
  }

  // Messaggio passeggero in fondo allo schermo (stili in meetoo.css).
  var toastTimer = 0;
  function toast(msg, ico) {
    var t = document.getElementById('mt-toast');
    if (!t) { t = document.createElement('div'); t.id = 'mt-toast'; t.className = 'toast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
    t.innerHTML = icon(ico || 'check_circle') + esc(msg);
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('show'); }, 4000);
  }

  /* Condivide: sui telefoni apre il pannello di sistema (si può mandare a
   * WhatsApp, ai messaggi…), altrove copia il link e lo dice. */
  function share(url, titolo) {
    url = url || location.href;
    if (navigator.share) {
      navigator.share({ title: titolo || document.title, url: url }).catch(function () {});
      return;
    }
    var fatto = function () { toast('Link copiato negli appunti'); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(fatto, function () { copiaAlVolo(url, fatto); });
    } else copiaAlVolo(url, fatto);
  }
  function copiaAlVolo(testo, poi) {
    var ta = document.createElement('textarea');
    ta.value = testo; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;left:-9999px';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); poi(); } catch (e) {}
    ta.remove();
  }

  /* Markup della coppia. `id` è ciò che si condivide e si segna: il percorso
   * dell'evento o l'@id del luogo. `url` è dove porta la condivisione. */
  function social(o) {
    o = o || {};
    var attivo = o.kind === 'event' ? false : favSet().has(o.id);
    return '<div class="card-social" data-social-kind="' + esc(o.kind || 'place') + '" data-social-id="' + esc(o.id || '') + '"' +
      (o.url ? ' data-social-url="' + esc(o.url) + '"' : '') + '>' +
      '<button type="button" class="share" title="Condividi">' + icon('share') + '</button>' +
      '<button type="button" class="fav' + (attivo ? ' on' : '') + '" title="Mi interessa">' + icon('favorite') + '</button>' +
      '</div>';
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.card-social button');
    if (!btn) return;
    var box = btn.closest('.card-social');
    e.preventDefault(); e.stopPropagation();   // la card sotto è un link: non seguirlo
    var id = box.getAttribute('data-social-id') || '';
    if (btn.classList.contains('share')) {
      var url = box.getAttribute('data-social-url');
      share(url ? new URL(url, location.href).href : location.href);
      return;
    }
    // «mi interessa»
    var kind = box.getAttribute('data-social-kind');
    var S = window.meetooSession;
    if (kind === 'event' && S && S.getUser && S.getUser()) {
      btn.classList.toggle('on');                       // risposta immediata
      S.api('like', { path: id }).then(function (r) {
        var ok = r.status === 200 && r.body && typeof r.body.liked === 'boolean';
        if (ok) btn.classList.toggle('on', r.body.liked);
        else { btn.classList.toggle('on'); toast('Non sono riuscito a registrare il tuo interesse.', 'error'); }
      });
      return;
    }
    if (kind === 'event') { toast('Accedi per segnare gli eventi che ti interessano.', 'info'); return; }
    var set = favSet();
    if (set.has(id)) set.delete(id); else set.add(id);
    favSalva(set);
    btn.classList.toggle('on', set.has(id));
  });

  // Scheletro unico: cambia solo il "cappello" (data o icona) e la coda.
  // Senza azioni la card È un link (freccia in coda); con opts.actions diventa un
  // contenitore con più azioni (i link non si annidano dentro un altro link):
  // il titolo resta cliccabile su href. Stessa struttura in entrambi i casi.
  function card(href, head, title, metas, opts) {
    opts = opts || {};
    var cls = 'card' + (opts.className ? ' ' + opts.className : '');
    var attrs = opts.external ? ' target="_blank" rel="noopener"' : '';
    var arrow = opts.external ? 'open_in_new' : 'arrow_forward';
    var acts = opts.actions || null;
    var titleHtml = (acts && href) ? '<a href="' + esc(href) + '"' + attrs + '>' + title + '</a>' : title;
    var body = '<div class="card-body"><h3 class="card-title">' + titleHtml + '</h3>' +
      (metas && metas.length ? '<div class="card-meta">' + metas.join('') + '</div>' : '') + '</div>';

    // Condividi + «mi interessa»: i pulsanti NON possono stare dentro il link
    // (un elemento cliccabile dentro un altro), quindi la card resta un link e
    // la coppia gli sta accanto, in un contenitore che li sovrappone in coda.
    var soc = opts.social ? social(opts.social) : '';

    if (!acts) {
      var link = '<a class="' + cls + '" href="' + esc(href) + '"' + attrs + '>' +
        head + body + (soc ? '' : '<div class="card-arrow">' + icon(arrow) + '</div>') + '</a>';
      return soc ? '<div class="card-holder">' + link + soc + '</div>' : link;
    }
    var tail = '<div class="card-actions">' + acts.map(function (a) {
      return '<a class="card-act' + (a.primary ? ' primary' : '') + '" href="' + esc(a.href) + '"' +
        (a.external ? ' target="_blank" rel="noopener"' : '') +
        (a.title ? ' title="' + esc(a.title) + '"' : '') + '>' +
        icon(a.icon) + '<span>' + esc(a.label) + '</span></a>';
    }).join('') + '</div>';
    return '<div class="' + cls + '">' + head + body + tail + soc + '</div>';
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
