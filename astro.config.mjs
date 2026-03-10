// @ts-check
import { defineConfig } from 'astro/config';

import tailwindcss from '@tailwindcss/vite';

import sitemap from '@astrojs/sitemap';

// https://astro.build/config
export default defineConfig({
  site: 'https://vicoyes.github.io',
  base: '/tac-advisors/',

  vite: {
    plugins: [tailwindcss()],
    server: {
      port: 4321,
      strictPort: false,
      hmr: {
        overlay: true
      },
      watch: {
        usePolling: true
      }
    }
  },

  integrations: [sitemap()]
});