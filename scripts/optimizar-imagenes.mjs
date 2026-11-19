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
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import sharp from 'sharp';
import data from '../src/data/projects.json' with { type: 'json' };

const root = fileURLToPath(new URL('..', import.meta.url));
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
  // El recorte va primero y no es cosmetico: marena.png traia 38x43 px de margen pegado al lienzo,
  // asi que en la misma caja se dibujaba 16 % mas chico que sus vecinos y descentrado abajo y a la
  // derecha. Los otros 30 vienen al filo. Con trim() el encuadre deja de depender de como venga
  // cada PNG. El umbral de 8 tolera el antialias del borde sin comerse trazos finos.
  //
  // 560 px, no 400: el uso mas grande es la mascara de .p-logo, 180x72 CSS px, que a DPR 3 pide
  // 540. Y sale mas barato que 400, porque a 560 casi ningun logo se reescala y el lossless
  // comprime mejor un borde limpio que uno interpolado: 256.3 KB contra 257.6 en los 30 juntos.
  const img = sharp(input).trim({ threshold: 8 }).resize({ width: 560, withoutEnlargement: true });
  // Siempre lossless. Antes se elegia el archivo mas chico entre lossless y lossy, y para
  // torre-acqua gano el lossy: dejo franjas magenta de compresion sobre los trazos finos de la gota
  // y de ACQUA, visibles al ampliar. Son marcas planas; el lossless es el formato correcto para
  // ellas y los 30 juntos cuestan 10.7 KB mas que la mezcla anterior.
  const best = await img.webp({ lossless: true, effort: 6 }).toBuffer();
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
// que declara el JSON-LD de la organización. paga-icon-navy.png es la marca del encabezado, que
// se dibuja a 42 px; ya dejo de declararse como favicon, eso lo cubre favicon.ico con sus mapas
// de 16, 32 y 48 px reales.
const marca = [
  { src: 'paga-icon-navy.png', w: 84 },
  { src: 'paga-logo-white.png', w: 240 },
  // la variante blanca ya estaba en el repositorio sin que nadie la usara: es la que va en la
  // pestana cuando el navegador esta en tema oscuro, donde el navy da 1.11:1 y desaparece
  { src: 'paga-icon-white.png', w: 84 },
];
for (const m of marca) {
  const input = path.join(pub, 'img', m.src);
  const out = input.replace(/\.png$/, '.webp');
  const antes = (await stat(input)).size;
  const img = sharp(input).resize({ width: m.w, withoutEnlargement: true });
  // Lossless, por la misma razon que los logos de desarrollo: son arte plano. Con la eleccion por
  // tamano, paga-icon-navy.webp habia salido lossy y su navy ya no era exactamente #13294b
  // —aparecian #13284a y #13294a—, que es el color del que cuelga toda la paleta del sitio.
  const best = await img.webp({ lossless: true, effort: 6 }).toBuffer();
  await writeFile(out, best);
  console.log(`marca: ${m.src} ${kb(antes)} → ${path.basename(out)} ${kb(best.length)} (${m.w} px)`);
}

// ——— 4. El juego de iconos de pestaña y de inicio ——————————————————————
// Habia un solo PNG declarado como favicon, y con eso:
//   · /favicon.ico lo pide el navegador aunque no se declare, y recibia un 404;
//   · iOS no respeta la transparencia al guardar el sitio en la pantalla de inicio, asi que sin un
//     apple-touch-icon ponia una captura de la pagina;
//   · el navy #13294b sobre la barra de pestanas en tema oscuro (#202124) da 1.11:1: invisible.
// Los tres se resuelven con archivos, sin tocar la marca.
const icoSizes = [16, 32, 48];
const iconSrc = path.join(pub, 'img', 'paga-icon-navy.png');

// ICO: cabecera de 6 bytes, una entrada de 16 por imagen, y los PNG enteros como carga util.
// Son dos: el navy para el tema claro y el blanco para el oscuro. El blanco era antes un solo PNG
// de 84 px y por eso no llegaba a usarse nunca: Chrome elige entre los <link rel=icon> por tamaño
// antes que por orden —medido, y documentado en src/layouts/Base.astro—, asi que los 16/32/48
// reales del .ico navy le ganaban en los dos esquemas. Con los dos en .ico la comparacion es justa.
async function armarIco(src, destino) {
  const partes = await Promise.all(
    icoSizes.map((s) => sharp(src).resize(s, s, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } }).png({ compressionLevel: 9 }).toBuffer()),
  );
  const head = Buffer.alloc(6);
  head.writeUInt16LE(0, 0); head.writeUInt16LE(1, 2); head.writeUInt16LE(icoSizes.length, 4);
  let offset = 6 + 16 * icoSizes.length;
  const dir = icoSizes.map((s, i) => {
    const e = Buffer.alloc(16);
    e.writeUInt8(s === 256 ? 0 : s, 0); e.writeUInt8(s === 256 ? 0 : s, 1);
    e.writeUInt8(0, 2); e.writeUInt8(0, 3);
    e.writeUInt16LE(1, 4); e.writeUInt16LE(32, 6);
    e.writeUInt32LE(partes[i].length, 8); e.writeUInt32LE(offset, 12);
    offset += partes[i].length;
    return e;
  });
  const ico = Buffer.concat([head, ...dir, ...partes]);
  // El `sizes` del <link> afirma 16, 32 y 48 px. Una afirmacion sin comprobar es justo lo que ya
  // fallo una vez en ese bloque, asi que el generador se revisa a si mismo antes de escribir.
  const dentro = Array.from({ length: ico.readUInt16LE(4) }, (_, i) => ico[6 + 16 * i] || 256);
  if (dentro.join(',') !== icoSizes.join(',')) {
    throw new Error(`${path.basename(destino)} quedo con ${dentro.join('/')} px y se esperaban ${icoSizes.join('/')}`);
  }
  await writeFile(destino, ico);
  console.log(`icono: ${path.basename(destino)} ${kb(ico.length)} (${dentro.join(', ')} px)`);
}

// El navy va en la raiz porque el navegador sondea /favicon.ico por su cuenta aunque no se declare.
// El blanco no necesita estar ahi: se declara explicitamente, y vive con el resto de los iconos.
await armarIco(iconSrc, path.join(pub, 'favicon.ico'));
await armarIco(path.join(pub, 'img', 'paga-icon-white.png'), path.join(pub, 'img', 'paga-icon-white.ico'));

// Fondo opaco en papel: iOS descarta el canal alfa y, con transparencia, la P queda sobre negro.
const touch = await sharp(iconSrc)
  .resize(148, 148, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
  .extend({ top: 16, bottom: 16, left: 16, right: 16, background: '#f5f3ef' })
  .flatten({ background: '#f5f3ef' })
  .png({ compressionLevel: 9 })
  .toFile(path.join(pub, 'img', 'paga-touch-icon.png'));
console.log(`icono: paga-touch-icon.png ${kb(touch.size)} (180 px, fondo papel)`);
