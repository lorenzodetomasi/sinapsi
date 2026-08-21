<?php
/*
 * Gestione eventi — elenco di tutti gli eventi con le azioni redazionali.
 *
 * Tre sezioni: Collezioni (le serie, senza data), Prossimi eventi (dal più vicino)
 * e Archivio (dal più recente, caricato solo su richiesta). Ogni card porta a
 * "Visualizza" (pagina pubblica), "Modifica" e "Duplica" (editor).
 *
 * I dati vengono dagli indici statici events/_index/*.json: la pagina li legge una
 * volta e ne mostra 10 alla volta mentre si scorre, quindi non serve paginare lato
 * server. Questo file è ANCHE il piccolo endpoint JSON della pagina (POST):
 *   action=auth        → identità + ruolo (login richiesto per vedere l'elenco)
 *   action=check-refs  → riferimenti rotti, per segnalarli sulle card
 */

require_once __DIR__ . '/../lib/ws-auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $user = ws_authenticate($_POST['credential'] ?? '');
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
        exit;
    }
    // Stessi ruoli che possono salvare un evento: chi non può editare non entra.
    $canEdit = in_array($user['role'], ['user', 'client', 'admin', 'super-admin'], true);

    if ($action === 'auth') {
        echo json_encode([
            'uid' => $user['uid'], 'email' => $user['email'], 'role' => $user['role'],
            'name' => $user['name'] ?? '', 'canEdit' => $canEdit,
        ]);
        exit;
    }

    if ($action === 'check-refs') {
        if (!$canEdit) { http_response_code(403); echo json_encode(['error' => 'Permessi insufficienti.']); exit; }
        require_once __DIR__ . '/../lib/events-check.php';
        $broken = event_check_refs(__DIR__ . '/../../ws-custom/contents/meetoo/it_IT');
        // Raggruppati per evento: la card mostra solo il proprio avviso.
        $byEvent = [];
        foreach ($broken as $b) $byEvent[$b['from']][] = ['field' => $b['field'], 'ref' => $b['ref']];
        echo json_encode(['broken' => $byEvent, 'total' => count($broken)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Azione non valida.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestione eventi — Meetoo</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Slab:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
  <link rel="stylesheet" href="../../ws-custom/themes/meetoo/meetoo.css">
  <style>
    /* Solo le specificità di questa pagina: il resto è in meetoo.css. */
    .toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
    .toolbar input[type="search"] { flex: 1 1 240px; min-width: 0; }
    .toolbar input, .toolbar select {
      background: var(--color-background-section1); color: var(--color-text);
      border: 1px solid var(--line); border-radius: 999px; padding: 8px 14px; font: inherit;
    }
    .toolbar input:focus, .toolbar select:focus { outline: none; border-color: var(--accent); }
    .filter-note { color: var(--color-hint); font-size: .85rem; }
    .sec-actions { margin-left: auto; display: flex; gap: 8px; align-items: center; }
    .btn-more {
      display: inline-flex; align-items: center; gap: 6px; margin: 14px auto 0;
      padding: 8px 18px; border: 1px solid var(--line); border-radius: 999px;
      background: transparent; color: var(--color-link); font: inherit; font-weight: 600; cursor: pointer;
    }
    .btn-more:hover { border-color: var(--accent); }
    .sentinel { height: 1px; }
    .badge.broken { background: var(--warn-bg); color: var(--warn-fg); }
    #gate { text-align: center; padding: 48px 16px; color: var(--color-hint); }
    #gate .material-symbols-outlined { font-size: 2.5rem; color: var(--accent); }
    #app { display: none; }
    #app.on { display: block; }
  </style>
</head>
<body>
  <div class="wrap">
    <div id="gate">
      <span class="material-symbols-outlined">lock</span>
      <p id="gate-msg">Accedi con Google (in alto a destra) per gestire gli eventi.</p>
    </div>

    <div id="app">
      <div class="toolbar">
        <input type="search" id="q" placeholder="Cerca per titolo, luogo o organizzatore…" aria-label="Cerca">
        <select id="f-org" aria-label="Filtra per organizzatore"><option value="">Tutti gli organizzatori</option></select>
        <select id="f-coll" aria-label="Filtra per collezione"><option value="">Tutte le collezioni</option></select>
      </div>
      <div class="filter-note" id="filter-note"></div>

      <section class="collections" id="sec-collections">
        <h2 class="sec-head"><span class="material-symbols-outlined">collections_bookmark</span>Collezioni <span class="count" id="coll-count"></span></h2>
        <div class="cards" id="coll-list"><div class="empty">Carico…</div></div>
      </section>

      <section id="sec-upcoming">
        <h2 class="sec-head"><span class="material-symbols-outlined">event_upcoming</span>Prossimi eventi <span class="count" id="up-count"></span></h2>
        <div class="cards" id="up-list"><div class="empty">Carico…</div></div>
        <div class="sentinel" id="up-sentinel"></div>
      </section>

      <section class="archive" id="sec-archive">
        <h2 class="sec-head"><span class="material-symbols-outlined">history</span>Archivio eventi passati <span class="count" id="ar-count"></span></h2>
        <div class="cards" id="ar-list"></div>
        <div class="sentinel" id="ar-sentinel"></div>
        <div style="display:flex"><button type="button" class="btn-more" id="ar-load">
          <span class="material-symbols-outlined">history</span> Carica eventi passati
        </button></div>
      </section>
    </div>
  </div>

  <button id="add-event-fab" class="fab" title="Aggiungi un nuovo evento" style="display:none">
    <span class="material-symbols-outlined">add</span>
  </button>

  <!-- Moduli condivisi: template delle card + header (prima dello script di pagina). -->
  <script src="../../ws-custom/themes/meetoo/cards.js"></script>
  <script src="../../ws-custom/themes/meetoo/header.js"></script>

  <script>
  (function () {
    const SITE_ROOT = location.pathname.replace(/\/ws-admin\/.*/, '/');
    const CONTENT_BASE = SITE_ROOT + 'ws-custom/contents/meetoo/it_IT/';
    const THEME = SITE_ROOT + 'ws-custom/themes/meetoo/';
    const EDIT = SITE_ROOT + 'ws-admin/events/edit/';
    const PAGE = 10;                       // quanti se ne mostrano per volta
    const esc = Meetoo.cardUtils.esc;

    const state = {
      series: [], upcoming: [], archive: [],
      shown: { up: 0, ar: 0 }, archiveOpen: false, broken: {},
    };

    (function crumb() {
      if (!window.Meetoo) { setTimeout(crumb, 100); return; }
      Meetoo.setBreadcrumb(
        [{ label: 'Lido di Ostia', href: THEME + 'index.html' }, { label: 'Gestione eventi', current: true }],
        [{ label: 'Admin' }]
      );
    })();

    /* ---------- Dati ---------- */
    const getJson = (url) => fetch(url, { headers: { Accept: 'application/json' } })
      .then((r) => {
        const ct = r.headers.get('content-type') || '';
        return (r.ok && ct.includes('json')) ? r.json() : Promise.reject(r.status);
      });

    const api = (action, fields) => {
      const body = new URLSearchParams(Object.assign({ action }, fields || {}));
      const token = window.meetooSession && meetooSession.getToken();
      if (token) body.set('credential', token);
      return fetch(location.pathname, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(),
      }).then((r) => r.json().then((j) => ({ status: r.status, body: j }), () => ({ status: r.status, body: {} })));
    };

    /* ---------- Card ---------- */
    // Avviso sui riferimenti rotti dell'evento (organizer/luogo/collezione mancanti).
    function brokenBadge(path) {
      const b = state.broken[path];
      if (!b || !b.length) return '';
      const what = b.map((x) => x.field + ' → ' + x.ref).join('; ');
      return '<span class="badge broken" title="' + esc(what) + '">' +
        '<span class="material-symbols-outlined">link_off</span>' + b.length + ' rif. rotti</span>';
    }
    const fmtDate = (s) => {
      const d = s ? new Date(s) : null;
      return d && !isNaN(d) ? d.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
    };
    // Azioni redazionali: la card è la stessa del sito, con la coda diversa.
    const actions = (ev) => [
      { href: THEME + 'event.html?id=' + encodeURIComponent(ev.path), icon: 'visibility', label: 'Visualizza', title: 'Apri la pagina pubblica', external: true },
      { href: EDIT + '?id=' + encodeURIComponent(ev.path), icon: 'edit', label: 'Modifica', title: 'Apri nell\'editor', primary: true },
      { href: EDIT + '?from=' + encodeURIComponent(ev.path), icon: 'content_copy', label: 'Duplica', title: 'Nuovo evento a partire da questo' },
    ];
    const eventCard = (ev) => Meetoo.eventCard(ev, {
      actions: actions(ev),
      badge: brokenBadge(ev.path),
      extraMeta: [{ icon: 'history', text: ev.dateModified ? 'agg. ' + fmtDate(ev.dateModified) : '' }],
    });
    const seriesCard = (ev) => Meetoo.tileCard({
      href: THEME + 'collection.html?id=' + encodeURIComponent(ev.path),
      icon: 'collections_bookmark',
      title: ev.name || ev.path,
      meta: (ev.organizer || 'Collezione di eventi'),
      badge: brokenBadge(ev.path),
      actions: actions(ev),
    });

    /* ---------- Filtri ---------- */
    const norm = (s) => String(s == null ? '' : s).toLowerCase();
    function matches(ev) {
      const q = norm(document.getElementById('q').value.trim());
      const org = document.getElementById('f-org').value;
      const coll = document.getElementById('f-coll').value;
      if (org && ev.organizerKey !== org) return false;
      if (coll && ev.collection !== coll) return false;
      if (!q) return true;
      const hay = [ev.name, ev.organizer, ev.place && ev.place.name,
        ev.place && ev.place.address && ev.place.address.addressLocality, ev.path].map(norm).join(' ');
      return hay.includes(q);
    }

    /* ---------- Render a blocchi (10 per volta, altri scorrendo) ---------- */
    function renderChunk(which) {
      const isUp = which === 'up';
      const list = (isUp ? state.upcoming : state.archive).filter(matches);
      const box = document.getElementById(isUp ? 'up-list' : 'ar-list');
      const n = Math.min(state.shown[which] + PAGE, list.length);
      const html = list.slice(state.shown[which], n).map(eventCard).join('');
      if (state.shown[which] === 0) box.innerHTML = '';
      box.insertAdjacentHTML('beforeend', html);
      state.shown[which] = n;
      document.getElementById(isUp ? 'up-count' : 'ar-count').textContent = list.length;
      if (!list.length) box.innerHTML = '<div class="empty">' + (isUp ? 'Nessun evento in programma.' : 'Nessun evento in archivio.') + '</div>';
      return n < list.length;   // true = ce ne sono ancora
    }

    function resetLists() {
      state.shown.up = 0;
      renderChunk('up');
      const colls = state.series.filter(matches);
      document.getElementById('coll-list').innerHTML = colls.length ? colls.map(seriesCard).join('') : '<div class="empty">Nessuna collezione.</div>';
      document.getElementById('coll-count').textContent = colls.length;
      if (state.archiveOpen) { state.shown.ar = 0; renderChunk('ar'); }
      const filtered = document.getElementById('q').value.trim() || document.getElementById('f-org').value || document.getElementById('f-coll').value;
      document.getElementById('filter-note').textContent = filtered ? 'Filtri attivi: i conteggi si riferiscono ai risultati.' : '';
    }

    // Scorrimento: quando la sentinella entra in vista, un altro blocco.
    function observe(id, which) {
      const el = document.getElementById(id);
      new IntersectionObserver((entries) => {
        if (!entries.some((e) => e.isIntersecting)) return;
        if (which === 'ar' && !state.archiveOpen) return;
        renderChunk(which);
      }, { rootMargin: '200px' }).observe(el);
    }

    /* ---------- Avvio (dopo il login) ---------- */
    let started = false;
    function start() {
      if (started) return;
      started = true;
      document.getElementById('gate').style.display = 'none';
      document.getElementById('app').classList.add('on');
      document.getElementById('add-event-fab').style.display = '';

      getJson(CONTENT_BASE + 'events/_index/events.json')
        .then((list) => {
          const all = Array.isArray(list) ? list : [];
          state.series = all.filter((e) => e.kind === 'series')
            .sort((a, b) => String(a.name).localeCompare(String(b.name)));
          // Prossimi: dal più vicino (è quello da curare adesso).
          state.upcoming = all.filter((e) => e.kind !== 'series')
            .sort((a, b) => new Date(a.startDate) - new Date(b.startDate));

          // Filtri popolati dai dati presenti.
          const orgs = new Map(), colls = new Map();
          all.forEach((e) => {
            if (e.organizerKey) orgs.set(e.organizerKey, e.organizer || e.organizerKey);
            if (e.collection) colls.set(e.collection, e.collection.split('/').pop());
          });
          const fill = (id, map) => {
            const sel = document.getElementById(id);
            [...map.entries()].sort((a, b) => String(a[1]).localeCompare(String(b[1])))
              .forEach(([v, label]) => sel.insertAdjacentHTML('beforeend', '<option value="' + esc(v) + '">' + esc(label) + '</option>'));
          };
          fill('f-org', orgs); fill('f-coll', colls);

          resetLists();
          observe('up-sentinel', 'up');
          observe('ar-sentinel', 'ar');
        })
        .catch(() => {
          document.getElementById('up-list').innerHTML =
            '<div class="state err">Indice non disponibile: apri l\'editor e usa «Rebuild index».</div>';
        });

      // Riferimenti rotti: avviso sulle card (non blocca l'elenco).
      api('check-refs').then((r) => {
        if (r.status !== 200 || !r.body.broken) return;
        state.broken = r.body.broken;
        resetLists();
      });
    }

    /* ---------- Login: l'elenco si vede solo da autenticati ---------- */
    (function auth() {
      if (!window.meetooSession) { setTimeout(auth, 100); return; }
      meetooSession.subscribe((user) => {
        if (!user) {
          document.getElementById('gate-msg').textContent = 'Accedi con Google (in alto a destra) per gestire gli eventi.';
          return;
        }
        api('auth').then((r) => {
          if (r.status === 200 && r.body.canEdit) start();
          else document.getElementById('gate-msg').textContent =
            'Il tuo account (' + (r.body.email || '') + ', ruolo ' + (r.body.role || '?') + ') non è abilitato a gestire gli eventi.';
        });
      });
    })();

    document.getElementById('add-event-fab').addEventListener('click', () => { location.href = EDIT; });
    document.getElementById('ar-load').addEventListener('click', function () {
      this.style.display = 'none';
      state.archiveOpen = true;
      getJson(CONTENT_BASE + 'events/_index/events.archive.json')
        .then((list) => {
          // Archivio: dal più recente.
          state.archive = (Array.isArray(list) ? list : [])
            .sort((a, b) => new Date(b.startDate) - new Date(a.startDate));
          state.shown.ar = 0;
          renderChunk('ar');
        })
        .catch(() => { document.getElementById('ar-list').innerHTML = '<div class="state err">Archivio non disponibile.</div>'; });
    });

    ['q', 'f-org', 'f-coll'].forEach((id) => {
      document.getElementById(id).addEventListener('input', resetLists);
    });
  })();
  </script>
</body>
</html>
