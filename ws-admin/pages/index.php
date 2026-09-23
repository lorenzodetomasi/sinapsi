<?php
/*
 * Gestione pagine — l'elenco delle pagine di un sito, con le azioni redazionali.
 *
 * A page here is a content whose `@type` names `WebPage`: the pages of a site,
 * not its entities. Events, places and organisations have their own editors,
 * and a page ABOUT one of them says so with its `mainEntity` instead of being
 * one.
 *
 * The list shows the pages still written by hand as well. Most of them are:
 * isotype has nine migrated and sixteen to go, your-website is entirely
 * unmigrated. A list of the JSON alone would show a fifth of the site, and
 * whoever opened it looking for a page they can see published would think the
 * tool was broken. A `.wsx` is listed for what it is, and can be migrated from
 * here, one page at a time - so the migration finishes where the editing
 * happens rather than as a chore to be done first, elsewhere.
 *
 * This file is ALSO its own JSON endpoint (POST):
 *   action=auth     → identità e ruolo
 *   action=list     → le pagine di una radice
 *   action=migrate  → una pagina da .wsx a JSON (anteprima, poi applica)
 */

// La pagina fa anche da endpoint JSON: gli errori PHP non devono finire nel corpo.
ini_set('display_errors', '0');

require_once __DIR__ . '/../lib/ws-auth.php';
require_once __DIR__ . '/../lib/ws-sites.php';
require_once __DIR__ . '/../_pages.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = (string)($_POST['action'] ?? 'auth');
    $user = ws_authenticate($_POST['credential'] ?? '');
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
        exit;
    }
    $role = (string)($user['role'] ?? '');
    /* Le pagine di un sito sono la sua struttura: chi le tocca decide che cosa
     * il sito È, non solo che cosa racconta. Restano agli amministratori. */
    $isAdmin = in_array($role, ['admin', 'super-admin'], true);

    if ($action === 'auth') {
        echo json_encode([
            'uid' => $user['uid'], 'email' => $user['email'], 'role' => $role,
            'name' => $user['name'] ?? '', 'isAdmin' => $isAdmin,
            /* L'editor è un'applicazione a parte: finché non è stata costruita
             * il pannello non offre un pulsante che porterebbe a un 404. */
            'hasEditor' => is_file(__DIR__ . '/edit/index.html'),
        ]);
        exit;
    }

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Le pagine sono riservate agli amministratori. Il tuo ruolo: ' . ($role ?: 'nessuno') . '.']);
        exit;
    }

    /* Quale radice. Mai un percorso preso dalla richiesta così com'è: il nome
     * deve essere una delle radici che esistono, e il controllo sta in un
     * posto solo (ws_admin_site_path). */
    $siteId = (string)($_POST['site'] ?? '');
    $root = ws_admin_site_path($siteId);

    if ($action === 'list') {
        /*
         * Senza un sito è la PRIMA richiesta: il pannello non sa ancora quali
         * radici esistono e le chiede. Zero pagine, e va bene.
         *
         * Con un sito che non esiste è un'altra cosa, ed è un errore: prima
         * rispondeva «riuscito, zero pagine» anche a questo, e chi guardava il
         * pannello leggeva «nessuna pagina» dove la verità era «quella radice
         * non c'è». Due guasti diversi non devono avere la stessa faccia.
         */
        if ($root === null) {
            $sites = array_values(ws_admin_sites(ws_admin_contents_abspath()));
            if ($siteId !== '') {
                http_response_code(404);
                echo json_encode(['error' => "La radice «{$siteId}» non esiste su questo server.", 'sites' => $sites]);
                exit;
            }
            echo json_encode(['success' => true, 'sites' => $sites, 'pages' => [], 'summary' => null, 'scanned' => 0]);
            exit;
        }
        $pages = ws_pages_list($root);
        echo json_encode([
            'success' => true,
            /* Quante cartelle sono state guardate. Zero pagine dopo averne
             * guardate quaranta e zero pagine dopo non averne guardata
             * nessuna sono due guasti diversi, e senza questo numero si
             * assomigliano troppo. */
            'scanned' => count(ws_pages_scan($root)),
            'root' => basename(dirname($root)) . '/' . basename($root),
            'sites'   => array_values(ws_admin_sites(ws_admin_contents_abspath())),
            'site'    => $siteId,
            'mount'   => ws_pages_mount($siteId),
            'pages'   => $pages,
            'summary' => ws_pages_summary($pages),
        ]);
        exit;
    }

    /*
     * Una pagina sola da .wsx a JSON. Anteprima per difetto: dice che cosa
     * scriverebbe e quali decisioni ha dovuto prendere (le note della
     * conversione), e scrive solo se glielo si chiede.
     */
    if ($action === 'migrate') {
        if ($root === null) { http_response_code(400); echo json_encode(['error' => "Radice sconosciuta: $siteId"]); exit; }

        $id = (string)($_POST['id'] ?? '');
        /* L'@id viene dalla richiesta e finisce in un percorso: si accetta solo
         * la forma di un @id, e il file deve stare davvero sotto la radice. */
        if (!preg_match('#^[a-z0-9][a-z0-9/_-]*$#i', $id) || strpos($id, '..') !== false) {
            http_response_code(400); echo json_encode(['error' => "Identificativo non valido: $id"]); exit;
        }
        $wsx = "$root/$id/index.wsx";
        if (!is_file($wsx)) { http_response_code(404); echo json_encode(['error' => "Non trovo $id/index.wsx"]); exit; }

        require_once __DIR__ . '/../_migrate-page.php';
        $apply = ($_POST['apply'] ?? '') === '1';
        $rep = ws_migrate_page($wsx, $apply);

        /* `ws_migrate_page` dice com'e' andata con `status`: would (anteprima),
         * migrated (fatto), skipped (c'era gia' il JSON, o non e' una pagina),
         * failed. `why` spiega gli ultimi due. */
        $ok = in_array($rep['status'] ?? '', ['would', 'migrated'], true);

        /* Migrare cambia che cosa la mappa dice di questa pagina — il suo tipo
         * viene dal JSON, adesso. La mappa non è pigra: se non la si rifà qui,
         * il sito continua a instradare con quella vecchia. */
        if ($ok && $apply) {
            require_once __DIR__ . '/../refresh-sitemaps.php';
            $rep['sitemap'] = ws_refresh_sitemaps($root, true)['map']['status'] ?? '';
        }
        /* Il JSON convertito non torna indietro: in anteprima puo' essere
         * grosso, e qui serve sapere CHE COSA farebbe, non vederne il corpo. */
        unset($rep['json']);
        echo json_encode(['success' => $ok] + $rep);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => "Azione sconosciuta: $action"]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pagine — Gestione</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Slab:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
  <link rel="stylesheet" href="../../ws-custom/themes/meetoo/meetoo.css">
  <style>
    /* Solo le specificità di questa pagina: i token stanno in meetoo.css. */
    #gate { text-align: center; padding: 48px 16px; color: var(--color-hint); }
    #gate .material-symbols-outlined { font-size: 2.5rem; color: var(--color-link); }
    #app { display: none; }
    #app.on { display: block; }

    .bar { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
    .bar label { font-size: .85rem; color: var(--color-hint); display: inline-flex; align-items: center; gap: 6px; }
    select, input[type=search] { background: var(--color-background-section1); color: var(--color-text);
      border: 1px solid var(--color-line); border-radius: 10px; padding: 7px 10px; font: inherit; }
    select:focus, input:focus { outline: none; border-color: var(--color-link); }

    .counts { color: var(--color-hint); font-size: .85rem; }
    .counts b { color: var(--color-text); }
    .counts .warn { color: var(--color-warning); }

    .rows { display: flex; flex-direction: column; gap: 6px; }
    .row { display: grid; grid-template-columns: minmax(0,2.2fr) minmax(0,1.4fr) auto auto;
      gap: 12px; align-items: center; padding: 10px 14px; border: 1px solid var(--color-line);
      border-radius: var(--border-radius); background: var(--color-background-section1); }
    .row .addr { font-weight: 600; word-break: break-word; }
    /* Un figlio si legge come figlio: il rientro è l'unica cosa che rende
       un elenco di indirizzi un albero senza disegnarlo. */
    .row .addr .depth { color: var(--color-hint); font-weight: 400; }
    .row .ttl { color: var(--color-hint); font-size: .88rem; word-break: break-word; }
    .row .tags { display: flex; gap: 6px; flex-wrap: wrap; }
    .tag { font-size: .72rem; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--color-line); color: var(--color-hint); white-space: nowrap; }
    .tag.role { color: var(--color-link); border-color: var(--color-link); }
    .tag.wsx { color: var(--color-warning); border-color: var(--color-warning); }
    .tag.both { color: var(--color-warning); border-color: var(--color-warning); }
    .tag.problem { color: #fff; background: var(--color-warning); border-color: transparent; }
    .row .acts { display: flex; gap: 6px; }
    .row.hidden { display: none; }

    /* `display` in un foglio di stile batte l'attributo `hidden`, che vale
       `display: none` solo come regola dell'agente utente. Senza questa riga
       un pulsante nascosto da JS resta visibile — ed e' successo: «Aggiungi
       pagina» compariva anche senza l'editor a cui porta. */
    [hidden] { display: none !important; }

    button, a.btn { font-family: inherit; display: inline-flex; align-items: center; gap: 5px;
      padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
      border: 1px solid var(--color-line); border-radius: 999px;
      background: var(--color-background-section2); color: var(--color-text); }
    button:hover:not(:disabled), a.btn:hover { border-color: var(--color-link); }
    button:disabled { opacity: .5; cursor: default; }
    button.primary { background: var(--color-link); color: var(--color-text-neg); border-color: transparent; }
    .material-symbols-outlined { font-size: 17px; }

    #out { background: var(--color-background-section2); border: 1px solid var(--color-line);
      border-radius: var(--border-radius); padding: 12px; margin-top: 14px; max-height: 360px;
      overflow: auto; white-space: pre-wrap; font-size: .85rem; }
    #out .err { color: var(--color-warning); font-weight: 600; }
    .intro { color: var(--color-hint); font-size: .9rem; margin: -6px 0 14px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div id="gate">
      <span class="material-symbols-outlined">lock</span>
      <p id="gate-msg">Accedi con Google (in alto a destra) per entrare nella Gestione.</p>
    </div>

    <div id="app">
      <section>
        <h2 class="sec-head"><span class="material-symbols-outlined">description</span>Pagine</h2>
        <p class="intro">Le pagine di un sito: quelle già in JSON e quelle ancora scritte a mano,
          che si possono migrare una alla volta da qui.</p>

        <div class="bar">
          <label>Sito <select id="f-site"></select></label>
          <label>Mostra
            <select id="f-state">
              <option value="">tutte</option>
              <option value="json">solo JSON</option>
              <option value="wsx">da migrare</option>
              <option value="problem">con problemi</option>
            </select>
          </label>
          <label>Cerca <input type="search" id="f-q" placeholder="indirizzo o titolo" autocomplete="off"></label>
          <span class="grow" style="flex:1 1 auto"></span>
          <button id="btn-new" class="primary" hidden><span class="material-symbols-outlined">add</span>Aggiungi pagina</button>
        </div>

        <p class="counts" id="counts"></p>
        <div class="rows" id="rows"></div>
      </section>

      <pre id="out" hidden></pre>
    </div>
  </div>

  <script src="../../ws-custom/themes/meetoo/cards.js"></script>
  <script src="../../ws-custom/themes/meetoo/header.js"></script>
  <script>
  (function () {
    const SITE_ROOT = location.pathname.replace(/\/ws-admin\/.*/, '/');
    const ADMIN = SITE_ROOT + 'ws-admin/';

    (function crumb() {
      if (!window.Meetoo) { setTimeout(crumb, 100); return; }
      Meetoo.setBreadcrumb([
        { label: 'Gestione', href: ADMIN + 'index.php' },
        { label: 'Pagine', current: true },
      ]);
      Meetoo.setNav([
        { label: 'Gestione', icon: 'home', href: ADMIN + 'index.php' },
        { label: 'Siti', icon: 'language', href: ADMIN + 'sites.php' },
        { label: 'Gestione eventi', icon: 'event_note', href: ADMIN + 'events/index.php' },
      ]);
    })();

    /*
     * Una richiesta che non torna JSON NON è una risposta vuota.
     *
     * Prima qui c'era un `.catch(() => ({}))`, e la conseguenza è costata un
     * pomeriggio a chi ha aperto il pannello sul server: un file mancante fa
     * fallire il `require` di PHP, che risponde 500 con dell'HTML, che non è
     * JSON — e il pannello diceva «Nessuna pagina con questi criteri», cioè la
     * cosa più tranquillizzante e più falsa che potesse dire. Adesso il corpo
     * che non si legge diventa un errore con dentro il suo stato e le sue
     * prime righe, che è quello che serve per capire.
     */
    const api = (action, extra) => {
      const body = new URLSearchParams(Object.assign({ action }, extra || {}));
      const token = window.meetooSession && meetooSession.getToken();
      if (token) body.set('credential', token);
      return fetch(location.pathname, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(),
      }).then((r) => r.text().then((testo) => {
        try {
          return { status: r.status, body: JSON.parse(testo) };
        } catch {
          return {
            status: r.status,
            body: { error: 'Il server ha risposto ' + r.status + ' con qualcosa che non è JSON: '
              + testo.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300) },
          };
        }
      }));
    };

    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const out = $('out');
    const show = (lines) => { out.hidden = false; out.innerHTML = lines.join('\n'); };

    let PAGES = [], MOUNT = '', HAS_EDITOR = false, SCANSIONATE = 0;

    /* L'indirizzo pubblico: il mount davanti al wspath. Il wspath di una home
     * è "/", e concatenarlo darebbe "/sito//" — il caso da togliere di mezzo
     * una volta sola, qui. */
    function publicHref(p) {
      if (!p.wspath) return '';
      const base = MOUNT.replace(/\/$/, '');
      const path = p.wspath === '/' ? '/' : p.wspath;
      return SITE_ROOT.replace(/\/$/, '') + base + (path === '/' && base ? '/' : path);
    }

    function depthOf(wspath) {
      if (!wspath || wspath === '/') return 0;
      return wspath.replace(/^\/|\/$/g, '').split('/').length - 1;
    }

    function render() {
      const wanted = $('f-state').value;
      const q = $('f-q').value.trim().toLowerCase();
      const box = $('rows');
      box.innerHTML = '';

      let shown = 0;
      PAGES.forEach((p) => {
        if (wanted === 'problem' ? !p.problem : (wanted && p.state !== wanted)) return;
        if (q && !(p.wspath + ' ' + p.title + ' ' + p.id).toLowerCase().includes(q)) return;
        shown++;

        const row = document.createElement('div');
        row.className = 'row';

        const indent = '— '.repeat(depthOf(p.wspath));
        const href = publicHref(p);

        const tags = [];
        if (p.role) tags.push('<span class="tag role">' + esc(p.role) + '</span>');
        if (p.template) tags.push('<span class="tag">' + esc(p.template) + '</span>');
        if (p.state === 'wsx') tags.push('<span class="tag wsx">da migrare</span>');
        if (p.state === 'both') tags.push('<span class="tag both">JSON + .wsx</span>');
        if (p.robots && /noindex/i.test(p.robots)) tags.push('<span class="tag">noindex</span>');
        if (p.problem) tags.push('<span class="tag problem">' + esc(p.problem) + '</span>');

        row.innerHTML =
          '<div class="addr"><span class="depth">' + indent + '</span>' + esc(p.wspath || '(nessun indirizzo)') + '</div>' +
          '<div class="ttl">' + esc(p.title) + '<br><span style="opacity:.7">' + esc(p.id) + '</span></div>' +
          '<div class="tags">' + tags.join('') + '</div>';

        const acts = document.createElement('div');
        acts.className = 'acts';

        if (href) {
          acts.innerHTML += '<a class="btn" href="' + esc(href) + '" target="_blank" rel="noopener" title="Vedi la pagina pubblica">'
            + '<span class="material-symbols-outlined">open_in_new</span></a>';
        }
        if (p.state === 'wsx') {
          const b = document.createElement('button');
          b.innerHTML = '<span class="material-symbols-outlined">move_up</span>Migra';
          b.addEventListener('click', () => migrate(p, false));
          acts.appendChild(b);
        } else if (HAS_EDITOR) {
          acts.innerHTML += '<a class="btn" href="edit/?site=' + encodeURIComponent($('f-site').value)
            + '&id=' + encodeURIComponent(p.id) + '"><span class="material-symbols-outlined">edit</span>Modifica</a>';
        }

        row.appendChild(acts);
        box.appendChild(row);
      });

      /* Zero perché non ce n'è, e zero perché i filtri le nascondono, sono due
       * cose diverse: la prima è una domanda («l'ho caricata, questa radice?»),
       * la seconda è una spiegazione. */
      if (!shown) {
        box.innerHTML = PAGES.length
          ? '<p class="intro">Nessuna delle ' + PAGES.length + ' pagine risponde a questi criteri.</p>'
          : '<p class="intro">Questa radice non ha pagine: nessuna delle <b>' + SCANSIONATE
            + '</b> cartelle guardate ha un <code>index.json</code> di tipo WebPage né un <code>index.wsx</code> con un indirizzo.</p>';
      }
    }

    /* La migrazione di una pagina: prima che cosa farebbe e che cosa ha dovuto
     * decidere (le note della conversione), poi, se si vuole, la scrittura. */
    function migrate(p, apply) {
      const extra = { site: $('f-site').value, id: p.id };
      if (apply) extra.apply = '1';
      api('migrate', extra).then(({ body }) => {
        if (body.error) { show(['<span class="err">✗ ' + esc(body.error) + '</span>']); return; }
        const lines = ['<b>' + esc(p.wspath) + '</b> — ' + esc(p.id)];
        if (!body.success) {
          lines.push('<span class="err">✗ ' + esc(body.status) + (body.why ? ': ' + esc(body.why) : '') + '</span>');
          show(lines);
          return;
        }
        /* Le note sono le decisioni che la conversione ha dovuto prendere da
         * sola — un commento tolto, un include diventato valore. Si leggono
         * PRIMA di scrivere: e' l'unico momento in cui servono. */
        (body.notes || []).forEach((n) => lines.push('  · ' + esc(n)));
        if (apply) {
          lines.push('\n✓ migrata' + (body.sitemap ? ' · mappa: ' + esc(body.sitemap) : ''));
          show(lines);
          load();
          return;
        }
        lines.push('\nNiente è stato scritto: questa è l\'anteprima.');
        show(lines);
        const go = document.createElement('button');
        go.className = 'primary';
        go.textContent = 'Migra «' + (p.wspath || p.id) + '»';
        go.addEventListener('click', () => { go.disabled = true; migrate(p, true); });
        out.appendChild(document.createElement('br'));
        out.appendChild(go);
      });
    }

    function load() {
      api('list', { site: $('f-site').value }).then(({ status, body }) => {
        /* `success` e basta: qualunque risposta che non lo dichiara è andata
         * male, anche quando non porta un `error` da mostrare. */
        if (!body.success) {
          show(['<span class="err">✗ ' + esc(body.error || ('Il server ha risposto ' + status + '.')) + '</span>']);
          $('rows').innerHTML = '';
          $('counts').textContent = '';
          return;
        }

        const sel = $('f-site');
        if (!sel.options.length) {
          (body.sites || []).forEach((s) => {
            const o = document.createElement('option');
            o.value = s.id; o.textContent = s.label;
            sel.appendChild(o);
          });
          /* La prima radice con delle pagine, non la prima in ordine
           * alfabetico: aprire il pannello su un archivio vuoto non dice
           * niente a nessuno. */
          if (sel.value !== 'isotype/it_IT' && [...sel.options].some((o) => o.value === 'isotype/it_IT')) {
            sel.value = 'isotype/it_IT';
            load();
            return;
          }
        }

        PAGES = body.pages || [];
        MOUNT = body.mount || '';
        SCANSIONATE = body.scanned || 0;
        const s = body.summary;
        $('counts').innerHTML = s
          ? '<b>' + s.total + '</b> pagine · <b>' + s.json + '</b> in JSON · <b>' + s.wsx + '</b> da migrare'
            + (s.both ? ' · <span class="warn">' + s.both + ' con JSON e .wsx</span>' : '')
            + (s.problems ? ' · <span class="warn">' + s.problems + ' illeggibili</span>' : '')
          : '';
        render();
      });
    }

    /*
     * Aggiungi pagina: si apre l'editor senza `id`, e la cartella la ricava
     * l'editor dall'indirizzo che si scrive.
     *
     * Non si chiede qui «come si chiamerà la cartella»: l'indirizzo lo si
     * decide comunque nel modulo, e domandarlo due volte in due posti è il
     * modo di ritrovarsi con una pagina il cui `@id` non c'entra niente con il
     * suo `wspath`. Il sito invece si passa: è la scelta gia' fatta qui sopra,
     * e ridomandarla sarebbe rifare una domanda con la risposta gia' in mano.
     */
    $('btn-new').addEventListener('click', () => {
      window.location.href = 'edit/?site=' + encodeURIComponent($('f-site').value);
    });

    ['f-state', 'f-q'].forEach((id) => $(id).addEventListener('input', render));
    $('f-site').addEventListener('change', load);

    (function auth() {
      if (!window.meetooSession) { setTimeout(auth, 100); return; }
      meetooSession.subscribe((user) => {
        if (!user) {
          $('gate-msg').textContent = 'Accedi con Google (in alto a destra) per entrare nella Gestione.';
          return;
        }
        api('auth').then(({ status, body }) => {
          if (status !== 200 || body.error) { $('gate-msg').textContent = body.error || 'Accesso non riuscito.'; return; }
          if (!body.isAdmin) {
            $('gate-msg').textContent = 'Il tuo account (' + (body.email || '') + ', ruolo '
              + (body.role || '?') + ') non è abilitato a gestire le pagine.';
            return;
          }
          HAS_EDITOR = !!body.hasEditor;
          $('btn-new').hidden = !HAS_EDITOR;
          $('gate').hidden = true;
          $('app').classList.add('on');
          load();
        });
      });
    })();
  })();
  </script>
</body>
</html>
