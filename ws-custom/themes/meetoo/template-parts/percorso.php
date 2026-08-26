<?php
/**
 * Il guscio della LINEA: il carosello delle fermate e le sue finestre.
 *
 * È il markup che stava dentro `waterfront.html` — carosello, modale della
 * fermata, legenda, richiesta di inserimento — spostato qui perché adesso la
 * pagina la serve il CMS. Il contenuto vero (le fermate) lo disegna
 * `js/lungomare.js` dentro `#carousel`; qui c'è solo dove metterlo.
 *
 * Quale raccolta disegnare lo dice `data-raccolta`: la linea non è del lungomare
 * di Ostia, è di qualunque percorso le si dia.
 */
global $raccolta;
?>
  <div id="app-loader"><span class="material-symbols-outlined spin">sync</span><div>Caricamento del lungomare…</div></div>


  <div class="carousel-container" id="carousel" data-raccolta="<?php echo mt_esc($raccolta); ?>">
    <div id="loader">
      <span class="material-symbols-outlined" style="font-size: 40px; animation: spin 2s linear infinite;">sync</span>
      Caricamento in corso...
    </div>
  </div>

  <div id="place-modal" class="modal-overlay" onclick="if(event.target===this) closePlaceModal()">
    <div class="modal" role="dialog" aria-modal="true">
      <button class="modal-close" title="Chiudi" onclick="closePlaceModal()"><span class="material-symbols-outlined">close</span></button>
      <div class="modal-scroll"><div id="place-modal-body"></div></div>
      <div class="scroll-hint"><span class="material-symbols-outlined">keyboard_arrow_down</span></div>
    </div>
  </div>

  <div id="legend-modal" class="modal-overlay" onclick="if(event.target===this) closeLegend()">
    <div class="modal" role="dialog" aria-modal="true">
      <button class="modal-close" title="Chiudi" onclick="closeLegend()"><span class="material-symbols-outlined">close</span></button>
      <div class="modal-scroll">
      <h2 class="modal-title">Tipologie di spiaggia</h2>
      <div class="modal-sub" style="text-transform:none">Classificazione di Roma Capitale</div>
      <div class="legend-list" id="legend-list">
        <div class="legend-row"><span class="legend-swatch tip-libera"><span class="material-symbols-outlined">beach_access</span></span><div><strong>Spiaggia libera</strong><span>Arenile accessibile gratuitamente a tutti, senza servizi aggiuntivi.</span></div></div>
        <div class="legend-row"><span class="legend-swatch tip-attrezzata"><span class="material-symbols-outlined">deck</span></span><div><strong>Spiaggia libera attrezzata</strong><span>Accesso gratuito con servizi facoltativi a pagamento (chioschi, docce, wc, spogliatoi).</span></div></div>
        <div class="legend-row"><span class="legend-swatch tip-concessione"><span class="material-symbols-outlined">holiday_village</span></span><div><strong>Stabilimento balneare (in concessione)</strong><span>Arenile dato in concessione a privati o associazioni, con servizi a pagamento.</span></div></div>
        <div class="legend-row"><span class="legend-swatch tip-riservato"><span class="material-symbols-outlined">block</span></span><div><strong>Accesso riservato</strong><span>Riservata a categorie specifiche (forze armate, circoli privati).</span></div></div>
        <div class="legend-row"><span class="legend-swatch tip-dog"><span class="material-symbols-outlined">pets</span></span><div><strong>Spiaggia per cani — BauBeach</strong><span>Aree, libere o attrezzate, dove è consentito l'accesso ai cani.</span></div></div>
        <div class="legend-row"><span class="legend-swatch status-chiusa"><span class="material-symbols-outlined">dangerous</span></span><div><strong>Area chiusa</strong><span> per questioni legali o altri motivi.</span></div></div>
      </div>
      <div class="legend-note">La classificazione può variare di anno in anno in base alle ordinanze del Comune di Roma.</div>
      </div>
      <div class="scroll-hint"><span class="material-symbols-outlined">keyboard_arrow_down</span></div>
    </div>
  </div>

  <!-- FAB: richiedi l'inserimento di un place / attività -->
  <button id="add-place-fab" class="fab" title="Richiedi l'inserimento di un luogo o attività"><span class="material-symbols-outlined">add_location</span></button>

  <!-- Modale: servizio (a pagamento) di gestione della presenza sulle mappe + form contatti -->
  <div id="contact-modal" class="modal-overlay" onclick="if(event.target===this) closeContact()">
    <div class="modal" role="dialog" aria-modal="true">
      <button class="modal-close" title="Chiudi" onclick="closeContact()"><span class="material-symbols-outlined">close</span></button>
      <div class="modal-scroll">
        <h2 class="modal-title">Sei sulla mappa di MeeToo?</h2>
        <p class="contact-intro">Gestiamo per te la presenza del tuo stabilimento, locale o punto d'interesse sulle mappe online: scheda curata, foto, servizi, orari e recensioni sempre aggiornati.</p>
        <div class="contact-highlight"><span class="material-symbols-outlined" style="vertical-align:middle;color:var(--color1)">verified</span> Servizio di gestione <b>a pagamento</b>. Compila il form: ti ricontattano i super-admin di MeeToo.</div>
        <form class="contact-form" id="contact-form" onsubmit="return submitContact(event)">
          <label>Nome e cognome
            <input type="text" name="nome" required autocomplete="name">
          </label>
          <label>Email
            <input type="email" name="email" required autocomplete="email">
          </label>
          <label>Cosa vuoi inserire
            <select name="tipo">
              <option value="Stabilimento / luogo (place)">Stabilimento / luogo</option>
              <option value="Attività / esercizio (localbusiness)">Attività / esercizio commerciale</option>
            </select>
          </label>
          <label>Nome del luogo / attività
            <input type="text" name="luogo" required>
          </label>
          <label>Messaggio (facoltativo)
            <textarea name="messaggio" placeholder="Indirizzo, sito web, social, orari…"></textarea>
          </label>
          <button type="submit" class="btn contact-submit"><span class="material-symbols-outlined">mail</span> Invia richiesta</button>
        </form>
      </div>
      <div class="scroll-hint"><span class="material-symbols-outlined">keyboard_arrow_down</span></div>
    </div>
  </div>
