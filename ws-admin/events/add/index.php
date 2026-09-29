<?php
/*
 * Add an event - the question that comes BEFORE the form.
 *
 * The events form is one and can do everything, which is why it opens the same
 * for everyone: here the one thing it cannot guess is asked - what SHAPE in
 * time the thing has - and the form arrives already set, showing only what that
 * shape needs (ws-admin/edit-src/src/forma.js).
 *
 * Six shapes in one list, the most frequent first (29 Sep 2026):
 *   - one appointment: an `Event`;
 *   - several meetings, each its own: a container, an `EventSeries` whose
 *     occurrences are events with a page each;
 *   - days in a row, the same event in several dates, every week always the
 *     same: an `EventSeries` in ONE file, its dates in eventSchedule;
 *   - a day with several appointments: STILL an `Event`, with its programme
 *     inside (`subEvent` without pages).
 * The shape decides the @id, and so the address: asked first, in plain words.
 *
 * Questo file è ANCHE il suo piccolo endpoint JSON (POST):
 *   action=auth → identità, ruolo e se questa persona può creare
 */

// La pagina fa anche da endpoint JSON: gli errori PHP non devono finire nel corpo
// della risposta (il client vedrebbe "Unexpected token" invece del vero errore).
ini_set('display_errors', '0');

require_once __DIR__ . '/../../lib/ws-auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $user = ws_authenticate($_POST['credential'] ?? '');
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Autenticazione Google fallita o token scaduto. Accedi di nuovo.']);
        exit;
    }
    if (($_POST['action'] ?? '') === 'auth') {
        /* La stessa domanda che farà il salvataggio. Se qui dicesse di sì e là di
         * no, questa pagina sarebbe un corridoio che finisce in un muro. */
        $base = __DIR__ . '/../../../ws-custom/contents/meetoo/it_IT';
        $gruppi = ws_gruppi_gestiti($base, $user['uid']);
        echo json_encode([
            'email' => $user['email'], 'role' => $user['role'],
            'gruppi' => $gruppi,
            'canCreate' => ws_can_create('events', $user['uid'], $user['role'], $gruppi),
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
  <?php /* The site being managed: its name, look, home and logo (lib/ws-admin-chrome.php). */
  require_once __DIR__ . '/../../lib/ws-admin-chrome.php';
  $ws_chrome = ws_admin_chrome_site('events'); ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aggiungi un evento — <?php echo ws_admin_chrome_esc($ws_chrome['name']); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Roboto+Slab:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">
  <link rel="stylesheet" href="../../../ws-custom/themes/your-theme/css/cards.css">
  <link rel="stylesheet" href="../../../ws-custom/themes/meetoo/css/meetoo.css">
  <?php echo ws_admin_chrome_head($ws_chrome); /* the site's look (lib/ws-admin-chrome.php) */ ?>
  <style>
    /* Solo ciò che è proprio di questa pagina: colori, tipi e header stanno in
       meetoo.css. Le classi si chiamano `tipo-*` e non `card` perché `card` nel
       tema è già la scheda del sito, e due significati sullo stesso nome si
       scoprono sempre tardi e sempre di venerdì. */
    .intro { color: var(--color-hint); max-width: 46rem; margin: 0 0 24px; }
    .tipi { display: flex; flex-direction: column; gap: 14px; max-width: 46rem; }

    .tipo-card {
      border: 1px solid var(--color-line); border-radius: var(--border-radius);
      background: var(--color-background-section1);
      padding: 18px 20px; cursor: pointer;
      transition: border-color .15s ease, background .15s ease;
    }
    .tipo-card:hover { border-color: var(--color-link); }
    .tipo-card:focus-visible { outline: 2px solid var(--color-link); outline-offset: 2px; }
    .tipo-card[aria-pressed="true"] { border-color: var(--color-link); background: var(--color-background-section2); }
    .tipo-card[aria-disabled="true"] { cursor: default; opacity: .62; }
    .tipo-card[aria-disabled="true"]:hover { border-color: var(--color-line); }

    .tipo-testa { display: flex; align-items: flex-start; gap: 14px; }
    .tipo-testa > .material-symbols-outlined { color: var(--color-hint); font-size: 24px; }
    .tipo-card[aria-pressed="true"] .tipo-testa > .material-symbols-outlined { color: var(--color-link); }
    .tipo-nome {
      font-family: 'Roboto Slab', Georgia, serif; font-weight: 600; font-size: 1.0625rem;
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .tipo-desc { margin: 4px 0 0; color: var(--color-text); }
    .tipo-esempio { margin: 2px 0 0; color: var(--color-hint); font-size: .9375rem; }

    .in-arrivo {
      font-size: .6875rem; letter-spacing: .06em; text-transform: uppercase; font-weight: 700;
      color: var(--color-hint); border: 1px solid var(--color-line);
      border-radius: 999px; padding: 2px 8px;
    }


    .nota-duplica {
      display: flex; align-items: flex-start; gap: 10px;
      max-width: 46rem; margin: 22px 0 0; padding: 14px 16px;
      border: 1px solid var(--color-line); border-left: 3px solid var(--color-link);
      border-radius: var(--border-radius); color: var(--color-hint);
    }
    .nota-duplica .material-symbols-outlined { color: var(--color-link); font-size: 20px; }
    .nota-duplica a { color: var(--color-link); }

    .azioni {
      display: flex; justify-content: flex-end; gap: 12px; align-items: center;
      max-width: 46rem; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--color-line);
    }
    .btn-annulla, .btn-avanti {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 22px; border-radius: 999px; font: inherit; font-weight: 600; cursor: pointer;
    }
    .btn-annulla { background: transparent; color: var(--color-hint); border: 1px solid var(--color-line); }
    .btn-annulla:hover { color: var(--color-text); border-color: var(--color-hint); }
    .btn-avanti { background: var(--color-link); color: var(--color-background-header); border: 1px solid var(--color-link); }
    .btn-avanti[disabled] { opacity: .45; cursor: not-allowed; }

    #gate { display: none; text-align: center; padding: 60px 20px; color: var(--color-hint); }
    #gate .material-symbols-outlined { font-size: 40px; }
    #app { display: none; }
    #app.on { display: block; }
  </style>
</head>
<body>
<?php echo ws_admin_chrome_header($ws_chrome); ?>
  <div class="wrap">
    <div id="gate">
      <span class="material-symbols-outlined">lock</span>
      <p id="gate-msg">Accedi con Google (in alto a destra) per aggiungere un evento.</p>
    </div>

    <div id="app">
      <h1>Aggiungi un evento</h1>
      <p class="intro">Che cosa stai per scrivere? La risposta decide come l'evento
        vive nel sito — e cambia il suo indirizzo, quindi conviene sceglierla adesso
        e non dopo.</p>

      <!-- The six shapes, the most frequent first (29 Sep 2026; ws-admin/edit-src/src/forma.js).
           The value goes to the editor as ?tipo=, and the form comes already set. -->
      <div class="tipi">
        <div class="tipo-card" role="button" tabindex="0" aria-pressed="false" data-tipo="singolo">
          <div class="tipo-testa">
            <span class="material-symbols-outlined">calendar_today</span>
            <div>
              <div class="tipo-nome">Un appuntamento</div>
              <p class="tipo-desc">Una data, un luogo: comincia, dura, finisce.</p>
              <p class="tipo-esempio">Una presentazione, un concerto, una passeggiata.</p>
            </div>
          </div>
        </div>

        <div class="tipo-card" role="button" tabindex="0" aria-pressed="false" data-tipo="incontri">
          <div class="tipo-testa">
            <span class="material-symbols-outlined">collections_bookmark</span>
            <div>
              <div class="tipo-nome">Più incontri, ognuno diverso</div>
              <p class="tipo-desc">Appuntamenti che stanno insieme, ognuno con i suoi contenuti e la sua pagina.</p>
              <p class="tipo-esempio">Un corso con la lezione 1, la lezione 2…; il libro del mese, con un libro diverso a ogni incontro.</p>
            </div>
          </div>
        </div>

        <div class="tipo-card" role="button" tabindex="0" aria-pressed="false" data-tipo="periodo">
          <div class="tipo-testa">
            <span class="material-symbols-outlined">date_range</span>
            <div>
              <div class="tipo-nome">Più giorni di fila</div>
              <p class="tipo-desc">Da un giorno a un altro, con gli orari di apertura se ci sono. Una pagina sola.</p>
              <p class="tipo-esempio">Una mostra, un festival, un laboratorio di una settimana.</p>
            </div>
          </div>
        </div>

        <div class="tipo-card" role="button" tabindex="0" aria-pressed="false" data-tipo="repliche">
          <div class="tipo-testa">
            <span class="material-symbols-outlined">event_repeat</span>
            <div>
              <div class="tipo-nome">Lo stesso evento in più date</div>
              <p class="tipo-desc">Le repliche: stesso contenuto, date diverse. Una pagina sola, con tutte le date.</p>
              <p class="tipo-esempio">Uno spettacolo il 29 e il 30 ottobre, alle 17.30 e alle 19.30.</p>
            </div>
          </div>
        </div>

        <div class="tipo-card" role="button" tabindex="0" aria-pressed="false" data-tipo="regola">
          <div class="tipo-testa">
            <span class="material-symbols-outlined">update</span>
            <div>
              <div class="tipo-nome">Ogni settimana o ogni mese, sempre uguale</div>
              <p class="tipo-desc">Si ripete con regolarità e ogni volta è la stessa cosa: le date le calcola il sito, tu scrivi solo le eccezioni. Una pagina sola.</p>
              <p class="tipo-esempio">Un laboratorio ogni lunedì da settembre a giugno.</p>
            </div>
          </div>
        </div>

        <div class="tipo-card" role="button" tabindex="0" aria-pressed="false" data-tipo="giornata">
          <div class="tipo-testa">
            <span class="material-symbols-outlined">view_timeline</span>
            <div>
              <div class="tipo-nome">Una giornata con più appuntamenti</div>
              <p class="tipo-desc">Un giorno solo, a blocchi: resta un evento, con dentro il suo programma.</p>
              <p class="tipo-esempio">Un convegno con tre interventi, una giornata di laboratori a fasce.</p>
            </div>
          </div>
        </div>

        <div class="tipo-card" role="button" tabindex="0" aria-disabled="true" aria-pressed="false" data-tipo="collaterale">
          <div class="tipo-testa">
            <span class="material-symbols-outlined">layers</span>
            <div>
              <div class="tipo-nome">Un Pree o un Poost <span class="in-arrivo">in arrivo</span></div>
              <p class="tipo-desc">Un ritrovo prima o dopo un evento di qualcun altro, proposto
                da chi ci va — non da chi organizza.</p>
              <p class="tipo-esempio">Un aperitivo prima dello spettacolo, una cena dopo il concerto.</p>
            </div>
          </div>
        </div>
      </div>

      <p class="nota-duplica">
        <span class="material-symbols-outlined">content_copy</span>
        <span>Se è una cosa che hai già pubblicato — la stessa serata del mese scorso —
          non ricominciare da qui: <a href="../">nell'elenco eventi</a> ogni scheda ha
          <strong>Duplica</strong>, che copia tutto e lascia da cambiare solo la data.</span>
      </p>

      <div class="azioni">
        <button type="button" class="btn-annulla" id="annulla">Annulla</button>
        <button type="button" class="btn-avanti" id="avanti" disabled>
          Avanti <span class="material-symbols-outlined" style="font-size:18px">arrow_forward</span>
        </button>
      </div>
    </div>
  </div>

  <script src="../../../ws-custom/themes/meetoo/header.js"></script>
  <script>
  (function () {
    const SITE_ROOT = location.pathname.replace(/\/ws-admin\/.*/, '/');
    const EDIT = SITE_ROOT + 'ws-admin/events/edit/';
    const ELENCO = SITE_ROOT + 'ws-admin/events/';

    let scelto = null;

    function api(action, fields) {
      const body = new URLSearchParams(Object.assign(
        { action, credential: (window.meetooSession && meetooSession.getToken()) || '' }, fields || {}));
      return fetch(location.pathname, { method: 'POST', body })
        .then((r) => r.json().then((j) => ({ status: r.status, body: j }), () => ({ status: r.status, body: {} })));
    }

    // The chosen shape: its value goes to the editor as ?tipo=.
    function valore() {
      return scelto ? (scelto.dataset.tipo || '') : '';
    }

    function aggiorna() {
      document.getElementById('avanti').disabled = !valore();
    }

    document.querySelectorAll('.tipo-card').forEach((card) => {
      const scegli = () => {
        if (card.getAttribute('aria-disabled') === 'true') return;
        document.querySelectorAll('.tipo-card').forEach((c) => c.setAttribute('aria-pressed', 'false'));
        card.setAttribute('aria-pressed', 'true');
        scelto = card;
        aggiorna();
      };
      card.addEventListener('click', scegli);
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); scegli(); }
      });
      // A double click chooses and goes on: one gesture for who knows already.
      card.addEventListener('dblclick', () => { scegli(); document.getElementById('avanti').click(); });
    });

    document.getElementById('annulla').addEventListener('click', () => { location.href = ELENCO; });
    document.getElementById('avanti').addEventListener('click', () => {
      const v = valore();
      if (v) location.href = EDIT + '?tipo=' + encodeURIComponent(v);
    });

    /* Chi può creare vede la pagina; gli altri leggono perché no. La domanda la
     * fa il server con la stessa funzione che accetterà il salvataggio. */
    (function auth() {
      if (!window.meetooSession) { setTimeout(auth, 100); return; }
      document.getElementById('gate').style.display = 'block';
      meetooSession.subscribe((user) => {
        const msg = document.getElementById('gate-msg');
        if (!user) { msg.textContent = 'Accedi con Google (in alto a destra) per aggiungere un evento.'; return; }
        api('auth').then((r) => {
          if (r.status === 200 && r.body.canCreate) {
            document.getElementById('gate').style.display = 'none';
            document.getElementById('app').classList.add('on');
            if (window.Meetoo) {
              Meetoo.setBreadcrumb([
                { label: 'Gestione eventi', href: ELENCO },
                { label: 'Aggiungi un evento', current: true },
              ]);
            }
          } else {
            msg.textContent = 'Per pubblicare un evento devi gestire un gruppo su Meetoo. '
              + 'Il tuo account (' + (r.body.email || '') + ') non ne gestisce nessuno.';
          }
        });
      });
    })();
  })();
  </script>
<?php echo ws_admin_chrome_footer($ws_chrome); ?>
</body>
</html>
