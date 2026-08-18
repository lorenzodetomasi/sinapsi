// Endpoint del backend PHP (convertitore/validatore/upload).
// In sviluppo resta "/api" (proxy di Vite verso :8080). In produzione su
// isotype.org NON c'è il proxy: imposta VITE_API_BASE (in .env.local o a build)
// all'URL reale di json-xml/index.php, es. "../json-xml/index.php" oppure
// "https://www.isotype.org/ws-admin/json-xml/index.php".
export const API_BASE = import.meta.env.VITE_API_BASE || '/api';

// Endpoint che verifica se un @id di place/localbusiness esiste già
// (ws-admin/places/id-exists.php). Se vuoto, il controllo live è disattivato.
export const ID_CHECK_URL = import.meta.env.VITE_ID_CHECK_URL || '';

// Base dei CONTENUTI (per aprire un evento dal web via @id/percorso).
// In produzione l'editor è servito da isotype.org, stessa origine dei contenuti:
// path assoluto relativo alla root del sito. In sviluppo si usa il proxy /content
// di Vite (vedi vite.config.js) verso un server statico locale del repo.
export const CONTENT_BASE =
  import.meta.env.VITE_CONTENT_BASE ||
  (import.meta.env.DEV
    ? '/content/ws-custom/contents/meetoo/it_IT/'
    : '/sinapsi/ws-custom/contents/meetoo/it_IT/');

// Indice degli eventi (per il picker con ricerca). Popolato al salvataggio web (Fase 4).
export const EVENTS_INDEX_URL =
  import.meta.env.VITE_EVENTS_INDEX_URL || CONTENT_BASE + 'events/_index/events.json';

// Endpoint di salvataggio EVENTO sul web (ws-admin/events/save-event.php).
// In produzione è a fianco dell'editor: ../save-event.php. In sviluppo si usa il
// proxy /save-event di Vite verso il PHP server locale del repo.
export const SAVE_EVENT_URL =
  import.meta.env.VITE_SAVE_EVENT_URL || (import.meta.env.DEV ? '/save-event' : '../save-event.php');

// Google Identity (login per il salvataggio sul web). Client id pubblico (frontend).
export const GOOGLE_CLIENT_ID =
  import.meta.env.VITE_GOOGLE_CLIENT_ID ||
  '947742864411-rs99t8lkv5qcv4f5afb3pnhi0lkegbk3.apps.googleusercontent.com';
