import { defineConfig } from 'astro/config';
import relativeLinks from 'astro-relative-links';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  site: 'https://pagadesarrollos.com',
  trailingSlash: 'always',
  build: { assets: 'assets' },
  integrations: [relativeLinks(), sitemap({ i18n: { defaultLocale: 'es', locales: { es: 'es-MX', en: 'en' } } })],
});
