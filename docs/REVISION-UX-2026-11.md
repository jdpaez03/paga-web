# Revisión de UI/UX contra los desarrolladores más grandes — noviembre 2026

Cuatro revisiones en paralelo, 5 de noviembre de 2026:

1. **Benchmark institucional global** — 10 sitios corporativos: Hines, Tishman Speyer, Related, Oxford Properties, Lendlease, Skanska, Mitsui Fudosan, BGRE (antes Brookfield Properties), Hochtief y Greystar.
2. **Benchmark México y LatAm** — 14 sitios: Thor Urbana, Be Grand, Artha Capital, Vinte, Javer, Ruba, GICSA, Fibra Danhos, FUNO, Abilia, Sordo Madaleno, GP Desarrollos, Carza, más Parque Arauco y JHSF como contraste regional.
3. **Experiencia y arquitectura de información** del sitio actual.
4. **Accesibilidad, móvil y desempeño**, con medición directa del build.

Las cifras del portafolio que se usan aquí están recalculadas de `projects.json`: **36 desarrollos, 27 entregados y 9 en curso** (7 en construcción, 1 en permisos, 1 en planeación); **1,826 unidades de vivienda, 948 entregadas**; **410,000 m² entregados y 218,000 m² en desarrollo**; 10 ciudades; 26 residencial, 5 usos mixtos, 4 comercial, 1 oficinas. Los seis desarrollos sin conteo de unidades son comerciales o de oficinas.

---

## El veredicto

**El sitio ya está por encima del mercado mexicano.** De los 14 sitios mexicanos revisados, ni uno solo fecha sus cifras institucionales; PAGA ya lo hace ("Cifras a octubre de 2026"). Fibra Danhos publica +130 millones de visitantes en su portada y +100 millones en su página de "Quiénes somos", sin fecha en ninguna de las dos. Thor Urbana pide datos personales sin casilla de consentimiento. GICSA tiene "Contacto" como título de su portada. El dominio `fibrauno.com` está a la venta en 25,000 dólares mientras la FIBRA más grande del país opera en otro. El listón del mercado está bajo.

**Contra los institucionales globales, tres decisiones ya tomadas resultaron correctas**, y vale dejarlas asentadas:

- Ninguno de los diez pone CTAs por público en el hero. Cero de diez. La decisión del 13 de octubre coincide con la práctica.
- Ninguno usa video de fondo en autoplay; todos lo ponen detrás de un clic. Aquí el hero sí autoreproduce, pero silenciado, con botón de pausa y saltándose móvil, datos limitados y `prefers-reduced-motion` — es el caso defendible.
- Ninguno nombra socios ni co-desarrolladores como dato de ficha. La decisión del 12 de octubre de dejar `partners` vacío coincide con los diez.

**Lo que falta no es diseño: es prueba.** Los institucionales no se ven mejor, se ven *verificables*. Hines pone una nota al pie numerada en cada cifra que dice qué incluye y a qué fecha. Tishman Speyer rotula sus renders como renders. Sordo Madaleno publica un índice de 505 proyectos filtrable por estatus. Oxford fecha todo el sitio con una línea en el pie. Ese es el terreno donde PAGA puede ganar, porque ya tiene los datos: los 36 desarrollos traen su campo `sources` y ninguna página lo muestra.

---

## Bloque A — Correcciones sin decisión de diseño

Son errores, no opiniones. Ninguno cambia el aspecto del sitio ni requiere aprobación de Dirección.

| # | Qué | Dónde | Por qué importa |
|---|---|---|---|
| A1 | El póster de un video oculto se descarga en cada visita al Inicio | `src/views/Home.astro:78` | 90 KB por carga, en los dos idiomas, por un reproductor que está `hidden`. Pasarlo a `data-poster` y asignarlo al abrir |
| A2 | El `.htaccess` no activa compresión | `public/.htaccess` | Sin `mod_deflate` la portada viaja en 25.8 KB en vez de 5.7 KB, y `/proyectos/` en 39.5 KB en vez de ~6 KB. Cinco líneas |
| A3 | Rutas absolutas en `data-src` y `data-srcset` | `Home.astro:20`, `Project.astro:46` | `astro-relative-links` no las reescribe: en la vista previa bajo `/nuevo/` se rompen el video del hero y el cambio de miniaturas. Afecta directo al plan de lanzamiento |
| A4 | El año está escrito a mano | `src/components/Timeline.astro:9` (`const nowYear = 2026`) | En enero de 2027 el sitio etiquetará mal "En curso" y "Estimado" sin que nadie toque el repositorio. `asOf` ya se exporta de `lib/data.ts` |
| A5 | El proyecto sin fecha encabeza el portafolio | `src/views/Projects.astro:12` (`?? 9999`) | Balcones 200, en planeación, abre "Todos los desarrollos" por delante de 27 entregas |
| A6 | El equipo se publica con la bandera `draft` puesta | `company.json:400` vs `About.astro:89` | Ocho nombres con puestos que el propio dato marca como no confirmados. La garantía sí tiene puerta (`showGuarantee`); el equipo no |
| A7 | Nombres de proyecto tecleados en la página | `src/views/Projects.astro:28` | "De Los Robles a Torre Acqua" viola la regla de CLAUDE.md y ya es inexacto |
| A8 | El anillo de foco se pierde sobre el cielo del hero | `global.css:44` | 1.98:1 contra 3:1 requerido, justo en los dos botones principales. Anillo blanco con sombra navy lo resuelve |
| A9 | El borde de campos y filtros está en 1.3–1.5:1 | `global.css:9`, `:199`, `:341`, `:349` | Es el único indicio de que ahí hay un control. Una variable aparte para bordes de control lo arregla sin tocar las líneas decorativas |
| A10 | Filtrar proyectos no anuncia nada | `Projects.astro:53` | La rejilla pasa de 36 a 18 tarjetas en silencio. Un `role="status"` con "18 de 36" |
| A11 | Los errores del formulario son solo color | `Contact.astro:138` | Sin mensaje por campo ni `aria-describedby`, y el estado dice "revisa los campos marcados" a quien no puede verlos |
| A12 | `scroll-behavior: smooth` ignora `prefers-reduced-motion` | `global.css:19` y `:85` | Una línea |

## Bloque B — Lo institucional: convertir datos en prueba

Aquí está la diferencia real contra el mercado, y casi todo ya existe en los datos.

**B1. Publicar la fuente de cada proyecto.** Los 36 traen `sources` ("Presentación institucional, junio 2026 (v6)", "136 units confirmed by Juan Diego") y ninguna página lo muestra. Una línea junto a "Datos a octubre de 2026" en cada ficha. Es el cambio más barato y más institucional de toda la revisión. Lo hace Hines con nota al pie numerada en cada cifra.

**B2. Separar lo entregado de lo que no, en la banda de cifras.** Hoy el cuarto número mezcla construido y en desarrollo en un solo dato; un banco lo descuenta entero porque no puede separarlo. Propuesta, todo calculable hoy: *25 años · 36 desarrollos (27 entregados) · 1,826 unidades de vivienda (948 entregadas) · 410,000 m² entregados · 218,000 m² en desarrollo*.

**B3. Rotular los renders como renders.** Tishman Speyer escribe "(Rendering)" en el pie de foto. Ninguno de los 14 mexicanos lo hace. En un portafolio que mezcla 27 entregas con 9 obras en curso, es la señal de honestidad más barata que existe. Requiere un campo nuevo en `projects.json`.

**B4. Unidades y superficie en la tarjeta de proyecto.** Hoy la tarjeta da nombre, ciudad, tipo, año y estatus; los dos números que permiten comparar escala están en el dato y no se imprimen. Seis líneas que mejoran a la vez el Inicio, el portafolio y las rejillas de plan maestro.

**B5. Filtros que piensen como el lector.** Con 36 desarrollos solo hay cuatro chips (Todos, México, EE.UU., En curso). Faltan tipo y ciudad, y el filtro no queda en la URL, así que nadie puede mandar por correo "el portafolio comercial en Estados Unidos". Tishman Speyer lo resuelve como una frase: "Show me all properties in [región] that are [tipo]".

**B6. Una página para socios de capital, dueños de terreno y bancos.** Oxford Properties le da a sus socios una entrada de primer nivel. El texto ya está escrito en `company.json` (`partnerTeaser`, redactado literalmente para ese público) y no se renderiza en ninguna parte.

**B7. El Proven Process no aparece en el Inicio.** "Identificamos · Estructuramos · Desarrollamos · Ejecutamos · Comercializamos · Entregamos" persuade más a este público que "Mejor producto", cuyo texto además roza la prohibición de superlativos. Hines nombra su proceso en cinco pasos; Lendlease en tres.

## Bloque C — Peso y hosting, ahora que no hay CDN

Medido sobre el build real. La primera carga del Inicio en escritorio son **5,591 KB**, de los cuales 4,941 KB son el video del hero. En teléfono son 497 KB y cero video: el corte por `max-width: 760px`, `saveData` y `prefers-reduced-motion` funciona y está bien resuelto.

**C1. Recodificar el hero.** 1920×1080 y 22.5 s a 1.80 Mbps para un fondo que se ve tras un velo navy al 62 %. A 1280×720, loop de 8–10 s, CRF 27–29 con tope de 1 Mbps, más una fuente WebM: **≤1.3 MB, ahorro de ~3.6 MB por visita**.

**C2. Sacar los dos films de 2:11 del servidor.** Son 75.3 MB en cuatro archivos y el 1080p se sirve por tamaño de pantalla, no por ancho de banda: un visitante de escritorio con módem 4G se baja 23.7 MB y ocupa un proceso de Apache durante ~47 segundos. En hosting compartido, veinte o treinta personas viéndolo a la vez pueden agotar los procesos del plan y tirar el sitio entero, **incluido el formulario**. Opciones, en orden: subirlos a YouTube o Vimeo como no listados e incrustarlos al clic (quita 75 MB y todo el riesgo), o quedarse solo con el 720p (ahorra 47 MB y la mitad del tráfico). La codificación en sí está bien hecha; esto no es un problema de calidad.

**C3. Los 31 logotipos PNG pesan 587 KB.** A WebP con alfa bajan ~80 %: −137 KB en la primera pintura del Inicio y −453 KB al recorrer la franja o la línea del tiempo.

**C4. Dos `sizes` mal calculados.** La foto principal de cada ficha descarga 307 KB para un hueco de 594 px, y las miniaturas usan el archivo de 480 px para un recuadro de 107 px. Juntos, −227 KB por ficha.

**C5. Subsetear Cormorant.** 23.6 KB —el 45 % del peso tipográfico— por una frase. Subsetada a los glifos que de verdad se usan: 2–3 KB. Y precargar las dos Jost que arrancan la página.

Con el bloque completo: **Inicio en escritorio de 5,591 KB a ~1,600 KB (−71 %)**, teléfono de 497 a ~250 KB, y el tráfico mensual de ~5 GB a ~1.6 GB por cada mil visitas. El paquete a subir baja de 112 MB a ~33 MB.

## Bloque D — Estructura y contenido (requiere decisión)

**D1. Subir "En desarrollo" en el Inicio.** Hoy el pipeline —878 unidades y 218,000 m² en obra— es la última sección, a cinco pantallas de scroll, después de dos secciones de mensaje. Es lo que un socio de capital vino a ver.

**D2. El sitio no tiene dirección, teléfono ni correo.** `offices` está vacío. Un analista de crédito haciendo diligencia sobre un desarrollador de 25 años encuentra un formulario y nada más. El código ya está escrito y espera el dato.

**D3. Los mensajes del formulario caen todos en un buzón**, aunque el sitio promete "cada mensaje llega directo al área que corresponde". El conector ya soporta buzón por perfil; falta decidir cuáles.

**D4. El equipo es nombre y puesto, sin trayectoria.** Un socio de capital evalúa a las personas antes que al portafolio. Dos líneas por socio fundador cambian la página.

**D5. Nada fechado después de octubre de 2026.** No hay noticias, avance de obra ni "última entrega": un banco no puede comprobar que la operación está viva. Parque Arauco publica incluso el calendario de cuándo volverá a publicar. La versión ligera: "Cifras actualizadas al 30 de septiembre · próxima actualización: enero de 2027".

**D6. El registro del español está partido.** La voz institucional usa usted ("Si tiene una oportunidad en mente, la evaluamos con usted") y el formulario tutea ("Cuéntanos quién eres"), justo en el momento del primer contacto. Para este público en México, el usted es lo esperado. Hay que decidirlo y escribirlo en CLAUDE.md.

**D7. Huecos de navegación.** No existe `/en/404/`; la 404 es bilingüe a medias. El logotipo del pie no liga al Inicio. No hay `BreadcrumbList` en las 72 páginas de proyecto. El conmutador de idioma no tiene `aria-label`.

---

## Lo que no hay que tocar

- **La línea de tiempo a dos carriles México / Estados Unidos.** Es la pieza más convincente del sitio y la que más se parece a un informe anual. Tiene un hueco —los proyectos sin fecha no aparecen— pero no se rediseña.
- **La disciplina de datos.** Todo sale de `projects.json` y `company.json` por `lib/data.ts`; cada cifra lleva fecha de corte; cada proyecto trae `sources` y `openQuestions`. Esta arquitectura es la razón de que la mitad de esta lista sea de esfuerzo bajo.
- **La contención del hero y el manejo del video.** Dos botones, sin CTAs por público, `preload="none"` real, y el teléfono sin descargar un byte de video.
- **Cero scroll horizontal a 360 px** en las 8 páginas probadas, un solo `h1` por página, `alt` en las 244 imágenes, `lang` correcto en las 89 páginas, y la paleta cumpliendo con holgura en todo lo que no toca el hero.
- **El espejo en inglés**, que está completo y bien escrito: los 36 proyectos, los 4 legales, `hreflang` correcto y conversión ft²/m² según idioma.

## Decisiones que hacen falta

1. ¿Los dos films se van a YouTube o Vimeo, o se quedan en Neubox solo en 720p?
2. ¿Domicilio, teléfono y correo oficiales para `offices`?
3. ¿Buzones por perfil, o se baja la promesa del formulario?
4. ¿Se publica el equipo ahora o se espera a que Dirección confirme los puestos?
5. ¿Usted o tú en el formulario?
6. ¿Se agregan a `projects.json` los campos de terreno, niveles e inicio de obra, y la marca de render?

---

## Lo que ya se aplicó — 5 de noviembre de 2026

Se hicieron los bloques A y C completos. B y D quedan pendientes porque dependen
de decisiones de Dirección.

### Peso de la primera carga

| | Antes | Ahora |
|---|---|---|
| Loop del hero (solo escritorio) | 5,060 KB · 1920×1080 | 1,827 KB · 1280×720 |
| Inicio, sin el loop | 531 KB | 336 KB |
| Proyectos y Contacto | — | 55 KB |
| **Inicio en escritorio, con todo** | **5,591 KB** | **2,163 KB** |

El loop conserva el material tal como está aprobado: los mismos tres planos, los
mismos 22.5 segundos, el mismo fundido de entrada y salida a negro. Lo único que
cambia es la resolución y la tasa de bits. Se descartó recortarlo a 8–10 segundos
—que es lo que recomendaba el bloque C— porque eso quita dos de los tres planos,
y eso ya no es peso: es edición, y la aprueba Dirección.

Comparación a tamaño real de un cuadro del primer plano, el más exigente porque
es monte denso: `docs/img/hero-comparacion-720.jpg`.

El comando, por si hay que regenerarlo con otro material:

```
ffmpeg -i hero-1080.mp4 -an -vf "scale=1280:720:flags=lanczos,hqdn3d=2:1:2:3,fps=25" \
  -c:v libx264 -profile:v high -preset slow -b:v 650k -maxrate 975k -bufsize 1950k \
  -pix_fmt yuv420p -g 50 -movflags +faststart -pass 1|2 hero-720.mp4
```

Lo demás del bloque C:

- Los 31 logos de proyecto a WebP: 573 → 249 KB. Los PNG de origen se borraron
  del árbol; están en el historial de git, en el commit «Revisión UX y peso de
  la primera carga».
- La marca del encabezado y del pie a WebP, al doble del tamaño al que se
  dibujan: 38 → 12 KB. El logo del pie, además, en diferido. El PNG se conserva
  porque es el favicon y el que declara el JSON-LD de la organización.
- Cormorant Garamond como subconjunto propio (ASCII, acentos del español,
  puntuación): 23.6 → 11.6 KB. Si alguna vez hace falta otro signo, hay que
  regenerarlo con `fontTools`.
- Las tres variantes de Jost se precargan con su nombre con hash, importado
  desde la plantilla para que coincida con el que pone la hoja de estilos.
- Las miniaturas de la galería a 256 px, que es como se dibujan.
- `.htaccess` comprime HTML, CSS y JS. Esto faltaba y es mío: la portada viajaba
  en 25.8 KB en vez de 5.7.

Todo lo de imágenes lo regenera `npm run imagenes`.

### Lo que se revisó y no se tocó

- **Las fotografías de los proyectos** ya están bien comprimidas. Recomprimir
  `hero.webp` (281 KB) solo la degradaba: a calidad 82 pesaba más que el
  original, y a 68 bajaba 12 KB con pérdida visible. Se quedan como están.
- **El video institucional de 2:11** (23.7 MB en escritorio, 13.9 en móvil)
  sigue igual. Dónde debe vivir es una de las decisiones pendientes.
- **Los .jpg de origen de los proyectos** siguen en `dist/` (unos 11 MB). No los
  baja ningún navegador moderno —el `srcset` sirve los WebP de `/img/w/`— pero
  son el `src` de respaldo y la fuente desde la que se regeneran las variantes.

### Correcciones del bloque A

Las doce, aplicadas y verificadas en el navegador. Están detalladas en el
mensaje del commit y en la descripción del pull request. Las dos que conviene
recordar porque no se ven:

- Las rutas de `data-src` de la galería y del hero ahora son relativas a la
  página. Sin esto, la vista previa en `/nuevo/` que documenta
  `docs/LANZAMIENTO.md` se habría visto sin galería y sin video.
- El póster del video institucional se adjunta al abrirlo. Chrome lo bajaba en
  cada visita a Inicio aunque el video estuviera oculto.

---

## Las seis decisiones, resueltas — 5 de noviembre de 2026

| # | Pregunta | Decisión de Juan Diego |
|---|---|---|
| 1 | ¿Los films a YouTube/Vimeo o solo 720p? | **Ninguna: fuera del sitio.** Queda una fotografía en Trayectoria. |
| 2 | ¿Domicilio, teléfono y correo? | **Solo teléfono y correo**, sin domicilio. Faltan los datos. |
| 3 | ¿Buzones por perfil? | **Sí**, y además copia al CRM con etiquetas. Falta saber cuál es el CRM. |
| 4 | ¿Se publica el equipo? | **No**, hasta que Dirección confirme los ocho puestos. |
| 5 | ¿Usted o tú? | **Usted**, en todo el español. |
| 6 | ¿Campos nuevos en projects.json? | **Solo la marca de render.** |

### Lo que apareció al revisar las imágenes

Se miraron una por una las 36 imágenes principales y las 12 de galería de los
proyectos en curso. Son renders siete:

- **En curso**, como es de esperarse: Torre Fiori, Amara Centro Primera Fase,
  Marena, Torre Acqua y Balcones 200. Sus galerías completas también son
  renders, así que la marca es por proyecto y no por imagen.
- **Entregados**, y esto sí llama la atención: **Parkview Pitic** (2020) y
  **Cinnamon Shore Six Town Center** (2024). Son obra terminada y la imagen que
  las representa es un render. Quedan marcadas, que es lo honesto, pero lo
  congruente es fotografiarlas.

Las otras cuatro en curso —Las Velas 6-7, Los Corales Bay Tower, Artesanías
Tercera Fase y Las Joyas 2— sí son fotografías: son fases nuevas de planes
maestros que ya tienen obra construida.

### Nota sobre B1, publicar la fuente de cada proyecto

La recomendación original no se puede aplicar tal cual. Los 36 proyectos traen
`sources`, pero son notas internas de trabajo —«54 units confirmed by juan
diego, 2026-10-12»—, no citas publicables. Si se quiere la línea de fuente en
cada ficha, hay que redactarla primero: algo como «Fuente: presentación
institucional de PAGA, junio 2026». Queda para el bloque B.

## El bloque B, aplicado — 6 de noviembre de 2026

Seis de los siete puntos. B3 (rotular los renders) ya se había hecho al resolver las decisiones
del 5 de noviembre.

**B1. La fuente de cada proyecto.** La recomendación original no se podía aplicar tal cual, y por
eso quedó pendiente: los 36 `sources` son notas internas —«54 units confirmed by juan diego,
2026-10-12»— que traen el nombre de quien confirmó el dato. Lo que se publica ahora lo arma
`sourceLine()` en `src/lib/data.ts` a partir de esas mismas notas, sin tocar los datos: de dónde
salió la cifra («Presentación institucional de PAGA, junio 2026 (v6)») y, cuando hubo una revisión
posterior, que la hubo y en qué mes. Nunca quién. Va bajo «Datos a octubre de 2026» en cada ficha.

**B2. Entregado y en curso, separados.** La banda pasa de cuatro cifras a cuatro cifras que dicen
el doble, todas calculadas desde `projects.json`:

| | |
|---|---|
| 25 | años desde 2001 |
| 36 | desarrollos en México y Estados Unidos, 27 de ellos entregados |
| 1,826 | unidades de vivienda, 948 entregadas |
| 410,000 | m² entregados, y 218,000 m² en desarrollo |

El cuarto número era antes un solo dato de 628,000 m² que mezclaba lo construido con lo que
todavía no existe.

**B4. Escala en la tarjeta.** Unidades y superficie, que ya estaban en el dato y no se imprimían.
Las unidades solo donde las hay: los cuatro centros comerciales y el edificio de oficinas muestran
superficie y nada más.

**B5. Filtros que se combinan y viven en la dirección.** De cuatro chips a cuatro filtros —país,
estatus, tipo y ciudad— que se cruzan entre sí. El resultado queda en la URL
(`/proyectos/?pais=US&tipo=residencial`), así que un enlace filtrado se puede mandar por correo, que
era justo lo que no se podía hacer. La página lee los nombres en los dos idiomas y escribe el del
idioma en que está. Las opciones salen del propio listado: un desarrollo en una ciudad nueva
aparece en el filtro solo.

**B6. El texto para socios, renderizado.** `partnerTeaser` de `company.json` estaba escrito para
propietarios de terrenos, socios de capital y codesarrolladores, y no se mostraba en ninguna parte.
Ahora es una sección del Inicio, después del proceso. La liga va al formulario **sin perfil
preseleccionado**, conforme a la decisión del 13 de octubre: el visitante elige su perfil ahí.

**B7. El proceso, en el Inicio.** Los seis pasos como una línea, con liga a Nosotros para los
textos completos. No se tocaron los Tres Diferenciadores.

### Lo que no se hizo, y por qué

- **La página de socios como tal.** B6 pedía «una página de primer nivel». Se renderizó el texto,
  que era lo que faltaba, pero una entrada nueva en el menú principal cambia la estructura del
  sitio y eso es el bloque D, no este. `ui.nav.partners` ya existe en `company.json` («Socios» /
  «Partners») esperando esa decisión.
- **Reescribir «Mejor producto».** B7 observa, de paso, que el texto del primer diferenciador
  —«que cada desarrollo sea un activo superior dentro de su segmento»— roza la prohibición de
  superlativos. Es cierto, pero los Tres Diferenciadores son contenido del V/TO y se cambian en
  Dirección, no aquí.

## El bloque D, aplicado — 6 de noviembre de 2026

Cinco de los siete puntos. D2 y D6 ya se habían resuelto antes de llegar aquí.

**D1. El pipeline subió.** «Proyectos en curso» era la última sección del Inicio, a cinco
pantallas de scroll y después de dos secciones de mensaje. Ahora va inmediatamente después de la
banda de cifras: primero lo que está pasando hoy, luego los 25 años. Queda a 1,300 px del
encabezado en lugar de a 4,500.

**D2. Ya hecho.** Teléfono y correo publicados el 6 de noviembre. Sin domicilio, por decisión.

**D3. La promesa del formulario, corregida.** Decía «cada mensaje llega directo al área que
corresponde», y desde que los leads van al CRM eso no es lo que ocurre: todo el correo llega a un
solo buzón y la clasificación la hace la Suite. Ahora dice lo que sí es cierto y lo que de verdad
justifica preguntar el perfil: «Su perfil nos dice con qué información responderle».

**D4. No se puede hacer todavía.** La trayectoria de cada persona no se puede publicar mientras el
equipo siga en borrador esperando a Dirección.

**D5. Fecha de la próxima actualización.** `nextUpdate` en `projects.json`, hoy en enero de 2027,
se imprime junto a la fecha de corte: «Cifras a octubre de 2026… Próxima actualización: enero de
2027.» **Es un compromiso, no un dato**: si Dirección prefiere otra fecha o ninguna, se cambia o se
vacía ese campo y la frase desaparece sola.

**D6. Ya hecho**, y quedaba un resto: la 404 seguía tuteando («la página que buscas», «Puedes
ver»). Corregido.

**D7. Los cuatro huecos de navegación.**

- La 404 es ahora simétrica en los dos idiomas: el mismo mensaje y las mismas dos salidas en
  español y en inglés, en vez de una página en español con una frase en inglés al pie.
- El logotipo del pie liga al Inicio, con su etiqueta para lector de pantalla.
- `BreadcrumbList` en las 72 fichas de proyecto, en los dos idiomas.
- El conmutador de idioma tiene `aria-label`: antes un lector de pantalla solo leía «EN».

### Lo que no se hizo, y por qué

- **Una 404 propia en `/en/404/`.** Apache sirve un solo `ErrorDocument` para todo el sitio, así
  que una 404 en inglés solo entraría en funcionamiento con un segundo `.htaccess` dentro de
  `/en/`. Ese archivo viajaría en el zip, y un zip hecho en Windows puede dejarlo en permisos 0600
  —que es exactamente lo que tira una carpeta entera con 403, y la razón por la que el `.htaccess`
  de la raíz se pega a mano—. Por un riesgo así no vale la pena: la 404 bilingüe resuelve lo mismo
  sin tocar la configuración del servidor.
