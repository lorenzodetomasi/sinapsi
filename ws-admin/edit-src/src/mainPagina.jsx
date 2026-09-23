import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import AppPagina from './AppPagina.jsx';
import './styles.css';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <AppPagina />
  </StrictMode>
);

// Header condiviso: logo, menu, Impostazioni e LOGIN. L'editor delle pagine usa
// la sua sessione (window.meetooSession) per parlare col backend — senza, ogni
// richiesta partirebbe senza gettone e il server risponderebbe 401.
(function () {
  const root = location.pathname.replace(/\/(ws-custom|ws-admin)\/.*/, '/');
  window.MEETOO_HEADER = {};
  const s = document.createElement('script');
  s.src = root + 'ws-custom/themes/meetoo/header.js';
  s.defer = true;
  document.body.appendChild(s);
})();
