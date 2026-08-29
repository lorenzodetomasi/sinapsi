/* Le tre cose che si possono fare su un evento.
 *
 * «Mi interessa» è un segnaposto: voglio ritrovarlo, forse ci vado.
 * «Mi piace» è un giudizio, e vale anche per un evento già passato.
 * «Parteciperò» è un impegno, e conta: consuma un posto.
 *
 * Sono tre domande diverse e il server le tiene separate — non è la stessa
 * spunta con tre nomi. I bottoni li scrive il PHP e stanno nella pagina anche
 * senza JavaScript: qui si aggiunge quello che senza non si può avere, cioè
 * ricordarsi la risposta.
 */
(function () {
  var box = document.querySelector('.mt-azioni');
  if (!box) return;
  var nota = document.querySelector('.mt-azioni-nota');
  var evento = box.getAttribute('data-evento') || '';
  var bottoni = {};
  Array.prototype.forEach.call(box.querySelectorAll('[data-azione]'), function (b) {
    bottoni[b.getAttribute('data-azione')] = b;
  });

  function dillo(msg, tipo) {
    if (!nota) return;
    nota.textContent = msg || '';
    nota.className = 'mt-azioni-nota' + (tipo ? ' ' + tipo : '');
    nota.hidden = !msg;
  }

  function segna(b, acceso, conto) {
    if (!b) return;
    b.classList.toggle('on', !!acceso);
    b.setAttribute('aria-pressed', acceso ? 'true' : 'false');
    var c = b.querySelector('.mt-conto');
    if (c) c.textContent = conto > 0 ? String(conto) : '';
  }

  var S = window.meetooSession;

  /* Lo stato di partenza: quante persone hanno risposto, e che cosa ho risposto
   * io. Si chiede una volta sola, all'apertura — chi non è collegato riceve
   * comunque i conteggi, che sono un'informazione pubblica. */
  function leggi() {
    if (!S) return;
    S.api('status', { path: evento }).then(function (r) {
      if (r.status !== 200 || !r.body) return;
      var d = r.body;
      segna(bottoni.interesse, d.liked, d.likes);
      segna(bottoni.piace, d.loved, d.loves);
      // Chi organizza vede anche chi ha detto che verrà.
      if (d.isAdmin) partecipanti();
      if (bottoni.iscrizione) {
        segna(bottoni.iscrizione, d.registered, d.count);
        var cap = d.capacity || {};
        if (!d.registered && cap.full) {
          bottoni.iscrizione.disabled = true;
          dillo('I posti sono esauriti.', 'avviso');
        }
      }
    });
  }

  /* L'ELENCO DEI PARTECIPANTI, per chi organizza.
   *
   * Il permesso lo decide il server: qui si chiede e basta, e se la risposta è
   * «no» la sezione resta chiusa. Non c'è niente da nascondere lato browser,
   * perché non c'è niente da mostrare finché il server non lo manda.
   *
   * Nomi ed email non stanno nel file dell'evento — quello lo serve il web — ma
   * nell'archivio privato: chi ha diritto di vederli li riceve ricomposti. */
  function partecipanti() {
    var sez = document.getElementById('mt-partecipanti');
    if (!sez || !S) return;
    S.api('participants', { path: evento }).then(function (r) {
      if (r.status !== 200 || !r.body || !r.body.success) return;
      var d = r.body;
      var righe = (d.participants || []).map(function (p) {
        var quando = p.date ? new Date(p.date).toLocaleDateString('it-IT', { day: 'numeric', month: 'short' }) : '';
        return '<li><span class="mt-p-nome">' + esc(p.name || '—') + '</span>' +
          (p.email ? '<a class="mt-p-mail" href="mailto:' + esc(p.email) + '">' + esc(p.email) + '</a>' : '') +
          '<span class="mt-p-nota">' + esc(p.mode === 'online' ? 'da remoto' : 'in presenza') +
          (quando ? ' · ' + esc(quando) : '') + '</span></li>';
      }).join('');
      sez.innerHTML =
        '<h2 class="sec-head"><span class="material-symbols-outlined">groups</span>Partecipanti' +
        '<span class="count">' + (d.count || 0) + '</span></h2>' +
        (d.newCount ? '<p class="mt-nota">' + d.newCount + ' dall’ultima volta che hai guardato.</p>' : '') +
        (righe ? '<ul class="mt-partecipanti-elenco">' + righe + '</ul>'
               : '<div class="empty">Ancora nessuno.</div>') +
        '<p class="mt-nota"><label><input type="checkbox" id="mt-avvisami"' +
        (d.notifyEnabled ? ' checked' : '') + '> Avvisami quando qualcuno si iscrive</label></p>';
      sez.hidden = false;
      var av = document.getElementById('mt-avvisami');
      if (av) av.addEventListener('change', function () {
        S.api('notify', { path: evento, enabled: av.checked ? '1' : '0' });
      });
    });
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
    });
  }

  function chiedi(azione, b) {
    if (!S) return;
    if (!S.getUser()) {
      dillo('Accedi con Google (in alto a destra) per rispondere.', 'avviso');
      S.requireLogin();
      return;
    }
    b.disabled = true;
    var acceso = b.getAttribute('aria-pressed') === 'true';
    var chiamata = azione === 'iscrizione'
      // La modalità la decide l'evento, non chi si iscrive: il server la valida
      // e risponde con quella buona.
      ? S.api(acceso ? 'unregister' : 'register', { path: evento, mode: 'offline' })
      : S.api('like', { path: evento, kind: azione });
    chiamata.then(function (r) {
      b.disabled = false;
      var d = r.body || {};
      if (r.status !== 200 || !d.success) {
        dillo(d.error || 'Non ha funzionato: riprova fra un momento.', 'avviso');
        return;
      }
      dillo('');
      if (azione === 'iscrizione') {
        var cap = d.capacity || {};
        segna(b, d.registered, cap.registered);
        var sez = document.getElementById('mt-partecipanti');
        if (sez && !sez.hidden) partecipanti();
      } else {
        segna(b, d.on, d.count);
      }
    });
  }

  box.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-azione]');
    if (!b) return;
    var azione = b.getAttribute('data-azione');
    if (azione === 'condividi') {
      var dati = { title: document.title, url: location.href };
      if (navigator.share) { navigator.share(dati).catch(function () {}); return; }
      if (navigator.clipboard) {
        navigator.clipboard.writeText(location.href).then(function () {
          dillo('Indirizzo copiato.', 'ok');
        });
      }
      return;
    }
    chiedi(azione, b);
  });

  // `subscribe` chiama subito e a ogni cambio: entrando o uscendo, i bottoni
  // dicono la verità senza ricaricare la pagina.
  if (S) S.subscribe(leggi);
})();
