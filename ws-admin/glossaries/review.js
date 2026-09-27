/*
 * The review of a glossary proposal, and its editor.
 *
 * Two versions: the CURRENT glossary, read-only, and the DRAFT - the proposal
 * as it arrived (an import, a new version) plus every edit made here. The
 * changes are always computed between the two, paired by @id and never by
 * position, and recomputed after each edit: an edit is simply a change that
 * starts accepted. Each change can be accepted or refused; the result is the
 * current version with the accepted changes, and nothing else.
 *
 *   Save the draft   the result becomes the proposal (the previous file goes to
 *                    history/): edits are kept, refused changes leave it.
 *   Apply            the result becomes the glossary.
 *
 * Every field of an entry can be edited, entries added, deleted, restored;
 * the glossary's settings and its parts (matter, paths, categories) too.
 */
(function () {
  'use strict';
  var G = window.WSGlossary, t = G.t;
  var box = document.getElementById('review');
  var current = JSON.parse(document.getElementById('review-current').textContent);
  var draft = JSON.parse(document.getElementById('review-proposal').textContent);
  if (!Array.isArray(draft.hasDefinedTerm)) draft.hasDefinedTerm = [];
  if (!Array.isArray(draft.hasPart)) draft.hasPart = [];
  var lang = current.inLanguage || draft.inLanguage || 'it';
  var collator = new Intl.Collator(lang, { sensitivity: 'base', numeric: true });
  var proposalBase = box.dataset.proposalBase;
  var dirty = false;

  var eq = function (a, b) { return JSON.stringify(a) === JSON.stringify(b); };
  var clone = function (v) { return v === undefined ? undefined : JSON.parse(JSON.stringify(v)); };
  var fold = function (s) { return String(s || '').normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); };
  function byId(list) { var m = new Map(); (list || []).forEach(function (x) { if (x && x['@id']) m.set(x['@id'], x); }); return m; }
  function keysOf(a, b) {
    var ks = Object.keys(a || {});
    Object.keys(b || {}).forEach(function (k) { if (ks.indexOf(k) === -1) ks.push(k); });
    return ks.filter(function (k) { return k !== '@id' && k !== '@type'; });
  }
  var curTerms = byId(current.hasDefinedTerm), curParts = byId(current.hasPart);
  var dTerms, dParts;
  function node(scope, id) { return scope === 'meta' ? draft : (scope === 'part' ? dParts : dTerms).get(id); }
  function nameOf(id) {
    var x = dTerms.get(id) || curTerms.get(id) || dParts.get(id) || curParts.get(id);
    return x ? String(x.name || id) : id;
  }
  function sets(role) { return draft.hasPart.filter(function (p) { return p['@type'] === 'DefinedTermSet' && p['ws:role'] === role; }); }

  /* ---- Changes: current <-> draft ------------------------------------------------- */
  var accepted = new Map();          // change key -> false when refused (absent: accepted)
  var changes = [], changesOf = new Map(), partIds = [], termIds = [];
  var keyOf = function (scope, id, field) { return [scope, id || '', field || ''].join('|'); };
  var isAccepted = function (c) { return accepted.get(c.key) !== false; };
  function computeChanges() {
    dTerms = byId(draft.hasDefinedTerm); dParts = byId(draft.hasPart);
    changes = []; changesOf = new Map();
    var kind = function (a, b) { return a === undefined ? 'added' : b === undefined ? 'removed' : 'modified'; };
    var add = function (c) {
      c.key = keyOf(c.scope, c.id, c.field);
      changes.push(c);
      var k = c.scope + '|' + (c.id || '');
      if (!changesOf.has(k)) changesOf.set(k, []);
      changesOf.get(k).push(c);
    };
    keysOf(current, draft).forEach(function (k) {
      if (k === '@context' || k === 'hasPart' || k === 'hasDefinedTerm') return;
      if (!eq(current[k], draft[k])) add({ scope: 'meta', id: '', field: k, kind: kind(current[k], draft[k]), before: current[k], after: draft[k] });
    });
    var compare = function (scope, curMap, dMap, ids) {
      ids.forEach(function (id) {
        var a = curMap.get(id), b = dMap.get(id);
        if (!a) return add({ scope: scope, id: id, kind: 'added', after: b });
        if (!b) return add({ scope: scope, id: id, kind: 'removed', before: a });
        keysOf(a, b).forEach(function (f) {
          if (!eq(a[f], b[f])) add({ scope: scope, id: id, field: f, kind: kind(a[f], b[f]), before: a[f], after: b[f] });
        });
      });
    };
    partIds = Array.from(dParts.keys()).concat(Array.from(curParts.keys()).filter(function (id) { return !dParts.has(id); }));
    compare('part', curParts, dParts, partIds);
    termIds = Array.from(new Set(Array.from(curTerms.keys()).concat(Array.from(dTerms.keys()))))
      .sort(function (a, b) { return collator.compare(nameOf(a), nameOf(b)); });
    compare('term', curTerms, dTerms, termIds);
  }
  function listOf(scope, id) { return changesOf.get(scope + '|' + (id || '')) || []; }
  function wholeOf(scope, id) { return listOf(scope, id).filter(function (c) { return !c.field; })[0]; }
  function kindOf(scope, id) { var w = wholeOf(scope, id); return w ? w.kind : (listOf(scope, id).length ? 'modified' : ''); }

  /* ---- The result: current + accepted changes ----------------------------------------- */
  function result() {
    var r = clone(current);
    var ok = changes.filter(isAccepted);
    ok.filter(function (c) { return c.scope === 'meta'; }).forEach(function (c) {
      if (c.after === undefined) delete r[c.field]; else r[c.field] = clone(c.after);
    });
    ['part', 'term'].forEach(function (scope) {
      var listKey = scope === 'part' ? 'hasPart' : 'hasDefinedTerm';
      var map = byId(r[listKey]);
      ok.filter(function (c) { return c.scope === scope; }).forEach(function (c) {
        if (!c.field && c.kind === 'added') map.set(c.id, clone(c.after));
        else if (!c.field && c.kind === 'removed') map.delete(c.id);
        else if (map.has(c.id)) { if (c.after === undefined) delete map.get(c.id)[c.field]; else map.get(c.id)[c.field] = clone(c.after); }
      });
      var list = Array.from(map.values());
      if (scope === 'part') list.sort(function (a, b) { return partIds.indexOf(a['@id']) - partIds.indexOf(b['@id']); });
      else list.sort(function (a, b) { return collator.compare(String(a.name), String(b.name)); });
      r[listKey] = list;
    });
    return r;
  }

  /* ---- Editing the draft ------------------------------------------------------------------ */
  function touch(scope, id) { dirty = true; computeChanges(); refresh(scope, id); }
  function setField(scope, id, field, value) {
    var n = node(scope, id);
    if (!n) return;
    var empty = value == null || value === '' || (Array.isArray(value) && !value.length);
    if (empty) delete n[field]; else n[field] = value;
    accepted.delete(keyOf(scope, id, field));
    touch(scope, id);
  }
  /* An entry's categories and selections, keeping any other set it names. */
  function setSets(id, category, selected) {
    var n = dTerms.get(id);
    var roles = sets('category').concat(sets('selection')).map(function (s) { return s['@id']; });
    var others = (n.inDefinedTermSet || []).filter(function (r) { return roles.indexOf(r['@id']) === -1; });
    var list = selected.map(function (s) { return { '@id': s }; }).concat(category ? [{ '@id': category }] : []).concat(others);
    setField('term', id, 'inDefinedTermSet', list);
  }
  function addTerm(name) {
    name = name.trim();
    if (!name) return null;
    var taken = Array.from(dTerms.values()).some(function (x) { return fold(x.name) === fold(name); });
    if (taken) { say(t('An entry with this name already exists.')); return null; }
    var base = '#' + G.slug(name), id = base;
    for (var i = 2; dTerms.has(id) || curTerms.has(id); i++) id = base + '-' + i;
    draft.hasDefinedTerm.push({ '@type': 'DefinedTerm', '@id': id, name: name, description: '' });
    expanded.add('term|' + id);
    touch('term', id);
    return id;
  }
  function deleteTerm(id) {
    draft.hasDefinedTerm = draft.hasDefinedTerm.filter(function (x) { return x['@id'] !== id; });
    accepted.delete(keyOf('term', id, ''));
    touch('term', id);
  }
  function restoreTerm(id) {
    draft.hasDefinedTerm.push(clone(curTerms.get(id)));
    touch('term', id);
  }

  /* ---- Showing values ------------------------------------------------------------------------ */
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
    if (typeof v !== 'string') return Array.isArray(v) && v.every(function (x) { return typeof x === 'string'; }) ? v.join(', ') : JSON.stringify(v);
    var d = document.createElement('div');
    d.appendChild(G.sanitize(v, true));
    return d.textContent;
  }
  /* References (related, members, rows) as names; a theme that is not an entry gets a ↗. */
  function refNames(v) {
    return [].concat(v || []).map(function (r) {
      if (r && r['@id']) return nameOf(r['@id']);
      if (r && r.item && r.item['@id']) return nameOf(r.item['@id']);
      if (r && r['@type'] === 'PropertyValue') return r.name + ': ' + plain(r.value);
      if (r && r.name != null) return String(r.name) + ' ↗';
      return typeof r === 'string' ? r : JSON.stringify(r);
    });
  }
  function chips(names, cls) {
    return h('ul', { class: 'review-chips' }, names.map(function (x) { return h('li', { class: cls || '', text: x }); }));
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
      if (op[0] === '=' && op[1].trim().length < 4 && k > 0 && k < ops.length - 1 && ops[k - 1][0] !== '=' && ops[k + 1][0] !== '=') op[0] = '~';
    });
    var p = h('p', { class: 'review-words' }), del = '', ins = '';
    var flush = function () {
      if (del) p.appendChild(h('del', { text: del }));
      if (ins) p.appendChild(h('ins', { text: ins }));
      del = ins = '';
    };
    ops.forEach(function (op) {
      if (op[0] === '=') { flush(); p.appendChild(document.createTextNode(op[1])); }
      else { if (op[0] !== '+') del += op[1]; if (op[0] !== '-') ins += op[1]; }
    });
    flush();
    return p;
  }
  var LIST_FIELDS = ['skos:related', 'inDefinedTermSet', 'itemListElement', 'ws:rows', 'ws:additionalProperty', 'hasDefinedTerm', 'keywords', 'ws:columns'];
  function changeView(c) {
    if (LIST_FIELDS.indexOf(c.field) !== -1) return listDiff(c.before, c.after);
    if (c.kind === 'modified' && (typeof c.before === 'string' || typeof c.after === 'string')) return wordDiff(c.before, c.after);
    return h('div', { class: 'review-values' },
      c.before !== undefined ? h('p', { class: 'review-before' }, h('span', { text: t('Before') }), ' ', plain(c.before)) : null,
      c.after !== undefined ? h('p', { class: 'review-after' }, h('span', { text: t('After') }), ' ', plain(c.after)) : null);
  }
  function valueView(field, v) {
    if (v === undefined || v === '' || (Array.isArray(v) && !v.length)) return h('p', { class: 'review-empty', text: '—' });
    if (LIST_FIELDS.indexOf(field) !== -1) return chips(refNames(v));
    return h('p', { class: 'review-value', text: plain(v) });
  }

  /* ---- Fields: labels, kinds of editor --------------------------------------------------------- */
  var FIELD_LABELS = {
    name: 'Name', alternateName: 'Name of the whole', description: 'Definition', disambiguatingDescription: 'Label',
    'skos:related': 'See also', inDefinedTermSet: 'Categories and selections', 'ws:additionalProperty': 'Properties',
    text: 'Text', itemListElement: 'Entries', 'ws:rows': 'Rows', 'ws:columns': 'Columns', version: 'Version',
    dateModified: 'Last modified', datePublished: 'Published', abstract: 'Abstract', creditText: 'Credits',
    license: 'License', image: 'Image', keywords: 'Keywords', 'ws:color': 'Colour'
  };
  function fieldLabel(scope, f) {
    if (scope === 'meta' && f === 'name') return t('Title');
    if (scope === 'meta' && f === 'description') return t('Subtitle');
    if (scope === 'meta' && f === 'disambiguatingDescription') return t('Note');
    return FIELD_LABELS[f] ? t(FIELD_LABELS[f]) : f;
  }
  var EDITORS = {
    name: 'line', alternateName: 'line', disambiguatingDescription: 'line', version: 'line', datePublished: 'date',
    dateModified: 'date', creditText: 'line', license: 'line', image: 'line',
    description: 'inline', abstract: 'inline', text: 'blocks',
    'skos:related': 'refs', itemListElement: 'terms', 'ws:additionalProperty': 'props', keywords: 'words', 'ws:color': 'color'
  };
  var TERM_FIELDS = ['name', 'disambiguatingDescription', 'description', 'skos:related', 'ws:additionalProperty'];
  var META_FIELDS = ['name', 'alternateName', 'description', 'disambiguatingDescription', 'abstract', 'version',
    'datePublished', 'dateModified', 'keywords', 'creditText', 'license', 'image'];
  function partFields(n) {
    var type = [].concat(n['@type'])[0];
    if (type === 'ItemList') return ['name', 'itemListElement'];
    if (type === 'DefinedTermSet') return n['ws:role'] === 'category' ? ['name', 'ws:color'] : ['name'];
    if (type === 'Table') return ['name'];
    return ['name', 'text'];
  }
  var KIND_LABELS = { added: 'new', removed: 'removed', modified: 'changed' };

  /* An editor in place of a value: OK writes to the draft, Cancel puts the value back. */
  function editor(scope, id, field) {
    var kind = EDITORS[field], v = clone(node(scope, id)[field]);
    var wrap = h('div', { class: 'review-editor' });
    var get;
    if (kind === 'line' || kind === 'date') {
      var input = h('input', { type: kind === 'date' ? 'date' : 'text', class: 'review-input' });
      input.value = v == null ? '' : String(v);
      wrap.appendChild(input);
      get = function () { return input.value.trim(); };
    } else if (kind === 'inline' || kind === 'blocks') {
      var area = h('textarea', { class: 'review-input', rows: kind === 'blocks' ? 10 : 4 });
      area.value = v == null ? '' : String(v);
      wrap.append(area, h('small', { class: 'review-hint', text: kind === 'blocks' ? t('Allowed markup: p, ul, ol, li, blockquote, and em, strong, sup, sub, q, cite, abbr, small.') : t('Allowed markup: em, strong, sup, sub, q, cite, abbr, small.') }));
      get = function () { return area.value.trim(); };
    } else if (kind === 'words') {
      var words = h('input', { type: 'text', class: 'review-input' });
      words.value = [].concat(v || []).join(', ');
      wrap.append(words, h('small', { class: 'review-hint', text: t('Separated by commas.') }));
      get = function () { return words.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean); };
    } else if (kind === 'color') {
      var color = h('input', { type: 'color', class: 'review-color' });
      color.value = /^#[0-9a-f]{6}$/i.test(v || '') ? v : '#888888';
      wrap.appendChild(color);
      get = function () { return color.value.toUpperCase(); };
    } else if (kind === 'refs' || kind === 'terms') {
      var items = [].concat(v || []);
      var ul = h('ul', { class: 'review-chips review-chips--edit' });
      var draw = function () {
        ul.replaceChildren.apply(ul, items.map(function (r, i) {
          return h('li', null, refNames([r])[0], ' ', h('button', { type: 'button', class: 'review-x', 'aria-label': t('Remove'), 'data-i': String(i), text: '×' }));
        }));
      };
      ul.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-i]');
        if (b) { items.splice(+b.getAttribute('data-i'), 1); draw(); }
      });
      var entry = h('input', { type: 'text', class: 'review-input', list: 'review-term-names', placeholder: kind === 'refs' ? t('An entry or a theme') : t('An entry') });
      var addRef = function () {
        var text = entry.value.trim();
        if (!text) return;
        var hit = Array.from(dTerms.values()).filter(function (x) { return fold(x.name) === fold(text); })[0];
        if (hit) items.push({ '@id': hit['@id'] });
        else if (kind === 'refs') items.push({ name: text });
        else { say(t('No entry is called "%s".', text)); return; }
        entry.value = ''; draw();
      };
      entry.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); addRef(); } });
      draw();
      wrap.append(ul, h('div', { class: 'review-editor__add' }, entry, h('button', { type: 'button', class: 'button', text: t('Add') })));
      wrap.querySelector('.review-editor__add button').addEventListener('click', addRef);
      get = function () { return items; };
    } else if (kind === 'props') {
      var rows = [].concat(v || []).map(function (p) { return { name: p.name || '', value: p.value || '' }; });
      var table = h('div', { class: 'review-props' });
      var drawRows = function () {
        table.replaceChildren.apply(table, rows.map(function (p, i) {
          var n = h('input', { type: 'text', class: 'review-input', placeholder: t('Property') }); n.value = p.name;
          var val = h('input', { type: 'text', class: 'review-input', placeholder: t('Value') }); val.value = p.value;
          n.addEventListener('input', function () { p.name = n.value; });
          val.addEventListener('input', function () { p.value = val.value; });
          var x = h('button', { type: 'button', class: 'review-x', 'aria-label': t('Remove'), text: '×' });
          x.addEventListener('click', function () { rows.splice(i, 1); drawRows(); });
          return h('div', { class: 'review-props__row' }, n, val, x);
        }));
      };
      drawRows();
      var more = h('button', { type: 'button', class: 'button', text: t('Add') });
      more.addEventListener('click', function () { rows.push({ name: '', value: '' }); drawRows(); });
      wrap.append(table, more);
      get = function () {
        return rows.filter(function (p) { return p.name.trim(); })
          .map(function (p) { return { '@type': 'PropertyValue', name: p.name.trim(), value: p.value.trim() }; });
      };
    }
    var ok = h('button', { type: 'button', class: 'button strong', text: t('Done') });
    var cancel = h('button', { type: 'button', class: 'button', text: t('Cancel') });
    ok.addEventListener('click', function () {
      var value = get();
      if (field === 'name' && !value) { say(t('The name cannot be empty.')); return; }
      setField(scope, id, field, value);
    });
    cancel.addEventListener('click', function () { refresh(scope, id); });
    wrap.appendChild(h('div', { class: 'review-editor__buttons' }, ok, cancel));
    setTimeout(function () { var f = wrap.querySelector('input, textarea'); if (f) f.focus(); }, 0);
    return wrap;
  }

  function acceptBox(c) {
    var input = h('input', { type: 'checkbox', 'data-key': c.key });
    input.checked = isAccepted(c);
    return h('label', { class: 'review-accept' }, input, h('span', { text: t('Accept') }));
  }
  function fieldRow(scope, id, field, c) {
    var n = node(scope, id);
    var editable = !!EDITORS[field] && !!n;
    return h('div', { class: 'review-field' + (c ? ' is-' + c.kind : '') + (c && !isAccepted(c) ? ' is-refused' : ''), 'data-scope': scope, 'data-id': id, 'data-field': field },
      h('div', { class: 'review-field__head' },
        h('strong', { text: fieldLabel(scope, field) }),
        c ? h('span', { class: 'review-kind', text: t(KIND_LABELS[c.kind]) }) : null,
        c ? acceptBox(c) : h('span', { class: 'review-accept' }),
        editable ? h('button', { type: 'button', class: 'review-edit', 'data-edit': field, title: t('Edit') }, h('span', { class: 'material-symbols-outlined', text: 'edit' })) : null),
      h('div', { class: 'review-field__body' }, c ? changeView(c) : valueView(field, n ? n[field] : undefined)));
  }
  function setsRow(id, c) {
    var n = dTerms.get(id);
    var ids = (n.inDefinedTermSet || []).map(function (r) { return r['@id']; });
    var cats = sets('category'), sels = sets('selection');
    var chosen = (cats.filter(function (s) { return ids.indexOf(s['@id']) !== -1; })[0] || {})['@id'] || '';
    var select = h('select', { 'data-sets': id, 'data-part': 'category' },
      h('option', { value: '', text: '—' }),
      cats.map(function (s) { var o = h('option', { value: s['@id'], text: s.name }); o.selected = s['@id'] === chosen; return o; }));
    return h('div', { class: 'review-field review-choices' + (c ? ' is-' + c.kind : '') + (c && !isAccepted(c) ? ' is-refused' : '') },
      h('label', { class: 'field horizontal' }, h('strong', { text: t('Category') }), select),
      sels.map(function (s) {
        var cb = h('input', { type: 'checkbox', 'data-sets': id, 'data-part': 'selection', value: s['@id'] });
        cb.checked = ids.indexOf(s['@id']) !== -1;
        return h('label', { class: 'review-accept' }, cb, h('span', { text: t('In the selection "%s"', s.name) }));
      }),
      c ? h('span', { class: 'review-kind', text: t(KIND_LABELS[c.kind]) }) : null,
      c ? acceptBox(c) : null);
  }

  /* ---- Cards ------------------------------------------------------------------------------------ */
  var expanded = new Set();    // "scope|id" of the cards open in full
  var cards = new Map();       // "scope|id" -> element
  function termCard(id) {
    var cur = curTerms.get(id), dr = dTerms.get(id), list = listOf('term', id), whole = wholeOf('term', id);
    var n = dr || cur;
    if (!n) return null;
    var kind = kindOf('term', id);
    var full = !!dr && (expanded.has('term|' + id) || (whole && whole.kind === 'added'));
    var el = h('article', { class: 'review-card review-term' + (kind ? ' is-' + kind : '') + (whole && !isAccepted(whole) ? ' is-refused' : ''),
        'data-scope': 'term', 'data-id': id, 'data-kind': kind, 'data-name': fold(n.name) },
      h('header', { class: 'review-card__head' },
        h('h3', { text: n.name || id }),
        n.disambiguatingDescription ? h('span', { class: 'review-label', text: n.disambiguatingDescription }) : null,
        kind ? h('span', { class: 'review-kind', text: t(KIND_LABELS[kind]) }) : null,
        whole ? acceptBox(whole) : null,
        h('span', { class: 'review-card__tools' },
          dr && !(whole && whole.kind === 'added') ? h('button', { type: 'button', class: 'button', 'data-toggle': 'term|' + id, text: full ? t('Close') : t('Edit the entry') }) : null,
          dr ? h('button', { type: 'button', class: 'button', 'data-delete': id, text: t('Delete the entry') }) : null,
          !dr && cur ? h('button', { type: 'button', class: 'button', 'data-restore': id, text: t('Restore') }) : null)));
    if (!dr) {
      el.appendChild(h('p', { class: 'review-def' }, G.sanitize(cur.description || '')));
    } else if (full) {
      TERM_FIELDS.forEach(function (f) { el.appendChild(fieldRow('term', id, f, list.filter(function (c) { return c.field === f; })[0])); });
      el.appendChild(setsRow(id, list.filter(function (c) { return c.field === 'inDefinedTermSet'; })[0]));
      list.forEach(function (c) {
        if (c.field && TERM_FIELDS.indexOf(c.field) === -1 && c.field !== 'inDefinedTermSet') el.appendChild(fieldRow('term', id, c.field, c));
      });
    } else if (list.length) {
      list.forEach(function (c) { el.appendChild(fieldRow('term', id, c.field, c)); });
    } else {
      el.appendChild(h('p', { class: 'review-def' }, G.sanitize(dr.description || '')));
    }
    return el;
  }
  function partCard(id) {
    var cur = curParts.get(id), dr = dParts.get(id), list = listOf('part', id), whole = wholeOf('part', id);
    var n = dr || cur;
    if (!n) return null;
    var kind = kindOf('part', id);
    var full = !!dr && (expanded.has('part|' + id) || (whole && whole.kind === 'added'));
    var el = h('article', { class: 'review-card' + (kind ? ' is-' + kind : '') + (whole && !isAccepted(whole) ? ' is-refused' : ''), 'data-scope': 'part', 'data-id': id, 'data-kind': kind },
      h('header', { class: 'review-card__head' },
        h('h3', { text: n.name || id }),
        h('span', { class: 'review-label', text: [].concat(n['@type'])[0] + (n['ws:role'] ? ' · ' + n['ws:role'] : '') }),
        kind ? h('span', { class: 'review-kind', text: t(KIND_LABELS[kind]) }) : null,
        whole ? acceptBox(whole) : null,
        h('span', { class: 'review-card__tools' },
          dr && !(whole && whole.kind === 'added') ? h('button', { type: 'button', class: 'button', 'data-toggle': 'part|' + id, text: full ? t('Close') : t('Edit') }) : null)));
    if (full) {
      partFields(n).forEach(function (f) { el.appendChild(fieldRow('part', id, f, list.filter(function (c) { return c.field === f; })[0])); });
      list.forEach(function (c) { if (c.field && partFields(n).indexOf(c.field) === -1) el.appendChild(fieldRow('part', id, c.field, c)); });
    } else if (whole) {
      var body = n.text || n.itemListElement || n['ws:rows'];
      if (body) el.appendChild(typeof body === 'string' ? h('p', { class: 'review-def', text: plain(body) }) : chips(refNames(body)));
    } else {
      list.forEach(function (c) { el.appendChild(fieldRow('part', id, c.field, c)); });
    }
    return el;
  }
  function metaCard() {
    var list = listOf('meta', '');
    var el = h('article', { class: 'review-card' + (list.length ? ' is-modified' : ''), 'data-scope': 'meta', 'data-id': '', 'data-kind': list.length ? 'modified' : '' },
      h('header', { class: 'review-card__head' }, h('h3', { text: t('The glossary') })));
    var fields = META_FIELDS.concat(list.map(function (c) { return c.field; }).filter(function (f) { return META_FIELDS.indexOf(f) === -1; }));
    fields.forEach(function (f) { el.appendChild(fieldRow('meta', '', f, list.filter(function (c) { return c.field === f; })[0])); });
    return el;
  }
  function build(scope, id) { return scope === 'meta' ? metaCard() : scope === 'part' ? partCard(id) : termCard(id); }

  /* ---- The page ------------------------------------------------------------------------------------ */
  var TABS = ['changes', 'added', 'modified', 'removed', 'settings', 'entries'];
  var TAB_LABELS = { changes: 'All changes', added: 'New entries', modified: 'Changed entries', removed: 'Removed entries', settings: 'Settings and parts', entries: 'All entries' };
  var tabs = h('div', { class: 'review-tabs', role: 'tablist' }, TABS.map(function (k) {
    return h('button', { type: 'button', role: 'tab', 'data-tab': k }, t(TAB_LABELS[k]), ' ', h('span', { 'data-count': k }));
  }));
  var search = h('input', { type: 'search', class: 'review-search', placeholder: t('Filter entries…') });
  var bulk = h('div', { class: 'review-bulk' },
    h('button', { type: 'button', class: 'button', 'data-bulk': '1', text: t('Accept all shown') }),
    h('button', { type: 'button', class: 'button', 'data-bulk': '0', text: t('Reject all shown') }));
  var newName = h('input', { type: 'text', class: 'review-input', placeholder: t('Name of the new entry') });
  var adder = h('form', { class: 'review-adder' }, newName, h('button', { type: 'submit', class: 'button strong', text: t('Add an entry') }));
  var datalist = h('datalist', { id: 'review-term-names' });
  var settings = h('section', { class: 'review-group', 'data-group': 'settings' });
  var terms = h('section', { class: 'review-group', 'data-group': 'terms' });
  var tally = h('p', { class: 'review-tally', 'aria-live': 'polite' });
  var status = h('p', { class: 'review-status', 'aria-live': 'polite' });
  var save = h('button', { type: 'button', class: 'button', title: t('What was accepted and edited becomes the proposal; refused changes leave it.'), text: t('Save the draft') });
  var preview = h('button', { type: 'button', class: 'button', text: t('Preview') });
  var apply = h('button', { type: 'button', class: 'button strong', text: t('Apply') });
  var bar = h('footer', { class: 'review-bar' }, tally, status, h('div', { class: 'review-bar__buttons' }, save, preview, apply));
  var dialog = h('dialog', { class: 'review-preview' },
    h('form', { method: 'dialog', class: 'review-preview__bar' }, h('button', { class: 'button', text: t('Close') })),
    h('div', { class: 'review-preview__body' }));
  box.append(h('div', { class: 'review-tools' }, tabs, search, bulk, adder), datalist, settings, terms, bar, dialog);
  function say(text) { status.textContent = text || ''; }

  var tab = 'changes';
  function visible(el) {
    var scope = el.getAttribute('data-scope'), kind = el.getAttribute('data-kind');
    var q = fold(search.value);
    if (scope === 'term') {
      if (q && el.getAttribute('data-name').indexOf(q) === -1) return false;
      if (tab === 'entries') return !!dTerms.get(el.getAttribute('data-id')) || kind === 'removed';
      if (tab === 'changes') return !!kind;
      return kind === tab;
    }
    if (q) return false;
    return tab === 'settings' || (tab === 'changes' && !!kind);
  }
  function filter() {
    cards.forEach(function (el) { el.hidden = !visible(el); });
    settings.hidden = !Array.from(settings.children).some(function (el) { return !el.hidden; });
  }
  function counts() {
    var c = { changes: 0, added: 0, modified: 0, removed: 0, settings: dParts.size + 1, entries: dTerms.size };
    cards.forEach(function (el) {
      var k = el.getAttribute('data-kind');
      if (!k) return;
      c.changes++;
      if (el.getAttribute('data-scope') === 'term') c[k]++;
    });
    TABS.forEach(function (k) { tabs.querySelector('[data-count="' + k + '"]').textContent = String(c[k]); });
    var n = changes.filter(isAccepted).length;
    tally.textContent = t('%1$s of %2$s changes accepted', n, changes.length);
    apply.disabled = n === 0;
  }
  function names() {
    datalist.replaceChildren.apply(datalist, Array.from(dTerms.values()).map(function (x) { return h('option', { value: x.name }); }));
  }
  /* Draw one card again (or for the first time, in its place). */
  function refresh(scope, id) {
    var k = scope + '|' + (id || '');
    var old = cards.get(k), el = build(scope, id);
    if (!el) { if (old) old.remove(); cards.delete(k); }
    else {
      if (old) old.replaceWith(el);
      else if (scope === 'term') {
        var after = termIds.slice(termIds.indexOf(id) + 1).map(function (x) { return cards.get('term|' + x); }).filter(Boolean)[0];
        terms.insertBefore(el, after || null);
      } else settings.appendChild(el);
      cards.set(k, el);
      el.hidden = !visible(el);
    }
    counts(); names();
  }
  function drawAll() {
    cards.forEach(function (el) { el.remove(); });
    cards.clear();
    [['meta', '']].concat(partIds.map(function (id) { return ['part', id]; })).forEach(function (p) {
      var el = build(p[0], p[1]); if (el) { settings.appendChild(el); cards.set(p[0] + '|' + p[1], el); }
    });
    termIds.forEach(function (id) { var el = build('term', id); if (el) { terms.appendChild(el); cards.set('term|' + id, el); } });
    filter(); counts(); names();
  }
  function selectTab(k) {
    tab = k;
    tabs.querySelectorAll('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', String(b.getAttribute('data-tab') === k)); });
    filter();
  }

  box.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el.matches('input[data-key]')) {
      accepted.set(el.getAttribute('data-key'), el.checked);
      dirty = true;
      var c = changes.filter(function (x) { return x.key === el.getAttribute('data-key'); })[0];
      if (c) refresh(c.scope, c.id);
    } else if (el.matches('[data-sets]')) {
      var id = el.getAttribute('data-sets'), card = cards.get('term|' + id);
      var category = card.querySelector('select[data-sets]').value;
      var selected = Array.from(card.querySelectorAll('input[data-sets]:checked')).map(function (x) { return x.value; });
      setSets(id, category, selected);
    }
  });
  box.addEventListener('click', function (ev) {
    var b = ev.target.closest('button');
    if (!b || !box.contains(b)) return;
    if (b.hasAttribute('data-tab')) return selectTab(b.getAttribute('data-tab'));
    if (b.hasAttribute('data-bulk')) {
      var on = b.getAttribute('data-bulk') === '1';
      cards.forEach(function (el) {
        if (el.hidden) return;
        el.querySelectorAll('input[data-key]').forEach(function (cb) { accepted.set(cb.getAttribute('data-key'), on); });
      });
      dirty = true;
      return drawAll();
    }
    if (b.hasAttribute('data-toggle')) {
      var k = b.getAttribute('data-toggle');
      expanded.has(k) ? expanded.delete(k) : expanded.add(k);
      return refresh(k.split('|')[0], k.slice(k.indexOf('|') + 1));
    }
    if (b.hasAttribute('data-delete')) return deleteTerm(b.getAttribute('data-delete'));
    if (b.hasAttribute('data-restore')) return restoreTerm(b.getAttribute('data-restore'));
    if (b.hasAttribute('data-edit')) {
      var row = b.closest('.review-field');
      row.querySelector('.review-field__body').replaceChildren(editor(row.getAttribute('data-scope'), row.getAttribute('data-id'), row.getAttribute('data-field')));
    }
  });
  adder.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var id = addTerm(newName.value);
    if (!id) return;
    newName.value = '';
    if (!visible(cards.get('term|' + id))) selectTab('added');
    var card = cards.get('term|' + id);
    card.scrollIntoView({ block: 'center' });
    var edit = card.querySelector('[data-field="description"] [data-edit]');
    if (edit) edit.click();
  });
  var timer;
  search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(filter, 120); });
  window.addEventListener('beforeunload', function (ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });

  function post(body) {
    return fetch(location.pathname, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ site: box.dataset.site, glossary: box.dataset.glossary, proposal: box.dataset.proposal, csrf: box.dataset.csrf }, body))
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (r) { if (!r.ok) throw new Error(r.body.error || ''); return r.body; });
  }
  save.addEventListener('click', function () {
    save.disabled = true; say(t('Saving…'));
    var r = result();
    post({ action: 'save', proposalBase: proposalBase, draft: r }).then(function (j) {
      proposalBase = j.proposalBase;
      draft = r; accepted.clear(); dirty = false;
      computeChanges(); drawAll();
      say(t('Draft saved.'));
    }).catch(function (e) { say(t('Could not save: %s', e.message)); })
      .then(function () { save.disabled = false; });
  });
  preview.addEventListener('click', function () {
    var body = dialog.querySelector('.review-preview__body');
    try { G.mount(body, result(), location.href); } catch (e) { G.fail(body, e); }
    dialog.showModal();
  });
  apply.addEventListener('click', function () {
    var ok = changes.filter(isAccepted);
    if (!confirm(t('Apply %d accepted changes? The current version is kept in the history.', ok.length))) return;
    apply.disabled = true; say(t('Saving…'));
    var summary = { accepted: ok.length, refused: changes.length - ok.length, added: 0, removed: 0, modified: 0 };
    ok.forEach(function (c) { if (c.scope === 'term' && !c.field) summary[c.kind]++; });
    summary.modified = new Set(ok.filter(function (c) { return c.scope === 'term' && c.field; }).map(function (c) { return c.id; })).size;
    post({ action: 'apply', base: box.dataset.base, result: result(), summary: summary }).then(function (j) {
      dirty = false;
      var epubs = Array.isArray(j.epubs) ? ' ' + t('EPUBs built: %s', j.epubs.join(', ')) : '';
      status.replaceChildren(t('Applied. The previous version is in %s.', j.previous) + epubs, ' ',
        h('a', { href: box.dataset.viewer, target: '_blank', text: t('View') }));
      box.querySelectorAll('input, select, textarea, .review-tools button, .review-card button, .review-bar button').forEach(function (el) { el.disabled = true; });
    }).catch(function (e) { say(t('Could not apply: %s', e.message)); apply.disabled = false; });
  });

  computeChanges();
  drawAll();
  selectTab(changes.length ? 'changes' : 'entries');
})();
