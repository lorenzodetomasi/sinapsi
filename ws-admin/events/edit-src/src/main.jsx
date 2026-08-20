import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './styles.css';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>
);

// Header Meetoo condiviso (riga 1: logo, menu, Impostazioni/tema, LOGIN). L'editor usa
// la sessione dell'header (window.meetooSession) per il salvataggio; le azioni sugli
// eventi sono la "riga 2" (la toolbar .appbar-row2 dentro l'app). Niente breadcrumb qui:
// header.js nasconde la riga 2 quando è vuota. header.js vive in ws-custom, fuori dal build.
(function () {
  const root = location.pathname.replace(/\/(ws-custom|ws-admin)\/.*/, '/');
  window.MEETOO_HEADER = {}; // login attivo (una sola init GIS: quella dell'header)
  const s = document.createElement('script');
  s.src = root + 'ws-custom/themes/meetoo/header.js';
  s.defer = true;
  document.body.appendChild(s);
})();
