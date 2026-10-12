import { defineConfig } from 'astro/config';
import relativeLinks from 'astro-relative-links';

export default defineConfig({
  site: 'https://pagadesarrollos.com',
  trailingSlash: 'always',
  build: { assets: 'assets' },
  integrations: [relativeLinks()],
});
