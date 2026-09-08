import { readFileSync, existsSync, watch } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Reads the site's gettext catalogue and hands it to the app as a plain object.
 *
 * The rule is that every string a user reads lives in ws-custom/languages/*.po,
 * and there is exactly one catalogue for the whole project. A React bundle
 * cannot read a .mo, and asking the server for one at every load would put a
 * request in front of an editor that otherwise needs none — so the catalogue is
 * read at BUILD time and inlined. Translations change rarely; a rebuild is a
 * fair price for keeping one source of truth instead of two files that drift.
 *
 * The msgid is the English string itself, exactly as on the PHP side: code
 * reads in English, and an untranslated string degrades to English rather than
 * to a blank or a key like "form.age.title".
 */

const HERE = dirname(fileURLToPath(import.meta.url));
const PO_FILE = resolve(HERE, '../../../ws-custom/languages/ui-it_IT.po');
const VIRTUAL = 'virtual:catalogue';
const RESOLVED = '\0' + VIRTUAL;

/** Unescapes one quoted .po chunk: "a \"b\"\n" → a "b" + newline. */
function unquote(line) {
  const m = line.match(/"((?:[^"\\]|\\.)*)"/);
  if (!m) return '';
  return m[1]
    .replace(/\\n/g, '\n')
    .replace(/\\t/g, '\t')
    .replace(/\\"/g, '"')
    .replace(/\\\\/g, '\\');
}

/**
 * Minimal .po parser: enough for this catalogue, and deliberately not more.
 * Skips the header (empty msgid) and obsolete entries (#~), and keeps only the
 * singular of a plural form — the editor has no plurals today, and guessing at
 * a rule we do not use would be code nobody can check.
 */
export function readCatalogue(file = PO_FILE) {
  if (!existsSync(file)) return {};
  const out = {};
  let id = null;
  let str = null;
  let target = null; // 'id' | 'str' — which one a bare "…" line continues

  const flush = () => {
    if (id && str) out[id] = str;
    id = null;
    str = null;
  };

  for (const raw of readFileSync(file, 'utf8').split('\n')) {
    const line = raw.trim();
    if (line === '' || line.startsWith('#')) {
      if (line === '') flush();
      target = null;
      continue;
    }
    if (line.startsWith('msgid_plural')) { target = null; continue; }
    if (line.startsWith('msgid')) { flush(); id = unquote(line); target = 'id'; continue; }
    if (line.startsWith('msgstr[0]')) { str = unquote(line); target = 'str'; continue; }
    if (line.startsWith('msgstr[')) { target = null; continue; }
    if (line.startsWith('msgstr')) { str = unquote(line); target = 'str'; continue; }
    if (line.startsWith('"')) {
      if (target === 'id') id += unquote(line);
      else if (target === 'str') str += unquote(line);
    }
  }
  flush();
  delete out['']; // the header
  return out;
}

/** Vite plugin: `import catalogue from 'virtual:catalogue'`. */
export default function poCatalogue() {
  return {
    name: 'po-catalogue',
    resolveId(id) {
      return id === VIRTUAL ? RESOLVED : null;
    },
    load(id) {
      if (id !== RESOLVED) return null;
      return 'export default ' + JSON.stringify(readCatalogue()) + ';';
    },
    // In development the catalogue is a file like any other: touching it
    // reloads the page, so a translator sees the change without a rebuild.
    configureServer(server) {
      if (!existsSync(PO_FILE)) return;
      watch(PO_FILE, () => {
        const mod = server.moduleGraph.getModuleById(RESOLVED);
        if (mod) server.moduleGraph.invalidateModule(mod);
        server.ws.send({ type: 'full-reload' });
      });
    },
  };
}
