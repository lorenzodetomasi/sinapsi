/**
 * Le liste lunghe, un pezzo per volta.
 *
 * La pagina arriva dal server già con le prime card di ogni sezione: quelle
 * bastano a riempire lo schermo, e sono quelle che legge un motore di ricerca.
 * Le altre si chiedono mentre si scorre, alla stessa pagina — `?parte=luoghi&da=8`
 * risponde con le card successive, scritte dallo stesso codice PHP che ha scritto
 * le prime — e si incollano al posto del segnaposto.
 *
 * Se questo file non arriva, o l'osservatore non c'è, non si perde niente: il
 * segnaposto è un collegamento vero a `?tutti=luoghi`, che stampa la sezione per
 * intero.
 */
(function () {
  'use strict';

  // Le sezioni che qualcuno ha aperto a mano: da lì in poi si comportano come le
  // altre.
  var chiesti = {};

  /* Ogni segnaposto si carica una volta sola: senza questa guardia
   * l'osservatore, che scatta anche mentre l'elenco cresce, chiederebbe lo stesso
   * pezzo due volte e le card comparirebbero doppie. */
  function carica(segnaposto) {
    if (!segnaposto || segnaposto.dataset.inCorso) return;
    chiesti[segnaposto.dataset.parte] = true;
    segnaposto.dataset.inCorso = '1';
    segnaposto.classList.add('caricando');

    var url = location.pathname +
      '?parte=' + encodeURIComponent(segnaposto.dataset.parte || '') +
      '&da=' + encodeURIComponent(segnaposto.dataset.da || '0');

    fetch(url, { headers: { Accept: 'text/html' } })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(new Error(r.status)); })
      .then(function (html) {
        segnaposto.insertAdjacentHTML('afterend', html);
        segnaposto.remove();
        // Aperto una volta, l'archivio continua da solo come gli altri elenchi:
        // chi l'ha chiesto lo sta leggendo.
        var seguente = document.querySelector('.mt-altri[data-manuale]');
        if (seguente && chiesti[seguente.dataset.parte]) seguente.removeAttribute('data-manuale');
        preferiti();
        osserva();
      })
      .catch(function () {
        // Rimane il collegamento: chi vuole vedere il resto ci arriva lo stesso.
        segnaposto.dataset.inCorso = '';
        segnaposto.classList.remove('caricando');
      });
  }

  var osservatore = ('IntersectionObserver' in window)
    ? new IntersectionObserver(function (voci) {
        voci.forEach(function (v) {
          if (!v.isIntersecting) return;
          osservatore.unobserve(v.target);
          carica(v.target);
        });
      }, { rootMargin: '600px 0px' })   // in anticipo: le card arrivano prima del vuoto
    : null;

  function osserva() {
    if (!osservatore) return;
    /* `data-manuale` = si carica solo se qualcuno lo chiede. È l'archivio degli
     * eventi passati: chi apre una pagina vuole sapere che cosa succede, non che
     * cosa è successo, e scaricarglielo comunque sarebbe la parte più pesante
     * della pagina per la meno guardata. Il click funziona come per gli altri. */
    var nodi = document.querySelectorAll('.mt-altri:not([data-visto]):not([data-manuale])');
    Array.prototype.forEach.call(nodi, function (s) {
      s.dataset.visto = '1';
      osservatore.observe(s);
    });
  }

  // Il click sul «mostra altri» carica qui, senza rifare la pagina. Senza
  // JavaScript lo stesso collegamento porta alla pagina con tutto stampato.
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('.mt-altri a');
    if (!a) return;
    e.preventDefault();
    carica(a.closest('.mt-altri'));
  });

  /* I luoghi già segnati stanno nel browser di chi guarda (stessa chiave del
   * lungomare), quindi il server non può sapere quali cuori sono accesi: si
   * accendono qui, appena la pagina è in piedi e a ogni pezzo che arriva. */
  function preferiti() {
    var segnati;
    try { segnati = JSON.parse(localStorage.getItem('meetoo:favorites') || '[]'); } catch (err) { segnati = []; }
    if (!segnati.length) return;
    var box = document.querySelectorAll('.card-social[data-social-kind="place"]');
    Array.prototype.forEach.call(box, function (b) {
      var cuore = b.querySelector('.fav');
      if (cuore) cuore.classList.toggle('on', segnati.indexOf(b.getAttribute('data-social-id')) !== -1);
    });
  }

  preferiti();
  osserva();
})();
