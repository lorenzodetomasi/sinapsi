<?php
/*
 * Sites — the panel that creates and governs the sites served from this domain.
 *
 * A MODULE, in the hub's sense: it lists things of one kind, previews what an
 * operation would do, and applies it. The kind here is the site itself, which
 * is why this one is not an operation in the maintenance registry: every entry
 * there runs on a `$base` — a content root that already exists — and a site
 * being created has none.
 *
 * What it does:
 *   - lists the sites in ws-custom/contents, with their locales, theme, mount
 *     and what their home is about;
 *   - creates a site: the content root, the base pages, the menus, the empty
 *     archives, a theme that inherits from your-theme, the mount, and the site
 *     map built from the pages;
 *   - adds a language to a site that exists;
 *   - attaches a place, a local business or an organisation found on Google
 *     Maps as the home's mainEntity.
 *
 * Creating a site is reserved to super-admins. Not because the others cannot
 * be trusted, but because this is the one operation whose blast radius is the
 * whole domain: it writes the mount, and a wrong mount takes an existing site
 * off the air.
 */

require_once __DIR__ . '/lib/ws-auth.php';
require_once __DIR__ . '/_site.php';

/* ---------------------------------------------------------------------------
 * The endpoint
 * ------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // The page is its own JSON endpoint: a PHP notice must not end up in the body.
    ini_set('display_errors', '0');

    $action = (string)($_POST['action'] ?? 'auth');
    $user = ws_authenticate($_POST['credential'] ?? '');
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
        exit;
    }
    $role = (string)($user['role'] ?? '');
    $isSuper = $role === 'super-admin';

    if ($action === 'auth') {
        echo json_encode([
            'uid' => $user['uid'], 'email' => $user['email'], 'role' => $role,
            'name' => $user['name'] ?? '', 'isSuper' => $isSuper,
        ]);
        exit;
    }

    /* Reading the list is for anyone who may be in the hub at all: knowing
     * which sites exist is not a privilege, and the panel has to show
     * something to explain why the buttons are not there. */
    if ($action === 'list') {
        if (!in_array($role, ['admin', 'super-admin'], true)) {
            http_response_code(403); echo json_encode(['error' => 'Riservato agli amministratori.']); exit;
        }
        $sites = [];
        foreach (site_list() as $s) {
            $sites[] = [
                'id' => $s['id'], 'locales' => $s['locales'], 'mount' => $s['mount'],
                'theme' => $s['theme'], 'mainEntity' => $s['mainEntity'],
                'name' => site_name_of($s['path'], $s['locales']),
                'menu' => site_menu($s['id']),
            ];
        }
        echo json_encode([
            'success' => true, 'sites' => $sites, 'catalog' => site_catalog_for_ui(),
            'menuStyles' => site_menu_styles(),
        ]);
        exit;
    }

    /* Everything that writes a site is super-admin only. The check is here,
     * once, before the dispatch: a permission decided next to each operation
     * is a permission that one day is missing from one of them. */
    if (!$isSuper) {
        http_response_code(403);
        echo json_encode(['error' => 'Solo un super-admin può creare o modificare un sito. Il tuo ruolo: ' . ($role ?: 'nessuno') . '.']);
        exit;
    }

    $apply = ($_POST['apply'] ?? '') === '1';

    if ($action === 'create') {
        $rep = site_create([
            'id'      => (string)($_POST['id'] ?? ''),
            'name'    => (string)($_POST['name'] ?? ''),
            'locales' => array_filter(array_map('trim', explode(',', (string)($_POST['locales'] ?? '')))),
            'theme'   => (string)($_POST['theme'] ?? ''),
            'mount'   => (string)($_POST['mount'] ?? ''),
            'url'     => (string)($_POST['url'] ?? ''),
            'pages'   => array_filter(array_map('trim', explode(',', (string)($_POST['pages'] ?? '')))),
            'offer_type' => (string)($_POST['offer_type'] ?? ''),
        ], $apply);
        echo json_encode(['success' => !$rep['errors']] + $rep);
        exit;
    }

    /* SI SCRIVE E BASTA, senza anteprima.
     *
     * Ogni altra azione di questo pannello mostra prima cosa scriverebbe,
     * perche' crea cartelle e file e tornare indietro costa. Questa cambia una
     * parola in un file, si vede subito guardando una pagina e si rimette com'era
     * scegliendo l'altra voce. Un'anteprima qui sarebbe un passaggio in piu' per
     * leggere cio' che si e' appena scelto. */
    if ($action === 'menu') {
        $rep = ['changes' => [], 'errors' => [], 'notes' => [], 'applied' => true];
        site_menu_set($rep, (string)($_POST['id'] ?? ''), (string)($_POST['style'] ?? ''), true);
        echo json_encode(['success' => !$rep['errors']] + $rep);
        exit;
    }

    if ($action === 'add-locale') {
        $rep = site_add_locale((string)($_POST['id'] ?? ''), (string)($_POST['locale'] ?? ''), $apply);
        echo json_encode(['success' => !$rep['errors']] + $rep);
        exit;
    }

    /* Searching Google costs a request, so it is its own action: the editor
     * searches once, picks from the results, and the choice travels as a
     * place id. Searching again at apply time would risk saving a different
     * place from the one that was on screen. */
    if ($action === 'place-search') {
        require_once __DIR__ . '/_place-google.php';
        $query = trim((string)($_POST['query'] ?? ''));
        if ($query === '') { http_response_code(400); echo json_encode(['error' => 'Cerca qualcosa.']); exit; }

        $site = (string)($_POST['id'] ?? '');
        $sites = site_list();
        $locale = $sites[$site]['locales'][0] ?? 'it_IT';

        [$places, $err] = google_places_search($query, $locale);
        if ($err !== null) { http_response_code(502); echo json_encode(['error' => $err]); exit; }

        /* Only what the chooser needs. The full answer is fetched again by id
         * when one is picked — the search result and the saved place should
         * not be the same object, or the chooser's shape becomes the content's. */
        $out = [];
        foreach ($places as $p) {
            $out[] = [
                'placeId' => $p['id'] ?? '',
                'name' => $p['displayName']['text'] ?? '',
                'address' => $p['formattedAddress'] ?? '',
                'primaryType' => $p['primaryTypeDisplayName']['text'] ?? ($p['primaryType'] ?? ''),
                'rating' => $p['rating'] ?? null,
                'proposedId' => google_place_id_for($p),
                'hasHours' => !empty($p['regularOpeningHours']['periods']),
            ];
        }
        echo json_encode(['success' => true, 'places' => $out]);
        exit;
    }

    if ($action === 'attach') {
        require_once __DIR__ . '/_place-google.php';
        $site = (string)($_POST['id'] ?? '');
        $placeId = trim((string)($_POST['place_id'] ?? ''));
        if ($placeId === '') { http_response_code(400); echo json_encode(['error' => 'Nessun luogo scelto.']); exit; }

        $sites = site_list();
        $locale = $sites[$site]['locales'][0] ?? 'it_IT';

        [$g, $err] = google_place_details($placeId, $locale);
        if ($err !== null) { http_response_code(502); echo json_encode(['error' => $err]); exit; }

        $types = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['types'] ?? '')))));
        $rep = site_attach_main_entity($site, $g, $types, $apply);
        echo json_encode(['success' => !$rep['errors']] + $rep);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => "Azione sconosciuta: $action"]);
    exit;
}

/* The catalog, as the form needs it: which pages are forced and which are a
 * tick. Derived from the catalog itself, so a page added there appears in the
 * form without anyone remembering to add it twice. */
function site_catalog_for_ui(): array {
    $out = [];
    foreach (site_page_catalog() as $key => $page) {
        $out[] = [
            'key'      => $key,
            'required' => !empty($page['required']),
            'title'    => site_page_title($page, 'it_IT'),
            'slug'     => site_page_slug($page, 'it_IT'),
            'types'    => $page['types'],
            'choice'   => $page['item_type_choice'] ?? null,
        ];
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Siti — Gestione</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Slab:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
  <link rel="stylesheet" href="../ws-custom/themes/meetoo/css/meetoo.css">
  <style>
    /* Solo le specificità di questa pagina: i token stanno in meetoo.css. */
    #gate { text-align: center; padding: 48px 16px; color: var(--color-hint); }
    #gate .material-symbols-outlined { font-size: 2.5rem; color: var(--color-link); }
    #app { display: none; }
    #app.on { display: block; }

    .site-row { display: flex; gap: 14px; align-items: baseline; flex-wrap: wrap;
                padding: 12px 14px; border: 1px solid var(--color-line); border-radius: var(--border-radius);
                background: var(--color-background-section1); margin-bottom: 8px; }
    .site-row b { font-size: 1.05rem; }
    .site-row .tag { font-size: .8rem; color: var(--color-hint); }
    .site-row .tag code { color: var(--color-link); }
    .site-row .warn { color: var(--color-warning); }
    .site-row .grow { flex: 1 1 auto; }

    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 14px; }
    .form-grid label { display: flex; flex-direction: column; gap: 4px; font-weight: 600; color: var(--color-hint); font-size: .85rem; }
    input[type=text], select { background: var(--color-background-section1); color: var(--color-text);
                               border: 1px solid var(--color-line); border-radius: 10px; padding: 8px 10px; font: inherit; }
    input[type=text]:focus, select:focus { outline: none; border-color: var(--color-link); }
    .hint { font-weight: 400; color: var(--color-hint); font-size: .78rem; }

    .ticks { display: flex; flex-wrap: wrap; gap: 8px 18px; margin-bottom: 14px; }
    .ticks label { display: inline-flex; align-items: center; gap: 6px; font-size: .9rem; }
    .ticks .locked { color: var(--color-hint); }

    .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 10px; }
    button { font-family: inherit; display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px;
             font-size: 14px; font-weight: 600; cursor: pointer; border: 1px solid var(--color-line);
             border-radius: 999px; background: var(--color-background-section2); color: var(--color-text); }
    button:hover:not(:disabled) { border-color: var(--color-link); }
    button:disabled { opacity: .5; cursor: default; }
    button.primary { background: var(--color-link); color: var(--color-text-neg); border-color: transparent; }

    #out { background: var(--color-background-section2); border: 1px solid var(--color-line);
           border-radius: var(--border-radius); padding: 12px; margin-top: 12px; max-height: 420px;
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
        <h2 class="sec-head"><span class="material-symbols-outlined">language</span>I siti</h2>
        <p class="intro">Ogni sito ha una cartella in <code>ws-custom/contents</code>, un tema, un indirizzo
          a cui risponde (il <i>mount</i>) e una o più lingue. Qui si vede se le quattro cose sono d'accordo.</p>
        <div id="sites"></div>
      </section>

      <section id="sec-new" hidden>
        <h2 class="sec-head"><span class="material-symbols-outlined">add_circle</span>Un sito nuovo</h2>
        <p class="intro">Prima mostra <b>che cosa scriverebbe</b>. Si scrive solo premendo «Crea».</p>

        <div class="form-grid">
          <label>Identificativo
            <input type="text" id="f-id" placeholder="acme" autocomplete="off">
            <span class="hint">La cartella in contents/ e la prima metà di ogni @id.</span>
          </label>
          <label>Nome
            <input type="text" id="f-name" placeholder="ACME Immobiliare" autocomplete="off">
            <span class="hint">Il nome del sito: il WebSite e il tema si chiamano così.</span>
          </label>
          <label>Lingua primaria
            <select id="f-primary"></select>
            <span class="hint">La lingua che risponde alla radice del sito.</span>
          </label>
          <label>Altre lingue
            <input type="text" id="f-locales" placeholder="en_US, fr_FR" autocomplete="off">
            <span class="hint">Separate da virgola. Si possono aggiungere anche dopo.</span>
          </label>
          <label>Indirizzo (mount)
            <input type="text" id="f-mount" placeholder="/acme" autocomplete="off">
            <span class="hint">Vuoto: il sito risponde alla radice del dominio.</span>
          </label>
          <label>URL del sito
            <input type="text" id="f-url" placeholder="https://www.isotype.org/acme" autocomplete="off">
            <span class="hint">Finisce nel nodo WebSite.</span>
          </label>
          <label>Tema
            <input type="text" id="f-theme" placeholder="(come l'identificativo)" autocomplete="off">
            <span class="hint">Nasce come guscio, con your-theme come genitore.</span>
          </label>
        </div>

        <h3 style="margin:0 0 6px">Le pagine</h3>
        <p class="intro">Home, Contatti e le tre legali ci sono sempre: il tema le cerca per ruolo, e la
          spunta del consenso nel modulo di contatto rimanda alla privacy.</p>
        <div class="ticks" id="f-pages"></div>
        <div id="f-offer-wrap" hidden style="margin-bottom:14px">
          <label style="font-size:.9rem">L'offerta elenca
            <select id="f-offer">
              <option value="Service">Servizi</option>
              <option value="Product">Prodotti</option>
            </select>
          </label>
        </div>

        <div class="actions">
          <button id="btn-preview"><span class="material-symbols-outlined">visibility</span>Anteprima</button>
          <button id="btn-create" class="primary" disabled><span class="material-symbols-outlined">add</span>Crea</button>
        </div>
      </section>

      <section id="sec-locale" hidden>
        <h2 class="sec-head"><span class="material-symbols-outlined">translate</span>Aggiungi una lingua</h2>
        <p class="intro">Le stesse pagine del sito, nella lingua nuova. <code>ws_languages.wsx</code> e la
          mappa vengono riscritti dalle cartelle che esistono, così non possono più dire cose diverse.</p>
        <div class="form-grid">
          <label>Sito <select id="l-site"></select></label>
          <label>Lingua <select id="l-locale"></select></label>
        </div>
        <div class="actions">
          <button id="btn-l-preview"><span class="material-symbols-outlined">visibility</span>Anteprima</button>
          <button id="btn-l-apply" class="primary" disabled><span class="material-symbols-outlined">add</span>Aggiungi</button>
        </div>
      </section>

      <section id="sec-entity" hidden>
        <h2 class="sec-head"><span class="material-symbols-outlined">storefront</span>Di che cosa parla il sito</h2>
        <p class="intro">Cerca l'attività su Google Maps. Quello che viene salvato sta in un posto solo —
          <code>places/&lt;area&gt;/&lt;slug&gt;</code> — e la home, le sedi e le testate lo nominano per <code>@id</code>.
          Le foto non si scaricano: i termini di Google non permettono di conservarle.</p>
        <div class="form-grid">
          <label>Sito <select id="e-site"></select></label>
          <label>Cerca
            <input type="text" id="e-query" placeholder="RES Immobiliare via Diego Simonetti Roma" autocomplete="off">
          </label>
          <label>Tipi schema.org da aggiungere
            <input type="text" id="e-types" placeholder="RealEstateAgent" autocomplete="off">
            <span class="hint">Separati da virgola. LocalBusiness o Place lo decide Google.</span>
          </label>
        </div>
        <div class="actions">
          <button id="btn-e-search"><span class="material-symbols-outlined">search</span>Cerca su Google Maps</button>
        </div>
        <div id="e-results"></div>
      </section>

      <pre id="out" hidden></pre>
    </div>
  </div>

  <script src="../ws-custom/themes/meetoo/cards.js"></script>
  <script src="../ws-custom/themes/meetoo/header.js"></script>
  <script>
  (function () {
    const SITE_ROOT = location.pathname.replace(/\/ws-admin\/.*/, '/');
    const ADMIN = SITE_ROOT + 'ws-admin/';

    /* The languages the catalog has words for, plus the ones the CMS has seen.
     * A language not in this list is not refused — it is typed in "Altre
     * lingue" — but it gets the English page titles, and the report says so. */
    const LOCALES = [
      ['it_IT', 'Italiano'], ['en_US', 'English'], ['fr_FR', 'Français'],
      ['de_DE', 'Deutsch'], ['es_ES', 'Español'], ['pt_PT', 'Português'],
    ];

    (function crumb() {
      if (!window.Meetoo) { setTimeout(crumb, 100); return; }
      Meetoo.setBreadcrumb([
        { label: 'Gestione', href: ADMIN + 'index.php' },
        { label: 'Siti', current: true },
      ]);
      Meetoo.setNav([
        { label: 'Gestione', icon: 'home', href: ADMIN + 'index.php' },
        { label: 'Luoghi e gruppi', icon: 'place', href: ADMIN + 'places/edit/' },
        { label: 'Utenti e ruoli', icon: 'manage_accounts', href: ADMIN + 'users/' },
      ]);
    })();

    const api = (action, extra) => {
      const body = new URLSearchParams(Object.assign({ action }, extra || {}));
      const token = window.meetooSession && meetooSession.getToken();
      if (token) body.set('credential', token);
      return fetch(location.pathname, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(),
      }).then((r) => r.json().then((j) => ({ status: r.status, body: j }), () => ({ status: r.status, body: {} })));
    };

    const $ = (id) => document.getElementById(id);
    const out = $('out');
    let CATALOG = [];
    let SITES = [];
    let MENU_STYLES = { responsive: 'Orizzontale dove ci sta, a cassetto dove no', drawer: 'Sempre e solo il menu a cassetto' };
    let IS_SUPER = false;
    let lastCreateSpec = null;   // what the preview described, so "Crea" writes exactly that
    let lastLocaleSpec = null;

    const show = (lines) => { out.hidden = false; out.innerHTML = lines.join('\n'); };
    const esc = (s) => String(s).replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

    /* A report, as the panel prints it. The errors first: if there are any,
     * nothing was written and nothing will be until they are gone. */
    function showReport(rep, appliedWord) {
      const lines = [];
      (rep.errors || []).forEach((e) => lines.push('<span class="err">✗ ' + esc(e) + '</span>'));
      if (rep.errors && rep.errors.length) { lines.push(''); }
      (rep.changes || []).forEach((c) => {
        const p = String(c.path).replace(/^.*\/(ws-custom|ws-admin)\//, '$1/');
        lines.push((rep.applied ? '✓ ' : '· ') + esc(c.what) + '  ' + esc(p) + (c.detail ? '  [' + esc(c.detail) + ']' : ''));
      });
      (rep.notes || []).forEach((n) => lines.push('\n⚠ ' + esc(n)));
      if (rep.applied) lines.push('\n' + appliedWord);
      else if (!rep.errors || !rep.errors.length) lines.push('\nNiente è stato scritto: questa è l\'anteprima.');
      show(lines);
    }

    function renderSites() {
      const box = $('sites');
      box.innerHTML = '';
      SITES.forEach((s) => {
        const row = document.createElement('div');
        row.className = 'site-row';
        const mount = s.mount ? '<code>' + esc(s.mount) + '</code>' : '<i>radice</i>';
        const entity = s.mainEntity
          ? '<code>' + esc(s.mainEntity) + '</code>'
          : '<span class="warn">nessuna</span>';
        row.innerHTML =
          '<b>' + esc(s.name || s.id) + '</b>' +
          '<span class="tag">id <code>' + esc(s.id) + '</code></span>' +
          '<span class="tag">tema <code>' + esc(s.theme || '—') + '</code></span>' +
          '<span class="tag">indirizzo ' + mount + '</span>' +
          '<span class="tag">lingue ' + (s.locales.join(', ') || '—') + '</span>' +
          '<span class="grow"></span>' +
          '<span class="tag">mainEntity ' + entity + '</span>';
        /* La scelta del menu la offre solo a chi puo' scriverla. Il server
         * rifiuta comunque: questo evita di mostrare un comando che poi dice
         * di no. */
        if (IS_SUPER) {
          const lab = document.createElement('label');
          lab.className = 'tag';
          lab.textContent = 'menu ';
          const sel = document.createElement('select');
          Object.keys(MENU_STYLES).forEach((k) => {
            const o = document.createElement('option');
            o.value = k; o.textContent = MENU_STYLES[k];
            sel.appendChild(o);
          });
          sel.value = s.menu || 'responsive';
          sel.addEventListener('change', () => {
            api('menu', { id: s.id, style: sel.value }).then(({ body }) => {
              report(body, 'Scritto.');
              load();
            });
          });
          lab.appendChild(sel);
          row.appendChild(lab);
        }
        box.appendChild(row);
      });
      if (!SITES.length) box.innerHTML = '<p class="intro">Nessun sito.</p>';

      ['l-site', 'e-site'].forEach((which) => {
        const sel = $(which);
        const keep = sel.value;
        sel.innerHTML = '';
        SITES.forEach((s) => {
          const o = document.createElement('option');
          o.value = s.id; o.textContent = s.id + ' (' + (s.locales.join(', ') || 'nessuna lingua') + ')';
          sel.appendChild(o);
        });
        if (keep) sel.value = keep;   // reloading the list must not move the choice
      });
    }

    function renderCatalog() {
      const box = $('f-pages');
      box.innerHTML = '';
      CATALOG.forEach((p) => {
        const label = document.createElement('label');
        if (p.required) {
          label.className = 'locked';
          label.innerHTML = '<input type="checkbox" checked disabled> ' + esc(p.title) +
                            ' <span class="hint">' + esc(p.slug) + '</span>';
        } else {
          label.innerHTML = '<input type="checkbox" value="' + esc(p.key) + '"> ' + esc(p.title) +
                            ' <span class="hint">' + esc(p.slug) + '</span>';
        }
        box.appendChild(label);
      });
      box.addEventListener('change', () => {
        const offer = box.querySelector('input[value="offer"]');
        $('f-offer-wrap').hidden = !(offer && offer.checked);
        $('btn-create').disabled = true;     // the ticks changed: the preview no longer describes this
        lastCreateSpec = null;
      });
    }

    function fillLocaleSelects() {
      const prim = $('f-primary'), add = $('l-locale');
      LOCALES.forEach(([code, label]) => {
        [prim, add].forEach((sel) => {
          const o = document.createElement('option');
          o.value = code; o.textContent = label + ' (' + code + ')';
          sel.appendChild(o);
        });
      });
      prim.value = 'it_IT';
    }

    function createSpec() {
      const primary = $('f-primary').value;
      const others = $('f-locales').value.split(',').map((s) => s.trim()).filter(Boolean)
                        .filter((l) => l !== primary);
      const pages = Array.from($('f-pages').querySelectorAll('input[type=checkbox]:not([disabled]):checked'))
                        .map((i) => i.value);
      return {
        id: $('f-id').value.trim(),
        name: $('f-name').value.trim(),
        locales: [primary].concat(others).join(','),
        theme: $('f-theme').value.trim(),
        mount: $('f-mount').value.trim(),
        url: $('f-url').value.trim(),
        pages: pages.join(','),
        offer_type: $('f-offer').value,
      };
    }

    $('btn-preview').addEventListener('click', () => {
      const spec = createSpec();
      api('create', spec).then(({ body }) => {
        if (body.error) { show(['<span class="err">✗ ' + esc(body.error) + '</span>']); return; }
        showReport(body, '');
        /* "Crea" writes what the preview described, not what the form says
         * now: between the two there is a person who may have typed. */
        lastCreateSpec = body.errors && body.errors.length ? null : spec;
        $('btn-create').disabled = !lastCreateSpec;
      });
    });

    $('btn-create').addEventListener('click', () => {
      if (!lastCreateSpec) return;
      if (!confirm('Creare il sito «' + lastCreateSpec.id + '»? Verranno scritte le cartelle, il tema e il mount.')) return;
      $('btn-create').disabled = true;
      api('create', Object.assign({ apply: '1' }, lastCreateSpec)).then(({ body }) => {
        if (body.error) { show(['<span class="err">✗ ' + esc(body.error) + '</span>']); return; }
        showReport(body, 'Sito creato.');
        lastCreateSpec = null;
        load();
      });
    });

    $('btn-l-preview').addEventListener('click', () => {
      const spec = { id: $('l-site').value, locale: $('l-locale').value };
      api('add-locale', spec).then(({ body }) => {
        if (body.error) { show(['<span class="err">✗ ' + esc(body.error) + '</span>']); return; }
        showReport(body, '');
        lastLocaleSpec = body.errors && body.errors.length ? null : spec;
        $('btn-l-apply').disabled = !lastLocaleSpec;
      });
    });

    $('btn-l-apply').addEventListener('click', () => {
      if (!lastLocaleSpec) return;
      $('btn-l-apply').disabled = true;
      api('add-locale', Object.assign({ apply: '1' }, lastLocaleSpec)).then(({ body }) => {
        if (body.error) { show(['<span class="err">✗ ' + esc(body.error) + '</span>']); return; }
        showReport(body, 'Lingua aggiunta.');
        lastLocaleSpec = null;
        load();
      });
    });

    /* ---------- What the site is about ---------- */

    let chosenPlace = null;

    $('btn-e-search').addEventListener('click', () => {
      const btn = $('btn-e-search');
      btn.disabled = true;
      $('e-results').innerHTML = '<p class="intro">Cerco…</p>';
      api('place-search', { id: $('e-site').value, query: $('e-query').value.trim() })
        .then(({ body }) => {
          btn.disabled = false;
          if (body.error) { $('e-results').innerHTML = ''; show(['<span class="err">✗ ' + esc(body.error) + '</span>']); return; }
          renderPlaces(body.places || []);
        });
    });

    function renderPlaces(places) {
      const box = $('e-results');
      box.innerHTML = '';
      if (!places.length) { box.innerHTML = '<p class="intro">Nessun risultato.</p>'; return; }
      places.forEach((p) => {
        const row = document.createElement('div');
        row.className = 'site-row';
        row.innerHTML =
          '<b>' + esc(p.name) + '</b>' +
          '<span class="tag">' + esc(p.address) + '</span>' +
          (p.primaryType ? '<span class="tag">' + esc(p.primaryType) + '</span>' : '') +
          (p.rating ? '<span class="tag">★ ' + esc(p.rating) + '</span>' : '') +
          /* Whether Google has opening hours is worth seeing BEFORE choosing:
           * it is the one field that cannot be filled in later from anywhere
           * else, and a place without them needs someone to type them. */
          '<span class="tag">orari ' + (p.hasHours ? 'sì' : '<span class="warn">no</span>') + '</span>' +
          '<span class="grow"></span>' +
          '<span class="tag">@id <code>' + esc(p.proposedId) + '</code></span>';
        const pick = document.createElement('button');
        pick.textContent = 'Anteprima';
        pick.addEventListener('click', () => attach(p, false));
        row.appendChild(pick);
        box.appendChild(row);
      });
    }

    function attach(place, apply) {
      const extra = { id: $('e-site').value, place_id: place.placeId, types: $('e-types').value.trim() };
      if (apply) extra.apply = '1';
      api('attach', extra).then(({ body }) => {
        if (body.error) { show(['<span class="err">✗ ' + esc(body.error) + '</span>']); return; }
        showReport(body, 'Abbinato.');
        if (!apply && !(body.errors || []).length) {
          chosenPlace = place;
          const go = document.createElement('button');
          go.className = 'primary';
          go.textContent = 'Abbina «' + place.name + '»';
          go.addEventListener('click', () => { go.disabled = true; attach(place, true); load(); });
          out.appendChild(document.createElement('br'));
          out.appendChild(go);
        }
      });
    }

    function load() {
      api('list').then(({ status, body }) => {
        if (status === 403 || body.error) {
          $('gate-msg').textContent = body.error || 'Riservato agli amministratori.';
          return;
        }
        SITES = body.sites || [];
        CATALOG = body.catalog || [];
        MENU_STYLES = body.menuStyles || MENU_STYLES;
        renderSites();
        if (!$('f-pages').children.length) renderCatalog();
      });
    }

    /* The gate: the page shows itself only to someone signed in with a role,
     * so it does not leave an index of the sites open to anyone. The session
     * announces itself through meetooSession.subscribe — the same way the hub
     * waits for it, because there is one session and it should be entered the
     * same way everywhere. */
    (function auth() {
      if (!window.meetooSession) { setTimeout(auth, 100); return; }
      meetooSession.subscribe((user) => {
        if (!user) {
          $('gate-msg').textContent = 'Accedi con Google (in alto a destra) per entrare nella Gestione.';
          return;
        }
        api('auth').then(({ status, body }) => {
          if (status !== 200 || body.error) {
            $('gate-msg').textContent = body.error || 'Accesso non riuscito.';
            return;
          }
          if (!['admin', 'super-admin'].includes(body.role)) {
            $('gate-msg').textContent =
              'Il tuo account (' + (body.email || '') + ', ruolo ' + (body.role || '?') + ') non può vedere i siti.';
            return;
          }
          $('gate').hidden = true;
          $('app').classList.add('on');
          /* An admin sees the sites; only a super-admin gets the forms. The
           * server refuses either way — this just does not offer what it
           * would then refuse. */
          IS_SUPER = !!body.isSuper;
          if (IS_SUPER) {
            $('sec-new').hidden = false; $('sec-locale').hidden = false; $('sec-entity').hidden = false;
          }
          fillLocaleSelects();
          load();
        });
      });
    })();
  })();
  </script>
</body>
</html>
