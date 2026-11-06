<?php
declare(strict_types=1);

/**
 * Formulario de contacto de pagadesarrollos.com — conector de correo.
 *
 * Recibe el JSON que manda src/views/Contact.astro, lo valida y lo envía por correo con mail(),
 * o sea por el Exim del cPanel de Neubox. Como el MX del dominio apunta a Microsoft 365, el correo
 * sale del servidor y entra al buzón del Suite por la misma ruta que cualquier correo de fuera.
 *
 * Requisitos del hosting (cPanel de Neubox, revisado antes de publicar):
 *   1. PHP 7.4 o superior con mail() habilitado.
 *   2. cPanel → Email Routing del dominio en **Remote Mail Exchanger**. Si está en Local, el correo
 *      se queda en el servidor y nunca llega al Suite.
 *   3. El SPF del dominio debe autorizar la IP de este servidor (hoy lo hace con `+a`). Si TI quita
 *      `+a`, hay que agregar `ip4:198.59.144.118` o este formulario empezará a caer en spam.
 *
 * Los destinatarios se pueden cambiar sin recompilar el sitio: ver $CONFIG y el archivo opcional
 * paga-contacto-config.php, que vive fuera de public_html.
 *
 * No guarda datos personales cuando el envío sale bien: solo una referencia en el error_log.
 * Si el correo falla, y solo entonces, escribe el mensaje completo en un archivo fuera de
 * public_html para que ningún contacto se pierda (docs/LANZAMIENTO.md).
 */

// ———————————————————————————————————————————————— Configuración

$CONFIG = [
    // Buzón que recibe todo lo que no tenga buzón propio.
    'to' => 'contacto@pagadesarrollos.com',
    // Opcional: un buzón por perfil. Los perfiles son los de src/data/contact.json.
    'to_by_profile' => [
        // 'inversion' => 'inversion@pagadesarrollos.com',
        // 'terreno'   => 'terrenos@pagadesarrollos.com',
        // 'empleo'    => 'rh@pagadesarrollos.com',
    ],
    // El CRM (PAGA Suite) se configura aparte: sus valores por omisión están en crm.php y la
    // llave va en /home/<usuario>/paga-contacto-config.php, nunca aquí.
    // Remitente. Tiene que ser una dirección del propio dominio para que el SPF cuadre.
    'from' => 'web@pagadesarrollos.com',
    'from_name' => 'PAGA Desarrollos (web)',
    // Máximo de envíos por hora desde la misma dirección IP.
    'max_per_hour' => 10,
];

// Override opcional, fuera de public_html: /home/<usuario>/paga-contacto-config.php
// Debe ser un archivo PHP que devuelva un arreglo con las llaves de arriba que se quieran cambiar.
$overrideFile = dirname(__DIR__, 2) . '/paga-contacto-config.php';
if (is_file($overrideFile)) {
    $override = include $overrideFile;
    if (is_array($override)) {
        $CONFIG = array_replace($CONFIG, $override);
    }
}

// Perfiles y etiquetas: los mismos ids que src/data/contact.json.
const PROFILES = [
    'inversion' => 'Inversión / socio de capital',
    'terreno' => 'Terreno',
    'banco' => 'Banco / institución financiera',
    'comprador' => 'Comprador',
    'broker' => 'Broker',
    'proveedor' => 'Proveedor',
    'prensa' => 'Prensa',
    'empleo' => 'Bolsa de trabajo',
];

define('REFERENCIA', 'PAGA-' . strtoupper(bin2hex(random_bytes(5))));

// El cliente del CRM. La constante es la que le permite cargarse: por web, solo, responde 403.
define('PAGA_CRM', true);
require __DIR__ . '/crm.php';
// Lo que el archivo de configuración no diga, lo pone crm.php.
$CONFIG = array_replace(crm_defectos(), $CONFIG);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// ———————————————————————————————————————————————— Utilidades

/** Responde JSON y termina. El front-end distingue por el código: 200 bien, 400 campos, 503 sin configurar. */
function responder(int $status, array $body): void
{
    http_response_code($status);
    error_log('pagadesarrollos contacto [' . REFERENCIA . '] HTTP ' . $status);
    echo json_encode($body + ['reference' => REFERENCIA], JSON_UNESCAPED_UNICODE);
    exit;
}

function cortar(string $value, int $max): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

/** Texto de una línea: sin saltos ni caracteres de control. Sirve también contra inyección de encabezados. */
function una_linea($value, int $max = 500): string
{
    if (!is_string($value)) {
        return '';
    }
    return cortar(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)), $max);
}

/** Texto de varias líneas: conserva los saltos, quita lo demás. */
function varias_lineas($value, int $max = 3000): string
{
    if (!is_string($value)) {
        return '';
    }
    $v = preg_replace("/\r\n?/", "\n", $value);
    $v = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', (string) $v);
    return cortar(trim((string) $v), $max);
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** Codifica un encabezado con acentos (RFC 2047). */
function encabezado(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

// ———————————————————————————————————————————————— Entrada

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, ['error' => 'method_not_allowed']);
}

// Solo envíos desde el propio sitio: no se habilita CORS.
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = parse_url($_SERVER['HTTP_ORIGIN']);
    $host = is_array($origin) ? strtolower($origin['host'] ?? '') : '';
    if (is_array($origin) && isset($origin['port'])) {
        $host .= ':' . $origin['port'];
    }
    if ($host !== strtolower($_SERVER['HTTP_HOST'] ?? '')) {
        responder(403, ['error' => 'forbidden_origin']);
    }
}

$tipo = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($tipo !== 'application/json') {
    responder(415, ['error' => 'bad_content_type']);
}

$raw = file_get_contents('php://input', false, null, 0, 32769);
if ($raw === false || strlen($raw) > 32768) {
    responder(413, ['error' => 'too_large']);
}
$b = json_decode((string) $raw, true);
if (!is_array($b)) {
    responder(400, ['error' => 'bad_json']);
}

// Campo trampa: el formulario lo manda vacío. A un robot se le contesta que todo salió bien.
if (una_linea($b['website'] ?? '') !== '') {
    responder(200, ['ok' => true]);
}

// Límite por IP y por hora, en un archivo temporal del servidor.
$maxPorHora = (int) $CONFIG['max_per_hour'];
if ($maxPorHora > 0) {
    $cubeta = sys_get_temp_dir() . '/paga-form-'
        . substr(sha1(($_SERVER['REMOTE_ADDR'] ?? '') . gmdate('YmdH')), 0, 16);
    $enviados = is_file($cubeta) ? (int) file_get_contents($cubeta) : 0;
    if ($enviados >= $maxPorHora) {
        responder(429, ['error' => 'too_many']);
    }
    @file_put_contents($cubeta, (string) ($enviados + 1));
}

// ———————————————————————————————————————————————— Validación (la misma del formulario)

$d = [
    'profile' => array_key_exists(una_linea($b['profile'] ?? '', 40), PROFILES) ? una_linea($b['profile'], 40) : '',
    'name' => una_linea($b['name'] ?? '', 120),
    'email' => una_linea($b['email'] ?? '', 160),
    'phone' => una_linea($b['phone'] ?? '', 40),
    'company' => una_linea($b['company'] ?? '', 120),
    'message' => varias_lineas($b['message'] ?? '', 3000),
    'project' => una_linea($b['project'] ?? '', 80),
    'projectName' => una_linea($b['projectName'] ?? '', 120),
    'landLocation' => una_linea($b['landLocation'] ?? '', 200),
    'landArea' => una_linea($b['landArea'] ?? '', 20),
    'deck' => !empty($b['deck']),
    'consent' => !empty($b['consent']),
    'lang' => ($b['lang'] ?? '') === 'en' ? 'en' : 'es',
    'page' => una_linea($b['page'] ?? '', 200),
    'srcLanding' => una_linea($b['srcLanding'] ?? '', 200),
    'srcRef' => una_linea($b['srcRef'] ?? '', 300),
    'srcUtm' => una_linea($b['srcUtm'] ?? '', 200),
];

$faltan = [];
if ($d['profile'] === '') {
    $faltan[] = 'profile';
}
if ($d['name'] === '') {
    $faltan[] = 'name';
}
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
    $faltan[] = 'email';
}
if ($d['message'] === '') {
    $faltan[] = 'message';
}
if (!$d['consent']) {
    $faltan[] = 'consent';
}
if ($faltan) {
    responder(400, ['error' => 'invalid', 'fields' => $faltan]);
}

// ———————————————————————————————————————————————— Destinatarios

$porPerfil = is_array($CONFIG['to_by_profile'] ?? null) ? $CONFIG['to_by_profile'] : [];
$to = una_linea($porPerfil[$d['profile']] ?? $CONFIG['to'] ?? '', 320);
$from = una_linea($CONFIG['from'] ?? '', 320);
$destinos = array_values(array_filter(array_map('trim', explode(',', $to)), static function ($dir) {
    return filter_var($dir, FILTER_VALIDATE_EMAIL) !== false;
}));
if (!$destinos || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
    // 503: el formulario contesta "aún no está conectado" y no se pierde nada en silencio.
    responder(503, ['error' => 'not_configured']);
}

// ———————————————————————————————————————————————— Mensaje

$filas = [
    ['Perfil', PROFILES[$d['profile']]],
    ['Nombre', $d['name']],
    ['Correo', $d['email']],
    ['Teléfono', $d['phone']],
    ['Empresa', $d['company']],
    ['Proyecto', $d['projectName'] !== '' ? $d['projectName'] : $d['project']],
    ['Ubicación del terreno', $d['landLocation']],
    ['Superficie (m²)', $d['landArea']],
    ['Presentación corporativa', $d['deck'] ? 'Sí' : ''],
    ['Idioma', $d['lang']],
    ['Página', $d['page']],
    ['Primera página de la visita', $d['srcLanding']],
    ['Llegó desde', $d['srcRef'] !== '' ? $d['srcRef'] : ($d['srcLanding'] !== '' ? 'Directo o sin referencia' : '')],
    ['Campaña (UTM)', $d['srcUtm']],
    ['Consentimiento aviso de privacidad', 'Sí'],
    ['Referencia', REFERENCIA],
];

$html = '<h2 style="font:500 18px/1.3 Arial,sans-serif;color:#13294B;margin:0 0 12px">'
    . 'Nuevo mensaje desde pagadesarrollos.com</h2>'
    . '<table cellpadding="6" style="font:400 14px/1.45 Arial,sans-serif;border-collapse:collapse">';
foreach ($filas as $fila) {
    if ((string) $fila[1] === '') {
        continue;
    }
    $html .= '<tr><td style="color:#5B6573;vertical-align:top"><b>' . esc((string) $fila[0])
        . '</b></td><td>' . esc((string) $fila[1]) . '</td></tr>';
}
$html .= '</table><p style="font:400 14px/1.6 Arial,sans-serif;white-space:pre-wrap;'
    . 'border-left:3px solid #13294B;padding-left:12px;margin-top:16px">'
    . esc($d['message']) . '</p>';

// La parte de texto es la que leen los analizadores de correo de los CRM: una linea por campo,
// «campo: valor», con los identificadores sin traducir (el perfil y el slug del proyecto) para
// que se puedan mapear, y una linea de etiquetas al final.
$etiquetas = ['web', 'perfil-' . $d['profile'], 'idioma-' . $d['lang']];
if ($d['project'] !== '') {
    $etiquetas[] = 'proyecto-' . $d['project'];
}
if ($d['deck']) {
    $etiquetas[] = 'pide-presentacion';
}

$planas = [
    ['perfil', $d['profile']],
    ['perfil_texto', PROFILES[$d['profile']]],
    ['nombre', $d['name']],
    ['correo', $d['email']],
    ['telefono', $d['phone']],
    ['empresa', $d['company']],
    ['proyecto', $d['project']],
    ['proyecto_texto', $d['projectName']],
    ['terreno_ubicacion', $d['landLocation']],
    ['terreno_superficie_m2', $d['landArea']],
    ['presentacion_corporativa', $d['deck'] ? 'si' : 'no'],
    ['idioma', $d['lang']],
    ['pagina', $d['page']],
    ['primera_pagina', $d['srcLanding']],
    ['llego_desde', $d['srcRef']],
    ['campana_utm', $d['srcUtm']],
    ['consentimiento_aviso_privacidad', 'si'],
    ['fecha_utc', gmdate('c')],
    ['referencia', REFERENCIA],
    ['etiquetas', implode(', ', $etiquetas)],
];
$texto = '';
foreach ($planas as $linea) {
    if ((string) $linea[1] === '') {
        continue;
    }
    $texto .= $linea[0] . ': ' . str_replace(["\r", "\n"], ' ', (string) $linea[1]) . "\r\n";
}
$texto .= "\r\nmensaje:\r\n" . str_replace("\n", "\r\n", $d['message']) . "\r\n";

$asunto = encabezado('[Web] ' . PROFILES[$d['profile']] . ' · ' . $d['name']);
$deNombre = una_linea($CONFIG['from_name'] ?? '', 80);
$frontera = 'paga-' . bin2hex(random_bytes(12));

$encabezados = [
    'From: ' . ($deNombre !== '' ? encabezado($deNombre) . ' ' : '') . '<' . $from . '>',
    'Reply-To: ' . encabezado($d['name']) . ' <' . $d['email'] . '>',
    'MIME-Version: 1.0',
    'Content-Type: multipart/alternative; boundary="' . $frontera . '"',
    'X-Mailer: pagadesarrollos.com',
    'X-Entity-Ref-ID: ' . REFERENCIA,
    // Para los CRM y para las reglas de bandeja que filtran por encabezado.
    'X-PAGA-Perfil: ' . $d['profile'],
    'X-PAGA-Idioma: ' . $d['lang'],
    'X-PAGA-Etiquetas: ' . implode(', ', $etiquetas),
];
if ($d['project'] !== '') {
    $encabezados[] = 'X-PAGA-Proyecto: ' . $d['project'];
}

$cuerpo = '--' . $frontera . "\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: base64\r\n\r\n"
    . chunk_split(base64_encode($texto), 76, "\r\n")
    . '--' . $frontera . "\r\n"
    . "Content-Type: text/html; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: base64\r\n\r\n"
    . chunk_split(base64_encode($html), 76, "\r\n")
    . '--' . $frontera . "--\r\n";
$para = implode(', ', $destinos);

// El sobre va con el remitente del dominio, para que el SPF del dominio sea el que se revise.
$enviado = @mail($para, $asunto, $cuerpo, implode("\r\n", $encabezados), '-f' . $from);
if (!$enviado) {
    // Algunos servidores no permiten -f: se reintenta sin él antes de darlo por perdido.
    $enviado = @mail($para, $asunto, $cuerpo, implode("\r\n", $encabezados));
}

if (!$enviado) {
    // Único caso en que se guardan los datos: si no se guardaran, el contacto se perdería.
    $respaldo = dirname(__DIR__, 2) . '/paga-contacto-fallidos.log';
    @file_put_contents(
        $respaldo,
        gmdate('c') . ' ' . REFERENCIA . ' ' . json_encode($d, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
    responder(502, ['error' => 'send_failed']);
}

// ———————————————————————————————————————————————— El lead, al CRM
// Se deja en la cola en disco y lo entrega el cron (scripts/enviar-leads.php). El visitante no
// espera a la API: si estuviera lenta, el formulario tardaria lo mismo en contestar, y un timeout
// nunca se puede reintentar solo porque la API no deduplica (ver crm.php).
//
// Nada de esto puede tumbar la respuesta del formulario: el correo ya salio.
try {
    if ((string) ($CONFIG['crm_key'] ?? '') !== '' && crm_aplica($d['profile'], $CONFIG)) {
        $rutaLead = crm_encolar(REFERENCIA, crm_payload($d), $CONFIG);
        if (($CONFIG['crm_inline'] ?? false) === true) {
            $sobre = json_decode((string) @file_get_contents($rutaLead), true);
            if (is_array($sobre)) {
                crm_guardar($rutaLead, $sobre, crm_enviar($sobre['lead'], $CONFIG), $CONFIG);
            }
        }
    }
} catch (Throwable $e) {
    error_log('PAGA ' . REFERENCIA . ' crm: ' . $e->getMessage());
}

responder(200, ['ok' => true]);
