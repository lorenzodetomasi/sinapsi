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
      valutazioni(d);
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

  /* LE STELLE.
   *
   * Compaiono a evento finito. Le medie le vede chiunque — sono il racconto di
   * chi c'è stato, ed è un'informazione per chi deve ancora decidere se andare a
   * un'altra data —, ma votare può solo chi era iscritto: se il server dice di
   * no, le stelle si mostrano spente e non si toccano.
   *
   * Cliccando la stella che si è già data, il voto si ritira: ci si può
   * ripensare, e ritirare un giudizio dev'essere facile quanto darlo.
   */
  var statoVoti = null;
  var statoValuta = null;
  function valutazioni(d) {
    statoValuta = d;
    var sez = document.getElementById('mt-valuta');
    if (!sez) return;
    var bersagli;
    try { bersagli = JSON.parse(sez.getAttribute('data-bersagli') || '[]'); } catch (e) { return; }
    if (!bersagli.length || !d.past) return;
    var medie = d.ratings || {};
    /* Le mie risposte stanno in un oggetto solo per tutta la vita della pagina:
     * gli ascoltatori sono attaccati una volta e vedono sempre questo, non la
     * copia del disegno in cui erano nati. */
    if (!statoVoti) statoVoti = d.myRatings || {};
    else if (d.myRatings) { Object.keys(statoVoti).forEach(function (k) { delete statoVoti[k]; }); Object.assign(statoVoti, d.myRatings); }
    var miei = statoVoti;
    var puo = !!d.canRate;
    // Senza il diritto di votare e senza nemmeno un voto altrui non c'è niente
    // da dire: la sezione resta chiusa invece di mostrare cinque stelle vuote.
    if (!puo && !Object.keys(medie).length) return;

    function stelle(t) {
      var mio = (miei[t.id] || {}).value || 0;
      var media = medie[t.id];
      var s = '';
      for (var i = 1; i <= 5; i++) {
        s += '<button type="button" class="mt-stella' + (i <= mio ? ' on' : '') + '"' +
          (puo ? '' : ' disabled') + ' data-voto="' + i + '" aria-label="' + i + ' su 5">' +
          '<span class="material-symbols-outlined">star</span></button>';
      }
      /* La recensione compare DOPO il voto, e solo a chi il voto l'ha dato.
       * Prima sarebbe un campo di testo davanti a una domanda che non è ancora
       * stata fatta; dopo è la cosa che uno ha già in mente. */
      var mieTesto = (miei[t.id] || {}).text || '';
      var scritte = (d.reviews || {})[t.id] || [];
      var commento = (puo && mio)
        ? '<div class="mt-v-commento">' +
            '<textarea rows="2" maxlength="600" placeholder="Due righe, se ti va: com\'è andata?">' + esc(mieTesto) + '</textarea>' +
            '<button type="button" class="mt-v-salva">Salva</button>' +
          '</div>'
        : '';
      var altrui = scritte.length
        ? '<ul class="mt-v-scritte">' + scritte.slice(0, 5).map(function (x) {
            return '<li><span class="mt-v-voto">' + esc(x.value) + '★</span>' + esc(x.text) + '</li>';
          }).join('') + '</ul>'
        : '';
      return '<li data-bersaglio="' + esc(t.id) + '">' +
        '<span class="mt-v-tipo">' + esc(t.tipo) + '</span>' +
        '<span class="mt-v-nome">' + esc(t.nome) + '</span>' +
        '<span class="mt-stelle">' + s + '</span>' +
        '<span class="mt-v-media">' + (media ? esc(media.value + ' (' + media.count + ')') : '') + '</span>' +
        commento + altrui +
        '</li>';
    }

    sez.innerHTML =
      '<h2 class="sec-head"><span class="material-symbols-outlined">star</span>Com\'è andata' + '</h2>' +
      '<p class="mt-nota">' + (puo
        ? 'Eri fra gli iscritti: la tua voce vale. Tocca una stella per votare, tocca la stessa per ritirare il voto.'
        : 'Le valutazioni di chi c\'era.') + '</p>' +
      '<ul class="mt-valuta-elenco">' + bersagli.map(stelle).join('') + '</ul>';
    sez.hidden = false;

    if (!puo) return;
    /* Gli ascoltatori si attaccano UNA volta sola: la sezione si ridisegna a ogni
     * voto, e riattaccarli a ogni giro vorrebbe dire mandare due richieste al
     * secondo clic, tre al terzo. (Stanno sulla sezione e non sui bottoni proprio
     * perché i bottoni cambiano: la sezione no.) */
    if (sez.dataset.legato === '1') return;
    sez.dataset.legato = '1';
    sez.addEventListener('click', function (ev) {
      var st = ev.target.closest && ev.target.closest('.mt-stella');
      if (!st || st.disabled) return;
      var riga = st.closest('li');
      var target = riga.getAttribute('data-bersaglio');
      var voto = parseInt(st.getAttribute('data-voto'), 10);
      // La stessa stella una seconda volta = ritiro il voto.
      if (((miei[target] || {}).value || 0) === voto) voto = 0;
      manda(riga, target, voto, null);
    });

    // Il commento si salva a parte: cambiare idea sulle stelle non deve
    // cancellare quello che si era scritto, e viceversa.
    sez.addEventListener('click', function (ev) {
      var b = ev.target.closest && ev.target.closest('.mt-v-salva');
      if (!b) return;
      var riga = b.closest('li');
      var target = riga.getAttribute('data-bersaglio');
      var ta = riga.querySelector('textarea');
      manda(riga, target, (miei[target] || {}).value || 0, ta ? ta.value : '');
    });

    function manda(riga, target, voto, testo) {
      var campi = { path: evento, target: target, value: String(voto) };
      if (testo !== null) campi.text = testo;
      S.api('rate', campi).then(function (r) {
        var b = r.body || {};
        if (r.status !== 200 || !b.success) { dillo(b.error || 'Non ha funzionato.', 'avviso'); return; }
        dillo(testo !== null ? 'Grazie: la tua recensione è salvata.' : '', 'ok');
        miei[target] = voto ? { value: voto, text: testo !== null ? testo : ((miei[target] || {}).text || '') } : null;
        if (!voto) delete miei[target];
        Array.prototype.forEach.call(riga.querySelectorAll('.mt-stella'), function (x, i) {
          x.classList.toggle('on', i + 1 <= voto);
        });
        var m = (b.ratings || {})[target];
        var box = riga.querySelector('.mt-v-media');
        if (box) box.textContent = m ? m.value + ' (' + m.count + ')' : '';
        // Il campo del commento compare col primo voto e sparisce se lo si ritira:
        // si ridisegna la sezione con quello che il server ha appena risposto.
        statoValuta.ratings = b.ratings || statoValuta.ratings;
        statoValuta.reviews = b.reviews || statoValuta.reviews;
        statoValuta.myRatings = null;   // le mie risposte le tiene `statoVoti`
        valutazioni(statoValuta);
      });
    }
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
