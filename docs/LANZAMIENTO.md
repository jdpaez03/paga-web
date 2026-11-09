# Lanzamiento de pagadesarrollos.com — paso a paso

**Decisión (5 nov 2026, Juan Diego):** el sitio se publica en el **cPanel de Neubox**, el mismo
servidor y el mismo plan que ya sostienen el dominio y el correo de Microsoft 365. Sustituye la
decisión del 14 de octubre de usar Cloudflare Pages: el plan de Neubox no se puede cancelar porque
de él depende el correo, y todavía no sabemos a través de quién ni con quién se compró el dominio,
así que nada se mueve de ahí.

Lo bueno de esta ruta: **el DNS no se toca**, el correo no se toca, no hay cuenta nueva ni costo
nuevo, y `www` y el dominio sin www ya apuntan a ese servidor. Lo que cuesta: el sitio se compila en
una computadora y se sube a mano, y el formulario va en PHP (`public/api/contact.php`) en lugar de la
función de Cloudflare.

Nada de esto se hace antes de que Dirección apruebe el sitio.

---

## 0. Antes de empezar

**Aprobaciones y datos (son lo único que hoy detiene el lanzamiento):**

- [ ] Dirección aprueba el sitio. Jurídico aprueba los avisos legales.
- [ ] Los 8 textos de `src/legal/` (ES y EN) ya no llevan `draft: true` ni datos entre corchetes
      (`[DOMICILIO FISCAL]`, `[CORREO DE CONTACTO DE DATOS PERSONALES]`, `[FECHA DE ÚLTIMA
      ACTUALIZACIÓN]`, las `[DECISIÓN DE JURÍDICO: …]`). Con `preview: false` en
      `src/data/site.json`, `npm run build` **falla** mientras quede uno en borrador.
- [x] Teléfono y correo oficiales cargados en `offices` de `src/data/contact.json`: oficina
      general de Monterrey, `+52 (81) 8363 3737` y `notificaciones@pagadesarrollos.com`. Sin
      domicilio, por decisión del 5 de noviembre; el campo `address` sigue disponible.
- [ ] **Que `notificaciones@pagadesarrollos.com` exista en Microsoft 365 y alguien lo lea.** Es el
      buzón que se publica en el sitio y también al que entrega el formulario.

**Respaldos, antes de tocar el cPanel:**

- [ ] Respaldo de `public_html` completo: cPanel → **Administrador de archivos** → `public_html` →
      **Seleccione Todo** → **Comprimir** → Zip, guardado **fuera de `public_html`** (en la carpeta
      de inicio), para que no se pueda descargar desde internet.
- [ ] Respaldo de la base de datos del WordPress actual (cPanel → **phpMyAdmin** → Exportar), por si
      algún día hay que levantarlo de nuevo.
- [ ] Captura de toda la zona DNS actual (cPanel → **Zone Editor**), como referencia. En esta ruta no
      se cambia ningún registro, pero conviene tener la foto.

## 1. Revisar el servidor (una sola vez)

En el cPanel, antes de subir nada:

- [ ] **PHP 7.4 o superior**: cPanel → **Seleccionar versión de PHP**. Con `curl`, `mbstring`, `json`
      y `openssl` activos. El conector se probó en PHP 8.3.
- [ ] **Enrutamiento del correo del dominio en "Remote Mail Exchanger"**: cPanel → **Email Routing**
      (o *Enrutamiento de correo electrónico*) → `pagadesarrollos.com`. **Este es el punto crítico
      del formulario.** Como el MX apunta a Microsoft 365, el correo que manda el servidor tiene que
      salir hacia Microsoft. Si el dominio está en *Local Mail Exchanger*, el mensaje se queda en el
      servidor y nunca llega al Suite, sin dar error.
- [ ] **El SPF autoriza a este servidor.** Hoy lo hace con `+a`, que incluye la IP del hosting
      (198.59.144.118). Si TI quita `+a` (está sugerido en la revisión de correo), hay que agregar
      `ip4:198.59.144.118` al SPF, o el correo del formulario se irá a spam.
- [ ] **Espacio en disco.** El sitio compilado pesa **40 MB**, de los cuales 36 MB son imágenes y
      2.2 MB el video del hero. Revisa la cuota del plan antes de subir.
- [ ] Apache 2.4 (lo normal en cPanel). Si al subir el `.htaccess` el sitio responde 500, el bloque
      `Require all denied` del final es la línea a revisar: en Apache 2.2 se escribe distinto.

## 2. Compilar y armar el paquete

El cPanel no compila: el sitio se compila aquí y se sube el resultado.

```bash
npm ci
npm run build
```

Queda todo en `dist/`: 89 páginas, `assets/`, `img/`, `video/`, `api/contact.php` y `.htaccess`.

El `.htaccess` **no va en el zip**. Un zip armado en Windows puede traerlo en permisos 0600, y con un
`.htaccess` en 0600 **todo el sitio responde 403** (pasó en Distrito Amāra). Se crea a mano en el
editor del Administrador de archivos, pegando el contenido de [`public/.htaccess`](../public/.htaccess).

Zip del sitio completo, con el video dentro (37.6 MB medidos el 9 de noviembre), en PowerShell. Si
el Administrador de archivos lo rechazara por tamaño, saca `video/` del paquete y súbelo aparte:

```powershell
$stage = "$env:TEMP\paga-sitio"; Remove-Item $stage -Recurse -Force -ErrorAction SilentlyContinue; Copy-Item dist $stage -Recurse; Remove-Item "$stage\.htaccess" -Force; Compress-Archive -Path "$stage\*" -DestinationPath "$env:USERPROFILE\Downloads\paga-sitio.zip" -Force
```

Ahí va todo: `dist/video/` son 2.2 MB —el loop del hero a 720p y sus dos imágenes de respaldo—, no
los 74 MB de los cuatro films de los 25 años que se quitaron el 5 de noviembre.

## 3. Etapa 1 — vista previa en `/nuevo/`, sin tocar el sitio actual

Para que Dirección revise el sitio en una dirección real, con el WordPress todavía en su lugar.

1. Administrador de archivos → en `public_html` crea la carpeta **`nuevo`** con **+ Carpeta**.
   Revisa que aparezca con ícono de carpeta: si la carpeta no existe al extraer, cPanel escribe todo
   sobre un solo **archivo** llamado `nuevo`.
2. Sube `paga-sitio.zip` dentro de `public_html/nuevo/`, elige **Extract** y luego borra el zip.
3. Comprueba que quedó `nuevo/video/hero-720.mp4`: viene dentro del zip, ya no se sube aparte. Si
   falta, el hero se ve con su imagen fija y nadie lo nota hasta que alguien pregunta por el video.
4. Crea `public_html/nuevo/.htaccess` con **+ Archivo** y pega esto (es más corto que el del sitio:
   las redirecciones de HTTPS y de www viven en la raíz, que todavía es del WordPress):

   ```apache
   Options -Indexes
   DirectoryIndex index.html
   ErrorDocument 404 /nuevo/404.html
   <IfModule mod_headers.c>
     Header always set X-Robots-Tag "noindex"
     Header set Cache-Control "no-cache"
   </IfModule>
   ```

5. Revisa en **https://www.pagadesarrollos.com/nuevo/**.

Notas de esta etapa:

- Con `"preview": true` en `src/data/site.json`, cada página manda `noindex` y además el `.htaccess`
  de arriba manda `X-Robots-Tag: noindex`: Google no indexa la vista previa.
- El formulario **sí funciona** aquí, porque la ruta al conector es relativa a la página: queda en
  `/nuevo/api/contact.php`. Los mensajes de prueba llegan al buzón de verdad; avisa a quien lo revise.
- Las ligas del sitio son relativas, así que todo navega bien dentro de `/nuevo/`. La única cosa que
  no se puede probar aquí es la 404 de la raíz del dominio.

## 4. Etapa 2 — el sitio en la raíz

Solo con las aprobaciones del punto 0 y la vista previa revisada.

1. En `src/data/site.json`, cambia `"preview": true` a `false`. Eso quita la banda "Vista previa" y
   el `noindex`, y hace que `/robots.txt` deje entrar a los buscadores. Vuelve a compilar y a armar
   el zip (punto 2).
2. Respaldo de `public_html` otra vez, si el del punto 0 ya tiene días.
3. En `public_html`, borra los archivos y carpetas del WordPress. **No toques** `.well-known/`,
   `cgi-bin/`, ni los archivos que haya creado el propio cPanel. Si aparece un `.htaccess` con
   bloques como `# php -- BEGIN cPanel-generated handler`, **conserva esos bloques**: de ellos puede
   depender la versión de PHP. (El correo no vive en `public_html`: borrar el WordPress no lo toca.)
4. Sube `paga-sitio.zip` a `public_html/`, **Extract**, borra el zip, y comprueba que quedó
   `public_html/video/hero-720.mp4`: viene dentro del zip.
5. Crea `public_html/.htaccess` con el editor, pegando [`public/.htaccess`](../public/.htaccess).
6. **Revisa permisos** en la columna *Permisos* del Administrador de archivos: archivos **0644**,
   carpetas **0755**. Especialmente `.htaccess` y `api/contact.php`. Se corrigen con **Permisos**.
7. cPanel → **Domains** → **Force HTTPS Redirect** activado, y en **SSL/TLS Status** que el
   certificado cubra `pagadesarrollos.com` y `www.pagadesarrollos.com`.
8. Cuando todo esté verificado, borra `public_html/nuevo/`.

## 5. El formulario

El conector es [`public/api/contact.php`](../public/api/contact.php) y queda publicado en
`/api/contact.php`. Manda cada mensaje con `mail()`, o sea con el Exim del cPanel, que lo entrega al
MX del dominio: Microsoft 365.

- **Destinatarios del correo.** Están al principio del archivo (`$CONFIG`): `to` es el buzón que
  recibe los mensajes —`notificaciones@pagadesarrollos.com`, el mismo que el sitio publica— y
  `from` tiene que ser una dirección **del propio dominio** para que el SPF cuadre. Existe `to_by_profile` para dar un buzón distinto a cada perfil, pero no se usa: el
  reparto lo hace el CRM.
- **Cambiarlos sin recompilar:** crea `/home/<usuario>/paga-contacto-config.php` (fuera de
  `public_html`) con lo que quieras sobrescribir. Este archivo es también donde vive la llave del
  CRM, y por eso nunca está en el repositorio:

  ```php
  <?php
  return [
      'to' => 'notificaciones@pagadesarrollos.com',
      'crm_key' => 'LA-LLAVE-QUE-SE-GENERA-EN-EL-VPS',
  ];
  ```

  Permisos: `chmod 600`. Lo lee PHP y nadie más.

### El CRM: módulo de Leads de PAGA Suite

Cada mensaje de un perfil comercial crea un **lead** en `POST https://pagasuite.com/api/leads`, con
la llave en la cabecera `Authorization: Bearer`. El cliente es
[`public/api/crm.php`](../public/api/crm.php) y la entrega la hace un cron, no el formulario.

**Por qué por cron y no al momento.** Para que el visitante no espere a la API, y para que haya un
solo lugar donde se razone sobre los reintentos. Si el formulario llamara a la API y se le acabara
el tiempo de espera, el visitante vería un error por algo que sí entró.

**La ventana de deduplicación.** La Suite no crea un lead repetido si en los **60 minutos**
anteriores ya entró uno con el mismo correo, o con los mismos últimos 10 dígitos del teléfono:
contesta 200 con el `id_lead` que ya existía y `"duplicado": true`. Casa contra todos los leads,
también los capturados a mano. Eso es lo que permite reintentar un timeout sin duplicar, y de ahí
salen las dos condiciones del estado `revisar` más abajo.

Esa ventana es una variable de entorno del lado de la Suite, y **el cliente tiene que ir en paralelo**:
`crm_ventana_dedup` en `crm.php` (3600 s) y `crm_margen_dedup` (600 s, para no apostar al último
segundo de la ventana). Si allá se cambia, aquí también. La regla es que la ventana debe
ser al menos tan larga como el horizonte de reintentos: con la espera actual (2, 4, 5, 5 y 5 minutos,
tope `crm_espera_max` = 300 s) y el cron cada cinco, los seis intentos de un lead caen a los 5, 10,
15, 25, 35 y **45 minutos** de recibido, dentro de los 50 que deja el margen.

**Lo que se manda.** Solo `nombre` es obligatorio. `fuente` va siempre en `web`.
`nom_proyecto_interes` se traduce del slug del sitio al nombre que el CRM reconoce, y **si no hay
uno que case, va en `null`**: la API no valida ese campo, así que un nombre inventado entraría con
200 y después ese lead no saldría en ningún filtro. Hoy casan siete de los nueve proyectos del
desplegable; Las Joyas (2) y Las Velas (6, 7) no están en el catálogo del CRM y por eso van sin
proyecto. Marena va como `LAS PERLAS`: es el mismo desarrollo con otro nombre comercial, y la llave
del catálogo en la Suite sigue siendo la vieja. La tabla está en `crm.php`, es estática a propósito
—la llave de ingesta no puede leer `GET /projects`— y es el único lugar donde se toca.

**Si un desarrollo está cerrado.** Un proyecto vendido y archivado rechaza leads nuevos con 403. El
lead, en cambio, es real: se reenvía **una sola vez** sin proyecto, entra, y queda una línea
`sin_proyecto` en `enviados.log` para que alguien lo clasifique. El otro 403 —la llave usada en una
ruta que no es `POST /leads`— es permanente y no se reintenta. Se distinguen por el `detail`.

**Lo que NO se manda.** El perfil, el mensaje, la página de entrada y la campaña: la API de leads no
tiene dónde guardarlos y no se meten a la fuerza en otro campo. Esos datos viajan en el correo, que
por eso no es redundante.

**Perfiles.** Solo van al CRM los cinco comerciales: inversión, terreno, banco, comprador y broker.
Prensa, proveedores y bolsa de trabajo llegan por correo y nada más, para no ensuciar un módulo de
leads de venta. Se cambia con `crm_perfiles` en la configuración.

**El cron.** Copia `scripts/enviar-leads.php` a `/home/<usuario>/paga-enviar-leads.php` —**fuera de
`public_html`**— y prográmalo cada cinco minutos en *Trabajos cron* del cPanel:

```
/usr/local/bin/php /home/<usuario>/paga-enviar-leads.php >> /home/<usuario>/paga-leads/cron.log 2>&1
```

Para ver cómo va la cola, sin tocar la API:

```
php /home/<usuario>/paga-enviar-leads.php --estado
```

**Una corrida a la vez.** Una corrida puede pasar de los cinco minutos (20 leads, hasta 12 s por
intento, y un segundo intento cuando el desarrollo está cerrado), así que la siguiente se encimaría
y las dos mandarían el mismo lead. El script toma un candado —`flock` sobre
`/home/<usuario>/paga-leads/.candado`, un archivo vacío que no se borra— y si ya hay una corrida en
marcha, la nueva no espera: escribe `otra corrida sigue en marcha: esta se salta` y sale con 0, sin
correo de fallo del cPanel. Si eso aparece en `cron.log` una vez, es normal; si aparece corrida tras
corrida, la API está respondiendo muy lento y hay que mirarla. `--estado` no toma el candado: se
puede consultar la cola mientras una corrida trabaja.

**La cola.** Cada lead se escribe en `/home/<usuario>/paga-leads/<REFERENCIA>.json` antes de
intentar nada, así que no se pierde aunque se caiga el proceso. Estados:

| Estado | Qué pasó | ¿Se reintenta? |
|---|---|---|
| `pendiente` | Aún no se entrega, o la API contestó 429 o 5xx, o no hubo red, o se acabó el tiempo de espera y la ventana de deduplicación cubre el reintento | Sí, con espera creciente |
| `enviado` | 200 con `id_lead`, sea un lead nuevo o uno que el guardia reconoció | — el archivo se borra |
| `rechazado` | 400, 401, 422, o un 403 de ruta equivocada | No: reintentar con el mismo cuerpo da lo mismo |
| `revisar` | Se acabó el tiempo de espera **y** reintentar podría duplicar: el lead no trae correo ni teléfono de 10 dígitos, o ya salió de la ventana | **No**, a propósito: hay que capturarlo a mano |
| `agotado` | Se acabaron los seis intentos | No |

El 200 siempre es éxito, también cuando trae `"duplicado": true`: significa que el lead ya estaba y
no se creó otro, que es exactamente lo que se buscaba. Queda anotado en `enviados.log`.

Cuando un lead se entrega, **el archivo se borra** y solo queda una línea en
`paga-leads/enviados.log` con la referencia y el `id_lead`, sin datos personales. Los otros estados
se quedan en disco para que una persona los capture a mano: son el único caso, junto con
`paga-contacto-fallidos.log`, en que hay datos personales en el servidor.

Topes, porque la API no tiene rate limiting y el único freno es éste: **6 intentos por lead** y
**20 leads por corrida** (`crm_max_intentos` y `crm_max_por_corrida`). Si se suben los intentos o
`crm_espera_max`, hay que volver a hacer la cuenta: el último intento tiene que caber dentro de la
ventana de deduplicación, o los últimos no corren.

**Dos cosas que hay que revisar en el servidor** antes de dar esto por hecho:

1. **Que el PHP del cPanel pueda salir por HTTPS.** En hosting compartido a veces está bloqueado o
   pasa por proxy. Pruébalo por SSH o con un cron de una sola vez:
   `php -r 'var_dump(curl_version()["version"] ?? null);'` y después un `curl -I https://pagasuite.com`
   desde el servidor. Si hay proxy, se pone en `crm_proxy` (`'host:puerto'`).
2. **Que la extensión cURL esté activa** (*Selector de PHP → Extensiones* en el cPanel). Sin ella el
   cliente no intenta nada y lo dice en el log.

Si la salida HTTPS estuviera bloqueada, **no hay plan B automático**: no existe un buzón que
alimente al CRM. Los leads se quedarían en la cola y habría que capturarlos a mano desde el correo.

**Lo único que hay que confirmar con un envío real:** que el teléfono se acepte. Se manda solo con
dígitos (`528112345678`), que es el formato del contrato; si la API lo rechazara con 400, se ve en
`--estado` y se cambia en una línea de `crm.php`. El prefijo del país no estorba para la
deduplicación: la Suite casa por los últimos diez dígitos.

- **Prueba de humo**, antes de probar el formulario completo: abre `https://www.pagadesarrollos.com/api/contact.php`
  en el navegador. Tiene que responder un JSON `{"error":"method_not_allowed",…}`. Si sale el código
  PHP en pantalla, el PHP no está corriendo; si sale un 500, revisa el `error_log` de la carpeta.
- **Prueba de verdad:** manda un mensaje de perfil comercial y uno de bolsa de trabajo. Los dos
  tienen que llegar al Suite, con el **Responder** apuntando al visitante y los acentos bien. A los
  cinco minutos, `--estado` tiene que mostrar la cola vacía y una línea nueva en `enviados.log`
  para el comercial, y nada para el de bolsa de trabajo.
- Si no llega: revisa el enrutamiento del correo (punto 1), la carpeta de spam del buzón, y el
  `error_log` de `public_html/api/`. Cada envío deja ahí una línea con su referencia (`PAGA-…`) y el
  código, sin datos personales.
- Si `mail()` falla, el formulario responde "No pudimos enviar su mensaje" y guarda el mensaje
  completo en `/home/<usuario>/paga-contacto-fallidos.log`, fuera de `public_html`, para que ningún
  contacto se pierda. Es el único caso en que se guardan datos personales en el servidor: revísalo y
  vacíalo cuando se resuelva. **El lead sí llega al CRM**: se encola antes de intentar el correo,
  así que una caída del correo no se lo lleva. Cada línea del log lo dice: `crm=encolado` significa
  que el lead ya está en la cola y el cron lo entrega —solo hay que contestar el mensaje—, y
  `crm=no` que no se encoló, porque el perfil no va al CRM (prensa, proveedores, bolsa de trabajo)
  o porque la llave no está puesta. El formato de la línea es: fecha, referencia, `crm=…` y los
  datos del envío en JSON.
- Límites: 10 envíos por hora por dirección IP, 32 KB por mensaje, campo trampa contra robots y solo
  se aceptan envíos desde el propio dominio.

## 6. Verificación (el mismo día)

```bash
curl -I https://www.pagadesarrollos.com/                 # 200
curl -I https://pagadesarrollos.com/                     # 301 → https://www.pagadesarrollos.com/
curl -I http://www.pagadesarrollos.com/                  # 301 → https://
curl -I https://www.pagadesarrollos.com/proyectos/       # 200
curl -I https://www.pagadesarrollos.com/proyectos        # 301 → /proyectos/
curl -I https://www.pagadesarrollos.com/en/              # 200
curl -I https://www.pagadesarrollos.com/no-existe        # 404 con la página del sitio
curl -I https://www.pagadesarrollos.com/sitemap-index.xml # 200
curl -I https://www.pagadesarrollos.com/robots.txt       # 200, y sin "Disallow: /"
curl -I https://www.pagadesarrollos.com/_headers         # 403: no tiene por qué ser público
```

Y a mano, en escritorio y en celular:

- [ ] Inicio, Nosotros, Proyectos, cada proyecto, Contacto y los 4 avisos legales, en ES y EN.
- [ ] El video de fondo del hero corre en escritorio; en celular queda su imagen fija. Y en Inicio,
      que Trayectoria muestre la fotografía, en las dos versiones de idioma.
- [ ] El cambio de idioma lleva a la página equivalente.
- [ ] Un mensaje de prueba del formulario por cada perfil con buzón propio.
- [ ] Al compartir la URL en WhatsApp aparecen la imagen y el título.
- [ ] Sin scroll horizontal a 360 px.
- [ ] **Correo:** manda un correo desde Gmail a una cuenta @pagadesarrollos.com y respóndelo. En
      admin.microsoft.com → Dominios, `pagadesarrollos.com` debe seguir en "Healthy".
- [ ] Google Search Console: verifica el dominio y envía
      `https://www.pagadesarrollos.com/sitemap-index.xml`.

## 7. Cómo regresar (si algo falla)

No hay nada que revertir en el DNS, porque no se cambió. Para volver al WordPress:

1. Administrador de archivos → borra el contenido de `public_html`.
2. Sube y extrae el zip de respaldo del punto 0.
3. Si el WordPress usaba base de datos, confirma que siga ahí (phpMyAdmin); si no, restaura el
   respaldo.

Mientras el respaldo exista, el regreso es de minutos y no depende de propagación.

## 8. Lo que NO se toca nunca

En esta ruta **no se cambia ningún registro de DNS**. Y en particular, nunca se toca nada de esto,
que es el correo, Teams y los servicios de Microsoft 365, más los accesos al servidor:

- `pagadesarrollos.com` **MX** → `pagadesarrollos-com.mail.protection.outlook.com`
- `pagadesarrollos.com` **TXT** (SPF) y `_dmarc` (DMARC)
- `autodiscover` CNAME → `autodiscover.outlook.com`
- `selector2._domainkey` CNAME (DKIM de Microsoft) y `default._domainkey` TXT
- `sip`, `lyncdiscover` CNAME y los SRV `_sip._tls` y `_sipfederationtls._tcp`
- `pagadesarrollos.com` **A** 198.59.144.118, el `CNAME` de `www`, y `mail`, `ftp`, `cpanel`,
  `webmail`, `whm`, `webdisk`, `cpcontacts`, `cpcalendars`, `autoconfig`
- Registros técnicos de cPanel: `_acme-challenge…`, `_cpanel-dcv…`, `comodoca.com`, `_caldav…`,
  `_carddav…`

El plan de hosting de Neubox **no se cancela**: sostiene el dominio, el correo, el DNS y ahora el
sitio.

## 9. Después del lanzamiento

- **En enero de 2027 hay que actualizar las cifras.** El sitio lo promete: la banda dice «Próxima
  actualización: enero de 2027», confirmado por Juan Diego el 6 de noviembre. Es revisar `asOf` y
  los datos de `projects.json`, mover `nextUpdate` al siguiente corte y volver a subir. Si se
  decide no seguir publicando la promesa, se vacía `nextUpdate` y la frase desaparece sola.
- **Cada cambio del sitio** es volver a compilar y volver a subir: `npm run build`, armar el zip del
  punto 2 y extraerlo en `public_html`. El video viaja dentro del zip: no hay paso aparte.
- **Analítica: pendiente de decidir.** Cloudflare Web Analytics ya no aplica. Hoy el sitio no mide
  nada: el `track()` de Contacto no hace nada si no hay script de analítica. Si se quiere medir, lo
  congruente con el aviso de cookies —que dice que el sitio no usa cookies de seguimiento— es algo
  sin cookies, autoalojado o tipo Plausible/Umami. Si se eligiera Google Analytics, **hay que
  cambiar el aviso de cookies y pedir consentimiento**, y eso vuelve a pasar por Jurídico.
- Con Maparnet: confirmar a nombre de quién está el dominio, su fecha de vencimiento y la renovación
  automática. Mientras no se sepa, no se mueve nada de ahí.
- Revisión de TI (no urgente): si se quita `+a` del SPF, agregar antes `ip4:198.59.144.118`;
  confirmar la firma DKIM de Microsoft 365 (`selector1` y `selector2`); quitar `doy.com.mx` del
  tenant.

## 10. Apéndice: Cloudflare Pages, si algún día se retoma

Todo lo necesario sigue en el repositorio: `functions/api/contact.js` (el formulario como función de
Cloudflare, con Resend) y `public/_headers` (encabezados y caché). En ese caso: framework preset
**Astro**, build `npm run build`, salida `dist`, `NODE_VERSION=20`, variables `RESEND_API_KEY`,
`CONTACT_TO` y `CONTACT_FROM`, dominio `www.pagadesarrollos.com` dado de alta **antes** de cambiar el
DNS, y el `CNAME` de `www` apuntado a `<proyecto>.pages.dev`. El límite de Cloudflare es de 25 MiB
por archivo, y ya nada se le acerca. Eso requiere cuenta de Cloudflare a nombre de
PAGA y tocar un registro de DNS; nada de eso hace falta en la ruta del cPanel.
