/* ===========================================================================
 * consent.js - the statistics of visits (Google Analytics) only with consent.
 *
 * Italian rules (Garante, guidelines of 10 June 2021) want the consent BEFORE
 * an analytics cookie is written: so gtag.js is not in the page at all until
 * whoever looks says yes. The server writes, instead of the tag,
 *
 *   window.WS_CONSENT = { site, name, ga, policy }
 *
 * and this file asks. The answer stays in this browser, per site:
 * localStorage 'ws:consent:<site>' = { analytics: true|false, at: ISO date }.
 *
 *   - no answer yet: a banner with two buttons of the same weight, Rifiuta
 *     and Accetta, and the cookie policy; nothing is loaded meanwhile;
 *   - yes: gtag.js is loaded, now and on every page;
 *   - no: nothing is loaded; after a yes (a change of mind) the _ga cookies
 *     are removed and the page reloads without them.
 *
 * The answer can be changed from the Preferences window: its buttons carry
 * data-consent-analytics="1" / "0".
 * =========================================================================== */
(function () {
  var C = window.WS_CONSENT || {};
  if (!C.ga) return;
  var KEY = 'ws:consent:' + (C.site || 'site');
  var IT = /^it/i.test(document.documentElement.lang || '');
  function esc(x) { return String(x == null ? '' : x).replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]; }); }
  var NAME = esc(C.name);
  var T = IT ? {
    text: (NAME || 'Questo sito') + ' usa cookie tecnici, necessari a farlo funzionare, e — solo se lo accetti — Google Analytics, per contare le visite in forma aggregata.',
    later: 'Puoi cambiare idea quando vuoi dalle Preferenze.',
    policy: 'Cookie policy', accept: 'Accetta', refuse: 'Rifiuta', label: 'Consenso ai cookie'
  } : {
    text: (NAME || 'This site') + ' uses technical cookies, needed for it to work, and — only if you accept — Google Analytics, to count visits in aggregate.',
    later: 'You can change your mind at any time from the Preferences.',
    policy: 'Cookie policy', accept: 'Accept', refuse: 'Refuse', label: 'Cookie consent'
  };

  function read() {
    try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; }
  }
  function write(yes) {
    try { localStorage.setItem(KEY, JSON.stringify({ analytics: !!yes, at: new Date().toISOString() })); } catch (e) {}
  }

  var loaded = false;
  function loadGA() {
    if (loaded) return;
    loaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', C.ga);
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(C.ga);
    document.head.appendChild(s);
  }
  // The cookies of Google Analytics, on this host and on its parents.
  function dropGA() {
    var parts = location.hostname.split('.');
    document.cookie.split(';').forEach(function (c) {
      var name = c.split('=')[0].trim();
      if (!/^_ga/.test(name)) return;
      document.cookie = name + '=; Max-Age=0; path=/';
      for (var i = 0; i < parts.length - 1; i++) {
        document.cookie = name + '=; Max-Age=0; path=/; domain=.' + parts.slice(i).join('.');
      }
    });
  }

  function sync() {
    var st = read();
    Array.prototype.forEach.call(document.querySelectorAll('[data-consent-analytics]'), function (b) {
      var yes = b.getAttribute('data-consent-analytics') === '1';
      b.setAttribute('aria-pressed', st && st.analytics === yes ? 'true' : 'false');
    });
  }

  function choose(yes) {
    var before = read();
    write(yes);
    var b = document.getElementById('ws-consent');
    if (b) b.remove();
    sync();
    if (yes) { loadGA(); return; }
    if (before && before.analytics) { dropGA(); location.reload(); }
  }

  function banner() {
    if (document.getElementById('ws-consent')) return;
    var b = document.createElement('div');
    b.id = 'ws-consent';
    b.setAttribute('role', 'region');
    b.setAttribute('aria-label', T.label);
    b.innerHTML =
      '<p>' + T.text + ' ' + T.later +
        (C.policy ? ' <a href="' + esc(C.policy) + '">' + T.policy + '</a>' : '') + '</p>' +
      '<div class="ws-consent-actions">' +
        '<button type="button" class="pill" data-consent-analytics="0">' + T.refuse + '</button>' +
        '<button type="button" class="pill" data-consent-analytics="1">' + T.accept + '</button>' +
      '</div>';
    document.body.appendChild(b);
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-consent-analytics]');
    if (!b) return;
    e.preventDefault();
    choose(b.getAttribute('data-consent-analytics') === '1');
  });

  var st = read();
  if (st && st.analytics) loadGA();
  function start() { sync(); if (!st) banner(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
