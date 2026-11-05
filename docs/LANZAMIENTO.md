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
- [ ] Domicilio, teléfono y correo oficiales confirmados por Jurídico y cargados en `offices` de
      `src/data/contact.json` (`city` ES/EN, `address`, `phone`, `email`). Hoy `offices` está vacío,
      así que Contacto no muestra domicilio y el footer tampoco.
- [ ] **Buzón del Suite que recibe el formulario**, confirmado y probado (por ejemplo
      `contacto@pagadesarrollos.com`). Si va a haber uno por perfil, la lista completa.

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
- [ ] **Espacio en disco.** El sitio compilado pesa ~112 MB, de los cuales **79 MB son 4 archivos de
      video**. Revisa la cuota del plan antes de subir.
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
`.htaccess` en 0600 **todo el sitio responde 403** (pasó en Distrito Amara). Se crea a mano en el
editor del Administrador de archivos, pegando el contenido de [`public/.htaccess`](../public/.htaccess).

Zip del sitio sin los videos (~33 MB, para que entre en el límite de subida del Administrador de
archivos), en PowerShell:

```powershell
$stage = "$env:TEMP\paga-sitio"; Remove-Item $stage -Recurse -Force -ErrorAction SilentlyContinue; Copy-Item dist $stage -Recurse; Remove-Item "$stage\video" -Recurse -Force; Remove-Item "$stage\.htaccess" -Force; Compress-Archive -Path "$stage\*" -DestinationPath "$env:USERPROFILE\Downloads\paga-sitio.zip" -Force
```

Los 5 videos de `dist/video/` se suben aparte, directo a la carpeta `video/`, uno por uno o por FTP.
Pesan 23, 23, 14, 14 y 4.9 MB.

## 3. Etapa 1 — vista previa en `/nuevo/`, sin tocar el sitio actual

Para que Dirección revise el sitio en una dirección real, con el WordPress todavía en su lugar.

1. Administrador de archivos → en `public_html` crea la carpeta **`nuevo`** con **+ Carpeta**.
   Revisa que aparezca con ícono de carpeta: si la carpeta no existe al extraer, cPanel escribe todo
   sobre un solo **archivo** llamado `nuevo`.
2. Sube `paga-sitio.zip` dentro de `public_html/nuevo/`, elige **Extract** y luego borra el zip.
3. Sube los 5 videos a `public_html/nuevo/video/`.
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
4. Sube `paga-sitio.zip` a `public_html/`, **Extract**, borra el zip, y sube los 5 videos a
   `public_html/video/`.
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

- **Destinatarios.** Están al principio del archivo (`$CONFIG`): `to` es el buzón general y
  `to_by_profile` permite uno por perfil (`inversion`, `terreno`, `banco`, `comprador`, `broker`,
  `proveedor`, `prensa`, `empleo`). `from` tiene que ser una dirección **del propio dominio** para
  que el SPF cuadre.
- **Cambiarlos sin recompilar:** crea `/home/<usuario>/paga-contacto-config.php` (fuera de
  `public_html`) con lo que quieras sobrescribir:

  ```php
  <?php
  return [
      'to' => 'contacto@pagadesarrollos.com',
      'to_by_profile' => ['terreno' => 'terrenos@pagadesarrollos.com'],
  ];
  ```

- **Prueba de humo**, antes de probar el formulario completo: abre `https://www.pagadesarrollos.com/api/contact.php`
  en el navegador. Tiene que responder un JSON `{"error":"method_not_allowed",…}`. Si sale el código
  PHP en pantalla, el PHP no está corriendo; si sale un 500, revisa el `error_log` de la carpeta.
- **Prueba de verdad:** manda un mensaje por cada perfil que tenga buzón propio y revisa que llegue
  al Suite, que el **Responder** del correo vaya al visitante y que los acentos se vean bien.
- Si no llega: revisa el enrutamiento del correo (punto 1), la carpeta de spam del buzón, y el
  `error_log` de `public_html/api/`. Cada envío deja ahí una línea con su referencia (`PAGA-…`) y el
  código, sin datos personales.
- Si `mail()` falla, el formulario responde "No pudimos enviar tu mensaje" y guarda el mensaje
  completo en `/home/<usuario>/paga-contacto-fallidos.log`, fuera de `public_html`, para que ningún
  contacto se pierda. Es el único caso en que se guardan datos personales en el servidor: revísalo y
  vacíalo cuando se resuelva.
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
- [ ] El video del hero y el de los 25 años se reproducen, en las dos versiones de idioma.
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

- **Cada cambio del sitio** es volver a compilar y volver a subir: `npm run build`, armar el zip del
  punto 2 y extraerlo en `public_html`. Los videos solo se vuelven a subir si cambiaron.
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
por archivo: los videos de 23 MB entran, pero por poco. Eso requiere cuenta de Cloudflare a nombre de
PAGA y tocar un registro de DNS; nada de eso hace falta en la ruta del cPanel.
