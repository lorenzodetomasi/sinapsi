<?php
/*
 * Amministrazione — punto d'ingresso agli strumenti redazionali di Meetoo.
 *
 * Solo elenco e collegamenti: ogni strumento gestisce da sé i propri permessi.
 * La pagina però si mostra solo a chi è autenticato con un ruolo abilitato, così
 * non resta un indice degli strumenti visibile a chiunque.
 *
 * È anche il proprio endpoint POST: action=auth → identità + ruolo.
 */

require_once __DIR__ . '/lib/ws-auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // La pagina fa anche da endpoint JSON: gli errori PHP non devono finire nel corpo.
    ini_set('display_errors', '0');
    $action = (string)($_POST['action'] ?? 'auth');
    $user = ws_authenticate($_POST['credential'] ?? '');
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
        exit;
    }
    // --- Manutenzione: migrazioni e conversioni ---
    // Ogni operazione ha due modi: ANTEPRIMA (dice cosa farebbe, non scrive) e
    // APPLICA. Il valore di default è l anteprima: si scrive solo se richiesto
    // esplicitamente. Riservate agli admin perché toccano i file dei contenuti.
    if (in_array($action, ['privacy', 'covers', 'media-index', 'places-index'], true)) {
        if (!in_array($user['role'], ['admin', 'super-admin'], true)) {
            http_response_code(403); echo json_encode(['error' => 'Solo admin/super-admin possono eseguire migrazioni.']); exit;
        }
        $base  = __DIR__ . '/../ws-custom/contents/meetoo/it_IT';
        $apply = ($_POST['apply'] ?? '') === '1';

        if ($action === 'privacy') {
            require_once __DIR__ . '/lib/ws-private.php';
            $r = ws_privacy_migrate($base, $apply);
            $righe = array_merge(
                array_map(fn($p) => "users/{$p['uid']} → " . implode(', ', $p['fields']), $r['profiles']),
                array_map(fn($x) => "{$x['event']}/rsvp.json → {$x['entries']} registrazioni", $r['rsvp'])
            );
            echo json_encode(['success' => true, 'applied' => $apply, 'lines' => $righe,
                'summary' => $righe ? (count($r['profiles']) . ' profili, ' . count($r['rsvp']) . ' file di registrazioni')
                                       : 'Nessun dato personale nei file pubblici.']);
            exit;
        }

        if ($action === 'covers') {
            require_once __DIR__ . '/lib/ws-media.php';
            $r = ws_media_covers($base, $apply, ($_POST['adopt'] ?? '') === '1');
            $righe = array_merge(
                array_map(fn($d) => "{$d['event']} → " . ($d['exact'] ? 'già 1920×1080' : "{$d['from']} → cover"), $r['done']),
                array_map(fn($x) => "{$x['event']} → {$x['why']}", $r['skipped']),
                array_map(fn($x) => "{$x['event']} → esterna: {$x['url']}", $r['external']),
                array_map(fn($b) => "⚠ {$b['event']} → {$b['ref']}" . ($b['found'] ? " (in cartella: {$b['found']})" : ' (nessuna immagine)'), $r['broken'])
            );
            if ($apply) { require_once __DIR__ . '/lib/events-index.php'; event_index_rebuild($base); }
            echo json_encode(['success' => true, 'applied' => $apply, 'lines' => $righe,
                'summary' => count($r['done']) . ' cover, ' . count($r['broken']) . ' da sistemare a mano']);
            exit;
        }

        if ($action === 'media-index') {
            require_once __DIR__ . '/lib/ws-media.php';
            $n = count(ws_media_reindex($base));
            echo json_encode(['success' => true, 'applied' => true, 'lines' => [], 'summary' => "$n immagini indicizzate"]);
            exit;
        }

        // places: indice di deduplica google_place_id + elenco Gruppi
        require_once __DIR__ . '/places/index-lib.php';
        list($idx, $conf) = ws_index_rebuild(); ws_index_save($idx);
        $g = ws_gruppi_rebuild(); ws_gruppi_save($g);
        echo json_encode(['success' => true, 'applied' => true,
            'lines' => array_map(fn($ids, $gid) => "⚠ stesso place_id su: " . implode(', ', array_unique($ids)), $conf, array_keys($conf)),
            'summary' => count($idx) . ' luoghi, ' . count($g) . ' gruppi']);
        exit;
    }

    echo json_encode([
        'uid' => $user['uid'], 'email' => $user['email'], 'role' => $user['role'],
        'name' => $user['name'] ?? '',
        'canEdit' => in_array($user['role'], ['user', 'client', 'admin', 'super-admin'], true),
        'isAdmin' => in_array($user['role'], ['admin', 'super-admin'], true),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Amministrazione — Meetoo</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Slab:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
  <link rel="stylesheet" href="../ws-custom/themes/meetoo/meetoo.css">
  <style>
    /* Solo le specificità di questa pagina: il resto è in meetoo.css. */
    #gate { text-align: center; padding: 48px 16px; color: var(--color-hint); }
    #gate .material-symbols-outlined { font-size: 2.5rem; color: var(--color-link); }
    #app { display: none; }
    #app.on { display: block; }
    .card.soon { opacity: .6; }
    .card.soon .card-title { color: var(--color-hint); }
    .maint-intro { color: var(--color-hint); font-size: .9rem; margin: -6px 0 12px; }
    .maint-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    #maint-out { background: var(--color-background-section2); border: 1px solid var(--color-line); border-radius: var(--border-radius);
                 padding: 12px; margin-top: 12px; max-height: 320px; overflow: auto; white-space: pre-wrap; font-size: .85rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <div id="gate">
      <span class="material-symbols-outlined">lock</span>
      <p id="gate-msg">Accedi con Google (in alto a destra) per entrare nell'amministrazione.</p>
    </div>

    <div id="app">
      <section>
        <h2 class="sec-head"><span class="material-symbols-outlined">event</span>Eventi</h2>
        <div class="cards" id="sec-eventi"></div>
      </section>

      <section>
        <h2 class="sec-head"><span class="material-symbols-outlined">place</span>Luoghi e attività</h2>
        <div class="cards" id="sec-luoghi"></div>
      </section>

      <section>
        <h2 class="sec-head"><span class="material-symbols-outlined">groups</span>Organizzazioni</h2>
        <div class="cards" id="sec-organizzazioni"></div>
      </section>

      <section>
        <h2 class="sec-head"><span class="material-symbols-outlined">group</span>Utenti</h2>
        <div class="cards" id="sec-utenti"></div>
      </section>

      <section id="sec-manutenzione-box" hidden>
        <h2 class="sec-head"><span class="material-symbols-outlined">construction</span>Manutenzione</h2>
        <p class="maint-intro">Ogni operazione mostra prima <b>cosa farebbe</b>; si scrive solo premendo «Applica».</p>
        <div class="cards" id="sec-manutenzione"></div>
        <pre id="maint-out" hidden></pre>
      </section>

      <section>
        <h2 class="sec-head"><span class="material-symbols-outlined">build</span>Strumenti</h2>
        <div class="cards" id="sec-tools"></div>
      </section>
    </div>
  </div>

  <!-- Moduli condivisi: template delle card + header (prima dello script di pagina). -->
  <script src="../ws-custom/themes/meetoo/cards.js"></script>
  <script src="../ws-custom/themes/meetoo/header.js"></script>

  <script>
  (function () {
    const SITE_ROOT = location.pathname.replace(/\/ws-admin\/.*/, '/');
    const ADMIN = SITE_ROOT + 'ws-admin/';
    const THEME = SITE_ROOT + 'ws-custom/themes/meetoo/';

    (function crumb() {
      if (!window.Meetoo) { setTimeout(crumb, 100); return; }
      // Nell'admin il breadcrumb dice dove sei DENTRO la gestione: "Gestione" è la
      // radice (questa pagina), il sito si raggiunge dal logo.
      Meetoo.setBreadcrumb([{ label: 'Gestione', current: true }]);
    })();

    const api = (action, extra) => {
      const body = new URLSearchParams(Object.assign({ action }, extra || {}));
      const token = window.meetooSession && meetooSession.getToken();
      if (token) body.set('credential', token);
      return fetch(location.pathname, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(),
      }).then((r) => r.json().then((j) => ({ status: r.status, body: j }), () => ({ status: r.status, body: {} })));
    };

    // Ogni voce è una card del sito: stesso componente delle altre pagine.
    const TOOLS = {
      'sec-eventi': [
        { href: ADMIN + 'events/index.php', icon: 'event_note', title: 'Gestione eventi', meta: 'Elenco, ricerca, cestino, indice' },
        { href: ADMIN + 'events/edit/', icon: 'note_add', title: 'Nuovo evento', meta: 'Editor JSON-LD (form)' },
      ],
      // Luoghi e organizzazioni usano lo STESSO strumento: cambia solo dove
      // finiscono (places/<IT+CAP>/<slug> o organizations/<slug>), scelto lì col
      // selettore «Salva come» — che ?as=organization preimposta.
      'sec-luoghi': [
        { href: ADMIN + 'places/edit/', icon: 'place', title: 'Luoghi e attività', meta: 'Apri e aggiorna i luoghi salvati' },
        { href: ADMIN + 'places/edit/', icon: 'add_location', title: 'Nuovo luogo o attività', meta: 'Importa da Google Maps' },
      ],
      'sec-organizzazioni': [
        { href: ADMIN + 'places/edit/', icon: 'groups', title: 'Organizzazioni', meta: 'Apri e aggiorna le organizzazioni salvate' },
        { href: ADMIN + 'places/edit/?as=organization', icon: 'group_add', title: 'Nuova organizzazione', meta: 'Importa da Google Maps come organizations/…' },
      ],
      'sec-utenti': [
        { href: '#', icon: 'manage_accounts', title: 'Utenti e ruoli', meta: 'In arrivo — oggi i ruoli si cambiano in users/users.xml', soon: true },
      ],
      'sec-tools': [
        { href: ADMIN + 'json-xml/index.php', icon: 'sync_alt', title: 'Convertitore JSON ⇄ XML', meta: 'Converte e valida i contenuti' },
        { href: THEME + 'index.html', icon: 'public', title: 'Vai al sito', meta: 'Lido di Ostia (pagina pubblica)', external: true },
      ],
    };


    /* ---------- Manutenzione ----------
     * Migrazioni e conversioni che finora si lanciavano da riga di comando.
     * Regola: prima ANTEPRIMA (non scrive), poi APPLICA con conferma. Le due
     * strade usano le stesse funzioni della CLI, quindi non possono divergere. */
    const MAINT = [
      { id: 'privacy', icon: 'shield_lock', title: 'Dati personali fuori dai file pubblici',
        meta: 'Sposta nome ed email dai profili e dalle registrazioni all archivio privato', preview: true,
        confirm: 'Spostare i dati personali nell archivio privato? I file pubblici verranno riscritti senza nome ed email.' },
      { id: 'covers', icon: 'crop_16_9', title: 'Genera le copertine 1920×1080',
        meta: 'Dalle immagini già caricate; l originale resta in media-sources', preview: true, adopt: true,
        confirm: 'Generare le copertine? Verranno creati file in media/ e aggiornato il campo image degli eventi.' },
      { id: 'media-index', icon: 'photo_library', title: 'Rigenera l indice delle immagini',
        meta: 'Serve al riuso senza duplicati (impronta → percorso)' },
      { id: 'places-index', icon: 'manage_history', title: 'Rigenera indice luoghi e Gruppi',
        meta: 'Deduplica per Google Place ID + elenco dei Gruppi della home' },
    ];

    function maintShow(titolo, r) {
      const out = document.getElementById('maint-out');
      out.hidden = false;
      const testa = titolo + ' — ' + (r.applied ? 'APPLICATO' : 'anteprima (nessuna scrittura)');
      out.textContent = testa + '\n' + (r.summary || '') + (r.lines && r.lines.length ? '\n\n' + r.lines.join('\n') : '');
      out.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function renderMaint() {
      const box = document.getElementById('sec-manutenzione');
      box.innerHTML = MAINT.map((m) => Meetoo.tileCard({ href: '#' + m.id, icon: m.icon, title: m.title, meta: m.meta })).join('');
      // Le card qui non sono link: portano i pulsanti dell operazione.
      [...box.querySelectorAll('.card')].forEach((card, i) => {
        const m = MAINT[i];
        card.removeAttribute('href');
        card.querySelector('.card-arrow')?.remove();
        const az = document.createElement('div');
        az.className = 'card-actions maint-actions';
        az.innerHTML =
          (m.preview ? '<button type="button" class="card-act" data-do="preview">Anteprima</button>' : '') +
          (m.adopt ? '<label class="card-act"><input type="checkbox" data-adopt> adotta orfane</label>' : '') +
          '<button type="button" class="card-act primary" data-do="apply">' + (m.preview ? 'Applica' : 'Esegui') + '</button>';
        card.appendChild(az);
        az.querySelectorAll('button[data-do]').forEach((b) => b.addEventListener('click', () => {
          const applica = b.dataset.do === 'apply';
          if (applica && m.confirm && !confirm(m.confirm)) return;
          const adopt = az.querySelector('[data-adopt]')?.checked ? '1' : '';
          b.disabled = true; b.textContent = '…';
          api(m.id, { apply: applica ? '1' : '', adopt })
            .then((r) => {
              b.disabled = false; b.textContent = applica ? (m.preview ? 'Applica' : 'Esegui') : 'Anteprima';
              if (r.status !== 200) { maintShow(m.title, { summary: r.body.error || 'Operazione fallita.', lines: [] }); return; }
              maintShow(m.title, r.body);
            });
        }));
      });
    }

    function render(adminOnlyOk) {
      Object.keys(TOOLS).forEach((id) => {
        document.getElementById(id).innerHTML = TOOLS[id].map((t) => Meetoo.tileCard({
          href: t.soon ? '#' : t.href,
          icon: t.icon, title: t.title, meta: t.meta,
          external: !!t.external,
          className: t.soon ? 'soon' : '',
        })).join('');
      });
      if (adminOnlyOk) { document.getElementById('sec-manutenzione-box').hidden = false; renderMaint(); }
      // Le voci "in arrivo" non portano da nessuna parte: niente clic a vuoto.
      document.querySelectorAll('.card.soon a').forEach((a) => a.addEventListener('click', (e) => e.preventDefault()));
    }

    (function auth() {
      if (!window.meetooSession) { setTimeout(auth, 100); return; }
      meetooSession.subscribe((user) => {
        if (!user) {
          document.getElementById('gate-msg').textContent = 'Accedi con Google (in alto a destra) per entrare nell\'amministrazione.';
          return;
        }
        api('auth').then((r) => {
          if (r.status === 200 && r.body.canEdit) {
            document.getElementById('gate').style.display = 'none';
            document.getElementById('app').classList.add('on');
            render(r.body.isAdmin);
          } else {
            document.getElementById('gate-msg').textContent =
              'Il tuo account (' + (r.body.email || '') + ', ruolo ' + (r.body.role || '?') + ') non è abilitato all\'amministrazione.';
          }
        });
      });
    })();
  })();
  </script>
</body>
</html>
