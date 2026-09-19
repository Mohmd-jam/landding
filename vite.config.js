import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(fileURLToPath(import.meta.url));

/**
 * Two React bundles are built from one codebase:
 *   public → the portfolio site (hydrates the server-rendered page)
 *   admin  → the CMS panel (schema-driven, authenticated)
 *
 * Asset names are deterministic on purpose: the HTML shells in public/app are
 * hand-written and reference /app/assets/{public,admin}.js + site.css, while
 * cache busting is handled with the ASSET_VERSION query string in production.
 */
export default defineConfig({
  plugins: [react()],
  publicDir: path.resolve(root, 'frontend/static'),
  resolve: {
    alias: {
      '@shared': path.resolve(root, 'frontend/src/shared'),
      '@site': path.resolve(root, 'frontend/src/public'),
      '@admin': path.resolve(root, 'frontend/src/admin'),
    },
  },
  build: {
    outDir: path.resolve(root, 'public/app'),
    emptyOutDir: false,
    cssCodeSplit: false,
    minify: 'esbuild',
    target: 'es2020',
    sourcemap: false,
    rollupOptions: {
      input: {
        public: path.resolve(root, 'frontend/src/public/main.jsx'),
        admin: path.resolve(root, 'frontend/src/admin/main.jsx'),
      },
      output: {
        entryFileNames: 'assets/[name].js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: (asset) => (asset.name && asset.name.endsWith('.css') ? 'assets/site.css' : 'assets/[name][extname]'),
      },
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: false,
  },
});
