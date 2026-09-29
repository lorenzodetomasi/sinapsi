/* ===========================================================================
 * cards.js - the markup of one card in the browser, and what its tools do.
 *
 * The twin of template-parts/cards.php: same classes, same order of the
 * parts, same options (see there). css/cards.css draws both.
 *
 *   WS.cards.card(o)      one card, as HTML
 *   WS.cards.share(url)   the system sheet where there is one, else the link
 *                         copied to the clipboard
 *   WS.cards.toast(msg)   a passing message at the bottom of the screen
 *
 * The tools are caught once, on the document: cards come and go all the time
 * and a listener on each would be wasted. "Interest" is the site's to answer -
 * a bookmark in the browser, an interest recorded on the server - so this file
 * asks, with the event 'ws:card-interest' (detail: button, kind, id). A site
 * that answers calls event.preventDefault(); if none does, the thing is kept
 * among the bookmarks of this browser.
 * =========================================================================== */
(function () {
  var WS = window.WS = window.WS || {};

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
    });
  }
  function icon(name) { return '<span class="material-symbols-outlined" aria-hidden="true">' + esc(name) + '</span>'; }
  function meta(ico, text) {
    text = String(text == null ? '' : text).trim();
    return text ? '<span>' + (ico ? icon(ico) : '') + esc(text) + '</span>' : '';
  }
  // The words of the page: the server writes them in <html lang>, and these are few.
  var IT = /^it/i.test(document.documentElement.lang || '');
  var WORDS = IT
    ? { share: 'Condividi', interest: 'Mi interessa', edit: 'Modifica', copied: 'Link copiato negli appunti' }
    : { share: 'Share', interest: 'I am interested', edit: 'Edit', copied: 'Link copied to the clipboard' };

  var toastTimer = 0;
  function toast(msg, ico) {
    var t = document.getElementById('ws-toast');
    if (!t) {
      t = document.createElement('div');
      t.id = 'ws-toast'; t.className = 'toast'; t.setAttribute('role', 'status');
      document.body.appendChild(t);
    }
    t.innerHTML = icon(ico || 'check_circle') + esc(msg);
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('show'); }, 4000);
  }

  /* On a phone the system sheet (WhatsApp, messages…); elsewhere the link is
   * copied and the page says so. */
  function share(url, title) {
    url = url ? new URL(url, location.href).href : location.href;
    if (navigator.share) {
      navigator.share({ title: title || document.title, url: url }).catch(function () {});
      return;
    }
    var done = function () { toast(WORDS.copied); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done, function () { copyByHand(url, done); });
    } else copyByHand(url, done);
  }
  function copyByHand(text, then) {
    var ta = document.createElement('textarea');
    ta.value = text; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;left:-9999px';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); then(); } catch (e) {}
    ta.remove();
  }

  function tools(t, href) {
    t = t || {};
    var shareUrl = (t.share === true || t.share == null) ? (href || '') : String(t.share);
    var it = t.interest || null;
    var edit = t.edit ? String(t.edit) : '';
    if (!t.share && !it && !edit) return '';
    var out = '<div class="card-tools"' +
      (it && it.kind ? ' data-kind="' + esc(it.kind) + '"' : '') +
      (it && it.id ? ' data-id="' + esc(it.id) + '"' : '') +
      (shareUrl ? ' data-url="' + esc(shareUrl) + '"' : '') + '>';
    if (t.share) out += '<button type="button" class="card-tool" data-tool="share" title="' + esc(WORDS.share) + '" aria-label="' + esc(WORDS.share) + '">' + icon('share') + '</button>';
    if (it) out += '<button type="button" class="card-tool' + (it.on ? ' on' : '') + '" data-tool="interest" aria-pressed="' + (it.on ? 'true' : 'false') + '" title="' + esc(WORDS.interest) + '" aria-label="' + esc(WORDS.interest) + '">' + icon('bookmark') + '</button>';
    if (edit) out += '<a class="card-tool" data-tool="edit" href="' + esc(edit) + '" title="' + esc(WORDS.edit) + '" aria-label="' + esc(WORDS.edit) + '">' + icon('edit') + '</a>';
    return out + '</div>';
  }

  function actions(list) {
    if (!list || !list.length) return '';
    return '<div class="card-actions">' + list.map(function (a) {
      return '<a class="card-act' + (a.primary ? ' primary' : '') + '" href="' + esc(a.href) + '"' +
        (a.external ? ' target="_blank" rel="noopener"' : '') +
        (a.title ? ' title="' + esc(a.title) + '"' : '') + '>' +
        (a.icon ? icon(a.icon) : '') + '<span>' + esc(a.label) + '</span></a>';
    }).join('') + '</div>';
  }

  function card(o) {
    o = o || {};
    var href = String(o.href || '').trim();
    var ext = !!o.external;
    var tag = /^(li|div|article)$/.test(o.tag || '') ? o.tag : 'div';
    var cls = 'card' + (o.className ? ' ' + o.className : '') + (href ? '' : ' card-sheet');
    var attrs = '';
    Object.keys(o.attrs || {}).forEach(function (k) { attrs += ' ' + k.replace(/[^a-z0-9_:-]/gi, '') + '="' + esc(o.attrs[k]) + '"'; });

    var media = '';
    if (o.media && o.media.src) {
      media = '<div class="card-media"><img src="' + esc(o.media.src) + '" alt="' + esc(o.media.alt || '') + '" loading="lazy" decoding="async">' +
        (o.media.src2 ? '<img class="card-media-alt" src="' + esc(o.media.src2) + '" alt="" loading="lazy" decoding="async">' : '') + '</div>';
    }
    var title = o.title || '';
    if (href && title) title = '<a class="card-link" href="' + esc(href) + '"' + (ext ? ' target="_blank" rel="noopener"' : '') + '>' + title + '</a>';
    var metas = (o.meta || []).filter(function (m) { return m && String(m).trim(); });
    var text = o.text ? String(o.text).trim() : '';
    var body = (title || metas.length || text)
      ? '<div class="card-body">' + (title ? '<h3 class="card-title">' + title + '</h3>' : '') +
        (metas.length ? '<div class="card-meta">' + metas.join('') + '</div>' : '') +
        (text ? '<div class="card-text">' + text + '</div>' : '') + '</div>'
      : '';
    var t = tools(o.tools, href);
    var a = actions(o.actions);
    var arrow = (href && !t && !a && o.arrow !== false) ? '<div class="card-arrow">' + icon(ext ? 'open_in_new' : 'arrow_forward') + '</div>' : '';
    return '<' + tag + ' class="' + esc(cls) + '"' + attrs + '>' + media + (o.head || '') + body + t + a + arrow + '</' + tag + '>';
  }

  /* Bookmarks of this browser, for the things no site answers for. */
  var KEY = 'ws:bookmarks';
  function bookmarks() {
    try { return new Set(JSON.parse(localStorage.getItem(KEY) || '[]')); } catch (e) { return new Set(); }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.card-tool[data-tool]');
    if (!btn) return;
    var box = btn.closest('.card-tools');
    var tool = btn.getAttribute('data-tool');
    if (tool === 'edit') return;                           // a link: let it go
    e.preventDefault();
    if (tool === 'share') {
      var titleEl = btn.closest('.card') && btn.closest('.card').querySelector('.card-title');
      share(box.getAttribute('data-url'), titleEl ? titleEl.textContent.trim() : '');
      return;
    }
    if (tool === 'interest') {
      var ask = new CustomEvent('ws:card-interest', {
        cancelable: true,
        detail: { button: btn, kind: box.getAttribute('data-kind') || '', id: box.getAttribute('data-id') || '' }
      });
      if (!document.dispatchEvent(ask)) return;            // the site answered
      var id = box.getAttribute('data-id') || box.getAttribute('data-url') || '';
      var set = bookmarks();
      if (set.has(id)) set.delete(id); else set.add(id);
      try { localStorage.setItem(KEY, JSON.stringify(Array.from(set))); } catch (err) {}
      btn.classList.toggle('on', set.has(id));
      btn.setAttribute('aria-pressed', set.has(id) ? 'true' : 'false');
    }
  });

  WS.cards = { card: card, tools: tools, actions: actions, meta: meta, icon: icon, esc: esc, share: share, toast: toast };
})();
