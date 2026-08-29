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
