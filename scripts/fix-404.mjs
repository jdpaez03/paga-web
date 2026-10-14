// 404.html is served at any missing path, so its links must be root-absolute, and it must never be indexed.
import { readFile, writeFile } from 'node:fs/promises';
const file = new URL('../dist/404.html', import.meta.url);
let html = await readFile(file, 'utf8');
html = html
  .replace(/(href|src|poster)="\.\//g, '$1="/')
  .replace(/srcset="([^"]*)"/g, (_, v) => `srcset="${v.replace(/(^|,\s*)\.\//g, '$1/')}"`);
if (!/name="robots"/.test(html)) html = html.replace('<head>', '<head><meta name="robots" content="noindex">');
await writeFile(file, html);
console.log('404.html: links made root-absolute');
