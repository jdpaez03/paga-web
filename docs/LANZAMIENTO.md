# Lanzamiento de pagadesarrollos.com — paso a paso

Decisiones (14 oct 2026, Juan Diego):
- El sitio se aloja en **Cloudflare Pages**.
- El DNS **se queda en Neubox**, administrado con Maparnet (Eugenio).
- La dirección pública es **www.pagadesarrollos.com**. Quien escriba pagadesarrollos.com, sin www, llega al hosting actual de Neubox y se le redirige a www.

Nada de esto se hace antes de que Dirección apruebe el sitio.

---

## 0. Antes de empezar

- [ ] Dirección aprueba el sitio. Jurídico aprueba los avisos legales.
- [ ] Cuenta de Cloudflare **a nombre de PAGA**: correo corporativo, verificación en dos pasos y, de preferencia, un segundo administrador.
- [ ] Repositorio en GitHub accesible para esa cuenta. Idealmente, pasado a una organización de PAGA en lugar de `jdpaez03/paga-web`.
- [ ] Respaldo completo del cPanel y del WordPress descargado y guardado.
- [ ] Captura de toda la zona DNS actual (cPanel → Zone Editor), como referencia para regresar.

## 1. Crear el proyecto en Cloudflare Pages

1. Cloudflare → **Workers & Pages** → **Create** → **Pages** → **Connect to Git** → elegir el repositorio.
2. Configuración de compilación:
   - Framework preset: **Astro**
   - Build command: `npm run build`
   - Build output directory: `dist`
   - Variable de entorno de compilación: `NODE_VERSION` = `20`
   - Rama de producción: `main`
3. Guardar y publicar. El sitio queda en `https://<proyecto>.pages.dev`. Esa dirección temporal no se indexa porque `public/_headers` manda `noindex`.
4. **Analítica:** en el proyecto → **Metrics** → activar **Web Analytics**. Es gratis y no usa cookies, como dice el aviso de cookies.

## 2. Formulario de contacto

La función vive en `functions/api/contact.js` y responde en `/api/contact`.

1. En el proyecto → **Settings** → **Variables and Secrets** (producción):
   - `RESEND_API_KEY`, como **secreto**
   - `CONTACT_TO`: bandeja general, por ejemplo `contacto@pagadesarrollos.com`
   - `CONTACT_FROM`: remitente verificado, por ejemplo `PAGA Desarrollos <web@pagadesarrollos.com>`
   - Opcionales, una por perfil: `CONTACT_TO_INVERSION`, `CONTACT_TO_TERRENO`, `CONTACT_TO_BANCO`, `CONTACT_TO_COMPRADOR`, etc.
2. En Resend, dar de alta el dominio de envío. Resend pide unos registros DNS (TXT/MX/DKIM) en un **subdominio**, por ejemplo `send.pagadesarrollos.com`. Se **agregan** en el Zone Editor de Neubox **sin modificar ningún registro existente**.
3. Mientras falten las variables, el formulario responde "aún no conectado" y no pierde datos en silencio.

## 3. Un día antes del lanzamiento

- [ ] En `src/data/site.json`, cambiar `"preview": true` a `false`. Eso quita la banda "Vista previa" y el `noindex`. Publicar ese cambio en `main`.
- [ ] En el cPanel → Zone Editor: bajar el **TTL** del registro `www.pagadesarrollos.com` (CNAME) de 14400 a **300**. Así, si hay que regresar, el cambio se nota en minutos.

## 4. Día del lanzamiento

1. **Dominio en Cloudflare:** proyecto → **Custom domains** → **Set up a custom domain** → `www.pagadesarrollos.com`. Cloudflare indica el destino: `<proyecto>.pages.dev`. Este paso va **antes** de cambiar el DNS; sin él aparece un error 522.
2. **Cambio en Neubox** (cPanel → Zone Editor), **un solo registro**:

   | Registro | Hoy | Nuevo |
   |---|---|---|
   | `www.pagadesarrollos.com.` CNAME | `pagadesarrollos.com` | `<proyecto>.pages.dev` |

3. Esperar a que Cloudflare marque el dominio como **Active**, con certificado emitido. Suele tardar de minutos a una hora.
4. **Redirección del dominio sin www** (cPanel → Dominios → **Redirecciones**):
   - Tipo: **Permanente (301)**
   - Dominio: `pagadesarrollos.com`, ruta vacía
   - Redirigir a: `https://www.pagadesarrollos.com/`
   - Opción: **"Redirigir con o sin www"**
   - Marcar **"Redirección comodín"**, para que cualquier dirección vieja llegue al sitio nuevo. Si esa página ya no existe, cae en la 404 de PAGA, con ligas a Proyectos y Contacto.

   El registro A de `pagadesarrollos.com` (198.59.144.118) **no se toca**. El hosting de Neubox sigue respondiendo y redirigiendo, con el certificado que ya tiene.

## 5. Verificación (el mismo día)

- [ ] `https://www.pagadesarrollos.com` carga el sitio nuevo, con candado.
- [ ] `https://pagadesarrollos.com` y `http://pagadesarrollos.com` llevan a `https://www.pagadesarrollos.com/`.
- [ ] Una dirección vieja inventada (`https://pagadesarrollos.com/algo-viejo`) termina en la 404 de PAGA.
- [ ] Envío de prueba del formulario, con cada perfil que tenga bandeja propia.
- [ ] **Correo:** mandar un correo desde Gmail hacia una cuenta @pagadesarrollos.com y responderlo. En admin.microsoft.com → Dominios, pagadesarrollos.com debe seguir en "Healthy".
- [ ] Google Search Console: verificar el dominio (registro TXT en Neubox, que se **agrega**) y enviar `https://www.pagadesarrollos.com/sitemap-index.xml`.

## 6. Cómo regresar (si algo falla)

1. Zone Editor: `www.pagadesarrollos.com` CNAME de vuelta a `pagadesarrollos.com`.
2. cPanel → Redirecciones: eliminar la redirección.

Con TTL de 300, en unos minutos se vuelve a ver el WordPress.

## 7. Registros que NO se tocan nunca

Son los registros del correo, Teams y servicios de Microsoft 365, más los accesos al servidor actual:

- `pagadesarrollos.com` **MX** → `pagadesarrollos-com.mail.protection.outlook.com`
- `pagadesarrollos.com` **TXT** (SPF) y `_dmarc` (DMARC)
- `autodiscover` CNAME → `autodiscover.outlook.com`
- `selector2._domainkey` CNAME (DKIM de Microsoft) y `default._domainkey` TXT
- `sip`, `lyncdiscover` CNAME y los SRV `_sip._tls` y `_sipfederationtls._tcp`
- `pagadesarrollos.com` **A** 198.59.144.118, y `mail`, `ftp`, `cpanel`, `webmail`, `whm`, `webdisk`, `cpcontacts`, `cpcalendars`, `autoconfig`
- Registros técnicos de cPanel: `_acme-challenge…`, `_cpanel-dcv…`, `comodoca.com`, `_caldav…`, `_carddav…`

## 8. Después del lanzamiento (con Maparnet)

- Confirmar a nombre de quién está el dominio, su fecha de vencimiento y la renovación automática.
- **No cancelar el hosting de Neubox:** sostiene el DNS, el correo y la redirección sin www. Si algún día se quiere cancelar, primero hay que mover el DNS completo a Cloudflare. Con eso, el dominio sin www también se puede servir directo desde Cloudflare Pages.
- Revisión de TI (no urgente):
  - quitar `+a` del SPF;
  - confirmar la firma DKIM de Microsoft 365 (`selector1` y `selector2`);
  - quitar `doy.com.mx` del tenant.
