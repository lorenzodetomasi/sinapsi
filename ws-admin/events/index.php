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

// La pagina fa anche da endpoint JSON: gli errori PHP non devono finire nel corpo
// della risposta (il client vedrebbe "Unexpected token" invece del vero errore).
ini_set('display_errors', '0');

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

    $base = __DIR__ . '/../../ws-custom/contents/meetoo/it_IT';

    if ($action === 'check-refs') {
        if (!$canEdit) { http_response_code(403); echo json_encode(['error' => 'Permessi insufficienti.']); exit; }
        require_once __DIR__ . '/../lib/events-check.php';
        $broken = event_check_refs($base);
        // Raggruppati per evento: la card mostra solo il proprio avviso.
        $byEvent = [];
        foreach ($broken as $b) $byEvent[$b['from']][] = ['field' => $b['field'], 'ref' => $b['ref']];
        echo json_encode(['broken' => $byEvent, 'total' => count($broken)]);
        exit;
    }

    // --- Cestino ---------------------------------------------------------
    // Cestinare/ripristinare: come modificare un evento (chi può editare, può
    // toglierlo di mezzo). Eliminare PER SEMPRE: solo admin/super-admin.
    if (in_array($action, ['trash', 'trash-list', 'trash-restore', 'trash-delete', 'trash-empty'], true)) {
        if (!$canEdit) { http_response_code(403); echo json_encode(['error' => 'Permessi insufficienti.']); exit; }
        $lib = __DIR__ . '/../lib/events-trash.php';
        if (!is_file($lib)) { http_response_code(500); echo json_encode(['error' => 'Cestino non disponibile: manca lib/events-trash.php sul server (deploy incompleto).']); exit; }
        require_once $lib;
        $isAdmin = in_array($user['role'], ['admin', 'super-admin'], true);

        if ($action === 'trash-list') { echo json_encode(['items' => ws_trash_load($base)]); exit; }

        if ($action === 'trash') {
            $res = ws_trash_move($base, (string)($_POST['path'] ?? ''), $user);
            if (!$res['ok']) { http_response_code(400); echo json_encode(['error' => $res['error']]); exit; }
            // L'evento è uscito da events/: gli indici vanno rifatti, altrimenti
            // resterebbe nelle liste (e nelle pagine pubbliche).
            require_once __DIR__ . '/../lib/events-index.php';
            $rebuilt = event_index_rebuild($base);
            echo json_encode(['success' => true, 'entry' => $res['entry'], 'index' => $rebuilt]);
            exit;
        }

        if ($action === 'trash-restore') {
            $res = ws_trash_restore($base, (string)($_POST['id'] ?? ''));
            if (!$res['ok']) { http_response_code(400); echo json_encode(['error' => $res['error']]); exit; }
            require_once __DIR__ . '/../lib/events-index.php';
            $rebuilt = event_index_rebuild($base);
            echo json_encode(['success' => true, 'entry' => $res['entry'], 'index' => $rebuilt]);
            exit;
        }

        // Da qui in poi si cancella davvero: niente ripristino possibile.
        if (!$isAdmin) { http_response_code(403); echo json_encode(['error' => 'Solo admin/super-admin possono eliminare definitivamente.']); exit; }

        if ($action === 'trash-delete') {
            $res = ws_trash_delete($base, (string)($_POST['id'] ?? ''));
            if (!$res['ok']) { http_response_code(400); echo json_encode(['error' => $res['error']]); exit; }
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'trash-empty') {
            $res = ws_trash_empty($base);
            echo json_encode(['success' => $res['ok'], 'deleted' => $res['deleted'], 'failed' => $res['failed']]);
            exit;
        }
    }

    // Manutenzione (solo admin/super-admin), stessa semantica dell'editor:
    //   rebuild-index → normalizza i riferimenti + reindicizza + controlla
    //   normalize     → in più ripara la struttura (serie annidate, occorrenze
    //                   dichiarate ma assenti, subEvent↔superEvent)
    if ($action === 'rebuild-index' || $action === 'normalize') {
        if (!in_array($user['role'], ['admin', 'super-admin'], true)) {
            http_response_code(403); echo json_encode(['error' => 'Solo admin/super-admin possono fare manutenzione sugli indici.']); exit;
        }
        require_once __DIR__ . '/../lib/events-index.php';
        require_once __DIR__ . '/../lib/events-migrate.php';
        require_once __DIR__ . '/../lib/events-check.php';
        $norm = null;
        if ($action === 'normalize') {
            require_once __DIR__ . '/../lib/events-normalize.php';
            $norm = event_normalize($base, true);
        }
        $mig = event_migrate_refs($base, true);
        $res = event_index_rebuild($base);
        echo json_encode([
            'success' => true,
            'index' => $res,
            'migrated' => is_array($mig) ? (count($mig['files'] ?? $mig)) : 0,
            'normalized' => $norm,
            'brokenRefs' => count(event_check_refs($base)),
        ]);
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
      border: 1px solid var(--color-line); border-radius: 999px; padding: 8px 14px; font: inherit;
    }
    .toolbar input:focus, .toolbar select:focus { outline: none; border-color: var(--color-link); }
    .filter-note { color: var(--color-hint); font-size: .85rem; }
    .sec-actions { margin-left: auto; display: flex; gap: 8px; align-items: center; }
    .btn-more {
      display: inline-flex; align-items: center; gap: 6px; margin: 14px auto 0;
      padding: 8px 18px; border: 1px solid var(--color-line); border-radius: 999px;
      background: transparent; color: var(--color-link); font: inherit; font-weight: 600; cursor: pointer;
    }
    .btn-more:hover { border-color: var(--color-link); }
    .btn-more.danger { color: var(--color-danger); }
    .btn-more.danger:hover { border-color: var(--color-danger); }
    .admin-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 10px 0 0; }
    .admin-bar .btn-more { margin: 0; }
    .admin-bar .count { min-width: 1.6em; }
    #sec-trash .card { opacity: .9; }
    .sentinel { height: 1px; }
    .badge.broken { background: var(--color-background-warning); color: var(--color-warning); }
    #gate { text-align: center; padding: 48px 16px; color: var(--color-hint); }
    #gate .material-symbols-outlined { font-size: 2.5rem; color: var(--color-link); }
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

      <div class="admin-bar" id="admin-bar" hidden>
        <button type="button" class="btn-more" id="btn-rebuild">
          <span class="material-symbols-outlined">manage_history</span> Rigenera indice
        </button>
        <button type="button" class="btn-more" id="btn-normalize"
                title="Ripara la struttura: serie annidate, occorrenze dichiarate ma assenti, subEvent↔superEvent. Poi reindicizza.">
          <span class="material-symbols-outlined">healing</span> Normalizza
        </button>
        <button type="button" class="btn-more" id="btn-trash">
          <span class="material-symbols-outlined">delete</span> Cestino <span class="count" id="trash-count">0</span>
        </button>
        <span class="filter-note" id="admin-msg"></span>
      </div>

      <section id="sec-trash" hidden>
        <h2 class="sec-head"><span class="material-symbols-outlined">delete</span>Cestino <span class="count" id="tr-count"></span>
          <button type="button" class="btn-more danger" id="btn-empty" style="margin-left:auto">
            <span class="material-symbols-outlined">delete_forever</span> Svuota il cestino
          </button>
        </h2>
        <div class="cards" id="tr-list"></div>
      </section>

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
      shown: { up: 0, ar: 0 }, archiveOpen: false, broken: {}, trash: [],
    };

    (function crumb() {
      if (!window.Meetoo) { setTimeout(crumb, 100); return; }
      // "Gestione" risale all'hub, "Eventi" è dove sei.
      Meetoo.setBreadcrumb([
        { label: 'Gestione', href: SITE_ROOT + 'ws-admin/index.php', title: 'Amministrazione' },
        { label: 'Eventi', current: true },
      ]);
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
    // Avviso sui problemi dell'evento: riferimenti rotti (organizer/luogo/collezione
    // mancanti) e @id non canonico. Li ripara «Normalizza» / «Rigenera indice».
    function brokenBadge(path) {
      const b = state.broken[path];
      if (!b || !b.length) return '';
      const what = b.map((x) => x.field + ' → ' + x.ref).join('; ');
      return '<span class="badge broken" title="' + esc(what) + '">' +
        '<span class="material-symbols-outlined">link_off</span>' + b.length + (b.length === 1 ? ' problema' : ' problemi') + '</span>';
    }
    const fmtDate = (s) => {
      const d = s ? new Date(s) : null;
      return d && !isNaN(d) ? d.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
    };
    // Azioni redazionali: la card è la stessa del sito, con la coda diversa.
    // "Cestina" non è un link ma un'azione: data-trash la intercetta più sotto.
    const actions = (ev) => [
      { href: THEME + 'event.html?id=' + encodeURIComponent(ev.path), icon: 'visibility', label: 'Visualizza', title: 'Apri la pagina pubblica', external: true },
      { href: EDIT + '?id=' + encodeURIComponent(ev.path), icon: 'edit', label: 'Modifica', title: 'Apri nell\'editor', primary: true },
      { href: EDIT + '?from=' + encodeURIComponent(ev.path), icon: 'content_copy', label: 'Duplica', title: 'Nuovo evento a partire da questo' },
      { href: '#trash-' + encodeURIComponent(ev.path), icon: 'delete', label: 'Cestina', title: 'Sposta nel cestino (ripristinabile)' },
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
      metaIcon: ev.organizer ? Meetoo.orgIcon(ev.organizerType, ev.organizer) : '',
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

      loadTrash();
    }

    /* ---------- Cestino ----------
     * Cestinare sposta la cartella fuori da events/ (ripristinabile); svuotare
     * cancella per davvero, quindi lo può fare solo un admin e con conferma. */
    const adminMsg = (text, err) => {
      const el = document.getElementById('admin-msg');
      el.textContent = text || '';
      el.style.color = err ? 'var(--color-danger)' : 'var(--color-hint)';
    };

    function loadTrash() {
      document.getElementById('admin-bar').hidden = false;
      return api('trash-list').then((r) => {
        state.trash = (r.status === 200 && Array.isArray(r.body.items)) ? r.body.items : [];
        document.getElementById('trash-count').textContent = state.trash.length;
        renderTrash();
      });
    }

    function renderTrash() {
      const box = document.getElementById('tr-list');
      document.getElementById('tr-count').textContent = state.trash.length;
      document.getElementById('btn-empty').hidden = !state.trash.length;
      if (!state.trash.length) { box.innerHTML = '<div class="empty">Il cestino è vuoto.</div>'; return; }
      box.innerHTML = state.trash.map((t) => Meetoo.eventCard(
        { path: t.path, name: t.name, startDate: t.startDate },
        {
          viewUrl: '#',
          organizer: false,
          extraMeta: [{ icon: 'delete', text: 'cestinato ' + fmtDate(t.trashedAt) + (t.trashedByName ? ' da ' + t.trashedByName : '') }],
          actions: [
            { href: '#restore-' + encodeURIComponent(t.id), icon: 'restore_from_trash', label: 'Ripristina', title: 'Rimetti l\'evento al suo posto', primary: true },
            { href: '#delete-' + encodeURIComponent(t.id), icon: 'delete_forever', label: 'Elimina', title: 'Elimina definitivamente (non recuperabile)' },
          ],
        }
      )).join('');
    }

    // Ricarica gli indici dopo un'operazione che li cambia (cestina/ripristina).
    function reloadIndexes() {
      return getJson(CONTENT_BASE + 'events/_index/events.json').then((list) => {
        const all = Array.isArray(list) ? list : [];
        state.series = all.filter((e) => e.kind === 'series').sort((a, b) => String(a.name).localeCompare(String(b.name)));
        state.upcoming = all.filter((e) => e.kind !== 'series').sort((a, b) => new Date(a.startDate) - new Date(b.startDate));
        resetLists();
      }).catch(() => {});
    }

    // Un solo gestore per tutte le azioni delle card (delega sul contenitore).
    document.addEventListener('click', (e) => {
      const a = e.target.closest && e.target.closest('a.card-act');
      if (!a) return;
      const href = a.getAttribute('href') || '';
      const m = href.match(/^#(trash|restore|delete)-(.+)$/);
      if (!m) return;                       // Visualizza/Modifica/Duplica: link veri
      e.preventDefault();
      const what = m[1], id = decodeURIComponent(m[2]);

      if (what === 'trash') {
        if (!confirm('Spostare «' + id + '» nel cestino?\nPotrai ripristinarlo dal cestino.')) return;
        adminMsg('Sposto nel cestino…');
        api('trash', { path: id }).then((r) => {
          if (r.status !== 200) { adminMsg(r.body.error || 'Operazione fallita.', true); return; }
          adminMsg('Spostato nel cestino. Indice aggiornato.');
          loadTrash(); reloadIndexes();
        });
        return;
      }
      if (what === 'restore') {
        adminMsg('Ripristino…');
        api('trash-restore', { id }).then((r) => {
          if (r.status !== 200) { adminMsg(r.body.error || 'Ripristino fallito.', true); return; }
          adminMsg('Ripristinato in ' + (r.body.entry && r.body.entry.path) + '.');
          loadTrash(); reloadIndexes();
        });
        return;
      }
      // Eliminazione definitiva: doppia conferma, non si torna indietro.
      if (!confirm('Eliminare DEFINITIVAMENTE «' + id + '»?\nLa cartella e i suoi file verranno cancellati: l\'operazione non è reversibile.')) return;
      adminMsg('Elimino…');
      api('trash-delete', { id }).then((r) => {
        if (r.status !== 200) { adminMsg(r.body.error || 'Eliminazione fallita.', true); return; }
        adminMsg('Eliminato definitivamente.');
        loadTrash();
      });
    });

    document.getElementById('btn-trash').addEventListener('click', () => {
      const sec = document.getElementById('sec-trash');
      sec.hidden = !sec.hidden;
      if (!sec.hidden) { renderTrash(); sec.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });

    document.getElementById('btn-empty').addEventListener('click', () => {
      if (!state.trash.length) return;
      if (!confirm('Svuotare il cestino?\n' + state.trash.length + ' event' + (state.trash.length === 1 ? 'o verrà eliminato' : 'i verranno eliminati') +
                   ' definitivamente, con i loro file. L\'operazione non è reversibile.')) return;
      adminMsg('Svuoto il cestino…');
      api('trash-empty').then((r) => {
        if (r.status !== 200) { adminMsg(r.body.error || 'Operazione fallita.', true); return; }
        adminMsg('Cestino svuotato: ' + r.body.deleted + ' eliminati' +
          (r.body.failed && r.body.failed.length ? ' · non eliminati: ' + r.body.failed.join(', ') : '') + '.');
        loadTrash();
      });
    });

    // Manutenzione: "Rigenera indice" reindicizza, "Normalizza" ripara anche la
    // struttura (è il caso raro: si usa quando il check segnala incoerenze).
    function maintenance(action, btn, label) {
      btn.disabled = true;
      adminMsg(label + '…');
      api(action).then((r) => {
        btn.disabled = false;
        if (r.status !== 200) { adminMsg(r.body.error || 'Operazione fallita.', true); return; }
        const i = r.body.index || {}, n = r.body.normalized;
        const fixes = n ? [
          (n.removedSeries || []).length && (n.removedSeries.length + ' serie annidate'),
          (n.completedOccurrences || []).length && (n.completedOccurrences.length + ' occorrenze completate'),
          (n.repairedSuperEvent || []).length && (n.repairedSuperEvent.length + ' superEvent riparati'),
          (n.seriesSubEventUpdated || []).length && (n.seriesSubEventUpdated.length + ' serie riallineate'),
        ].filter(Boolean) : [];
        adminMsg(label + ': ' + (i.indexed || 0) + ' eventi · ' + (i.series || 0) + ' collezioni · ' +
          (i.organizers || 0) + ' organizzatori' +
          (fixes.length ? ' · riparati → ' + fixes.join(', ') : (n ? ' · nulla da riparare' : '')) +
          (r.body.brokenRefs ? ' · ⚠ ' + r.body.brokenRefs + ' riferimenti rotti' : ''),
          !!r.body.brokenRefs);
        reloadIndexes();
      });
    }
    document.getElementById('btn-rebuild').addEventListener('click', function () {
      maintenance('rebuild-index', this, 'Indice rigenerato');
    });
    document.getElementById('btn-normalize').addEventListener('click', function () {
      if (!confirm('Normalizzare i contenuti?\nRipara la struttura degli eventi (serie annidate, occorrenze mancanti, subEvent↔superEvent) e riscrive i file interessati.')) return;
      maintenance('normalize', this, 'Contenuti normalizzati');
    });

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
