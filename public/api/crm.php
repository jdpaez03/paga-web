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
 * Cuatro propiedades de la API mandan sobre el diseño de este archivo:
 *
 *   1. Deduplica durante 60 minutos, por correo o por los últimos 10 dígitos del teléfono, contra
 *      todos los leads, incluidos los capturados a mano. Un repetido contesta 200 con el id_lead
 *      que ya existía y «duplicado»: siempre es un éxito. Esa ventana es lo que vuelve
 *      reintentable un timeout: si el lead es reconocible y seguimos dentro de ella, reintentar no
 *      puede duplicar, así que se reintenta. Pasa a «revisar» solo cuando reintentar sí podría
 *      duplicar: sin correo y sin teléfono de 10 dígitos, o con la ventana ya vencida.
 *   2. El 403 tiene dos causas. La ruta equivocada es permanente y va a «rechazado». Un desarrollo
 *      cerrado no: el lead se reenvía una vez sin nom_proyecto_interes y se anota sin_proyecto.
 *   3. No hay rate limiting. El tope lo ponemos nosotros: intentos por lead y leads por corrida.
 *   4. nom_proyecto_interes es texto libre y la API no lo valida. Un «Sonoma» o un «SONOMA » con
 *      espacio entra con 200 y después ese lead no sale en ningún filtro. Se valida aquí contra la
 *      lista cerrada de abajo, y lo que no case va en null, que es un estado legítimo.
 *
 * La ventana de 60 minutos es un dato de allá, no de aquí: vive en crm_ventana_dedup y tiene que
 * seguir a la variable de entorno de la Suite. De ella depende que el calendario de reintentos
 * —5, 10, 15, 25, 35 y 45 minutos, con corte a los 50— alcance a correr completo. Subir
 * crm_max_intentos o crm_espera_max sin rehacer esa cuenta mata los últimos intentos en silencio.
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
        // La ventana del guardia anti-duplicados de la Suite, en segundos. Dentro de ella, un
        // reintento del mismo correo o teléfono devuelve el lead que ya existe en vez de crear
        // otro. Si allá la cambian, aquí también: de esto depende poder reintentar un timeout.
        'crm_ventana_dedup' => 3600,
        // Margen para no apostar al último segundo de la ventana.
        'crm_margen_dedup' => 600,
        // Tope de la espera entre intentos. Con 10 minutos el sexto intento caía a los 55 —fuera
        // de la ventana— y nunca llegaba a correr. Con 5 cae a los 45 y los 6 caben. Bajarlo no
        // aprieta a la API: el cron corre cada 5 minutos, así que esa es la separación mínima real.
        'crm_espera_max' => 300,
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
 * Las Joyas (2) y Las Velas (6, 7) no existen en el catálogo del CRM, y adivinarles un nombre los
 * dejaría fuera de todos los filtros.
 *
 * Marena apunta a LAS PERLAS: es el mismo desarrollo, que cambió de nombre comercial. En la Suite
 * la llave del catálogo sigue siendo LAS PERLAS —la guardan media docena de tablas— y lo que
 * viaja aquí es la llave, no el nombre con el que se anuncia.
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
    'marena' => 'LAS PERLAS',
    'torre-acqua' => 'SONOMA',
    'torre-fiori' => 'SONOMA',
    'torre-pietra' => 'SONOMA',
];

/** Estados de un lead en la cola. */
const CRM_PENDIENTE = 'pendiente';   // no se ha procesado; el cron lo reintenta
const CRM_ENVIADO = 'enviado';       // 200, con id_lead
const CRM_RECHAZADO = 'rechazado';   // 400, 401, 403, 404, 422: reintentar no lo arregla
const CRM_REVISAR = 'revisar';       // timeout que reintentar podría duplicar; lo ve una persona
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

/**
 * ¿El guardia anti-duplicados de la Suite puede reconocer este lead si se manda dos veces?
 *
 * Casa por correo o por los últimos 10 dígitos del teléfono, nunca por el nombre solo. Un lead
 * que no traiga ninguno de los dos SÍ se duplicaría, así que ese no se reintenta nunca después de
 * un timeout: se queda en «revisar» para que lo vea una persona.
 */
function crm_reconocible(array $lead): bool
{
    if ((string) ($lead['correo'] ?? '') !== '') {
        return true;
    }
    $tel = preg_replace('/\D+/', '', (string) ($lead['telefono'] ?? ''));
    return is_string($tel) && strlen($tel) >= 10;
}

/**
 * ¿Sigue dentro de la ventana en la que un reintento no duplica?
 *
 * Se mide desde que se creó el lead, no desde el último intento, porque la ventana de la Suite
 * cuenta desde el lead original.
 */
function crm_en_ventana(array $sobre, array $cfg): bool
{
    $creado = strtotime((string) ($sobre['creado'] ?? ''));
    if ($creado === false) {
        return false;
    }
    $limite = (int) ($cfg['crm_ventana_dedup'] ?? 3600) - (int) ($cfg['crm_margen_dedup'] ?? 600);
    return (time() - $creado) < $limite;
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
 * Guarda el resultado de un intento y devuelve el estado con el que quedó.
 *
 * Devuelve el estado porque aquí se decide: un timeout llega como «revisar» y puede salir como
 * «pendiente». Quien llama necesita el de salida, no el de entrada, o el conteo del cron miente.
 *
 * Cuando sale bien, el archivo se borra y solo queda una línea sin datos personales en el registro
 * de enviados: el aviso de privacidad dice que no guardamos datos cuando el envío es correcto.
 */
function crm_guardar(string $ruta, array $sobre, array $resultado, array $cfg): string
{
    // Un timeout ya no es un callejón sin salida: con el guardia anti-duplicados de la Suite,
    // reintentar no crea otro lead, siempre que el guardia pueda reconocerlo (correo o teléfono)
    // y que todavía estemos dentro de la ventana. Si falta cualquiera de las dos, se queda en
    // «revisar» y lo captura una persona, que es como se comportaba antes en todos los casos.
    if (($resultado['ambiguo'] ?? false) === true) {
        $recuperable = crm_reconocible($sobre['lead'] ?? []) && crm_en_ventana($sobre, $cfg);
        $resultado['estado'] = $recuperable ? CRM_PENDIENTE : CRM_REVISAR;
        $sobre['ambiguo'] = true;
        if (!$recuperable) {
            $resultado['detalle'] .= crm_reconocible($sobre['lead'] ?? [])
                ? ' (fuera de la ventana de deduplicacion)'
                : ' (sin correo ni telefono: reintentar duplicaria)';
        }
    }

    $sobre['estado'] = $resultado['estado'];
    $sobre['intentos'] = ($sobre['intentos'] ?? 0) + 1;
    $sobre['ultimo_intento'] = gmdate('c');
    $sobre['detalle'] = $resultado['detalle'];

    if ($resultado['estado'] === CRM_ENVIADO) {
        @unlink($ruta);
        @file_put_contents(
            dirname($ruta) . '/enviados.log',
            gmdate('c') . ' ' . $sobre['referencia'] . ' id_lead=' . ($resultado['id_lead'] ?? '?')
                . (($resultado['duplicado'] ?? false) ? ' duplicado' : '')
                . (($resultado['sin_proyecto'] ?? false) ? ' sin_proyecto' : '') . "\n",
            FILE_APPEND | LOCK_EX
        );
        return (string) $resultado['estado'];
    }

    @file_put_contents($ruta, json_encode($sobre, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    return (string) $resultado['estado'];
}

/**
 * Una petición HTTP a la API. Devuelve lo crudo; la interpretación va en crm_enviar().
 */
function crm_http(array $payload, array $cfg): array
{
    $ch = curl_init((string) $cfg['crm_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . (string) $cfg['crm_key'],
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
    $r = [
        'errno' => curl_errno($ch),
        'error' => curl_error($ch),
        'http' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'cuerpo' => (string) $respuesta,
    ];
    curl_close($ch);
    return $r;
}

/**
 * Un intento de entrega.
 *
 * Devuelve ['estado' =>, 'http' =>, 'id_lead' =>, 'detalle' =>] y, cuando aplica, los avisos
 * 'duplicado', 'sin_proyecto' y 'ambiguo'. Lo que importa es de qué tipo es la falla:
 *
 *   - El servidor contestó que no y volverá a contestar lo mismo  → rechazado.
 *   - El servidor contestó que no, pero por el proyecto           → se reintenta una vez sin él.
 *   - La petición no llegó a la aplicación                        → pendiente, reintentar es seguro.
 *   - No llegamos a saber (timeout)                               → ambiguo; lo resuelve crm_guardar()
 *                                                                   según el guardia de duplicados.
 */
function crm_enviar(array $payload, array $cfg): array
{
    if ((string) ($cfg['crm_url'] ?? '') === '' || (string) ($cfg['crm_key'] ?? '') === '') {
        return ['estado' => CRM_PENDIENTE, 'http' => 0, 'id_lead' => null, 'detalle' => 'sin configurar'];
    }
    if (!function_exists('curl_init')) {
        return ['estado' => CRM_PENDIENTE, 'http' => 0, 'id_lead' => null, 'detalle' => 'sin extension curl'];
    }

    $r = crm_http($payload, $cfg);

    if ($r['errno'] !== 0) {
        // Fallos en los que la petición no llegó a la aplicación: reintentar es seguro.
        $sinLlegar = [CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, CURLE_SSL_CONNECT_ERROR];
        if (in_array($r['errno'], $sinLlegar, true)) {
            return ['estado' => CRM_PENDIENTE, 'http' => 0, 'id_lead' => null, 'detalle' => 'red: ' . $r['error']];
        }
        // Timeout: la petición pudo haber entrado. Si el guardia de la Suite puede reconocer este
        // lead y seguimos dentro de su ventana, reintentar no duplica. Eso lo decide crm_guardar().
        return [
            'estado' => CRM_REVISAR,
            'ambiguo' => true,
            'http' => 0,
            'id_lead' => null,
            'detalle' => 'sin respuesta: ' . $r['error'],
        ];
    }

    if ($r['http'] === 200 || $r['http'] === 201) {
        $json = json_decode($r['cuerpo'], true);
        $json = is_array($json) ? $json : [];
        // 200 siempre es éxito, también cuando el guardia devolvió un lead que ya existía.
        return [
            'estado' => CRM_ENVIADO,
            'http' => $r['http'],
            'id_lead' => $json['id_lead'] ?? null,
            'duplicado' => ($json['duplicado'] ?? false) === true,
            'sin_proyecto' => ($payload['nom_proyecto_interes'] ?? null) === null,
            'detalle' => 'ok',
        ];
    }

    // El 403 tiene dos causas. Una es permanente (la llave solo da de alta leads, cualquier otra
    // ruta se rechaza) y la otra no: un desarrollo vendido y archivado rechaza leads nuevos. En
    // ese caso el lead es real y la consulta también; entra sin proyecto, que es un estado válido,
    // y alguien lo clasifica después. Un solo reintento, y solo si traía proyecto.
    $cerrado = $r['http'] === 403 && stripos($r['cuerpo'], 'cerrado') !== false;
    if ($cerrado && ($payload['nom_proyecto_interes'] ?? null) !== null) {
        $proyecto = (string) $payload['nom_proyecto_interes'];
        $payload['nom_proyecto_interes'] = null;
        $segundo = crm_enviar($payload, $cfg);
        $segundo['detalle'] = 'proyecto ' . $proyecto . ' cerrado; reenviado sin proyecto: ' . $segundo['detalle'];
        return $segundo;
    }

    if (in_array($r['http'], [400, 401, 403, 404, 422], true)) {
        return [
            'estado' => CRM_RECHAZADO,
            'http' => $r['http'],
            'id_lead' => null,
            'detalle' => 'http ' . $r['http'] . ': ' . substr($r['cuerpo'], 0, 300),
        ];
    }

    // 429 y 5xx: el servidor dice que no lo procesó. Se reintenta.
    return ['estado' => CRM_PENDIENTE, 'http' => $r['http'], 'id_lead' => null, 'detalle' => 'http ' . $r['http']];
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
    $cuenta = [
        'revisados' => 0, 'enviados' => 0, 'pendientes' => 0,
        'rechazados' => 0, 'agotados' => 0, 'esperando' => 0, 'revisar' => 0,
    ];

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
        // Espera creciente con tope, medida desde el último intento: 2, 4, 5, 5 y 5 minutos. Como
        // el cron corre cada cinco, los 6 intentos de un lead caen a los 5, 10, 15, 25, 35 y 45
        // minutos de haberse recibido, y el sexto todavía cabe dentro de la ventana de la Suite.
        $ultimo = strtotime((string) ($sobre['ultimo_intento'] ?? $sobre['creado'] ?? 'now'));
        $espera = min(60 * (2 ** $intentos), (int) ($cfg['crm_espera_max'] ?? 300));
        if ($intentos > 0 && (time() - $ultimo) < $espera) {
            $cuenta['esperando']++;
            continue;
        }

        // Si este lead viene de un timeout y ya se salio de la ventana de deduplicacion,
        // reintentarlo si duplicaria: pasa a revisar y lo captura una persona.
        if (($sobre['ambiguo'] ?? false) === true && !crm_en_ventana($sobre, $cfg)) {
            $sobre['estado'] = CRM_REVISAR;
            $sobre['detalle'] = 'timeout fuera de la ventana de deduplicacion';
            @file_put_contents($ruta, json_encode($sobre, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
            $cuenta['revisar']++;
            continue;
        }

        $cuenta['revisados']++;
        $resultado = crm_enviar($sobre['lead'], $cfg);
        // El estado de salida, no el de entrada: crm_guardar() convierte algunos timeout en
        // «pendiente» y deja otros en «revisar», y el conteo tiene que decir cuál fue.
        $estado = crm_guardar($ruta, $sobre, $resultado, $cfg);
        if ($estado === CRM_ENVIADO) {
            $cuenta['enviados']++;
        } elseif ($estado === CRM_RECHAZADO) {
            $cuenta['rechazados']++;
        } elseif ($estado === CRM_REVISAR) {
            $cuenta['revisar']++;
        } else {
            $cuenta['pendientes']++;
        }
    }
    return $cuenta;
}
