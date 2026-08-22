import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// PoC dev server. Il proxy /api inoltra al convertitore PHP (json-xml/index.php)
// se lo avvii su :8080, così si può chiudere il ciclo JSON-LD -> XML con CDATA.
export default defineConfig({
  // Base relativo: gli asset sono referenziati come ./assets/... così la build
  // funziona a qualsiasi percorso (root o sottocartella di isotype.org).
  base: './',
  // Sorgenti separati dal deploy: la build esce in ../edit (la cartella servita sul
  // server), così events/edit/ contiene SOLO i file compilati (niente src/config).
  build: {
    outDir: '../edit',
    emptyOutDir: true,
  },
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (p) => p.replace(/^\/api/, '/index.php'),
      },
      // Apri-da-web in sviluppo: server statico locale del repo (php -S :8091 -t <repo>).
      // In produzione l'editor è same-origin coi contenuti e NON usa questo proxy.
      '/content': {
        target: 'http://localhost:8091',
        changeOrigin: true,
        rewrite: (p) => p.replace(/^\/content/, ''),
      },
      // «Questo luogo esiste già sul sito?» in sviluppo → id-exists.php sul PHP
      // locale (:8091). Serve il proxy perché 5173→8091 è un'altra origine; in
      // produzione l'editor e l'endpoint stanno sullo stesso host e non passa di qui.
      '/id-exists': {
        target: 'http://localhost:8091',
        changeOrigin: true,
        rewrite: (p) => '/ws-admin/places/id-exists.php' + (p.includes('?') ? p.slice(p.indexOf('?')) : ''),
      },
      // Salva-evento sul web in sviluppo → save-event.php servito dal PHP locale (:8091).
      '/save-event': {
        target: 'http://localhost:8091',
        changeOrigin: true,
        rewrite: () => '/ws-admin/events/save-event.php',
      },
    },
  },
});
