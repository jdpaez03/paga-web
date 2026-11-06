// Generates the image variants the site needs, with the sharp that Astro already brings.
// Run with `npm run imagenes` after adding a project, a gallery image or a logo.
//
//  1. The WebP variants of every project image: the widths that srcset() asks for in src/lib/data.ts
//     (480, 800, 1200 and 1600, the ones that fit the original) plus the 256 px thumbnail of the
//     gallery strip, which renders at 107 px and used to load the 480 px file.
//  2. The 31 project logos as WebP with alpha: as PNG they weighed 587 KB in total.
//
// A variant is rebuilt when it is missing or older than its source, and the sources are left
// untouched. Lo segundo importa: antes solo se miraba si el archivo existia, asi que cambiar la
// foto de un proyecto —misma ruta, imagen nueva— dejaba publicadas las variantes de la anterior.

import { readdir, stat, writeFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';
import data from '../src/data/projects.json' with { type: 'json' };

const root = new URL('..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const pub = path.join(root, 'public');
const THUMB_W = 256;

const kb = (n) => `${(n / 1024).toFixed(1)} KB`;

// ——— 1. Variantes de las imagenes de proyecto ————————————————————————
// Las mismas anchuras que pide srcset() en src/lib/data.ts, y con el mismo criterio: la de 480
// siempre, y las mayores solo si el original da para ellas. Si las dos listas se separan, el
// navegador pide un archivo que no existe.
const SRCSET_W = [480, 800, 1200, 1600];
// La miniatura de 256 solo la usa la tira de la galeria, asi que solo la llevan los proyectos que
// tienen galeria; para los demas seria un archivo que nadie pide.
const anchuras = (natural, conTira) =>
  [...(conTira ? [THUMB_W] : []), ...SRCSET_W.filter((w) => w === 480 || w <= natural)];

// Cada imagen con la anchura que declara projects.json, que es la que srcset() usa para decidir.
const fuentes = data.projects.flatMap((p) => {
  const tira = (p.gallery?.length ?? 0) > 0;
  return [
    { src: p.image, natural: p.imageSize?.[0] ?? 0, tira },
    ...(p.gallery ?? []).map((g) => ({ src: g.src, natural: g.size?.[0] ?? 0, tira: true })),
  ];
});

await mkdir(path.join(pub, 'img', 'w'), { recursive: true });
let made = 0;
let total = 0;
for (const { src, natural, tira } of fuentes) {
  const input = path.join(pub, src.replace(/^\//, ''));
  if (!existsSync(input)) {
    console.warn(`  falta el original: ${src}`);
    continue;
  }
  const fuenteMs = (await stat(input)).mtimeMs;
  const base = path.basename(src).replace(/\.jpg$/, '');
  for (const w of anchuras(natural, tira)) {
    total++;
    const out = path.join(pub, 'img', 'w', `${base}-${w}.webp`);
    // Se rehace si falta o si la fuente es mas nueva: cambiar la foto sin cambiar la ruta es un
    // caso real, y con solo mirar si existe quedaban publicadas las variantes de la anterior.
    if (existsSync(out) && (await stat(out)).mtimeMs >= fuenteMs) continue;
    await sharp(input)
      .resize({ width: w, withoutEnlargement: true })
      .webp({ quality: w === THUMB_W ? 74 : 80 })
      .toFile(out);
    made++;
  }
}
console.log(`variantes webp: ${made} generadas de ${total}`);

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
