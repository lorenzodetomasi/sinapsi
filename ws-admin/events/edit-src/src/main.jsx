import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import './styles.css';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>
);

// Header Meetoo condiviso (solo chrome: logo, menu, Impostazioni/tema, breadcrumb — l'editor
// gestisce da sé l'auth Google). Iniettato a runtime: header.js vive in ws-custom, fuori dal build.
(function () {
  const root = location.pathname.replace(/\/(ws-custom|ws-admin)\/.*/, '/');
  window.MEETOO_HEADER = { noAuth: true };
  const s = document.createElement('script');
  s.src = root + 'ws-custom/themes/meetoo/header.js';
  s.defer = true;
  document.body.appendChild(s);
  const crumb = () => { if (window.Meetoo) window.Meetoo.setBreadcrumb([{ label: 'Editor eventi', current: true }]); else setTimeout(crumb, 150); };
  crumb();
})();
