/**
 * Le azioni della riga contestuale.
 *
 * Qui è rimasto solo «condividi»: il resto dell'header — cassetto, impostazioni,
 * tema, account — torna a essere lavoro di `header.js`, che quel comportamento
 * ce l'ha già scritto e collaudato. Per un po' era finito qui una seconda volta,
 * in una versione più povera, e si vedeva: il cassetto si apriva senza vestito e
 * le impostazioni non aprivano niente.
 *
 * Se questo file non arriva, la pagina resta leggibile e navigabile.
 */
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-mt-condividi]');
    if (!b) return;
    var dati = { title: document.title, url: location.href };
    if (navigator.share) { navigator.share(dati).catch(function () {}); return; }
    // Dove il foglio di sistema non c'è, si copia l'indirizzo e lo si dice —
    // `Meetoo.toast` arriva da cards.js, che è già in pagina.
    if (navigator.clipboard) {
      navigator.clipboard.writeText(location.href).then(function () {
        if (window.Meetoo && Meetoo.toast) Meetoo.toast('Link copiato negli appunti');
      });
    }
  });
})();
