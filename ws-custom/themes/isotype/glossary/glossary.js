/*!
 * WS Glossary - a schema.org DefinedTermSet (JSON-LD) drawn as an interactive
 * glossary: search, categories, reading paths, selections (an "essential"
 * reading inside the whole), cross-references, an alphabetical index.
 *
 * The data model is documented in glossary/README.md. In short:
 *   DefinedTermSet            the glossary
 *     hasDefinedTerm[]        DefinedTerm: name, disambiguatingDescription
 *                             (origin/usage label), description, skos:related,
 *                             inDefinedTermSet (its categories and selections),
 *                             ws:additionalProperty (PropertyValue[])
 *     hasPart[]               DefinedTermSet with ws:role "category" | "selection"
 *                             ItemList (a reading path), Table (rows = terms),
 *                             CreativeWork with text (front or back matter)
 *
 * Where the data comes from, first found wins:
 *   1. the root's data-source="#id" - an inline <script type="application/ld+json">
 *   2. the root's data-src="url"
 *   3. the page's ?src=url - only for a root that declares no source of its
 *      own, so a page that embeds its glossary cannot be made to show another.
 *
 * Every text the visitor reads that is not content goes through t()/tn(),
 * with English msgids; the catalogue is window.WSGlossaryL10n, built from
 * ws-custom/languages/glossary-<locale>.po.
 *
 * Content is untrusted: names are text, descriptions and matter may carry
 * only the markup in ALLOWED below, rebuilt element by element from an inert
 * document, without attributes except lang (and title on abbr/dfn).
 */
(function (global) {
  'use strict';

  /* ---- Markup allowed in content ------------------------------------------
   * Twin: ws-admin/glossaries/lib/glossary.php (GLOSSARY_INLINE / _BLOCK).
   * The two lists must stay equal, or the page and the EPUB would disagree on
   * what a definition may contain. */
  var INLINE = ['em', 'strong', 'i', 'b', 'sup', 'sub', 'q', 'cite', 'abbr', 'dfn',
    'small', 'mark', 's', 'u', 'code', 'kbd', 'bdi', 'br'];
  var BLOCK = ['p', 'ul', 'ol', 'li', 'blockquote'];
  var DROP = ['script', 'style', 'template', 'iframe', 'object', 'embed', 'noscript',
    'svg', 'math', 'head', 'title', 'textarea', 'select', 'button', 'form', 'input'];
  var INLINE_SET = new Set(INLINE);
  var ALL_SET = new Set(INLINE.concat(BLOCK));
  var DROP_SET = new Set(DROP);
  var LANG_RE = /^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/;
  var COLOR_RE = /^(#[0-9A-Fa-f]{3,8}|(rgb|hsl|oklch)a?\([0-9.,%\s/+-]+\))$/;

  function sanitize(html, blocks) {
    var frag = document.createDocumentFragment();
    if (html == null || html === '') return frag;
    // A DOMParser document is inert: no script runs, no image loads.
    var doc = new DOMParser().parseFromString('<!DOCTYPE html><body>' + String(html), 'text/html');
    copyAllowed(doc.body, frag, blocks ? ALL_SET : INLINE_SET);
    return frag;
  }
  function copyAllowed(from, to, allowed) {
    for (var n = from.firstChild; n; n = n.nextSibling) {
      if (n.nodeType === 3) { to.appendChild(document.createTextNode(n.nodeValue)); continue; }
      if (n.nodeType !== 1) continue;
      var tag = n.localName;
      if (DROP_SET.has(tag)) continue;
      if (!allowed.has(tag)) { copyAllowed(n, to, allowed); continue; } // unwrap, keep the text
      var el = document.createElement(tag);
      var lang = n.getAttribute('lang');
      if (lang && LANG_RE.test(lang)) el.setAttribute('lang', lang);
      if ((tag === 'abbr' || tag === 'dfn') && n.getAttribute('title')) el.setAttribute('title', n.getAttribute('title'));
      copyAllowed(n, el, allowed);
      to.appendChild(el);
    }
  }
  function plain(html) {
    var box = document.createElement('div');
    box.appendChild(sanitize(html, true));
    return box.textContent;
  }

  /* ---- Localisation -------------------------------------------------------- */
  function catalogue() { return global.WSGlossaryL10n || {}; }
  function lookup(key) { var c = catalogue(); return Object.prototype.hasOwnProperty.call(c, key) ? c[key] : null; }
  /* Without arguments the placeholders stay: sentence() fills them with nodes. */
  function format(s, args) {
    if (!args.length) return String(s);
    var i = 0;
    return String(s).replace(/%(?:(\d+)\$)?[sd]/g, function (m, pos) {
      var v = pos ? args[pos - 1] : args[i++];
      return v == null ? '' : String(v);
    });
  }
  function t(msgid) {
    var s = lookup(msgid);
    return format(typeof s === 'string' && s !== '' ? s : msgid, [].slice.call(arguments, 1));
  }
  function tx(context, msgid) {
    var s = lookup(context + '\u0004' + msgid);
    return format(typeof s === 'string' && s !== '' ? s : msgid, [].slice.call(arguments, 2));
  }
  /* The plural form for n, placeholders untouched. */
  function pick(single, plural, n) {
    var s = lookup(single);
    var idx = n === 1 ? 0 : 1;
    return Array.isArray(s) && s[idx] ? s[idx] : (idx ? plural : single);
  }
  /* tn('%d entry', '%d entries', n) - n fills the placeholder unless other arguments follow. */
  function tn(single, plural, n) {
    var args = [].slice.call(arguments, 3);
    return format(pick(single, plural, n), args.length ? args : [n]);
  }

  /* A .po file into the catalogue t() reads: msgid -> msgstr, or -> [forms]
   * for plurals; a msgctxt is joined to the msgid by U+0004, as gettext does. */
  function parsePo(text) {
    var out = {}, cur = null, field = null;
    function unq(s) {
      return JSON.parse(s.replace(/\\(?!["\\nt])/g, '\\\\'));
    }
    function flush() {
      if (cur && cur.msgid) {
        var key = (cur.msgctxt ? cur.msgctxt + '\u0004' : '') + cur.msgid;
        out[key] = cur.plural ? cur.forms : (cur.forms[0] || '');
      }
      cur = { msgid: '', forms: [] }; field = null;
    }
    flush();
    String(text).split(/\r?\n/).forEach(function (line) {
      var m;
      line = line.trim();
      if (line === '' || line[0] === '#') { if (line === '' && field) flush(); return; }
      if ((m = line.match(/^msgctxt\s+(".*")$/))) { if (field && field !== 'msgctxt') flush(); cur.msgctxt = unq(m[1]); field = 'msgctxt'; }
      else if ((m = line.match(/^msgid\s+(".*")$/))) { if (field && field !== 'msgctxt') flush(); cur.msgid = unq(m[1]); field = 'msgid'; }
      else if ((m = line.match(/^msgid_plural\s+(".*")$/))) { cur.plural = unq(m[1]); field = 'msgid_plural'; }
      else if ((m = line.match(/^msgstr(?:\[(\d+)\])?\s+(".*")$/))) { field = 'msgstr' + (m[1] || 0); cur.forms[+(m[1] || 0)] = unq(m[2]); }
      else if ((m = line.match(/^(".*")$/)) && field) {
        var s = unq(m[1]);
        if (field === 'msgctxt') cur.msgctxt += s;
        else if (field === 'msgid') cur.msgid += s;
        else if (field === 'msgid_plural') cur.plural += s;
        else { var k = +field.slice(6); cur.forms[k] = (cur.forms[k] || '') + s; }
      }
    });
    flush();
    return out;
  }

  /* ---- Reading JSON-LD --------------------------------------------------------
   * Not a JSON-LD processor: it reads the compacted form this glossary is
   * written in, and tolerates what a hand-written file does - a @graph, a
   * value wrapped in {"@value"}, a "schema:" prefix, a single value where a
   * list is expected, a term declared outside the set that points to it. */
  var PREFIXES = {
    '': ['', 'schema:', 'https://schema.org/', 'http://schema.org/'],
    'ws:': ['ws:', 'https://localbiz.it/ws#'],
    'skos:': ['skos:', 'http://www.w3.org/2004/02/skos/core#']
  };
  function get(node, key) {
    if (!node || typeof node !== 'object') return undefined;
    var m = key.match(/^(ws:|skos:)(.*)$/);
    var list = m ? PREFIXES[m[1]] : PREFIXES[''];
    var local = m ? m[2] : key;
    for (var i = 0; i < list.length; i++) {
      if (Object.prototype.hasOwnProperty.call(node, list[i] + local)) return node[list[i] + local];
    }
    return undefined;
  }
  function list(v) { return v == null ? [] : (Array.isArray(v) ? v : [v]); }
  function str(v) {
    v = Array.isArray(v) ? v[0] : v;
    if (v == null) return '';
    if (typeof v === 'object') return str(v['@value'] != null ? v['@value'] : (get(v, 'name') != null ? get(v, 'name') : (v['@id'] || '')));
    return String(v);
  }
  function types(node) {
    return list(node && node['@type']).map(function (x) { return String(x).replace(/^(schema:|https?:\/\/schema\.org\/)/, ''); });
  }
  function isA(node, type) { return types(node).indexOf(type) !== -1; }

  function slug(s) {
    return String(s).normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'x';
  }
  function fold(s) {
    return String(s).normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  }
  /* An @id as an HTML id: "#kundalini", "…/glossary#kundalini" -> "kundalini". */
  function anchor(id) {
    var s = String(id || '');
    var h = s.lastIndexOf('#');
    return slug(h >= 0 ? s.slice(h + 1) : s);
  }

  function buildIndex(root) {
    var index = new Map();
    (function walk(x) {
      if (Array.isArray(x)) { x.forEach(walk); return; }
      if (!x || typeof x !== 'object') return;
      var id = x['@id'];
      var keys = Object.keys(x).filter(function (k) { return k !== '@id'; });
      if (id && keys.length) {
        var prev = index.get(id);
        index.set(id, prev ? Object.assign({}, prev, x) : x);
      }
      keys.forEach(function (k) { if (k !== '@context') walk(x[k]); });
    })(root);
    return index;
  }

  /* The glossary as the renderer and the editor want it: plain arrays, all
   * references resolved, the problems found on the way collected in `issues`. */
  function model(data, options) {
    options = options || {};
    var index = buildIndex(data);
    var resolve = function (x) { return x && x['@id'] && index.has(x['@id']) ? index.get(x['@id']) : x; };
    var issues = [];

    var sets = [];
    index.forEach(function (n) { if (isA(n, 'DefinedTermSet')) sets.push(n); });
    var top = Array.isArray(data) ? data[0] : (data && data['@graph'] ? null : data);
    var main = (top && isA(top, 'DefinedTermSet')) ? top
      : sets.filter(function (s) { return !get(s, 'ws:role'); })[0] || sets[0];
    if (!main) throw new Error(t('This file has no DefinedTermSet.'));
    var mainId = main['@id'] || '';

    // Terms: the ones the set lists, and the ones that say they belong to it.
    var termNodes = list(get(main, 'hasDefinedTerm')).map(resolve);
    index.forEach(function (n) {
      if (!isA(n, 'DefinedTerm') || termNodes.indexOf(n) !== -1) return;
      if (list(get(n, 'inDefinedTermSet')).some(function (r) { return (r && r['@id']) === mainId; })) termNodes.push(n);
    });

    var lang = str(get(main, 'inLanguage')) || document.documentElement.lang || 'en';
    var collator = new Intl.Collator(lang, { sensitivity: 'base', numeric: true });
    var seen = new Set();
    var terms = termNodes.filter(Boolean).map(function (n) {
      var name = str(get(n, 'name'));
      if (!name) issues.push({ level: 'error', message: t('A term has no name.'), id: n['@id'] || '' });
      var id = n['@id'] || '#' + slug(name);
      var a = anchor(id);
      if (seen.has(a)) issues.push({ level: 'error', message: t('Duplicate id: %s', a), id: id });
      seen.add(a);
      var first = fold(name).replace(/^[^a-z0-9]+/, '').charAt(0).toUpperCase();
      return {
        node: n, id: id, anchor: a, name: name,
        letter: /[A-Z]/.test(first) ? first : '#',
        label: str(get(n, 'disambiguatingDescription')),
        description: str(get(n, 'description')),
        related: list(get(n, 'skos:related')).map(function (r) {
          return r && r['@id'] ? { id: r['@id'], name: str(get(r, 'name')) } : { id: '', name: str(r) };
        }),
        properties: list(get(n, 'ws:additionalProperty')).map(function (p) {
          return { name: str(get(p, 'name')), value: str(get(p, 'value')) };
        }),
        memberOf: new Set(list(get(n, 'inDefinedTermSet')).map(function (r) { return r && r['@id']; }).filter(Boolean))
      };
    }).sort(function (x, y) { return collator.compare(x.name, y.name); });
    var byId = new Map(terms.map(function (x) { return [x.id, x]; }));

    var parts = list(get(main, 'hasPart')).map(resolve).filter(Boolean);
    function subset(role) {
      return parts.filter(function (p) { return isA(p, 'DefinedTermSet') && str(get(p, 'ws:role')) === role; }).map(function (p) {
        var s = { id: p['@id'] || '', name: str(get(p, 'name')), node: p, members: new Set() };
        // Membership may be written on either side: the term's inDefinedTermSet or the set's hasDefinedTerm.
        list(get(p, 'hasDefinedTerm')).forEach(function (r) { if (r && byId.has(r['@id'])) s.members.add(r['@id']); });
        terms.forEach(function (x) { if (x.memberOf.has(s.id)) s.members.add(x.id); });
        return s;
      });
    }
    var categories = subset('category').map(function (c) {
      var color = str(get(c.node, 'ws:color'));
      c.color = COLOR_RE.test(color) ? color : '';
      return c;
    });
    var selections = subset('selection');
    var paths = parts.filter(function (p) { return isA(p, 'ItemList'); }).map(function (p) {
      var items = list(get(p, 'itemListElement')).map(function (li, i) {
        var target = get(li, 'item') || li;
        return { id: target && target['@id'], position: Number(get(li, 'position')) || i + 1 };
      }).sort(function (a, b) { return a.position - b.position; });
      items.forEach(function (it) {
        if (!byId.has(it.id)) issues.push({ level: 'warning', message: t('Reading path "%1$s": unknown entry %2$s', str(get(p, 'name')), it.id), id: p['@id'] || '' });
      });
      return { id: p['@id'] || '', name: str(get(p, 'name')), description: str(get(p, 'description')),
        members: new Set(items.map(function (it) { return it.id; }).filter(function (id) { return byId.has(id); })) };
    });
    var tables = parts.filter(function (p) { return isA(p, 'Table'); }).map(function (p) {
      return { id: p['@id'] || '', name: str(get(p, 'name')), columns: list(get(p, 'ws:columns')).map(str),
        rows: list(get(p, 'ws:rows')).map(function (r) { return byId.get(r && r['@id']); }).filter(Boolean) };
    });
    var matter = parts.filter(function (p) { return get(p, 'text') != null && !isA(p, 'Table'); }).map(function (p) {
      return { id: p['@id'] || '', name: str(get(p, 'name')), text: str(get(p, 'text')),
        placement: str(get(p, 'ws:placement')) === 'back' ? 'back' : 'front' };
    });

    terms.forEach(function (x) {
      x.categories = categories.filter(function (c) { return c.members.has(x.id); });
      x.related.forEach(function (r) {
        if (r.id && !byId.has(r.id)) issues.push({ level: 'warning', message: t('"%1$s" refers to an unknown entry: %2$s', x.name, r.id), id: x.id });
      });
      x.search = fold([x.name, x.label, plain(x.description)]
        .concat(x.related.map(function (r) { return r.id && byId.has(r.id) ? byId.get(r.id).name : r.name; }))
        .concat(x.properties.map(function (p) { return plain(p.value); })).join(' \u2022 '));
    });

    var series = resolve(get(main, 'isPartOf'));
    return {
      node: main, id: mainId, lang: lang, base: options.base || document.baseURI,
      name: str(get(main, 'name')),
      alternateName: str(get(main, 'alternateName')),
      description: str(get(main, 'description')),
      note: str(get(main, 'disambiguatingDescription')),
      series: series ? [str(get(series, 'alternativeHeadline')), str(get(series, 'name'))].filter(Boolean) : [],
      author: list(get(main, 'author')).map(function (a) { return str(get(resolve(a), 'name') || a); }).filter(Boolean),
      copyrightHolder: str(get(resolve(get(main, 'copyrightHolder')), 'name') || get(main, 'copyrightHolder')),
      copyrightYear: str(get(main, 'copyrightYear')),
      creditText: str(get(main, 'creditText')),
      license: str(get(main, 'license')),
      version: str(get(main, 'version')),
      datePublished: str(get(main, 'datePublished')),
      dateModified: str(get(main, 'dateModified')),
      downloads: list(get(main, 'encoding')).concat(list(get(main, 'associatedMedia'))).map(resolve).map(function (m) {
        return { url: str(get(m, 'contentUrl')), format: str(get(m, 'encodingFormat')), name: str(get(m, 'name')) };
      }).filter(function (d) { return d.url; }),
      terms: terms, byId: byId, categories: categories, selections: selections,
      paths: paths, tables: tables, matter: matter, issues: issues
    };
  }

  /* ---- Drawing ---------------------------------------------------------------- */
  function h(tag, attrs) {
    var el = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      var v = attrs[k];
      if (v == null || v === false) return;
      if (k === 'text') el.textContent = v;
      else if (k === 'class') el.className = v;
      else el.setAttribute(k, v === true ? '' : v);
    });
    for (var i = 2; i < arguments.length; i++) {
      [].concat(arguments[i]).forEach(function (c) {
        if (c == null || c === false) return;
        el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
      });
    }
    return el;
  }
  /* A translated sentence whose %1$s… are nodes (a number in bold, a link). */
  function sentence(text, nodes) {
    var frag = document.createDocumentFragment();
    String(text).split(/(%\d+\$s)/).forEach(function (piece) {
      var m = piece.match(/^%(\d+)\$s$/);
      frag.appendChild(m ? nodes[m[1] - 1] : document.createTextNode(piece));
    });
    return frag;
  }
  function safeUrl(url, base) {
    try {
      var u = new URL(url, base);
      return /^https?:$/.test(u.protocol) ? u.href : '';
    } catch (e) { return ''; }
  }
  function withColor(el, color) { if (color) el.style.setProperty('--c', color); return el; }
  function formatDate(iso, lang) {
    var d = new Date(iso.length === 10 ? iso + 'T12:00:00' : iso);
    if (isNaN(d)) return iso;
    try { return new Intl.DateTimeFormat(lang, { day: 'numeric', month: 'long', year: 'numeric' }).format(d); } catch (e) { return iso; }
  }

  var LICENSE_PARTS = {
    by: ['Attribution', 'You must give appropriate credit, provide a link to the license, and indicate if changes were made.'],
    nc: ['NonCommercial', 'You may not use the material for commercial purposes.'],
    sa: ['ShareAlike', 'If you remix, transform, or build upon the material, you must distribute your contributions under the same license as the original.'],
    nd: ['NoDerivatives', 'If you remix, transform, or build upon the material, you may not distribute the modified material.']
  };
  function licenseBox(m) {
    var url = safeUrl(m.license, m.base);
    if (!url) return null;
    var cc = url.match(/creativecommons\.org\/licenses\/((?:by)(?:-(?:nc|sa|nd))*)\/(\d\.\d)/);
    if (!cc) {
      return h('div', { class: 'glossary-license' },
        h('p', { class: 'glossary-license__text' }, sentence(t('This work is licensed under %1$s.'), [h('a', { href: url, rel: 'license noopener', target: '_blank', text: url })])));
    }
    var codes = cc[1].split('-');
    var name = t('Creative Commons %1$s %2$s International',
      codes.map(function (c) { return tx('license name', LICENSE_PARTS[c][0]); }).join('-'), cc[2]);
    var deed = 'https://creativecommons.org/licenses/' + cc[1] + '/' + cc[2] + '/deed.' + m.lang.slice(0, 2);
    return h('div', { class: 'glossary-license' },
      h('p', { class: 'glossary-license__badge' }, h('span', { class: 'glossary-license__cc', text: 'CC' }), ' ',
        h('span', { class: 'glossary-license__code', text: cc[1].toUpperCase() + ' ' + cc[2] })),
      h('p', { class: 'glossary-license__text' }, sentence(t('This work is licensed under a %1$s license.'),
        [h('a', { href: deed, rel: 'license noopener', target: '_blank', text: name })])),
      h('ul', { class: 'glossary-license__terms' }, codes.map(function (c) {
        return h('li', null, h('b', { text: tx('license term', LICENSE_PARTS[c][0]) }), ' \u2014 ', tx('license term', LICENSE_PARTS[c][1]));
      })));
  }

  function renderEntry(m, x) {
    var entry = withColor(h('article', { class: 'glossary-entry', id: x.anchor, 'data-id': x.id }), x.categories[0] && x.categories[0].color);
    entry.appendChild(h('header', { class: 'glossary-entry__head' },
      h('h3', { class: 'glossary-term', text: x.name }),
      x.label ? h('span', { class: 'glossary-origin' }, h('span', { class: 'glossary-dot', 'aria-hidden': 'true' }), x.label) : null));
    if (x.description) entry.appendChild(h('p', { class: 'glossary-def' }, sanitize(x.description)));
    if (x.properties.length) {
      entry.appendChild(h('dl', { class: 'glossary-props' }, x.properties.map(function (p) {
        return h('div', null, h('dt', { text: p.name }), h('dd', null, sanitize(p.value)));
      })));
    }
    if (x.related.length) {
      var see = h('p', { class: 'glossary-see' }, h('span', { class: 'glossary-see__label', text: t('See also') }), ' ');
      x.related.forEach(function (r, i) {
        if (i) see.appendChild(document.createTextNode('; '));
        var target = r.id && m.byId.get(r.id);
        see.appendChild(target
          ? h('a', { class: 'glossary-xref', href: '#' + target.anchor, text: target.name })
          : h('span', { class: 'glossary-xref-plain', text: r.name || r.id }));
      });
      entry.appendChild(see);
    }
    return entry;
  }

  function renderTable(m, tb) {
    return h('figure', { class: 'glossary-table', id: tb.id ? anchor(tb.id) : null },
      tb.name ? h('figcaption', { text: tb.name }) : null,
      h('div', { class: 'glossary-table__scroll' }, h('table', null,
        h('thead', null, h('tr', null, tb.columns.map(function (c) { return h('th', { scope: 'col', text: c }); }))),
        h('tbody', null, tb.rows.map(function (x) {
          return withColor(h('tr', null,
            h('th', { scope: 'row' }, h('a', { class: 'glossary-xref', href: '#' + x.anchor, text: x.name })),
            tb.columns.slice(1).map(function (c) {
              var p = x.properties.filter(function (p) { return p.name === c; })[0];
              return h('td', null, p ? sanitize(p.value) : '');
            })), x.categories[0] && x.categories[0].color);
        })))));
  }

  function render(root, m) {
    var letters = [];
    m.terms.forEach(function (x) { if (letters.indexOf(x.letter) === -1) letters.push(x.letter); });
    var stats = [
      [m.terms.length, tn('entry', 'entries', m.terms.length)],
      [letters.length, tn('letter', 'letters', letters.length)]
    ];
    if (m.paths.length) stats.push([m.paths.length, tn('reading path', 'reading paths', m.paths.length)]);

    var front = m.matter.filter(function (p) { return p.placement === 'front'; });
    var back = m.matter.filter(function (p) { return p.placement === 'back'; });

    var hero = h('header', { class: 'glossary-hero' },
      h('div', { class: 'glossary-hero__ornament', 'aria-hidden': 'true' }, h('i'), h('i'), h('i')),
      h('div', { class: 'glossary-hero__inner' },
        m.series.length ? h('p', { class: 'glossary-eyebrow', text: m.series.join(' \u00b7 ') }) : null,
        h('h1', { class: 'glossary-title', text: m.name }),
        m.description ? h('p', { class: 'glossary-subtitle' }, sanitize(m.description)) : null,
        m.note ? h('p', { class: 'glossary-note' }, sanitize(m.note)) : null,
        h('p', { class: 'glossary-stats' }, stats.map(function (s) { return h('span', null, h('b', { text: String(s[0]) }), ' ', s[1]); }))));

    var intro = front.length ? h('div', { class: 'glossary-intro' }, h('details', null,
      h('summary', null, h('span', { class: 'glossary-intro__label', text: t('Introduction') }),
        h('span', { class: 'glossary-intro__parts', text: front.map(function (p) { return p.name; }).join(' \u00b7 ') }),
        h('span', { class: 'glossary-plus', 'aria-hidden': 'true' })),
      h('div', { class: 'glossary-intro__body' }, front.map(function (p) {
        return h('section', { id: p.id ? anchor(p.id) : null }, p.name ? h('h2', { text: p.name }) : null, sanitize(p.text, true));
      })))) : null;

    var paths = m.paths.length ? h('section', { class: 'glossary-paths' },
      h('h2', { class: 'glossary-section-title', text: t('Reading paths') }),
      h('p', { class: 'glossary-hint', text: t('Choose a path to see only its entries; choose it again to return to the whole glossary.') }),
      h('div', { class: 'glossary-path-grid' }, m.paths.map(function (p, i) {
        return h('button', { class: 'glossary-path', type: 'button', 'data-path': String(i), 'aria-pressed': 'false', title: p.description || null },
          h('span', { class: 'glossary-path__name', text: p.name }),
          h('span', { class: 'glossary-path__count', text: tn('%d entry', '%d entries', p.members.size) }));
      }))) : null;

    var tables = m.tables.length ? h('section', { class: 'glossary-tables' }, m.tables.map(function (tb) { return renderTable(m, tb); })) : null;

    var readings = m.selections.length ? h('div', { class: 'glossary-readings', role: 'group', 'aria-label': t('Reading') },
      [{ id: '', name: m.alternateName || t('Whole glossary'), size: m.terms.length }].concat(m.selections.map(function (s) {
        return { id: s.id, name: s.name, size: s.members.size };
      })).map(function (s) {
        return h('button', { class: 'glossary-reading', type: 'button', 'data-selection': s.id, 'aria-pressed': s.id ? 'false' : 'true' },
          s.name, ' ', h('span', { class: 'glossary-reading__count', text: String(s.size) }));
      })) : null;

    var search = h('label', { class: 'glossary-search' },
      svgIcon(),
      h('input', { type: 'search', class: 'glossary-search__input', placeholder: t('Search a word, a concept\u2026'), autocomplete: 'off', spellcheck: 'false', 'aria-label': t('Search the glossary') }),
      h('button', { class: 'glossary-search__clear', type: 'button', 'aria-label': t('Clear search'), hidden: true, text: '\u00d7' }));

    var chips = m.categories.length ? h('div', { class: 'glossary-chips', role: 'group', 'aria-label': t('Categories') },
      m.categories.map(function (c) {
        return withColor(h('button', { class: 'glossary-chip', type: 'button', 'data-category': c.id, 'aria-pressed': 'false' },
          h('span', { class: 'glossary-dot', 'aria-hidden': 'true' }), c.name), c.color);
      }), h('button', { class: 'glossary-chip glossary-chip--reset', type: 'button', 'data-reset': '', text: t('Reset filters') })) : null;

    var controls = h('div', { class: 'glossary-controls' }, h('div', { class: 'glossary-controls__inner' },
      readings, search, h('span', { class: 'glossary-count', 'aria-live': 'polite' }), chips));

    var spine = h('nav', { class: 'glossary-spine', 'aria-label': t('Alphabetical index') },
      h('span', { class: 'glossary-spine__rail', 'aria-hidden': 'true' }),
      letters.map(function (L) { return h('a', { href: '#glossary-letter-' + slug(L), 'data-letter': L, text: L }); }));

    var listEl = h('div', { class: 'glossary-list' }, letters.map(function (L) {
      var entries = m.terms.filter(function (x) { return x.letter === L; });
      return h('section', { class: 'glossary-letter', id: 'glossary-letter-' + slug(L), 'data-letter': L },
        h('h2', { class: 'glossary-letter__head' },
          h('span', { class: 'glossary-letter__char', text: L }),
          h('span', { class: 'glossary-letter__rule', 'aria-hidden': 'true' }),
          h('span', { class: 'glossary-letter__count', text: tn('%d entry', '%d entries', entries.length) })),
        entries.map(function (x) { return renderEntry(m, x); }));
    }));

    var empty = h('div', { class: 'glossary-empty', hidden: true },
      h('p', { class: 'glossary-empty__title', text: t('No entry matches') }),
      h('p', { text: t('Try another word or remove the filters.') }),
      h('button', { type: 'button', 'data-reset': '', text: t('Show all entries') }));

    var closing = back.length ? h('div', { class: 'glossary-closing' }, back.map(function (p) {
      return h('section', { class: 'glossary-closing__part', id: p.id ? anchor(p.id) : null },
        p.name ? h('h2', { text: p.name }) : null, sanitize(p.text, true));
    })) : null;

    var credits = [];
    if (m.copyrightHolder) credits.push(h('p', { text: '\u00a9 ' + [m.copyrightYear, m.copyrightHolder].filter(Boolean).join(' ') }));
    else if (m.author.length) credits.push(h('p', { text: m.author.join(', ') }));
    if (m.creditText) credits.push(h('p', { text: m.creditText }));
    if (m.version || m.dateModified) {
      credits.push(h('p', { class: 'glossary-colophon__version', text: m.version && m.dateModified
        ? t('Version %1$s, updated on %2$s', m.version, formatDate(m.dateModified, m.lang))
        : (m.version ? t('Version %s', m.version) : t('Updated on %s', formatDate(m.dateModified, m.lang))) }));
    }
    var downloads = m.downloads.map(function (d) {
      var url = safeUrl(d.url, m.base);
      if (!url) return null;
      var fmt = /epub/.test(d.format) ? 'EPUB' : /pdf/.test(d.format) ? 'PDF' : (d.format.split('/').pop() || '').toUpperCase();
      return h('a', { class: 'glossary-download', href: url, download: '' }, d.name || t('Download (%s)', fmt));
    }).filter(Boolean);
    var colophon = h('footer', { class: 'glossary-colophon' }, credits,
      downloads.length ? h('p', { class: 'glossary-downloads' }, downloads) : null, licenseBox(m));

    root.classList.add('glossary');
    root.setAttribute('lang', m.lang);
    root.replaceChildren(hero, intro || '', paths || '', tables || '', controls,
      h('div', { class: 'glossary-layout' }, spine, listEl), empty, closing || '', colophon);
    return root;
  }
  function svgIcon() {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
    var c = document.createElementNS(ns, 'circle'); c.setAttribute('cx', '11'); c.setAttribute('cy', '11'); c.setAttribute('r', '7');
    var p = document.createElementNS(ns, 'path'); p.setAttribute('d', 'M21 21l-4.3-4.3');
    svg.append(c, p);
    return svg;
  }

  /* ---- Behaviour -------------------------------------------------------------- */
  function enhance(root, m) {
    var $ = function (s) { return root.querySelector(s); };
    var $$ = function (s) { return [].slice.call(root.querySelectorAll(s)); };
    var input = $('.glossary-search__input'), clear = $('.glossary-search__clear');
    var count = $('.glossary-count'), empty = $('.glossary-empty');
    var entries = $$('.glossary-entry'), groups = $$('.glossary-letter');
    var state = { q: '', categories: new Set(), path: null, selection: '' };
    // A root drawn again (the viewer opening another file) drops the old listeners first.
    if (root.glossaryListeners) root.glossaryListeners.abort();
    var listeners = root.glossaryListeners = new AbortController();
    var on = { signal: listeners.signal };
    var selectionOf = new Map(m.selections.map(function (s) { return [s.id, s]; }));
    var termOf = new Map(m.terms.map(function (x) { return [x.anchor, x]; }));

    function scope() {
      if (state.path != null) return m.paths[state.path].members;
      return state.selection ? selectionOf.get(state.selection).members : null;
    }
    function apply() {
      var words = fold(state.q).split(/\s+/).filter(Boolean);
      var inScope = scope();
      var shown = 0, total = inScope ? inScope.size : m.terms.length;
      entries.forEach(function (el) {
        var x = termOf.get(el.id);
        var ok = (!inScope || inScope.has(x.id))
          && (!state.categories.size || x.categories.some(function (c) { return state.categories.has(c.id); }))
          && words.every(function (w) { return x.search.indexOf(w) !== -1; });
        el.hidden = !ok;
        if (ok) shown++;
      });
      groups.forEach(function (g) {
        var any = g.querySelector('.glossary-entry:not([hidden])');
        g.hidden = !any;
        var link = $('.glossary-spine a[data-letter="' + g.getAttribute('data-letter') + '"]');
        if (link) link.classList.toggle('is-off', !any);
      });
      count.replaceChildren(sentence(shown === total
        ? pick('%1$s entry', '%1$s entries', total)
        : pick('%1$s of %2$s entry', '%1$s of %2$s entries', total),
        [h('b', { text: String(shown) }), document.createTextNode(String(total))]));
      empty.hidden = shown > 0;
      clear.hidden = !state.q;
      $$('.glossary-chip[data-category]').forEach(function (b) { b.setAttribute('aria-pressed', String(state.categories.has(b.getAttribute('data-category')))); });
      $$('.glossary-path').forEach(function (b) { b.setAttribute('aria-pressed', String(+b.getAttribute('data-path') === state.path)); });
      $$('.glossary-reading').forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-selection') === state.selection)); });
    }
    function reset() {
      state.q = ''; input.value = ''; state.categories.clear(); state.path = null; state.selection = '';
      apply();
    }
    /* Make an entry visible - the fewest filters dropped - then go there. */
    function reveal(id, smooth) {
      if (!id) return false;
      var el = root.querySelector('#' + CSS.escape(id));
      if (!el || !el.classList.contains('glossary-entry')) return false;
      if (el.hidden) {
        var x = termOf.get(id);
        if (state.path != null && !m.paths[state.path].members.has(x.id)) state.path = null;
        if (state.selection && !selectionOf.get(state.selection).members.has(x.id)) state.selection = '';
        apply();
        if (el.hidden) reset();
      }
      el.scrollIntoView({ block: 'center', behavior: smooth ? 'smooth' : 'auto' });
      el.classList.remove('is-flash'); void el.offsetWidth; el.classList.add('is-flash');
      return true;
    }

    var timer;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { state.q = input.value; apply(); }, 90);
    });
    clear.addEventListener('click', function () { input.value = ''; state.q = ''; apply(); input.focus(); });
    root.addEventListener('click', function (ev) {
      var b = ev.target.closest('button, a');
      if (!b || !root.contains(b)) return;
      if (b.hasAttribute('data-reset')) { reset(); return; }
      if (b.hasAttribute('data-category')) {
        var c = b.getAttribute('data-category');
        state.categories.has(c) ? state.categories.delete(c) : state.categories.add(c);
        apply(); return;
      }
      if (b.hasAttribute('data-path')) {
        var i = +b.getAttribute('data-path');
        state.path = state.path === i ? null : i;
        apply();
        if (state.path != null) $('.glossary-controls').scrollIntoView({ block: 'start', behavior: 'smooth' });
        return;
      }
      if (b.hasAttribute('data-selection')) { state.selection = b.getAttribute('data-selection'); state.path = null; apply(); return; }
      if (b.classList.contains('glossary-xref')) {
        var id = b.getAttribute('href').slice(1);
        if (!termOf.has(id)) return;
        ev.preventDefault();
        history.pushState(null, '', '#' + id);
        reveal(id, true);
      }
    }, on);
    global.addEventListener('hashchange', function () { reveal(decodeURIComponent(location.hash.slice(1)), true); }, on);

    // The sticky bar's height, for scroll-margin: it changes with the chips' wrapping.
    var bar = $('.glossary-controls');
    if (global.ResizeObserver) {
      var ro = new ResizeObserver(function () { root.style.setProperty('--glossary-controls-h', bar.offsetHeight + 'px'); });
      ro.observe(bar);
      listeners.signal.addEventListener('abort', function () { ro.disconnect(); });
    }

    apply();
    if (location.hash) setTimeout(function () { reveal(decodeURIComponent(location.hash.slice(1)), false); }, 60);
    return { apply: apply, reset: reset, reveal: reveal, state: state };
  }

  /* ---- Loading ------------------------------------------------------------------ */
  var MAX_BYTES = 8 * 1024 * 1024;
  function fetchJson(url) {
    return fetch(url, { credentials: 'omit', mode: 'cors' }).then(function (r) {
      if (!r.ok) throw new Error(t('Could not load %1$s (HTTP %2$s).', url, r.status));
      return r.text();
    }).then(function (text) {
      if (text.length > MAX_BYTES) throw new Error(t('The file is too large.'));
      return JSON.parse(text);
    });
  }
  function source(root) {
    var sel = root.getAttribute('data-source');
    if (sel) {
      var script = document.querySelector(sel);
      if (!script) return Promise.reject(new Error(t('Data not found: %s', sel)));
      return Promise.resolve({ data: JSON.parse(script.textContent), base: root.getAttribute('data-base') || document.baseURI });
    }
    var url = root.getAttribute('data-src') || new URLSearchParams(location.search).get('src');
    if (!url) return Promise.reject(new Error(t('No glossary to show: add ?src= followed by the address of a JSON-LD file.')));
    var abs = new URL(url, document.baseURI).href;
    if (!/^https?:/.test(abs)) return Promise.reject(new Error(t('Only http and https addresses can be opened.')));
    return fetchJson(abs).then(function (data) { return { data: data, base: abs }; });
  }
  function mount(root, data, base) {
    var m = model(data, { base: base });
    render(root, m);
    var ctl = enhance(root, m);
    root.dispatchEvent(new CustomEvent('glossary:ready', { bubbles: true, detail: { model: m, controller: ctl } }));
    return { model: m, controller: ctl };
  }
  function fail(root, err) {
    root.classList.add('glossary', 'glossary--error');
    root.replaceChildren(h('p', { class: 'glossary-error', role: 'alert', text: err.message || String(err) }));
  }
  function boot() {
    [].slice.call(document.querySelectorAll('[data-glossary]')).forEach(function (root) {
      source(root).then(function (s) { mount(root, s.data, s.base); }).catch(function (e) { fail(root, e); });
    });
  }

  global.WSGlossary = {
    sanitize: sanitize, parsePo: parsePo, model: model, render: render, enhance: enhance,
    mount: mount, fail: fail, fetchJson: fetchJson, t: t, tn: tn, tx: tx, pick: pick, slug: slug
  };
  if (!global.WSGlossaryManual) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
  }
})(window);
