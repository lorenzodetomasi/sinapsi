import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import AppScheda from './AppScheda.jsx';
import './styles.css';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <AppScheda />
  </StrictMode>
);

// Header Meetoo condiviso: logo, menu, Impostazioni e LOGIN. L'editor delle schede
// usa la sua sessione (window.meetooSession) per parlare col backend, e la sua
// `Meetoo.urlPagina()` per sapere dove sta la pagina pubblica di ciò che modifica.
(function () {
  const root = location.pathname.replace(/\/(ws-custom|ws-admin)\/.*/, '/');
  window.MEETOO_HEADER = {};
  const s = document.createElement('script');
  s.src = root + 'ws-custom/themes/meetoo/header.js';
  s.defer = true;
  document.body.appendChild(s);
})();
