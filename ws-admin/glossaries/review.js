/*
 * The review of a glossary proposal.
 *
 * Current and proposed version are paired by @id - entries, parts - never by
 * position: an entry added under "A" must not make every entry after it look
 * changed. Every difference becomes a change that can be accepted or refused
 * (accepted by default: the proposal is the newer text); a new entry can also
 * have its category and its selections set here, since those are the choices
 * an import can only guess. The result is the current version with the
 * accepted changes, and nothing else.
 */
(function () {
  'use strict';
  var G = window.WSGlossary, t = G.t;
  var box = document.getElementById('review');
  var current = JSON.parse(document.getElementById('review-current').textContent);
  var proposal = JSON.parse(document.getElementById('review-proposal').textContent);
  var lang = current.inLanguage || proposal.inLanguage || 'it';
  var collator = new Intl.Collator(lang, { sensitivity: 'base', numeric: true });

  /* ---- Comparing -------------------------------------------------------------- */
  var eq = function (a, b) { return JSON.stringify(a) === JSON.stringify(b); };
  var clone = function (v) { return v === undefined ? undefined : JSON.parse(JSON.stringify(v)); };
  function byId(list) { var m = new Map(); (list || []).forEach(function (x) { if (x && x['@id']) m.set(x['@id'], x); }); return m; }
  function keysOf(a, b) {
    var ks = Object.keys(a || {});
    Object.keys(b || {}).forEach(function (k) { if (ks.indexOf(k) === -1) ks.push(k); });
    return ks.filter(function (k) { return k !== '@id' && k !== '@type'; });
  }
  var curTerms = byId(current.hasDefinedTerm), propTerms = byId(proposal.hasDefinedTerm);
  var curParts = byId(current.hasPart), propParts = byId(proposal.hasPart);
  var nameOf = function (id) {
    var x = propTerms.get(id) || curTerms.get(id) || propParts.get(id) || curParts.get(id);
    return x ? String(x.name || id) : id;
  };

  var changes = [];   // { key, scope: meta|part|term, id, field, kind: added|removed|modified, before, after, accepted }
  function add(c) { c.key = [c.scope, c.id || '', c.field || ''].join('|'); c.accepted = true; changes.push(c); }

  keysOf(current, proposal).forEach(function (k) {
    if (k === '@context' || k === 'hasPart' || k === 'hasDefinedTerm') return;
    if (!eq(current[k], proposal[k])) add({ scope: 'meta', field: k, kind: kind(current[k], proposal[k]), before: current[k], after: proposal[k] });
  });
  function kind(a, b) { return a === undefined ? 'added' : b === undefined ? 'removed' : 'modified'; }
  function compareNodes(scope, curMap, propMap, ids) {
    ids.forEach(function (id) {
      var a = curMap.get(id), b = propMap.get(id);
      if (!a) return add({ scope: scope, id: id, kind: 'added', after: b });
      if (!b) return add({ scope: scope, id: id, kind: 'removed', before: a });
      keysOf(a, b).forEach(function (f) {
        if (!eq(a[f], b[f])) add({ scope: scope, id: id, field: f, kind: kind(a[f], b[f]), before: a[f], after: b[f] });
      });
    });
  }
  var partIds = Array.from(propParts.keys()).concat(Array.from(curParts.keys()).filter(function (id) { return !propParts.has(id); }));
  compareNodes('part', curParts, propParts, partIds);
  var termIds = Array.from(new Set(Array.from(curTerms.keys()).concat(Array.from(propTerms.keys()))))
    .sort(function (a, b) { return collator.compare(nameOf(a), nameOf(b)); });
  compareNodes('term', curTerms, propTerms, termIds);

  /* The choices an import can only guess, per new entry: its category, its selections. */
  var sets = (proposal.hasPart || []).filter(function (p) { return p['@type'] === 'DefinedTermSet'; });
  var categories = sets.filter(function (p) { return p['ws:role'] === 'category'; });
  var selections = sets.filter(function (p) { return p['ws:role'] === 'selection'; });
  var edits = new Map();   // term id -> { category, selections:Set }
  changes.filter(function (c) { return c.scope === 'term' && c.kind === 'added'; }).forEach(function (c) {
    var ids = (c.after.inDefinedTermSet || []).map(function (r) { return r['@id']; });
    edits.set(c.id, {
      category: (categories.filter(function (s) { return ids.indexOf(s['@id']) !== -1; })[0] || {})['@id'] || '',
      selections: new Set(selections.map(function (s) { return s['@id']; }).filter(function (s) { return ids.indexOf(s) !== -1; }))
    });
  });

  /* ---- The result ------------------------------------------------------------------ */
  function result() {
    var r = clone(current);
    var accepted = changes.filter(function (c) { return c.accepted; });
    accepted.filter(function (c) { return c.scope === 'meta'; }).forEach(function (c) {
      if (c.after === undefined) delete r[c.field]; else r[c.field] = clone(c.after);
    });
    ['part', 'term'].forEach(function (scope) {
      var listKey = scope === 'part' ? 'hasPart' : 'hasDefinedTerm';
      var map = byId(r[listKey]);
      accepted.filter(function (c) { return c.scope === scope; }).forEach(function (c) {
        if (c.kind === 'added' && !c.field) {
          var node = clone(c.after);
          var e = edits.get(c.id);
          if (e) {
            var others = (node.inDefinedTermSet || []).filter(function (x) {
              return !categories.some(function (s) { return s['@id'] === x['@id']; }) && !selections.some(function (s) { return s['@id'] === x['@id']; });
            });
            node.inDefinedTermSet = Array.from(e.selections).map(function (id) { return { '@id': id }; })
              .concat(e.category ? [{ '@id': e.category }] : []).concat(others);
          }
          map.set(c.id, node);
        } else if (c.kind === 'removed' && !c.field) {
          map.delete(c.id);
        } else if (map.has(c.id)) {
          if (c.after === undefined) delete map.get(c.id)[c.field]; else map.get(c.id)[c.field] = clone(c.after);
        }
      });
      var list = Array.from(map.values());
      if (scope === 'part') {
        // The proposal's order; what it dropped and the review kept stays where it was, at the end.
        var order = partIds;
        list.sort(function (a, b) { return order.indexOf(a['@id']) - order.indexOf(b['@id']); });
      } else {
        list.sort(function (a, b) { return collator.compare(String(a.name), String(b.name)); });
      }
      r[listKey] = list;
    });
    return r;
  }

  /* ---- Showing a value ----------------------------------------------------------------- */
  function h(tag, attrs) {
    var el = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v == null || v === false) return;
      if (k === 'text') el.textContent = v; else if (k === 'class') el.className = v; else el.setAttribute(k, v === true ? '' : v);
    });
    for (var i = 2; i < arguments.length; i++) [].concat(arguments[i]).forEach(function (c) {
      if (c != null && c !== false) el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return el;
  }
  function plain(v) {
    if (v == null) return '';
    if (typeof v !== 'string') return JSON.stringify(v);
    var d = document.createElement('div');
    d.appendChild(G.sanitize(v, true));
    return d.textContent;
  }
  /* A list of references (related, members, rows) as names, marking what came and went. */
  function refNames(v) {
    return [].concat(v || []).map(function (r) {
      if (r && r['@id']) return nameOf(r['@id']);
      if (r && r.item && r.item['@id']) return nameOf(r.item['@id']);
      if (r && r['@type'] === 'PropertyValue') return r.name + ': ' + plain(r.value);
      if (r && r.name != null) return String(r.name) + ' ↗';   // a theme, not an entry
      return typeof r === 'string' ? r : JSON.stringify(r);
    });
  }
  function listDiff(before, after) {
    var a = refNames(before), b = refNames(after);
    var ul = h('ul', { class: 'review-chips' });
    b.forEach(function (x) { ul.appendChild(h('li', { class: a.indexOf(x) === -1 ? 'is-in' : '', text: x })); });
    a.forEach(function (x) { if (b.indexOf(x) === -1) ul.appendChild(h('li', { class: 'is-out', text: x })); });
    return ul;
  }
  /* Words that stayed, went, came: a longest common subsequence over words
   * (each with its trailing space). Changes next to each other are shown as
   * one struck run and one new run, and a lone short word that stayed between
   * two changes is folded into them, or a rewritten sentence reads as confetti. */
  function wordDiff(before, after) {
    var a = plain(before).match(/\S+\s*/g) || [], b = plain(after).match(/\S+\s*/g) || [];
    var n = a.length, m = b.length, dp = [];
    for (var i = 0; i <= n; i++) dp.push(new Uint16Array(m + 1));
    for (i = n - 1; i >= 0; i--) for (var j = m - 1; j >= 0; j--) {
      dp[i][j] = a[i].trim() === b[j].trim() ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
    }
    var ops = [], ii = 0, jj = 0;
    while (ii < n && jj < m) {
      if (a[ii].trim() === b[jj].trim()) { ops.push(['=', b[jj]]); ii++; jj++; }
      else if (dp[ii + 1][jj] >= dp[ii][jj + 1]) ops.push(['-', a[ii++]]);
      else ops.push(['+', b[jj++]]);
    }
    while (ii < n) ops.push(['-', a[ii++]]);
    while (jj < m) ops.push(['+', b[jj++]]);
    ops.forEach(function (op, k) {
      if (op[0] === '=' && op[1].trim().length < 4 && k > 0 && k < ops.length - 1 && ops[k - 1][0] !== '=' && ops[k + 1][0] !== '=') {
        op[0] = '~';   // both: struck and new
      }
    });
    var p = h('p', { class: 'review-words' }), del = '', ins = '';
    function flush() {
      if (del) p.appendChild(h('del', { text: del }));
      if (ins) p.appendChild(h('ins', { text: ins }));
      del = ins = '';
    }
    ops.forEach(function (op) {
      if (op[0] === '=') { flush(); p.appendChild(document.createTextNode(op[1])); }
      else { if (op[0] !== '+') del += op[1]; if (op[0] !== '-') ins += op[1]; }
    });
    flush();
    return p;
  }
  var LIST_FIELDS = ['skos:related', 'inDefinedTermSet', 'itemListElement', 'ws:rows', 'ws:additionalProperty', 'hasDefinedTerm', 'keywords', 'ws:columns'];
  function valueView(c) {
    if (LIST_FIELDS.indexOf(c.field) !== -1) return listDiff(c.before, c.after);
    if (c.kind === 'modified' && (typeof c.before === 'string' || typeof c.after === 'string')) return wordDiff(c.before, c.after);
    return h('div', { class: 'review-values' },
      c.before !== undefined ? h('p', { class: 'review-before' }, h('span', { text: t('Before') }), ' ', plain(c.before)) : null,
      c.after !== undefined ? h('p', { class: 'review-after' }, h('span', { text: t('After') }), ' ', plain(c.after)) : null);
  }
  var FIELD_LABELS = {
    name: 'Name', alternateName: 'Name of the whole', description: 'Definition', disambiguatingDescription: 'Label',
    'skos:related': 'See also', inDefinedTermSet: 'Categories and selections', 'ws:additionalProperty': 'Properties',
    text: 'Text', itemListElement: 'Entries', 'ws:rows': 'Rows', 'ws:columns': 'Columns', version: 'Version',
    dateModified: 'Last modified', datePublished: 'Published'
  };
  function fieldLabel(scope, f) {
    if (scope === 'meta' && f === 'name') return t('Title');
    if (scope === 'meta' && f === 'description') return t('Subtitle');
    if (scope === 'meta' && f === 'disambiguatingDescription') return t('Note');
    return FIELD_LABELS[f] ? t(FIELD_LABELS[f]) : f;
  }
  var KIND_LABELS = { added: 'new', removed: 'removed', modified: 'changed' };

  /* ---- The page ------------------------------------------------------------------------ */
  function acceptBox(c) {
    var input = h('input', { type: 'checkbox', 'data-key': c.key });
    input.checked = c.accepted;
    return h('label', { class: 'review-accept' }, input, h('span', { text: t('Accept') }));
  }
  function fieldRow(c) {
    return h('div', { class: 'review-field is-' + c.kind, 'data-key': c.key },
      h('div', { class: 'review-field__head' }, h('strong', { text: fieldLabel(c.scope, c.field) }),
        h('span', { class: 'review-kind', text: t(KIND_LABELS[c.kind]) }), acceptBox(c)),
      valueView(c));
  }
  function termCard(id, list) {
    var whole = list.filter(function (c) { return !c.field; })[0];
    var node = whole ? (whole.after || whole.before) : (propTerms.get(id) || curTerms.get(id));
    var card = h('article', { class: 'review-card review-term is-' + (whole ? whole.kind : 'modified'), 'data-id': id, 'data-name': G.slug(nameOf(id)).replace(/-/g, ' ') },
      h('header', { class: 'review-card__head' },
        h('h3', { text: nameOf(id) }),
        node.disambiguatingDescription ? h('span', { class: 'review-label', text: node.disambiguatingDescription }) : null,
        h('span', { class: 'review-kind', text: t(KIND_LABELS[whole ? whole.kind : 'modified']) }),
        whole ? acceptBox(whole) : null));
    if (whole && whole.kind === 'added') {
      card.appendChild(h('p', { class: 'review-def' }, G.sanitize(node.description || '')));
      if (node['skos:related']) card.appendChild(h('div', { class: 'review-see' }, h('span', { text: t('See also') }), listDiff([], node['skos:related'])));
      var e = edits.get(id);
      var select = h('select', { 'data-edit': id },
        h('option', { value: '', text: '—' }),
        categories.map(function (s) { var o = h('option', { value: s['@id'], text: s.name }); o.selected = s['@id'] === e.category; return o; }));
      card.appendChild(h('div', { class: 'review-choices' },
        h('label', { class: 'field horizontal' }, h('strong', { text: t('Category') }), select),
        selections.map(function (s) {
          var cb = h('input', { type: 'checkbox', 'data-selection': s['@id'], 'data-term': id });
          cb.checked = e.selections.has(s['@id']);
          return h('label', { class: 'review-accept' }, cb, h('span', { text: t('In the selection "%s"', s.name) }));
        })));
    } else if (whole && whole.kind === 'removed') {
      card.appendChild(h('p', { class: 'review-def' }, G.sanitize(node.description || '')));
    } else {
      list.forEach(function (c) { card.appendChild(fieldRow(c)); });
    }
    return card;
  }
  function partCard(id, list) {
    var whole = list.filter(function (c) { return !c.field; })[0];
    var node = whole ? (whole.after || whole.before) : (propParts.get(id) || curParts.get(id));
    var card = h('article', { class: 'review-card is-' + (whole ? whole.kind : 'modified') },
      h('header', { class: 'review-card__head' },
        h('h3', { text: node.name || id }), h('span', { class: 'review-label', text: [].concat(node['@type'])[0] + (node['ws:role'] ? ' · ' + node['ws:role'] : '') }),
        h('span', { class: 'review-kind', text: t(KIND_LABELS[whole ? whole.kind : 'modified']) }),
        whole ? acceptBox(whole) : null));
    if (whole) {
      var body = node.text || node.itemListElement || node['ws:rows'];
      if (body) card.appendChild(typeof body === 'string' ? h('p', { class: 'review-def', text: plain(body) }) : listDiff([], body));
    } else list.forEach(function (c) { card.appendChild(fieldRow(c)); });
    return card;
  }

  var groups = { meta: [], part: new Map(), term: new Map() };
  changes.forEach(function (c) {
    if (c.scope === 'meta') groups.meta.push(c);
    else { var g = groups[c.scope]; if (!g.has(c.id)) g.set(c.id, []); g.get(c.id).push(c); }
  });
  var termKind = function (list) { var w = list.filter(function (c) { return !c.field; })[0]; return w ? w.kind : 'modified'; };
  var counts = { added: 0, modified: 0, removed: 0 };
  groups.term.forEach(function (list) { counts[termKind(list)]++; });

  var TABS = [
    ['all', t('All'), groups.term.size + groups.part.size + (groups.meta.length ? 1 : 0)],
    ['added', t('New entries'), counts.added],
    ['modified', t('Changed entries'), counts.modified],
    ['removed', t('Removed entries'), counts.removed],
    ['settings', t('Settings and parts'), groups.part.size + (groups.meta.length ? 1 : 0)]
  ];
  var tabs = h('div', { class: 'review-tabs', role: 'tablist' }, TABS.map(function (tb, i) {
    return h('button', { type: 'button', role: 'tab', 'data-tab': tb[0], 'aria-selected': String(i === 0) }, tb[1], ' ', h('span', { text: String(tb[2]) }));
  }));
  var search = h('input', { type: 'search', class: 'review-search', placeholder: t('Filter entries…') });
  var bulk = h('div', { class: 'review-bulk' },
    h('button', { type: 'button', class: 'button', 'data-bulk': '1', text: t('Accept all shown') }),
    h('button', { type: 'button', class: 'button', 'data-bulk': '0', text: t('Reject all shown') }));

  var settings = h('section', { class: 'review-group', 'data-group': 'settings' });
  if (groups.meta.length) {
    var meta = h('article', { class: 'review-card is-modified' }, h('header', { class: 'review-card__head' }, h('h3', { text: t('The glossary') })));
    groups.meta.forEach(function (c) { meta.appendChild(fieldRow(c)); });
    settings.appendChild(meta);
  }
  groups.part.forEach(function (list, id) { settings.appendChild(partCard(id, list)); });
  var terms = h('section', { class: 'review-group', 'data-group': 'terms' });
  groups.term.forEach(function (list, id) { terms.appendChild(termCard(id, list)); });

  var tally = h('p', { class: 'review-tally', 'aria-live': 'polite' });
  var status = h('p', { class: 'review-status', 'aria-live': 'polite' });
  var preview = h('button', { type: 'button', class: 'button', text: t('Preview') });
  var apply = h('button', { type: 'button', class: 'button strong', text: t('Apply') });
  var bar = h('footer', { class: 'review-bar' }, tally, status, h('div', { class: 'review-bar__buttons' }, preview, apply));
  var dialog = h('dialog', { class: 'review-preview' },
    h('form', { method: 'dialog', class: 'review-preview__bar' }, h('button', { class: 'button', text: t('Close') })),
    h('div', { class: 'review-preview__body' }));

  box.append(h('div', { class: 'review-tools' }, tabs, search, bulk), settings, terms, bar, dialog);

  /* ---- Behaviour -------------------------------------------------------------------------- */
  var byKey = new Map(changes.map(function (c) { return [c.key, c]; }));
  var activeTab = 'all';
  function refresh() {
    var n = changes.filter(function (c) { return c.accepted; }).length;
    tally.textContent = t('%1$s of %2$s changes accepted', n, changes.length);
    apply.disabled = n === 0;
  }
  function filter() {
    var q = search.value.trim() ? G.slug(search.value).replace(/-/g, ' ') : '';
    settings.hidden = !(activeTab === 'all' || activeTab === 'settings') || !!q;
    terms.hidden = activeTab === 'settings';
    [].forEach.call(terms.children, function (card) {
      var list = groups.term.get(card.getAttribute('data-id'));
      var okTab = activeTab === 'all' || termKind(list) === activeTab;
      card.hidden = !okTab || (q && card.getAttribute('data-name').indexOf(q) === -1);
    });
  }
  box.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el.matches('input[data-key]')) {
      byKey.get(el.getAttribute('data-key')).accepted = el.checked;
      el.closest('.review-field, .review-card').classList.toggle('is-refused', !el.checked);
      refresh();
    } else if (el.matches('select[data-edit]')) {
      edits.get(el.getAttribute('data-edit')).category = el.value;
    } else if (el.matches('input[data-selection]')) {
      var e = edits.get(el.getAttribute('data-term'));
      el.checked ? e.selections.add(el.getAttribute('data-selection')) : e.selections.delete(el.getAttribute('data-selection'));
    }
  });
  box.addEventListener('click', function (ev) {
    var tab = ev.target.closest('[data-tab]');
    if (tab) {
      activeTab = tab.getAttribute('data-tab');
      tabs.querySelectorAll('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', String(b === tab)); });
      filter(); return;
    }
    var bulkButton = ev.target.closest('[data-bulk]');
    if (bulkButton) {
      var on = bulkButton.getAttribute('data-bulk') === '1';
      box.querySelectorAll('input[data-key]').forEach(function (cb) {
        if (cb.closest('[hidden]') || cb.checked === on) return;
        cb.checked = on; cb.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }
  });
  var timer;
  search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(filter, 120); });

  preview.addEventListener('click', function () {
    var body = dialog.querySelector('.review-preview__body');
    try { G.mount(body, result(), location.href); } catch (e) { G.fail(body, e); }
    dialog.showModal();
  });
  apply.addEventListener('click', function () {
    var accepted = changes.filter(function (c) { return c.accepted; });
    if (!confirm(t('Apply %d accepted changes? The current version is kept in the history.', accepted.length))) return;
    apply.disabled = true;
    status.textContent = t('Saving…');
    var summary = { accepted: accepted.length, refused: changes.length - accepted.length, added: 0, removed: 0, modified: 0 };
    accepted.forEach(function (c) { if (c.scope === 'term' && !c.field) summary[c.kind]++; });
    summary.modified = new Set(accepted.filter(function (c) { return c.scope === 'term' && c.field; }).map(function (c) { return c.id; })).size;
    fetch(location.pathname, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify({ action: 'apply', site: box.dataset.site, glossary: box.dataset.glossary, proposal: box.dataset.proposal,
        base: box.dataset.base, csrf: box.dataset.csrf, result: result(), summary: summary })
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (r) {
        if (!r.ok) throw new Error(r.body.error || '');
        status.replaceChildren(t('Applied. The previous version is in %s.', r.body.previous), ' ',
          h('a', { href: box.dataset.viewer, target: '_blank', text: t('View') }));
        box.querySelectorAll('input, select').forEach(function (el) { el.disabled = true; });
      })
      .catch(function (e) { status.textContent = t('Could not apply: %s', e.message); apply.disabled = false; });
  });
  refresh(); filter();
})();
