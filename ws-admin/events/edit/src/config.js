// Endpoint del backend PHP (convertitore/validatore/upload).
// In sviluppo resta "/api" (proxy di Vite verso :8080). In produzione su
// isotype.org NON c'è il proxy: imposta VITE_API_BASE (in .env.local o a build)
// all'URL reale di json-xml/index.php, es. "../json-xml/index.php" oppure
// "https://www.isotype.org/ws-admin/json-xml/index.php".
export const API_BASE = import.meta.env.VITE_API_BASE || '/api';

// Endpoint che verifica se un @id di place/localbusiness esiste già
// (ws-admin/places/id-exists.php). Se vuoto, il controllo live è disattivato.
export const ID_CHECK_URL = import.meta.env.VITE_ID_CHECK_URL || '';
