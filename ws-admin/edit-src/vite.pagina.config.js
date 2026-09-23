import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import poCatalogue from './vite-po.js';
import { renameSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

/* La build dell'editor delle PAGINE.
 *
 * Stesso progetto, stesse dipendenze, stessi sorgenti: cambia solo che cosa
 * entra (`pagina.html`) e dove esce (`ws-admin/pages/edit/`). Un terzo progetto
 * Vite avrebbe voluto dire un terzo `node_modules` e tre copie delle stesse
 * versioni, che è il modo più sicuro per ritrovarsi con tre React diversi in
 * casa.
 */
const QUI = dirname(fileURLToPath(import.meta.url));
const USCITA = resolve(QUI, '../pages/edit');

export default defineConfig({
  base: './',
  build: {
    outDir: USCITA,
    emptyOutDir: true,
    rollupOptions: { input: 'pagina.html' },
  },
  plugins: [
    poCatalogue(),
    react(),
    /* Il file di partenza si chiama `pagina.html` perché nella cartella dei
     * sorgenti c'è già l'`index.html` dell'editor eventi; ma all'arrivo deve
     * chiamarsi `index.html`, altrimenti `/ws-admin/pages/edit/` non serve
     * niente e bisogna ricordarsi il nome del file. È anche il file che
     * l'elenco delle pagine cerca per sapere se l'editor esiste. */
    {
      name: 'pagina-index',
      closeBundle() {
        const da = resolve(USCITA, 'pagina.html');
        if (existsSync(da)) renameSync(da, resolve(USCITA, 'index.html'));
      },
    },
  ],
});
