// Backend PHP endpoint (converter / validator / upload).
// In development this stays "/api" (Vite proxies it to :8080). In production on
// isotype.org there is NO proxy: set VITE_API_BASE (in .env.local or at build
// time) to the real URL of json-xml/index.php, e.g. "../json-xml/index.php" or
// "https://www.isotype.org/ws-admin/json-xml/index.php".
export const API_BASE = import.meta.env.VITE_API_BASE || (import.meta.env.DEV ? '/api' : '../../json-xml/index.php');

// Endpoint that checks whether a place/localbusiness @id already exists
// (ws-admin/places/id-exists.php). Empty disables the live check.
export const ID_CHECK_URL = import.meta.env.VITE_ID_CHECK_URL || (import.meta.env.DEV ? '' : '../../places/id-exists.php');

// Base of the CONTENT tree (to open an event from the web by @id/path).
// In production the editor is served by isotype.org, same origin as the content:
// an absolute path from the site root. In development it goes through Vite's
// /content proxy (see vite.config.js) to a local static server on the repo.
//
// The site root is derived from where the editor is served, cutting at
// /ws-admin/: today that gives '/sinapsi/', after the move it will give '/'. It
// used to be written by hand ('/sinapsi/…') and the move would have broken it
// silently.
const SITE_ROOT = typeof location !== 'undefined'
  ? location.pathname.replace(/\/ws-admin\/.*/, '/')
  : '/';

export const CONTENT_BASE =
  import.meta.env.VITE_CONTENT_BASE ||
  (import.meta.env.DEV
    ? '/content/ws-custom/contents/meetoo/it_IT/'
    : SITE_ROOT + 'ws-custom/contents/meetoo/it_IT/');

// Event index (for the search picker). Written when saving from the web.
export const EVENTS_INDEX_URL =
  import.meta.env.VITE_EVENTS_INDEX_URL || CONTENT_BASE + 'events/_index/events.json';

// Endpoint that SAVES an event to the web (ws-admin/events/save-event.php).
// In production it sits next to the editor: ../save-event.php. In development it
// goes through Vite's /save-event proxy to the repo's local PHP server.
export const SAVE_EVENT_URL =
  import.meta.env.VITE_SAVE_EVENT_URL || (import.meta.env.DEV ? '/save-event' : '../save-event.php');

// Google Identity (sign-in for saving to the web). Public client id (frontend).
export const GOOGLE_CLIENT_ID =
  import.meta.env.VITE_GOOGLE_CLIENT_ID ||
  '947742864411-rs99t8lkv5qcv4f5afb3pnhi0lkegbk3.apps.googleusercontent.com';

// Cover image endpoint: uploads into the event's own folder, produces the
// 1920x1080 version, reuses originals that are already there.
export const MEDIA_URL =
  import.meta.env.VITE_MEDIA_URL || (import.meta.env.DEV ? '/save-event/../media.php' : '../media.php');
