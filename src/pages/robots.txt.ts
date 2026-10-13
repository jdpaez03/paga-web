import site from '../data/site.json';
export function GET() {
  const body = site.preview
    ? 'User-agent: *\nDisallow: /\n'
    : 'User-agent: *\nAllow: /\nDisallow: /api/\n\nSitemap: https://pagadesarrollos.com/sitemap-index.xml\n';
  return new Response(body, { headers: { 'Content-Type': 'text/plain' } });
}
