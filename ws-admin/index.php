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
    // L'elenco delle operazioni NON sta qui: sta nel registro (lib/ws-maintenance.php),
    // che è anche ciò che legge la Gestione eventi. Questa pagina è solo il posto da cui
    // si eseguono tutte. Ogni operazione ha due modi: ANTEPRIMA (dice cosa farebbe, non
    // scrive) e APPLICA; il default è l'anteprima. Riservate agli admin: toccano i contenuti.
    if ($action === 'maint' || $action === 'maint-list') {
        if (!in_array($user['role'], ['admin', 'super-admin'], true)) {
            http_response_code(403); echo json_encode(['error' => 'Solo admin/super-admin possono eseguire migrazioni.']); exit;
        }
        // Il registro è una libreria a parte: se manca (deploy incompleto) si perde
        // la manutenzione, NON l'accesso all'amministrazione. Per questo il require
        // sta qui dentro ed è controllato, non in cima al file.
        $registro = __DIR__ . '/lib/ws-maintenance.php';
        if (!is_file($registro)) {
            http_response_code(503);
            echo json_encode(['error' => 'Manutenzione non disponibile: manca ws-admin/lib/ws-maintenance.php sul server (carica le librerie insieme alle pagine).']);
            exit;
        }
        require_once $registro;
        $base = __DIR__ . '/../ws-custom/contents/meetoo/it_IT';

        // maint-list: che cosa esiste, quando è stata eseguita l'ultima volta e —
        // per chi sa dirlo senza scrivere — quante cose sono in attesa. È questo
        // che rende la pagina utile dopo un aggiornamento di Meetoo.
        if ($action === 'maint-list') {
            $ops = ws_maint_list($base);
            if (($_POST['probe'] ?? '') === '1') {
                foreach ($ops as &$op) {
                    if (empty($op['preview'])) continue;
                    try { $op['pending'] = (int)(ws_maint_run($base, $op['id'], false)['changes'] ?? 0); }
                    catch (Throwable $e) { $op['pending'] = null; }
                }
                unset($op);
            }
            echo json_encode(['success' => true, 'version' => MEETOO_VERSION, 'ops' => $ops]);
            exit;
        }

        // Le opzioni le dichiara il registro (una spunta per operazione): si passa
        // ciò che è arrivato, senza che l'endpoint conosca i nomi a memoria.
        $opts = [];
        foreach (['adopt', 'create', 'root'] as $k) if (($_POST[$k] ?? '') === '1') $opts[$k] = true;
        $rep  = ws_maint_run($base, (string)($_POST['op'] ?? ''), ($_POST['apply'] ?? '') === '1', $opts, $user['email'] ?? '');
        if (isset($rep['error'])) { http_response_code(400); echo json_encode($rep); exit; }
        echo json_encode(['success' => true] + $rep);
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
    .maint-stato { color: var(--color-hint); font-size: .8rem; margin: 4px 0 0; }
    .maint-stato b { color: var(--color-link); }
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
        <h2 class="sec-head"><span class="material-symbols-outlined">construction</span>Manutenzione<span class="count" id="maint-version"></span></h2>
        <p class="maint-intro">Tutte le operazioni disponibili in questa versione: è da qui che si completa un aggiornamento.
          Ognuna mostra prima <b>cosa farebbe</b>; si scrive solo premendo «Applica».</p>
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
     * L'elenco NON è scritto qui: arriva dal registro (lib/ws-maintenance.php),
     * che è anche quello letto dalla Gestione eventi. Aggiungere un'operazione
     * al registro la fa comparire qui da sola: è questa pagina il posto da cui
     * si governa un aggiornamento di Meetoo, e non può restare indietro.
     * Regola invariata: prima ANTEPRIMA (non scrive), poi APPLICA con conferma. */
    let MAINT = [];

    function maintShow(titolo, r) {
      const out = document.getElementById('maint-out');
      out.hidden = false;
      const testa = titolo + ' — ' + (r.applied ? 'APPLICATO' : 'anteprima (nessuna scrittura)');
      out.textContent = testa + '\n' + (r.summary || '') + (r.lines && r.lines.length ? '\n\n' + r.lines.join('\n') : '');
      out.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Riga di stato sotto il titolo: quando è stata eseguita l'ultima volta qui e —
    // per chi sa dirlo senza scrivere — quante cose restano in attesa. Dopo un
    // aggiornamento «mai eseguita» è il segnale che quella migrazione va ancora fatta.
    function maintStato(m) {
      const parti = [];
      if (m.pending > 0) parti.push('<b>' + m.pending + ' in attesa</b>');
      else if (m.pending === 0) parti.push('a posto');
      if (m.last) {
        const d = new Date(m.last.at);
        parti.push('eseguita il ' + d.toLocaleDateString('it-IT', { day: 'numeric', month: 'short', year: 'numeric' }));
      } else {
        parti.push('mai eseguita qui');
      }
      return parti.join(' · ');
    }

    function renderMaint() {
      const box = document.getElementById('sec-manutenzione');
      box.innerHTML = MAINT.map((m) => Meetoo.tileCard({ href: '#' + m.id, icon: m.icon, title: m.title, meta: m.meta })).join('');
      // Le card qui non sono link: portano i pulsanti dell operazione.
      [...box.querySelectorAll('.card')].forEach((card, i) => {
        const m = MAINT[i];
        card.removeAttribute('href');
        card.querySelector('.card-arrow')?.remove();
        const stato = document.createElement('p');
        stato.className = 'maint-stato';
        stato.innerHTML = maintStato(m);
        (card.querySelector('.card-body') || card).appendChild(stato);
        const az = document.createElement('div');
        az.className = 'card-actions maint-actions';
        az.innerHTML =
          (m.preview ? '<button type="button" class="card-act" data-do="preview">Anteprima</button>' : '') +
          (m.option ? '<label class="card-act"><input type="checkbox" data-opt> ' + m.option.label + '</label>' : '') +
          '<button type="button" class="card-act primary" data-do="apply">' + (m.preview ? 'Applica' : 'Esegui') + '</button>';
        card.appendChild(az);
        az.querySelectorAll('button[data-do]').forEach((b) => b.addEventListener('click', () => {
          const applica = b.dataset.do === 'apply';
          if (applica && m.confirm && !confirm(m.confirm)) return;
          const extra = {};
          if (m.option && az.querySelector('[data-opt]')?.checked) extra[m.option.key] = '1';
          b.disabled = true; b.textContent = '…';
          api('maint', Object.assign({ op: m.id, apply: applica ? '1' : '' }, extra))
            .then((r) => {
              b.disabled = false; b.textContent = applica ? (m.preview ? 'Applica' : 'Esegui') : 'Anteprima';
              if (r.status !== 200) { maintShow(m.title, { summary: r.body.error || 'Operazione fallita.', lines: [] }); return; }
              maintShow(m.title, r.body);
              // Dopo un'esecuzione lo stato è cambiato: ricaricalo invece di indovinarlo.
              if (applica) caricaMaint();
            });
        }));
      });
    }

    // Chiede al registro l'elenco aggiornato; probe=1 fa girare le anteprime,
    // così le card sanno dire se c'è ancora qualcosa da fare.
    function caricaMaint() {
      return api('maint-list', { probe: '1' }).then((r) => {
        if (r.status !== 200 || !r.body.ops) {
          // Meglio dire perché la sezione è vuota che lasciarla vuota e basta.
          document.getElementById('sec-manutenzione').innerHTML =
            '<p class="maint-intro">' + (r.body.error || 'Elenco delle operazioni non disponibile.') + '</p>';
          return;
        }
        MAINT = r.body.ops;
        document.getElementById('maint-version').textContent = 'Meetoo ' + r.body.version;
        renderMaint();
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
      if (adminOnlyOk) { document.getElementById('sec-manutenzione-box').hidden = false; caricaMaint(); }
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
