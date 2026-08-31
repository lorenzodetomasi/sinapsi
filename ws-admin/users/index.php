<?php
/*
 * Gestione utenti — chi c'è, che ruolo ha, quali gruppi gestisce.
 *
 * Finora i ruoli si cambiavano aprendo `users/<uid>/index.xml` con un editor di
 * testo, e chi gestisce un gruppo si scriveva a mano nel file del gruppo. Sono le
 * due leve che decidono chi può fare che cosa su tutto il sito: tenerle in un file
 * vuol dire che le può muovere solo chi sa dove sta il file.
 *
 * DUE COSE DIVERSE, e la pagina le tiene separate perché lo sono:
 *   - il RUOLO dice che cosa una persona può fare in generale (entrare in
 *     Gestione, amministrare). Sta sull'utente, e si cambia qui.
 *   - GESTIRE UN GRUPPO dice per conto di chi può pubblicare. Sta sul gruppo
 *     (`meetoo:manager`), e si cambia nella scheda del gruppo — qui si vede
 *     soltanto, perché è la risposta alla domanda «e questa persona, che cosa
 *     tocca?», che è la domanda che ci si fa guardando un elenco di utenti.
 *
 * Questo file è ANCHE il suo endpoint JSON (POST):
 *   action=auth  → chi sei
 *   action=list  → l'elenco
 *   action=role  → cambia il ruolo di qualcuno (solo super-admin)
 */

ini_set('display_errors', '0');

require_once __DIR__ . '/../lib/ws-auth.php';

const RUOLI = ['logged-visitor', 'verified-visitor', 'user', 'client', 'admin', 'super-admin'];

function utenti_base(): string { return __DIR__ . '/../../ws-custom/contents/meetoo/it_IT'; }

/** Il nome di una persona, dal suo profilo. Vuoto se non l'ha mai scritto. */
function utenti_persona(string $uid): array {
    $f = utenti_base() . "/persons/$uid/index.xml";
    if (!is_file($f)) return ['name' => '', 'alternateName' => ''];
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    libxml_use_internal_errors(true);
    $ok = $dom->load($f, LIBXML_NONET);
    libxml_clear_errors();
    if (!$ok) return ['name' => '', 'alternateName' => ''];
    $x = simplexml_import_dom($dom);
    return [
        'name' => $x ? trim((string)$x->name) : '',
        'alternateName' => ($x && isset($x->alternateName)) ? trim((string)$x->alternateName) : '',
    ];
}

/** Il documento dell'utente, com'è sul disco (spazi compresi: si riscrive un nodo
 *  solo, e il resto del file deve restare identico a com'era). */
function utenti_dom(string $uid): ?DOMDocument {
    $f = utenti_base() . "/users/$uid/index.xml";
    if (!is_file($f)) return null;
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;   // NON toccare la formattazione del resto
    libxml_use_internal_errors(true);
    $ok = $dom->load($f, LIBXML_NONET);   // niente xinclude: qui serve il file, non l'albero risolto
    libxml_clear_errors();
    return $ok ? $dom : null;
}

function utenti_ruolo(DOMDocument $dom): string {
    $n = $dom->getElementsByTagName('role')->item(0);
    return $n ? trim($n->textContent) : '';
}

/** I gruppi che questa persona gestisce, con il loro nome. */
function utenti_gruppi(string $uid): array {
    $out = [];
    foreach (glob(utenti_base() . '/organizations/*/index.json') as $f) {
        $j = json_decode((string)@file_get_contents($f), true);
        if (!is_array($j)) continue;
        $e = $j['mainEntity'] ?? $j;
        if (!is_array($e)) continue;
        if (in_array("users/$uid", ws_ref_ids($e['meetoo:manager'] ?? null), true)) {
            $out[] = ['id' => (string)($e['@id'] ?? ''), 'name' => (string)($e['name'] ?? '')];
        }
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $user = ws_authenticate($_POST['credential'] ?? '');
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
        exit;
    }
    $action = $_POST['action'] ?? '';
    $sonoAdmin = in_array($user['role'], ['admin', 'super-admin'], true);

    if ($action === 'auth') {
        echo json_encode(['uid' => $user['uid'], 'email' => $user['email'], 'role' => $user['role'], 'canAdmin' => $sonoAdmin]);
        exit;
    }

    if (!$sonoAdmin) {
        http_response_code(403);
        echo json_encode(['error' => "Solo amministratori (ruolo attuale: {$user['role']})."]);
        exit;
    }

    if ($action === 'list') {
        $out = [];
        foreach (scandir(utenti_base() . '/users') ?: [] as $voce) {
            if (!preg_match('/^\d{6,}$/', $voce)) continue;
            $dom = utenti_dom($voce);
            if (!$dom) continue;
            $p = utenti_persona($voce);
            $ultimo = $dom->getElementsByTagName('last_login')->item(0);
            $out[] = [
                'uid' => $voce,
                'role' => utenti_ruolo($dom),
                'name' => $p['name'],
                'alternateName' => $p['alternateName'],
                'lastLogin' => $ultimo ? trim($ultimo->textContent) : '',
                'gruppi' => utenti_gruppi($voce),
                'io' => $voce === $user['uid'],
            ];
        }
        usort($out, function ($a, $b) {
            $peso = function ($r) { $i = array_search($r, RUOLI, true); return $i === false ? -1 : $i; };
            return $peso($b['role']) <=> $peso($a['role']) ?: strcasecmp($a['name'] ?: $a['uid'], $b['name'] ?: $b['uid']);
        });
        echo json_encode(['users' => $out, 'ruoli' => RUOLI]);
        exit;
    }

    if ($action === 'role') {
        /* Il ruolo lo cambia solo un super-admin. Un admin vede l'elenco ma non
         * si promuove da sé: il gradino più alto lo dà chi ce l'ha già. */
        if ($user['role'] !== 'super-admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Solo un super-admin può cambiare i ruoli.']);
            exit;
        }
        $uid = preg_replace('/[^0-9]/', '', (string)($_POST['uid'] ?? ''));
        $ruolo = (string)($_POST['role'] ?? '');
        if ($uid === '' || !in_array($ruolo, RUOLI, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Utente o ruolo non valido.']);
            exit;
        }
        /* Su se stessi no. Non è paternalismo: è che togliersi il proprio ruolo è
         * l'unico errore di questa pagina che non si può correggere DA questa
         * pagina — dopo, per rientrare, servirebbe un editor di testo sul server. */
        if ($uid === $user['uid']) {
            http_response_code(400);
            echo json_encode(['error' => 'Il tuo ruolo non lo puoi cambiare da qui: chiedilo a un altro super-admin.']);
            exit;
        }
        $dom = utenti_dom($uid);
        if (!$dom) { http_response_code(404); echo json_encode(['error' => "Nessun utente $uid."]); exit; }
        $n = $dom->getElementsByTagName('role')->item(0);
        if (!$n) { http_response_code(500); echo json_encode(['error' => 'Il file di questo utente non ha un ruolo.']); exit; }
        $prima = trim($n->textContent);
        $n->nodeValue = $ruolo;
        $f = utenti_base() . "/users/$uid/index.xml";
        if (@file_put_contents($f, $dom->saveXML()) === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Scrittura fallita (permessi?).']);
            exit;
        }
        echo json_encode(['success' => true, 'uid' => $uid, 'prima' => $prima, 'adesso' => $ruolo]);
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
  <title>Utenti e ruoli — Meetoo</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Slab:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
  <link rel="stylesheet" href="../../ws-custom/themes/meetoo/meetoo.css">
  <style>
    /* Solo ciò che è proprio di questa pagina: il resto è in meetoo.css. */
    .u-tab { width: 100%; border-collapse: collapse; }
    .u-tab th {
      text-align: left; padding: 8px 12px; font-size: .72rem; letter-spacing: .08em;
      text-transform: uppercase; color: var(--color-hint); border-bottom: 1px solid var(--color-line);
      white-space: nowrap;
    }
    .u-tab td { padding: 12px; border-bottom: 1px solid var(--color-line); vertical-align: top; }
    .u-tab tr:last-child td { border-bottom: none; }
    .u-nome { font-weight: 600; }
    .u-uid { font-family: 'Source Code Pro', ui-monospace, monospace; font-size: .78rem; color: var(--color-hint); }
    .u-io { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: var(--color-link); margin-left: 6px; }
    .u-gruppi { display: flex; flex-wrap: wrap; gap: 6px; }
    .u-gruppo {
      font-size: .8rem; padding: 2px 10px; border-radius: 999px;
      border: 1px solid var(--color-line); color: var(--color-text); text-decoration: none;
    }
    .u-gruppo:hover { border-color: var(--color-link); color: var(--color-link); }
    .u-nessuno { color: var(--color-hint); font-size: .85rem; }
    .u-tab select {
      background: var(--color-background-section1); color: var(--color-text);
      border: 1px solid var(--color-line); border-radius: 999px; padding: 6px 12px; font: inherit;
    }
    .u-tab select:disabled { opacity: .5; }
    .u-nota { color: var(--color-hint); max-width: 46rem; margin: 0 0 20px; }
    .u-esito { margin-left: 10px; font-size: .85rem; }
    .u-esito.ok { color: var(--color-link); }
    .u-esito.ko { color: var(--color-danger, #d93025); }
    #gate { display: none; text-align: center; padding: 60px 20px; color: var(--color-hint); }
    #gate .material-symbols-outlined { font-size: 40px; }
    #app { display: none; }
    #app.on { display: block; }
  </style>
</head>
<body>
  <div class="wrap">
    <div id="gate">
      <span class="material-symbols-outlined">lock</span>
      <p id="gate-msg">Accedi con Google (in alto a destra) per gestire gli utenti.</p>
    </div>

    <div id="app">
      <h1>Utenti e ruoli</h1>
      <p class="u-nota">Il <strong>ruolo</strong> dice che cosa una persona può fare in generale.
        <strong>Gestire un gruppo</strong> è un'altra cosa: dice per conto di chi può pubblicare, sta
        scritto sul gruppo, e si cambia nella sua scheda — qui si vede soltanto.</p>

      <section>
        <h2 class="sec-head"><span class="material-symbols-outlined">group</span>Chi c'è <span class="count" id="u-count"></span></h2>
        <table class="u-tab">
          <thead>
            <tr><th>Persona</th><th>Ruolo</th><th>Gruppi che gestisce</th><th>Ultimo accesso</th></tr>
          </thead>
          <tbody id="u-corpo"><tr><td colspan="4" class="u-nessuno">Carico…</td></tr></tbody>
        </table>
      </section>
    </div>
  </div>

  <script src="../../ws-custom/themes/meetoo/header.js"></script>
  <script>
  (function () {
    const SITE_ROOT = location.pathname.replace(/\/ws-admin\/.*/, '/');
    const SCHEDA = SITE_ROOT + 'ws-admin/places/edit/';
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

    function api(action, extra) {
      const body = new URLSearchParams(Object.assign({ action }, extra || {}));
      const t = window.meetooSession && meetooSession.getToken();
      if (t) body.set('credential', t);
      return fetch(location.pathname, {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString(),
      }).then((r) => r.json().then((j) => ({ status: r.status, body: j }), () => ({ status: r.status, body: {} })));
    }

    let ruoli = [];
    let ioSonoSuperAdmin = false;

    function riga(u) {
      const nome = u.name || u.alternateName || '(senza nome)';
      const gruppi = u.gruppi.length
        ? '<div class="u-gruppi">' + u.gruppi.map((g) =>
            '<a class="u-gruppo" href="' + esc(SCHEDA + '?id=' + encodeURIComponent(g.id)) + '">'
            + esc(g.name || g.id) + '</a>').join('') + '</div>'
        : '<span class="u-nessuno">nessuno</span>';
      // Il proprio ruolo non si tocca da qui, e un admin non promuove nessuno:
      // il menu c'è ma è spento, così si vede la regola invece di indovinarla.
      const spento = !ioSonoSuperAdmin || u.io;
      const opzioni = ruoli.map((r) =>
        '<option value="' + esc(r) + '"' + (r === u.role ? ' selected' : '') + '>' + esc(r) + '</option>').join('');
      return '<tr data-uid="' + esc(u.uid) + '">'
        + '<td><div class="u-nome">' + esc(nome) + (u.io ? '<span class="u-io">sei tu</span>' : '') + '</div>'
        + '<div class="u-uid">' + esc(u.uid) + '</div></td>'
        + '<td><select class="u-ruolo"' + (spento ? ' disabled title="Solo un super-admin può cambiare i ruoli, e non il proprio"' : '') + '>'
        + opzioni + '</select><span class="u-esito"></span></td>'
        + '<td>' + gruppi + '</td>'
        + '<td class="u-nessuno">' + esc(u.lastLogin || '—') + '</td>'
        + '</tr>';
    }

    function disegna(lista) {
      const corpo = document.getElementById('u-corpo');
      corpo.innerHTML = lista.length ? lista.map(riga).join('')
        : '<tr><td colspan="4" class="u-nessuno">Nessun utente.</td></tr>';
      document.getElementById('u-count').textContent = lista.length;
      corpo.querySelectorAll('.u-ruolo').forEach((sel) => {
        sel.addEventListener('change', function () {
          const tr = this.closest('tr');
          const esito = tr.querySelector('.u-esito');
          const prima = this.dataset.prima || '';
          esito.textContent = '…';
          esito.className = 'u-esito';
          api('role', { uid: tr.dataset.uid, role: this.value }).then((r) => {
            if (r.status === 200 && r.body.success) {
              esito.textContent = 'da ' + r.body.prima + ' a ' + r.body.adesso;
              esito.className = 'u-esito ok';
              this.dataset.prima = r.body.adesso;
            } else {
              esito.textContent = r.body.error || 'non riuscito';
              esito.className = 'u-esito ko';
              if (prima) this.value = prima;
            }
          });
        });
        sel.dataset.prima = sel.value;
      });
    }

    function avvia() {
      document.getElementById('gate').style.display = 'none';
      document.getElementById('app').classList.add('on');
      if (window.Meetoo) {
        Meetoo.setBreadcrumb([{ label: 'Gestione', href: SITE_ROOT + 'ws-admin/' }, { label: 'Utenti e ruoli', current: true }]);
      }
      api('list').then((r) => {
        if (r.status !== 200) { document.getElementById('u-corpo').innerHTML =
          '<tr><td colspan="4" class="u-nessuno">' + esc(r.body.error || 'Elenco non disponibile.') + '</td></tr>'; return; }
        ruoli = r.body.ruoli || [];
        disegna(r.body.users || []);
      });
    }

    (function auth() {
      if (!window.meetooSession) { setTimeout(auth, 100); return; }
      document.getElementById('gate').style.display = 'block';
      meetooSession.subscribe((user) => {
        const msg = document.getElementById('gate-msg');
        if (!user) { msg.textContent = 'Accedi con Google (in alto a destra) per gestire gli utenti.'; return; }
        api('auth').then((r) => {
          if (r.status === 200 && r.body.canAdmin) { ioSonoSuperAdmin = r.body.role === 'super-admin'; avvia(); }
          else msg.textContent = 'Il tuo account (' + (r.body.email || '') + ', ruolo ' + (r.body.role || '?') + ') non amministra gli utenti.';
        });
      });
    })();
  })();
  </script>
</body>
</html>
