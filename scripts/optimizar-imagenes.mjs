// Generates the image variants the site needs, with the sharp that Astro already brings.
// Run with `npm run imagenes` after adding a project, a gallery image or a logo.
//
//  1. 256 px thumbnails for the project gallery: the strip renders them at 107 px and used to load
//     the 480 px file (56 KB for a 107 px box).
//  2. The 31 project logos as WebP with alpha: as PNG they weighed 587 KB in total.
//
// It never overwrites a variant that is already up to date, and it leaves the sources untouched.

import { readdir, stat, writeFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';
import data from '../src/data/projects.json' with { type: 'json' };

const root = new URL('..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const pub = path.join(root, 'public');
const THUMB_W = 256;

const kb = (n) => `${(n / 1024).toFixed(1)} KB`;

// ——— 1. Thumbnails ———————————————————————————————————————————————
const withGallery = data.projects.filter((p) => p.gallery?.length);
const thumbSources = withGallery.flatMap((p) => [p.image, ...p.gallery.map((g) => g.src)]);

await mkdir(path.join(pub, 'img', 'w'), { recursive: true });
let made = 0;
for (const src of thumbSources) {
  const base = path.basename(src).replace(/\.jpg$/, '');
  const out = path.join(pub, 'img', 'w', `${base}-${THUMB_W}.webp`);
  if (existsSync(out)) continue;
  const input = path.join(pub, src.replace(/^\//, ''));
  if (!existsSync(input)) {
    console.warn(`  falta el original: ${src}`);
    continue;
  }
  await sharp(input).resize({ width: THUMB_W, withoutEnlargement: true }).webp({ quality: 74 }).toFile(out);
  made++;
}
console.log(`miniaturas ${THUMB_W}w: ${made} nuevas de ${thumbSources.length}`);

// ——— 2. Logos a WebP —————————————————————————————————————————————
const logoDir = path.join(pub, 'img', 'logos');
const pngs = (await readdir(logoDir)).filter((f) => f.endsWith('.png'));
// Los PNG de origen se borraron del arbol una vez verificado el sitio: estan en el historial de
// git (commit "Peso de la primera carga"). Para regenerar un logo, recupera su PNG y vuelve a
// correr esto; si no hay ninguno, no hay nada que convertir.
let before = 0;
let after = 0;
for (const file of pngs) {
  const input = path.join(logoDir, file);
  const out = input.replace(/\.png$/, '.webp');
  before += (await stat(input)).size;
  // the largest use is the mask of .p-logo, 180x72 CSS px: 400 px covers it at DPR 2 with margin
  const img = sharp(input).resize({ width: 400, withoutEnlargement: true });
  // flat marks compress better losslessly; for the rest quality 82 with a high alpha is enough
  const [lossless, lossy] = await Promise.all([
    img.clone().webp({ lossless: true, effort: 6 }).toBuffer(),
    img.clone().webp({ quality: 82, alphaQuality: 90, effort: 6 }).toBuffer(),
  ]);
  const best = lossless.length <= lossy.length ? lossless : lossy;
  await writeFile(out, best);
  after += best.length;
}
if (pngs.length === 0) console.log('logos: no hay PNG por convertir (los .webp ya estan en el repositorio)');
else {
  console.log(`logos: ${pngs.length} archivos, ${kb(before)} → ${kb(after)} (${Math.round((1 - after / before) * 100)} % menos)`);
  console.log('Revisa el sitio y borra los .png de origen: su version buena es el .webp.');
}

// ——— 3. Marca del encabezado y del pie —————————————————————————————
// Los dos salen en todas las páginas. Los PNG miden 160 y 600 px de ancho y se dibujan a 42 y 120:
// basta el doble para una pantalla retina. El PNG de origen se conserva: paga-logo-navy.png es el
// que declara el JSON-LD de la organización y paga-icon-navy.png sigue siendo el favicon.
const marca = [
  { src: 'paga-icon-navy.png', w: 84 },
  { src: 'paga-logo-white.png', w: 240 },
];
for (const m of marca) {
  const input = path.join(pub, 'img', m.src);
  const out = input.replace(/\.png$/, '.webp');
  const antes = (await stat(input)).size;
  const img = sharp(input).resize({ width: m.w, withoutEnlargement: true });
  const [lossless, lossy] = await Promise.all([
    img.clone().webp({ lossless: true, effort: 6 }).toBuffer(),
    img.clone().webp({ quality: 88, alphaQuality: 95, effort: 6 }).toBuffer(),
  ]);
  const best = lossless.length <= lossy.length ? lossless : lossy;
  await writeFile(out, best);
  console.log(`marca: ${m.src} ${kb(antes)} → ${path.basename(out)} ${kb(best.length)} (${m.w} px)`);
}
