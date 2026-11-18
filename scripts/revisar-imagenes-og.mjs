// Comprueba que cada proyecto tenga su imagen para compartir antes de compilar.
//
// Por que existe: al renombrar Amara Centro a Distrito Amara (9 de noviembre de 2026) se movieron
// la pagina, la imagen principal y las cinco de galeria, y se quedo atras `public/img/og/`. La
// ficha siguio declarando `og:image` con el slug nuevo y el archivo ya no existia, asi que el
// desarrollo mas grande del portafolio se compartia en WhatsApp y LinkedIn con un 404: el hueco de
// la tarjeta reservado por `og:image:width` y vacio. Nada lo detecto, porque un og roto no rompe
// ninguna pagina, no sale en el build y solo se ve fuera del sitio.
//
// Corre antes de `astro build` (ver package.json) y detiene la compilacion si falta alguna.

import { existsSync } from 'node:fs';
import path from 'node:path';
import data from '../src/data/projects.json' with { type: 'json' };

const root = new URL('..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const dir = path.join(root, 'public', 'img', 'og');

const faltan = data.projects.map((p) => p.slug).filter((slug) => !existsSync(path.join(dir, `${slug}.jpg`)));

if (faltan.length > 0) {
  console.error(`\nFalta la imagen para compartir de ${faltan.length} proyecto(s):`);
  for (const slug of faltan) console.error(`  public/img/og/${slug}.jpg`);
  console.error('\nCada ficha declara og:image con su slug. Sin el archivo, el enlace se comparte');
  console.error('con la tarjeta vacia. Si el proyecto cambio de nombre, renombra tambien este archivo.\n');
  process.exit(1);
}

console.log(`imagenes para compartir: ${data.projects.length} proyectos, ninguna falta`);
