/**
 * Quel poco che l'header, ora, chiede ancora al browser.
 *
 * Il grosso arriva già scritto dal server: logo, menu, briciole, azioni. Qui
 * restano i gesti — aprire il cassetto, condividere — e nient'altro. Se questo
 * file non arriva, la pagina resta leggibile e navigabile: il cassetto è un
 * <nav> con dentro dei collegamenti veri.
 */
(function () {
  var cassetto = document.getElementById('mt-drawer');
  var velo = document.getElementById('mt-drawer-ov');
  var apri = document.getElementById('mt-menu');
  var chiudi = document.getElementById('mt-drawer-close');

  function mostra(si) {
    if (!cassetto || !velo) return;
    // `hidden` va tolto PRIMA di aggiungere la classe, altrimenti la transizione
    // parte su un elemento che non è ancora visibile e non si vede niente.
    if (si) { cassetto.hidden = false; velo.hidden = false; }
    requestAnimationFrame(function () {
      cassetto.classList.toggle('open', si);
      velo.classList.toggle('open', si);
      if (apri) apri.setAttribute('aria-expanded', si ? 'true' : 'false');
      if (!si) setTimeout(function () { cassetto.hidden = true; velo.hidden = true; }, 250);
    });
  }

  if (apri) apri.addEventListener('click', function () { mostra(true); });
  if (chiudi) chiudi.addEventListener('click', function () { mostra(false); });
  if (velo) velo.addEventListener('click', function () { mostra(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') mostra(false); });

  // Condividi: il foglio di sistema dove c'è, la copia dell'indirizzo dove non c'è.
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-mt-condividi]');
    if (!b) return;
    var dati = { title: document.title, url: location.href };
    if (navigator.share) { navigator.share(dati).catch(function () {}); return; }
    if (navigator.clipboard) {
      navigator.clipboard.writeText(location.href).then(function () {
        b.title = 'Indirizzo copiato';
      });
    }
  });
})();
