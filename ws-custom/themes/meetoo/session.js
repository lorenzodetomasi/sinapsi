/* Sessione utente condivisa per le pagine tema (login Google + header).
 * Incluso in organizer/collection/event.html:  <div id="session-header"></div><script src="session.js"></script>
 * Espone window.meetooSession: { user, token, getUser(), getToken(), subscribe(cb), requireLogin(), logout(), RSVP_URL, api(action, fields) }.
 * Login via Google Identity Services; verifica lato server (rsvp.php action=me). */
(function () {
  var CLIENT_ID = '947742864411-rs99t8lkv5qcv4f5afb3pnhi0lkegbk3.apps.googleusercontent.com';
  var SITE_ROOT = location.pathname.replace(/\/ws-custom\/.*/, '/');
  var RSVP_URL = SITE_ROOT + 'ws-admin/events/rsvp.php';
  var TOKEN_KEY = 'meetoo_gid_token';

  var S = {
    user: null, token: null, RSVP_URL: RSVP_URL,
    getUser: function () { return S.user; },
    getToken: function () { return S.token; },
    subscribe: function (cb) { subs.push(cb); if (S.user !== undefined) cb(S.user, S.token); },
    requireLogin: function () { try { google.accounts.id.prompt(); } catch (e) {} },
    logout: function () {
      S.user = null; S.token = null;
      try { sessionStorage.removeItem(TOKEN_KEY); } catch (e) {}
      try { google.accounts.id.disableAutoSelect(); } catch (e) {}
      render(); notify();
    },
    // POST a rsvp.php col token corrente (se presente).
    api: function (action, fields) {
      var body = new URLSearchParams(Object.assign({ action: action }, fields || {}));
      if (S.token) body.set('credential', S.token);
      return fetch(RSVP_URL, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
        .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); });
    },
  };
  window.meetooSession = S;
  var subs = [];
  function notify() { subs.forEach(function (cb) { try { cb(S.user, S.token); } catch (e) {} }); }

  // --- CSS minimale iniettato ---
  var css = document.createElement('style');
  css.textContent =
    '.mt-session{font-family:"Roboto",system-ui,sans-serif;display:flex;justify-content:flex-end;align-items:center;gap:10px;' +
    'max-width:880px;margin:0 auto;padding:8px 16px;min-height:44px;color:light-dark(#5f5c66,#b9b3c4);font-size:.9rem}' +
    '.mt-user{display:inline-flex;align-items:center;gap:8px}' +
    '.mt-user img{width:28px;height:28px;border-radius:50%;object-fit:cover}' +
    '.mt-user b{color:light-dark(#1d1b20,#e6e0e9);font-weight:600}' +
    '.mt-logout{background:none;border:1px solid light-dark(#e0e0e6,#48454e);border-radius:999px;color:inherit;' +
    'padding:3px 12px;font:inherit;cursor:pointer}.mt-logout:hover{border-color:light-dark(#2e3192,#adc6ff)}';
  document.head.appendChild(css);

  function container() {
    var c = document.getElementById('session-header');
    if (!c) { c = document.createElement('div'); c.id = 'session-header'; document.body.insertBefore(c, document.body.firstChild); }
    return c;
  }

  var btnHolder = null;
  function render() {
    var c = container();
    c.innerHTML = '<div class="mt-session"></div>';
    var bar = c.firstChild;
    if (S.user) {
      var u = document.createElement('div');
      u.className = 'mt-user';
      u.innerHTML = (S.user.picture ? '<img src="' + esc(S.user.picture) + '" alt="" referrerpolicy="no-referrer">' : '') +
        '<span>Ciao, <b>' + esc(S.user.name || S.user.email) + '</b></span>';
      var out = document.createElement('button'); out.className = 'mt-logout'; out.textContent = 'Esci';
      out.onclick = S.logout;
      bar.appendChild(u); bar.appendChild(out);
    } else {
      btnHolder = document.createElement('div');
      bar.appendChild(btnHolder);
      renderGoogleButton();
    }
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]; }); }

  function onCredential(resp) {
    var cred = resp && resp.credential; if (!cred) return;
    S.token = cred;
    try { sessionStorage.setItem(TOKEN_KEY, cred); } catch (e) {}
    S.api('me').then(function (r) {
      if (r.status === 200 && r.body && r.body.uid) { S.user = r.body; }
      else { S.user = null; S.token = null; try { sessionStorage.removeItem(TOKEN_KEY); } catch (e) {} }
      render(); notify();
    }).catch(function () { render(); notify(); });
  }

  function renderGoogleButton() {
    var g = window.google && google.accounts && google.accounts.id;
    if (!g) { setTimeout(renderGoogleButton, 150); return; }
    if (btnHolder) { btnHolder.innerHTML = ''; g.renderButton(btnHolder, { type: 'standard', theme: 'outline', size: 'medium', text: 'signin', shape: 'pill' }); }
  }

  function init() {
    var g = window.google && google.accounts && google.accounts.id;
    if (!g) { setTimeout(init, 150); return; }
    g.initialize({ client_id: CLIENT_ID, callback: onCredential, auto_select: true });
    // Prova a ripristinare la sessione da un token in sessionStorage; altrimenti mostra il login (+ One Tap).
    var saved = null; try { saved = sessionStorage.getItem(TOKEN_KEY); } catch (e) {}
    if (saved) {
      S.token = saved;
      S.api('me').then(function (r) {
        if (r.status === 200 && r.body && r.body.uid) { S.user = r.body; render(); notify(); }
        else { S.token = null; try { sessionStorage.removeItem(TOKEN_KEY); } catch (e) {} S.user = null; render(); notify(); g.prompt(); }
      }).catch(function () { S.user = null; render(); notify(); });
    } else {
      S.user = null; render(); notify();
      g.prompt(); // One Tap per i ritorni (auto_select)
    }
  }

  // Carica lo script GIS se non presente, poi init.
  if (!(window.google && google.accounts && google.accounts.id)) {
    var s = document.createElement('script'); s.src = 'https://accounts.google.com/gsi/client'; s.async = true; s.defer = true;
    s.onload = init; document.head.appendChild(s);
    render(); // intanto mostra il contenitore (vuoto)
  } else { init(); }
})();
