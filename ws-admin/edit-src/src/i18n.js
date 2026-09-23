import catalogue from 'virtual:catalogue';

/**
 * The one way this editor turns an English string into what the user reads.
 *
 * Same shape as the site's PHP side — `_e('Settings')` there, `t('Settings')`
 * here — and the same single catalogue behind both: ws-custom/languages. The
 * msgid IS the English string, which buys two things at once. The code reads in
 * English for whoever maintains it, and a string nobody has translated yet
 * degrades to English instead of to a blank space or to a key like
 * `form.age.title`, which is the failure mode that makes symbolic keys
 * expensive: you cannot tell a missing translation from a missing string.
 *
 * There is no language switch yet, and no second language: the interface is
 * Italian and the catalogue is Italian. Adding English later means shipping a
 * second catalogue and choosing between them here — one function, one place.
 */

/**
 * @param {string} english  The English source string; also the catalogue key.
 * @param {Object} [values] Optional placeholders: t('Missing {field}', {field: 'name'}).
 */
export function t(english, values) {
  let out = catalogue[english] ?? english;
  if (values) {
    for (const [k, v] of Object.entries(values)) {
      out = out.split('{' + k + '}').join(String(v));
    }
  }
  return out;
}

/** Every key the catalogue knows — used by the check that no string is left behind. */
export function knownKeys() {
  return Object.keys(catalogue);
}
