import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// PoC dev server. Il proxy /api inoltra al convertitore PHP (json-xml/index.php)
// se lo avvii su :8080, così si può chiudere il ciclo JSON-LD -> XML con CDATA.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (p) => p.replace(/^\/api/, '/index.php'),
      },
    },
  },
});
