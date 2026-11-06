<?php

/**
 * Cliente del módulo de Leads de PAGA Suite (FastAPI + PostgreSQL).
 *
 *   POST https://pagasuite.com/api/leads
 *   Authorization: Bearer <llave>
 *
 * Lo incluyen dos programas: api/contact.php, cuando alguien manda el formulario, y el script de
 * reintentos que corre por cron (scripts/enviar-leads.php). No se puede abrir por web.
 *
 * Tres límites de la API mandan sobre el diseño de este archivo:
 *
 *   1. No hay deduplicación. Dos POST del mismo mensaje crean dos leads distintos. Por eso un
 *      timeout NUNCA se reintenta solo: la petición pudo haber entrado. Queda en «revisar» y lo
 *      mira una persona. Solo se reintenta lo que con seguridad no se proceso: un fallo de red
 *      antes de conectar, un 429 o un 5xx.
 *   2. No hay rate limiting. El tope lo ponemos nosotros: intentos por lead y leads por corrida.
 *   3. nom_proyecto_interes es texto libre y la API no lo valida. Un «Sonoma» o un «SONOMA » con
 *      espacio entra con 200 y después ese lead no sale en ningún filtro. Se valida aquí contra la
 *      lista cerrada de abajo, y lo que no case va en null, que es un estado legítimo.
 *
 * El correo del formulario no es el respaldo técnico de esto: no hay buzón que alimente al CRM.
 * El respaldo es la cola en disco. El correo es el aviso a una persona, y además es donde viajan
 * el mensaje, el perfil y la campaña, que la API de leads no tiene dónde guardar.
 */

if (PHP_SAPI !== 'cli' && !defined('PAGA_CRM')) {
    http_response_code(403);
    exit;
}

/**
 * Valores por omisión del CRM. Los usan api/contact.php y el script del cron, para que no se
 * separen. Lo que traiga /home/<usuario>/paga-contacto-config.php gana sobre esto.
 */
function crm_defectos(): array
{
    return [
        'crm_url' => 'https://pagasuite.com/api/leads',
        // La llave NUNCA va en el repositorio: solo en el archivo de configuración del servidor.
        'crm_key' => '',
        // false: el lead se encola y lo entrega el cron, y el visitante no espera a la API.
        // true: además se intenta al momento del envío.
        'crm_inline' => false,
        'crm_connect_timeout' => 5,
        'crm_timeout' => 12,
        // Carpeta de la cola. Vacía = /home/<usuario>/paga-leads.
        'crm_cola' => '',
        // Solo los perfiles comerciales: prensa, proveedores y bolsa de trabajo ensucian un
        // módulo de leads de venta. Esos llegan por correo y nada más.
        'crm_perfiles' => ['inversion', 'terreno', 'banco', 'comprador', 'broker'],
        // Los dos topes que la API no tiene. Son la única protección contra un ciclo de reintentos.
        'crm_max_intentos' => 6,
        'crm_max_por_corrida' => 20,
        // Si el servidor obliga a salir por proxy: 'host:puerto'.
        'crm_proxy' => '',
    ];
}

/** Los 8 nombres que el CRM reconoce. Cualquier otra cosa se guarda pero no aparece en filtros. */
const CRM_PROYECTOS_VALIDOS = [
    'AMARA',
    'ANDENES HERMOSILLO',
    'ARTESANIAS',
    'BALCONES',
    'CORALES BAY',
    'JARDIN SECRETO',
    'LAS PERLAS',
    'SONOMA',
];

/**
 * De los slugs de src/data/projects.json a los nombres del CRM.
 *
 * Solo están los que de verdad casan. Los que no aparecen aquí viajan como null a propósito:
 * Las Joyas (2), Las Velas (6, 7) y Marena no existen como proyecto en el CRM, y adivinarles un
 * nombre los dejaría fuera de todos los filtros.
 *
 * Si algún día se agrega un proyecto al CRM, se agrega aquí y en CRM_PROYECTOS_VALIDOS.
 */
const CRM_PROYECTOS = [
    'amara-centro-primera-fase' => 'AMARA',
    'andenes-hermosillo' => 'ANDENES HERMOSILLO',
    'artesanias-tercera-fase' => 'ARTESANIAS',
    'balcones-200' => 'BALCONES',
    'balcones-300' => 'BALCONES',
    'balcones-600' => 'BALCONES',
    'balcones-residencial' => 'BALCONES',
    'los-corales-bay-tower' => 'CORALES BAY',
    'jardin-secreto' => 'JARDIN SECRETO',
    'torre-acqua' => 'SONOMA',
    'torre-fiori' => 'SONOMA',
    'torre-pietra' => 'SONOMA',
];

/** Estados de un lead en la cola. */
const CRM_PENDIENTE = 'pendiente';   // no se ha procesado; el cron lo reintenta
const CRM_ENVIADO = 'enviado';       // 200, con id_lead
const CRM_RECHAZADO = 'rechazado';   // 400, 401, 403, 422: reintentar no lo arregla
const CRM_REVISAR = 'revisar';       // timeout: pudo haber entrado, NO se reintenta solo
const CRM_AGOTADO = 'agotado';       // se acabaron los intentos

/** El nombre del proyecto que entiende el CRM, o null si no hay uno que case. */
function crm_proyecto(string $slug): ?string
{
    $nombre = CRM_PROYECTOS[$slug] ?? null;
    if ($nombre === null || !in_array($nombre, CRM_PROYECTOS_VALIDOS, true)) {
        return null;
    }
    return $nombre;
}

/**
 * Solo dígitos: el ejemplo del contrato es «5512345678». El teléfono tal como lo escribió el
 * visitante viaja completo en el correo, así que aquí no se pierde nada.
 */
function crm_telefono(string $tel): ?string
{
    $digitos = preg_replace('/\D+/', '', $tel);
    return ($digitos === null || $digitos === '') ? null : substr($digitos, 0, 20);
}

/**
 * El cuerpo del POST. Solo «nombre» es obligatorio.
 *
 * Lo que el formulario recoge y la API de leads no tiene dónde guardar —el perfil, el mensaje, la
 * página de entrada y la campaña— se queda en el correo. No se mete a la fuerza en tipo_unidad ni
 * en ningún otro campo: eso ensuciaría el CRM.
 */
function crm_payload(array $d): array
{
    return [
        'nombre' => $d['name'],
        'correo' => $d['email'] !== '' ? $d['email'] : null,
        'telefono' => crm_telefono($d['phone'] ?? ''),
        'empresa' => ($d['company'] ?? '') !== '' ? $d['company'] : null,
        'fuente' => 'web',
        'nom_proyecto_interes' => crm_proyecto($d['project'] ?? ''),
        'tipo_unidad' => null,
        'rango_min' => null,
        'rango_max' => null,
        'valor_estimado' => null,
        'asignado_a' => null,
    ];
}

/** ¿Este perfil va al CRM? El módulo es de leads comerciales: prensa y bolsa de trabajo lo ensucian. */
function crm_aplica(string $perfil, array $cfg): bool
{
    $perfiles = $cfg['crm_perfiles'] ?? [];
    return is_array($perfiles) && in_array($perfil, $perfiles, true);
}

/** La carpeta de la cola, fuera de public_html. */
function crm_cola_dir(array $cfg): string
{
    $dir = (string) ($cfg['crm_cola'] ?? '');
    if ($dir === '') {
        $dir = dirname(__DIR__, 2) . '/paga-leads';
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

/**
 * Deja el lead en disco antes de intentar nada. Si el proceso se muere a medio envío, el lead
 * sigue ahí. El nombre del archivo es la referencia del mensaje, que es también su llave de
 * idempotencia: un archivo, un lead, pase lo que pase.
 */
function crm_encolar(string $referencia, array $payload, array $cfg): string
{
    $ruta = crm_cola_dir($cfg) . '/' . $referencia . '.json';
    $sobre = [
        'referencia' => $referencia,
        'estado' => CRM_PENDIENTE,
        'intentos' => 0,
        'creado' => gmdate('c'),
        'ultimo_intento' => null,
        'detalle' => '',
        'lead' => $payload,
    ];
    @file_put_contents($ruta, json_encode($sobre, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($ruta, 0600);
    return $ruta;
}

/**
 * Guarda el resultado de un intento.
 *
 * Cuando sale bien, el archivo se borra y solo queda una línea sin datos personales en el registro
 * de enviados: el aviso de privacidad dice que no guardamos datos cuando el envío es correcto.
 */
function crm_guardar(string $ruta, array $sobre, array $resultado, array $cfg): void
{
    $sobre['estado'] = $resultado['estado'];
    $sobre['intentos'] = ($sobre['intentos'] ?? 0) + 1;
    $sobre['ultimo_intento'] = gmdate('c');
    $sobre['detalle'] = $resultado['detalle'];

    if ($resultado['estado'] === CRM_ENVIADO) {
        @unlink($ruta);
        @file_put_contents(
            dirname($ruta) . '/enviados.log',
            gmdate('c') . ' ' . $sobre['referencia'] . ' id_lead=' . ($resultado['id_lead'] ?? '?') . "\n",
            FILE_APPEND | LOCK_EX
        );
        return;
    }

    @file_put_contents($ruta, json_encode($sobre, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Un intento de entrega. No reintenta por dentro: quien llama decide.
 *
 * Devuelve ['estado' =>, 'http' =>, 'id_lead' =>, 'detalle' =>]. La diferencia que importa está
 * entre «el servidor contestó que no» (rechazado, no se reintenta) y «no llegamos a saber»
 * (revisar, tampoco se reintenta, porque el lead pudo haberse creado).
 */
function crm_enviar(array $payload, array $cfg): array
{
    $url = (string) ($cfg['crm_url'] ?? '');
    $llave = (string) ($cfg['crm_key'] ?? '');
    if ($url === '' || $llave === '') {
        return ['estado' => CRM_PENDIENTE, 'http' => 0, 'id_lead' => null, 'detalle' => 'sin configurar'];
    }
    if (!function_exists('curl_init')) {
        return ['estado' => CRM_PENDIENTE, 'http' => 0, 'id_lead' => null, 'detalle' => 'sin extension curl'];
    }

    $cuerpo = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $cuerpo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $llave,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: pagadesarrollos.com',
        ],
        CURLOPT_CONNECTTIMEOUT => (int) ($cfg['crm_connect_timeout'] ?? 5),
        CURLOPT_TIMEOUT => (int) ($cfg['crm_timeout'] ?? 12),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    // Algunos servidores compartidos solo salen por proxy.
    if (($cfg['crm_proxy'] ?? '') !== '') {
        curl_setopt($ch, CURLOPT_PROXY, (string) $cfg['crm_proxy']);
    }

    $respuesta = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $errmsg = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0) {
        // Fallos en los que la petición no llegó a la aplicación: reintentar es seguro.
        $sinLlegar = [CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, CURLE_SSL_CONNECT_ERROR];
        if (in_array($errno, $sinLlegar, true)) {
            return ['estado' => CRM_PENDIENTE, 'http' => 0, 'id_lead' => null, 'detalle' => 'red: ' . $errmsg];
        }
        // Timeout y todo lo demás: pudo haber entrado. No se reintenta solo.
        return ['estado' => CRM_REVISAR, 'http' => 0, 'id_lead' => null, 'detalle' => 'sin respuesta: ' . $errmsg];
    }

    if ($http === 200 || $http === 201) {
        $json = json_decode((string) $respuesta, true);
        $id = is_array($json) ? ($json['id_lead'] ?? null) : null;
        return ['estado' => CRM_ENVIADO, 'http' => $http, 'id_lead' => $id, 'detalle' => 'ok'];
    }

    // El servidor contestó que no. Reintentar con el mismo cuerpo da lo mismo.
    if (in_array($http, [400, 401, 403, 404, 422], true)) {
        return ['estado' => CRM_RECHAZADO, 'http' => $http, 'id_lead' => null, 'detalle' => 'http ' . $http . ': ' . substr((string) $respuesta, 0, 300)];
    }

    // 429 y 5xx: el servidor dice que no lo procesó. Se reintenta.
    return ['estado' => CRM_PENDIENTE, 'http' => $http, 'id_lead' => null, 'detalle' => 'http ' . $http];
}

/**
 * Vacía la cola. La corre el cron, nunca una petición web.
 *
 * Solo toca los «pendiente». Los «revisar» y los «rechazado» se quedan para que los vea una
 * persona. Hay dos topes, porque la API no tiene rate limiting y el único freno es este: intentos
 * por lead y leads por corrida. Entre intento e intento, espera creciente.
 */
function crm_drenar(array $cfg): array
{
    $dir = crm_cola_dir($cfg);
    $maxIntentos = (int) ($cfg['crm_max_intentos'] ?? 6);
    $maxCorrida = (int) ($cfg['crm_max_por_corrida'] ?? 20);
    $cuenta = ['revisados' => 0, 'enviados' => 0, 'pendientes' => 0, 'rechazados' => 0, 'agotados' => 0, 'esperando' => 0];

    $archivos = glob($dir . '/*.json') ?: [];
    sort($archivos);
    foreach ($archivos as $ruta) {
        if ($cuenta['revisados'] >= $maxCorrida) {
            break;
        }
        $sobre = json_decode((string) @file_get_contents($ruta), true);
        if (!is_array($sobre) || ($sobre['estado'] ?? '') !== CRM_PENDIENTE) {
            continue;
        }
        $intentos = (int) ($sobre['intentos'] ?? 0);
        if ($intentos >= $maxIntentos) {
            $sobre['estado'] = CRM_AGOTADO;
            @file_put_contents($ruta, json_encode($sobre, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            $cuenta['agotados']++;
            continue;
        }
        // Espera creciente: 2, 4, 8, 16... minutos desde el último intento.
        $ultimo = strtotime((string) ($sobre['ultimo_intento'] ?? $sobre['creado'] ?? 'now'));
        if ($intentos > 0 && (time() - $ultimo) < (60 * (2 ** $intentos))) {
            $cuenta['esperando']++;
            continue;
        }

        $cuenta['revisados']++;
        $resultado = crm_enviar($sobre['lead'], $cfg);
        crm_guardar($ruta, $sobre, $resultado, $cfg);
        if ($resultado['estado'] === CRM_ENVIADO) {
            $cuenta['enviados']++;
        } elseif ($resultado['estado'] === CRM_RECHAZADO) {
            $cuenta['rechazados']++;
        } else {
            $cuenta['pendientes']++;
        }
    }
    return $cuenta;
}
