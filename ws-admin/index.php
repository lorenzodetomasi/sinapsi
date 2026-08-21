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
    $user = ws_authenticate($_POST['credential'] ?? '');
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
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
    #gate .material-symbols-outlined { font-size: 2.5rem; color: var(--accent); }
    #app { display: none; }
    #app.on { display: block; }
    .card.soon { opacity: .6; }
    .card.soon .card-title { color: var(--color-hint); }
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
        <h2 class="sec-head"><span class="material-symbols-outlined">place</span>Luoghi e organizzazioni</h2>
        <div class="cards" id="sec-luoghi"></div>
      </section>

      <section>
        <h2 class="sec-head"><span class="material-symbols-outlined">group</span>Utenti</h2>
        <div class="cards" id="sec-utenti"></div>
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
      Meetoo.setBreadcrumb(
        [{ label: 'Lido di Ostia', href: THEME + 'index.html' }, { label: 'Amministrazione', current: true }],
        [{ label: 'Admin' }]
      );
    })();

    const api = (action) => {
      const body = new URLSearchParams({ action });
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
      'sec-luoghi': [
        { href: ADMIN + 'places/edit/', icon: 'add_location', title: 'Luoghi e attività', meta: 'Cerca fra i salvati o importa da Google Maps' },
        { href: ADMIN + 'places/edit/', icon: 'groups', title: 'Organizzazioni', meta: 'Stesso strumento: «Apri salvato» per organizations/…' },
      ],
      'sec-utenti': [
        { href: '#', icon: 'manage_accounts', title: 'Utenti e ruoli', meta: 'In arrivo — oggi i ruoli si cambiano in users/users.xml', soon: true },
      ],
      'sec-tools': [
        { href: ADMIN + 'json-xml/index.php', icon: 'sync_alt', title: 'Convertitore JSON ⇄ XML', meta: 'Converte e valida i contenuti' },
        { href: THEME + 'index.html', icon: 'public', title: 'Vai al sito', meta: 'Lido di Ostia (pagina pubblica)', external: true },
      ],
    };

    function render(adminOnlyOk) {
      Object.keys(TOOLS).forEach((id) => {
        document.getElementById(id).innerHTML = TOOLS[id].map((t) => Meetoo.tileCard({
          href: t.soon ? '#' : t.href,
          icon: t.icon, title: t.title, meta: t.meta,
          external: !!t.external,
          className: t.soon ? 'soon' : '',
        })).join('');
      });
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
