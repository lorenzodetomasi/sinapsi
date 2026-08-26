/* Header Meetoo condiviso per le pagine tema (stile waterfront.html): due righe
 *   riga 1 = logo + azioni (info/legenda opzionali, Impostazioni, login/account)
 *   riga 2 = breadcrumb (impostato dalla pagina via Meetoo.setBreadcrumb)
 * Ingloba la sessione utente (login Google), la modale Impostazioni (Aspetto + preferenze
 * dell'utente loggato: lingua, notifiche) e le chiamate a rsvp.php.
 *
 * Uso nelle pagine:  <script src="header.js"></script>
 *   poi:  Meetoo.setBreadcrumb([{label,href,title}, {label, current:true}], [rightItems])
 * Espone: window.meetooSession (getUser/getToken/subscribe/api/logout/requireLogin/renderButton/prefs)
 *         window.Meetoo.setBreadcrumb/setActions/setNav/getTheme/setTheme/settingsSlot
 *
 * GLI STILI STANNO IN meetoo.css (token, componenti comuni e header): qui c'è solo
 * il comportamento. Se la pagina non ha già il <link>, questo file lo aggiunge.
 */
(function () {
  var CFG = window.MEETOO_HEADER || {};   // { noAuth?: true } → solo chrome (no login/GIS), per le pagine admin che gestiscono da sé l'auth
  var CLIENT_ID = '947742864411-rs99t8lkv5qcv4f5afb3pnhi0lkegbk3.apps.googleusercontent.com';
  // Radice del sito: risale sopra /ws-custom/ o /ws-admin/ (funziona da temi e da admin).
  /* ---- Dove si trova il sito, e dove stanno i contenuti --------------------
   * DICHIARATO, non indovinato. Finora si ricavava tagliando l'indirizzo su
   * `/ws-custom/` o `/ws-admin/`: funziona finché quei segmenti CI SONO. Con gli
   * URL puliti — meetoo.it/lido-di-ostia/eventi — non ci sono più, e la deduzione
   * darebbe l'intero percorso come radice, rompendo login, menu e caricamento dei
   * contenuti tutti insieme.
   * Quando il sito sarà servito dal CMS, i due valori li stamperà lui:
   *   <meta name="meetoo:site-root"    content="/">
   *   <meta name="meetoo:content-base" content="/ws-custom/contents/meetoo/it_IT/">
   * Se i meta non ci sono si ricade sulla deduzione di prima: nulla cambia oggi. */
  function metaPath(nome) {
    var m = document.querySelector('meta[name="' + nome + '"]');
    var v = m && m.getAttribute('content');
    return v ? v.replace(/\/?$/, '/') : '';
  }
  var SITE_ROOT = metaPath('meetoo:site-root')
    || location.pathname.replace(/\/(ws-custom|ws-admin)\/.*/, '/');
  /* La base dei contenuti: dichiarata, oppure ricavata dalla posizione del tema,
   * oppure — ultimo ripiego — sotto la radice del sito. `?base=` la scavalca
   * sempre: serve a provare una copia dei contenuti senza toccare le pagine. */
  function contentBase() {
    var q = new URLSearchParams(location.search).get('base');
    if (q) return q.replace(/\/?$/, '/');
    var dichiarata = metaPath('meetoo:content-base');
    if (dichiarata) return dichiarata;
    if (location.pathname.indexOf('/themes/') !== -1) {
      return location.pathname.replace(/\/themes\/.*/, '/contents/meetoo/it_IT/');
    }
    return SITE_ROOT + 'ws-custom/contents/meetoo/it_IT/';
  }
  var RSVP_URL  = SITE_ROOT + 'ws-admin/events/rsvp.php';
  var LOGO_URL  = SITE_ROOT + 'ws-custom/contents/meetoo/it_IT/brand/media/logo-h.svg';
  var THEME_DIR = SITE_ROOT + 'ws-custom/themes/meetoo/';
  // Navigazione principale (hamburger). La pagina può sovrascriverla con Meetoo.setNav(...).
  var NAV = [
    { label: 'Home', icon: 'home', href: THEME_DIR + 'index.html' },
    { label: 'Il Lungomare', icon: 'directions_boat', href: THEME_DIR + 'waterfront.html' },
    { label: 'Eventi', icon: 'event', href: THEME_DIR + 'index.html#eventi' },
    { label: 'Gruppi', icon: 'groups', href: THEME_DIR + 'index.html#gruppi' },
    { label: 'Luoghi', icon: 'place', href: THEME_DIR + 'index.html#luoghi' },
    { label: 'Iniziative letterarie', icon: 'menu_book', href: THEME_DIR + 'index.html#letterarie' },
  ];
  var TOKEN_KEY = 'meetoo_gid_token', THEME_KEY = 'meetoo:theme';
  /* Dove si tiene il token dell'accesso.
   * Stava in sessionStorage, che vive UNA SCHEDA: aprendo il sito in una scheda
   * nuova non risultavi più collegato e il menu perdeva la voce Amministrazione —
   * la stessa pagina sembrava comportarsi in due modi. Tema e preferiti stavano
   * già in localStorage, quindi l'incoerenza era anche fra le due memorie.
   * Prezzo dichiarato: un token in localStorage sopravvive alla chiusura del
   * browser ed è leggibile da qualunque script di questa origine. Resta comunque
   * un token Google a scadenza breve (~1h), verificato dal server a ogni
   * richiesta: non è una sessione, è una credenziale che invecchia da sola. */
  var store = {
    get: function () {
      try {
        var v = localStorage.getItem(TOKEN_KEY);
        if (v) return v;
        // Chi era collegato prima del cambio ha il token nella vecchia memoria:
        // si trasferisce, così non deve riaccedere.
        v = sessionStorage.getItem(TOKEN_KEY);
        if (v) { localStorage.setItem(TOKEN_KEY, v); sessionStorage.removeItem(TOKEN_KEY); }
        return v;
      } catch (e) { return null; }
    },
    set: function (v) { try { localStorage.setItem(TOKEN_KEY, v); } catch (e) {} },
    clear: function () { try { localStorage.removeItem(TOKEN_KEY); sessionStorage.removeItem(TOKEN_KEY); } catch (e) {} },
  };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]; }); };

  /* ============ Sessione (logica) ============ */
  var subs = [];
  var S = {
    user: null, token: null, prefs: {}, RSVP_URL: RSVP_URL,
    getUser: function () { return S.user; },
    getToken: function () { return S.token; },
    getPrefs: function () { return S.prefs || {}; },
    subscribe: function (cb) { subs.push(cb); try { cb(S.user, S.token); } catch (e) {} },
    requireLogin: function () { try { google.accounts.id.prompt(); } catch (e) {} },
    renderButton: function (el) { var g = win_gis(); if (g && el) { el.innerHTML = ''; g.renderButton(el, { type: 'standard', theme: 'outline', size: 'medium', text: 'signin', shape: 'pill' }); } },
    logout: function () {
      // Con la sessione PHP l'uscita è un fatto del server: il cookie lo può
      // cancellare solo lui. Qui si va all'indirizzo che lo fa, e si torna.
      if (CFG.sessione === 'php') { location.href = CFG.logoutUrl || (location.pathname + '?logout=1'); return; }
      S.user = null; S.token = null; S.prefs = {}; store.clear();
      try { google.accounts.id.disableAutoSelect(); } catch (e) {}
      notify();
    },
    api: function (action, fields) {
      var body = new URLSearchParams(Object.assign({ action: action }, fields || {}));
      if (S.token) body.set('credential', S.token);
      /* Sessione PHP: chi sei lo dice il cookie, che il browser manda da solo —
       * anche se a chiedere fosse un altro sito. Per questo va accompagnato da un
       * gettone che solo questa pagina conosce: senza, una pagina qualunque
       * potrebbe mettere «mi interessa» al posto tuo. */
      if (!S.token && CFG.csrf) body.set('csrf', CFG.csrf);
      return fetch(RSVP_URL, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
        .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }, function () { return { status: r.status, body: {} }; }); });
    },
  };
  window.meetooSession = S;
  function notify() { subs.forEach(function (cb) { try { cb(S.user, S.token); } catch (e) {} }); }
  function win_gis() { return window.google && google.accounts && google.accounts.id; }

  /* ============ CSS ============ */
  // Gli stili (token + componenti comuni + header) vivono in meetoo.css, condiviso
  // da tutte le pagine. Se la pagina non l'ha già incluso, lo aggiungiamo qui: così
  // basta l'header per avere l'aspetto Meetoo, anche sulle pagine admin.
  (function loadCss() {
    var href = THEME_DIR + 'meetoo.css';
    var has = Array.prototype.some.call(document.styleSheets, function (s) { return s.href && s.href.indexOf('meetoo.css') !== -1; })
      || document.querySelector('link[href*="meetoo.css"]');
    if (has) return;
    var l = document.createElement('link');
    l.rel = 'stylesheet'; l.href = href;
    document.head.appendChild(l);
  })();


  /* ============ Header HTML ============
   * Se l'header c'è già — lo serve il CMS, con dentro logo, briciole e voci di
   * menu che devono esistere anche senza JavaScript — lo si ADOTTA. Altrimenti lo
   * si costruisce, come per le pagine che il CMS non serve: l'archivio e l'editor
   * in ws-admin. Il markup è lo stesso nei due casi, ed è questo che rende lecita
   * l'adozione: gli `id` sono l'appiglio del comportamento, le classi quello degli
   * stili, e nessuno dei due cambia a seconda di chi scrive l'HTML. */
  var header = document.querySelector('header.mt-header');
  var servito = !!header;   // «servito»: l'ha scritto il server
  if (!servito) {
  header = document.createElement('header');
  header.className = 'mt-header';
  header.innerHTML =
    '<div class="mt-row mt-row-1">' +
      '<div class="mt-left"><button class="mt-icon-btn" id="mt-menu" title="Menu" aria-label="Menu"><span class="material-symbols-outlined">menu</span></button>' +
        '<a class="mt-brand" href="' + esc(THEME_DIR + 'index.html') + '" title="Home Meetoo"><img class="mt-logo" src="' + esc(LOGO_URL) + '" alt="Meetoo" onerror="this.replaceWith(Object.assign(document.createElement(\'b\'),{textContent:\'Meetoo\',style:\'font-family:Roboto Slab,serif;color:var(--mt-red);font-size:1.2rem\'}))"></a></div>' +
      '<div class="mt-actions"><span id="mt-slot"></span>' +
        '<button class="mt-icon-btn" id="mt-settings" title="Impostazioni"><span class="material-symbols-outlined">settings</span></button>' +
        '<span id="mt-account"></span></div>' +
    '</div>' +
    '<div class="mt-row mt-row-2"><div class="mt-crumbs" id="mt-crumbs"></div><div class="mt-admin" id="mt-admin"></div></div>';
  // Rimuove un eventuale vecchio #session-header e inserisce l'header in cima.
  var old = document.getElementById('session-header'); if (old) old.remove();
  document.body.insertBefore(header, document.body.firstChild);
  }
  /* La riga 2 (briciole) si vede solo se ha qualcosa da dire: la pagina la popola
   * con setBreadcrumb, oppure arriva già piena dal server. Le pagine che non ne
   * hanno — l'editor eventi, per dire — non si portano dietro una riga vuota. */
  (function () {
    var r = header.querySelector('.mt-row-2');
    if (r && !r.textContent.trim()) r.style.display = 'none';
  })();

  /* ============ Modale Impostazioni ============
   * Questa NON la scrive mai il server: non è contenuto, è un pannello di
   * comandi. A un motore di ricerca non serve, e a chi legge senza JavaScript
   * nemmeno — non ci sarebbe niente da comandare. */
  var modal = document.getElementById('mt-settings-modal');
  if (!modal) {
  modal = document.createElement('div');
  modal.className = 'mt-ov'; modal.id = 'mt-settings-modal';
  modal.innerHTML =
    '<div class="mt-modal"><button class="mt-modal-close" id="mt-set-close"><span class="material-symbols-outlined">close</span></button>' +
    '<div class="mt-modal-scroll"><h2 class="mt-modal-title">Impostazioni</h2>' +
      '<div id="mt-userblock"></div>' +
      '<div class="mt-set-group"><div class="mt-set-label">Aspetto</div><div class="mt-theme" id="mt-theme">' +
        '<button data-tm="auto"><span class="material-symbols-outlined">brightness_auto</span>Automatico</button>' +
        '<button data-tm="light"><span class="material-symbols-outlined">light_mode</span>Chiaro</button>' +
        '<button data-tm="dark"><span class="material-symbols-outlined">dark_mode</span>Scuro</button></div></div>' +
      '<div id="mt-prefs"></div>' +
      // Slot per le impostazioni PROPRIE della pagina (montate sotto quelle
      // dell'header): es. l'editor eventi vi porta Densità e Salvataggio su PC.
      '<div id="mt-page-settings" class="mt-page-settings"></div>' +
    '</div></div>';
  document.body.appendChild(modal);
  }
  modal.addEventListener('click', function (e) { if (e.target === modal) closeSettings(); });
  document.getElementById('mt-set-close').onclick = closeSettings;
  document.getElementById('mt-settings').onclick = openSettings;

  /* ============ Drawer (hamburger) ============ */
  var drawer = document.querySelector('.mt-drawer');
  var drawerOv = document.querySelector('.mt-drawer-ov');
  if (!drawer) {
  drawerOv = document.createElement('div'); drawerOv.className = 'mt-drawer-ov';
  drawer = document.createElement('nav'); drawer.className = 'mt-drawer';
  drawer.innerHTML = '<div class="mt-drawer-head"><a class="mt-brand" href="' + esc(THEME_DIR + 'index.html') + '"><img src="' + esc(LOGO_URL) + '" alt="Meetoo" onerror="this.replaceWith(Object.assign(document.createElement(\'b\'),{textContent:\'Meetoo\',style:\'font-family:Roboto Slab,serif;color:var(--mt-red);font-size:1.1rem\'}))"></a>' +
    '<button class="mt-icon-btn" id="mt-drawer-close" aria-label="Chiudi"><span class="material-symbols-outlined">close</span></button></div><div class="mt-nav" id="mt-nav"></div>';
  document.body.appendChild(drawerOv); document.body.appendChild(drawer);
  }
  function voceNav(it) {
    return '<a href="' + esc(it.href) + '"' + (it.target ? ' target="' + esc(it.target) + '"' : '') +
      (it.data ? ' data-nav="' + esc(it.data) + '"' : '') +
      '><span class="material-symbols-outlined">' + esc(it.icon || 'chevron_right') + '</span>' + esc(it.label) + '</a>';
  }
  function renderNav() {
    var box = document.getElementById('mt-nav');
    if (!box) return;
    /* Le voci servite dal server NON si riscrivono: sono collegamenti veri, li ha
     * già letti chi indicizza, e vengono dal menu del sito (nav1) invece che da
     * una lista dentro questo file. Qui si aggiunge solo ciò che dipende da CHI
     * sei, e che il server non può sapere in anticipo. */
    if (!servito) box.innerHTML = (NAV || []).map(voceNav).join('');
    // Amministrazione: solo per chi è collegato con un ruolo redazionale. Il menu
    // si ridisegna a ogni apertura, quindi la voce compare appena si accede — e
    // sparisce appena si esce.
    var voce = box.querySelector('[data-nav="admin"]');
    var puo = S.user && ['admin', 'super-admin'].indexOf(S.user.role) !== -1;
    if (puo && !voce) {
      box.insertAdjacentHTML('beforeend', voceNav({ label: 'Amministrazione', icon: 'admin_panel_settings', href: SITE_ROOT + 'ws-admin/index.php', data: 'admin' }));
    } else if (!puo && voce) {
      voce.remove();
    }
  }
  function openDrawer() { renderNav(); drawerOv.classList.add('open'); drawer.classList.add('open'); }
  function closeDrawer() { drawerOv.classList.remove('open'); drawer.classList.remove('open'); }
  document.getElementById('mt-menu').onclick = openDrawer;
  document.getElementById('mt-drawer-close').onclick = closeDrawer;
  drawerOv.onclick = closeDrawer;

  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeSettings(); closeDrawer(); } });
  function openSettings() { renderModal(); modal.classList.add('open'); }
  function closeSettings() { modal.classList.remove('open'); }

  /* ============ Tema (color-scheme) ============ */
  function setTheme(mode) {
    if (mode !== 'light' && mode !== 'dark') mode = 'auto';
    document.documentElement.style.colorScheme = mode === 'auto' ? 'light dark' : mode;
    // …e la scelta si scrive anche come ATTRIBUTO. I colori seguono già
    // `color-scheme` attraverso i token light-dark(), ma tutto ciò che NON è un
    // colore — l'immagine di uno sfondo, una maschera, un bordo disegnato — non
    // può leggerlo: light-dark() vale solo per i colori. Con data-theme una
    // pagina può scriverne una regola. (Con 'auto' l'attributo si toglie: comanda
    // la preferenza di sistema, e le @media la leggono da sé.)
    if (mode === 'auto') document.documentElement.removeAttribute('data-theme');
    else document.documentElement.setAttribute('data-theme', mode);
    try { localStorage.setItem(THEME_KEY, mode); } catch (e) {}
    document.querySelectorAll('#mt-theme button').forEach(function (b) { b.classList.toggle('active', b.dataset.tm === mode); });
    // Le pagine con un tema proprio (es. l'editor eventi) si agganciano qui:
    // 'auto' viene risolto col preferenza di sistema.
    var resolved = mode === 'auto'
      ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')
      : mode;
    try { document.dispatchEvent(new CustomEvent('meetoo:theme', { detail: { mode: mode, resolved: resolved } })); } catch (e) {}
  }
  (function initTheme() { var m = 'auto'; try { m = localStorage.getItem(THEME_KEY) || 'auto'; } catch (e) {} setTheme(m); })();

  function renderModal() {
    // Tema attivo
    var m = 'auto'; try { m = localStorage.getItem(THEME_KEY) || 'auto'; } catch (e) {}
    document.querySelectorAll('#mt-theme button').forEach(function (b) { b.onclick = function () { setTheme(b.dataset.tm); }; b.classList.toggle('active', b.dataset.tm === m); });
    // Utente + preferenze
    var ub = document.getElementById('mt-userblock'), pf = document.getElementById('mt-prefs');
    if (S.user) {
      ub.innerHTML = (S.user.picture ? '<img src="' + esc(S.user.picture) + '" alt="" referrerpolicy="no-referrer">' : '') +
        '<div><div class="nm">' + esc(S.user.name || S.user.email) + '</div><div class="em">' + esc(S.user.email || '') + '</div></div>' +
        '<button id="mt-logout">Esci</button>';
      ub.querySelector('#mt-logout').onclick = function () { S.logout(); closeSettings(); };
      var lang = (S.prefs && S.prefs.language) || 'it';
      var noti = !!(S.prefs && S.prefs.notifications);
      pf.innerHTML = '<div class="mt-set-group"><div class="mt-set-label">Preferenze</div>' +
        '<div class="mt-pref"><label for="mt-lang">Lingua</label><select id="mt-lang"><option value="it"' + (lang === 'it' ? ' selected' : '') + '>Italiano</option><option value="en"' + (lang === 'en' ? ' selected' : '') + '>English</option></select></div>' +
        '<div class="mt-pref"><label for="mt-noti">Notifiche</label><span class="sw"><input type="checkbox" id="mt-noti"' + (noti ? ' checked' : '') + '><span></span></span></div></div>';
      pf.querySelector('#mt-lang').onchange = function () { savePrefs({ language: this.value }); };
      pf.querySelector('#mt-noti').onchange = function () { savePrefs({ notifications: this.checked ? '1' : '0' }); };
    } else {
      ub.innerHTML = CFG.noAuth ? '' : '<div style="color:var(--mt-hint);font-size:.9rem">Accedi con Google per registrarti agli eventi e salvare le tue preferenze.</div>';
      pf.innerHTML = '';
    }
  }
  function savePrefs(p) { S.api('prefs', p).then(function (r) { if (r.status === 200 && r.body && r.body.prefs) S.prefs = r.body.prefs; }); }

  /* ============ Account (riga 1) ============ */
  function renderAccount() {
    var el = document.getElementById('mt-account');
    if (!el) return;
    /* Se in quel posto c'è GIÀ QUALCOSA, l'ha messo il server — che sa chi sei
     * prima ancora di mandare la pagina — e non si tocca.
     *
     * La guardia guarda il DOM, non la configurazione, e non è un dettaglio: se
     * guardasse solo `CFG.sessione` basterebbe che questo file e `functions.php`
     * arrivassero sul server in momenti diversi — uno nuovo e uno vecchio — perché
     * il pulsante di accesso venisse cancellato da qui e la pagina restasse senza
     * login. È successo. Adesso l'ordine di caricamento non conta più. */
    if (CFG.sessione === 'php' || el.children.length) return;
    if (CFG.noAuth) { el.innerHTML = ''; return; } // pagine admin: nessun login qui
    if (S.user) {
      el.innerHTML = S.user.picture
        ? '<img class="mt-avatar" src="' + esc(S.user.picture) + '" alt="" title="' + esc(S.user.name || '') + '" referrerpolicy="no-referrer">'
        : '<button class="mt-avatar-fallback" title="' + esc(S.user.name || '') + '">' + esc((S.user.name || S.user.email || '?').charAt(0).toUpperCase()) + '</button>';
      el.firstChild.onclick = openSettings; // clic sull'avatar → impostazioni/logout
    } else {
      el.innerHTML = '';
      S.renderButton(el);
    }
  }
  S.subscribe(renderAccount);

  /* ============ Breadcrumb (riga 2) ============ */
  function crumbHtml(items) {
    return (items || []).map(function (it, i) {
      var sep = i ? '<span class="sep">|</span>' : '';
      if (it.current) return sep + '<span class="c cur">' + esc(it.label) + '</span>';
      if (it.href) return sep + '<a class="c" href="' + esc(it.href) + '"' + (it.title ? ' title="' + esc(it.title) + '"' : '') + '>' + esc(it.label) + '</a>';
      return sep + '<span class="c">' + esc(it.label) + '</span>';
    }).join('');
  }
  // MERGE, non assegnazione: altri moduli (es. cards.js) possono aver già messo
  // le loro funzioni su window.Meetoo, e l'ordine degli script non deve contare.
  window.Meetoo = Object.assign(window.Meetoo || {}, {
    session: S,
    setBreadcrumb: function (items, adminItems) {
      var c = document.getElementById('mt-crumbs'); if (c) c.innerHTML = crumbHtml(items);
      var a = document.getElementById('mt-admin'); if (a) a.innerHTML = adminItems ? crumbHtml(adminItems) : '';
      var has = !!((c && c.textContent.trim()) || (a && a.textContent.trim()));
      var r = document.querySelector('.mt-header .mt-row-2'); if (r) r.style.display = has ? '' : 'none';
    },
    // Slot opzionale per bottoni info/legenda a sinistra delle azioni.
    siteRoot: function () { return SITE_ROOT; },
    contentBase: contentBase,
    setActions: function (html) { var s = document.getElementById('mt-slot'); if (s) s.innerHTML = html || ''; },
    // Voci del menu hamburger (default in NAV): [{label, icon, href, target?}].
    setNav: function (items) { if (Array.isArray(items) && items.length) NAV = items; },
    // Tema: 'auto' | 'light' | 'dark'. Le pagine con stili propri ascoltano
    // l'evento 'meetoo:theme' su document (detail: {mode, resolved}).
    getTheme: function () { try { return localStorage.getItem(THEME_KEY) || 'auto'; } catch (e) { return 'auto'; } },
    setTheme: setTheme,
    // Contenitore delle impostazioni proprie della pagina, dentro la modale
    // Impostazioni e SOTTO quelle dell'header (tema, preferenze).
    settingsSlot: function () { return document.getElementById('mt-page-settings'); },
  });

  /* ============ GIS init ============ */
  function init() {
    var g = win_gis();
    if (!g) { setTimeout(init, 150); return; }
    g.initialize({ client_id: CLIENT_ID, callback: onCredential, auto_select: true });
    var saved = store.get();
    if (saved) { S.token = saved; verify(true); }
    else { notify(); g.prompt(); }
  }
  function onCredential(resp) { var cred = resp && resp.credential; if (!cred) return; S.token = cred; store.set(cred); verify(false); }
  function verify(promptOnFail) {
    S.api('me').then(function (r) {
      if (r.status === 200 && r.body && r.body.uid) { S.user = r.body; S.prefs = r.body.prefs || {}; }
      else { S.user = null; S.token = null; store.clear(); if (promptOnFail) try { google.accounts.id.prompt(); } catch (e) {} }
      notify();
    }).catch(function () { notify(); });
  }
  /* Chi sei, e chi lo dice.
   *
   * `sessione: 'php'` — la strada del CMS — vuol dire che l'ha già deciso il
   * server: l'utente arriva scritto nella pagina, e qui non si chiede niente a
   * Google. È la differenza che conta, perché il token di Google dura un'ora: era
   * quello a far ricomparire la richiesta di accesso di continuo, non un difetto
   * di questo file. Una sessione, invece, dura quanto il suo cookie.
   *
   * Senza quella dichiarazione si resta alla strada di prima: token in memoria,
   * verificato a ogni caricamento. La usano l'archivio e le pagine di ws-admin. */
  /* La sessione PHP la dichiara `functions.php`. Se la dichiarazione non è
   * arrivata ma nella pagina c'è il plugin di accesso (`#g_id_onload`), vuol dire
   * lo stesso che l'accesso lo governa il server: chiedere anche noi un token a
   * Google, in quel caso, vuol dire due login che si contendono lo stesso posto. */
  if (CFG.sessione !== 'php' && document.getElementById('g_id_onload')) {
    CFG.sessione = 'php';
  }
  if (CFG.sessione === 'php') {
    S.user = CFG.utente || null;
    S.prefs = (CFG.utente && CFG.utente.prefs) || {};
    notify();
  }
  else if (CFG.noAuth) { renderAccount(); }
  else if (!win_gis()) { var s = document.createElement('script'); s.src = 'https://accounts.google.com/gsi/client'; s.async = true; s.defer = true; s.onload = init; document.head.appendChild(s); renderAccount(); }
  else init();
})();
