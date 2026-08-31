import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { renameSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

/* La build dell'editor delle SCHEDE (luoghi e gruppi).
 *
 * Stesso progetto, stesse dipendenze, stessi sorgenti: cambia solo che cosa entra
 * (`scheda.html`) e dove esce (`ws-admin/places/edit/`). Un secondo progetto Vite
 * avrebbe voluto dire un secondo `node_modules` e due copie delle stesse versioni,
 * che è il modo più sicuro per ritrovarsi con due React diversi in casa.
 */
const QUI = dirname(fileURLToPath(import.meta.url));
const USCITA = resolve(QUI, '../../places/edit');

export default defineConfig({
  base: './',
  build: {
    outDir: USCITA,
    emptyOutDir: true,
    rollupOptions: { input: 'scheda.html' },
  },
  plugins: [
    react(),
    /* Il file di partenza si chiama `scheda.html` perché nella cartella dei
     * sorgenti c'è già l'`index.html` dell'editor eventi; ma all'arrivo deve
     * chiamarsi `index.html`, altrimenti `/ws-admin/places/edit/` non serve
     * niente e bisogna ricordarsi il nome del file. */
    {
      name: 'scheda-index',
      closeBundle() {
        const da = resolve(USCITA, 'scheda.html');
        if (existsSync(da)) renameSync(da, resolve(USCITA, 'index.html'));
      },
    },
  ],
});
