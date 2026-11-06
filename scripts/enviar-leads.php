<?php

/**
 * Entrega al CRM los leads que dejó el formulario en la cola. Lo corre el cron del cPanel.
 *
 * NO va dentro de public_html. Se copia a /home/<usuario>/paga-enviar-leads.php y se programa en
 * el cron del cPanel cada cinco minutos. La línea exacta está en docs/LANZAMIENTO.md.
 *
 * Uso:
 *     php paga-enviar-leads.php            entrega lo que esté pendiente
 *     php paga-enviar-leads.php --estado   solo reporta la cola, sin tocar la API
 *
 * Por qué existe, en vez de llamar a la API desde el formulario: así el visitante nunca espera a
 * la API para que el formulario le conteste, y los reintentos tienen una sola ruta, con un tiempo
 * de espera holgado y topes propios. La API deduplica 60 minutos por correo o teléfono, pero esa
 * es la red de abajo, no el diseño: aquí se decide qué se reintenta y qué no, y teniendo un solo
 * lugar donde eso se decide es más fácil razonar sobre él. El criterio está en api/crm.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este programa solo corre desde la línea de comandos.\n");
}

$raiz = __DIR__;
$cliente = $raiz . '/public_html/api/crm.php';
if (!is_file($cliente)) {
    // Para correrlo desde el repositorio, en pruebas.
    $cliente = dirname($raiz) . '/public/api/crm.php';
}
if (!is_file($cliente)) {
    fwrite(STDERR, "No encuentro crm.php. Esperaba $raiz/public_html/api/crm.php\n");
    exit(1);
}
require $cliente;

$CONFIG = crm_defectos();
$archivo = $raiz . '/paga-contacto-config.php';
if (is_file($archivo)) {
    $override = include $archivo;
    if (is_array($override)) {
        $CONFIG = array_replace($CONFIG, $override);
    }
}

$dir = crm_cola_dir($CONFIG);
$soloEstado = in_array('--estado', $argv, true);

// Conteo de la cola, que es lo primero que hay que mirar cuando algo no llega.
$contar = static function (string $dir): array {
    $por = [];
    foreach (glob($dir . '/*.json') ?: [] as $ruta) {
        $sobre = json_decode((string) @file_get_contents($ruta), true);
        $estado = is_array($sobre) ? (string) ($sobre['estado'] ?? '?') : 'ilegible';
        $por[$estado] = ($por[$estado] ?? 0) + 1;
    }
    return $por;
};
$porEstado = $contar($dir);
$resumen = [];
foreach ($porEstado as $estado => $n) {
    $resumen[] = "$estado=$n";
}
echo gmdate('c') . ' cola: ' . ($resumen ? implode(' ', $resumen) : 'vacía') . "\n";

if ($soloEstado) {
    foreach (glob($dir . '/*.json') ?: [] as $ruta) {
        $sobre = json_decode((string) @file_get_contents($ruta), true);
        if (!is_array($sobre) || ($sobre['estado'] ?? '') === CRM_PENDIENTE) {
            continue;
        }
        // Lo que necesita mirar una persona: sin datos personales en pantalla.
        printf(
            "  %s  %-10s intentos=%d  %s\n",
            $sobre['referencia'] ?? '?',
            $sobre['estado'] ?? '?',
            (int) ($sobre['intentos'] ?? 0),
            substr((string) ($sobre['detalle'] ?? ''), 0, 90)
        );
    }
    exit(0);
}

if ((string) ($CONFIG['crm_key'] ?? '') === '') {
    echo "  la llave del CRM no está configurada: no se intenta nada\n";
    exit(0);
}

$cuenta = crm_drenar($CONFIG);

// Dos corridas no se pisan: la segunda no espera, se salta. No es un error —el cron vuelve en
// cinco minutos— así que se sale con 0 y el cPanel no manda correo de fallo.
if (!empty($cuenta['bloqueado'])) {
    echo "  otra corrida sigue en marcha: esta se salta\n";
    exit(0);
}
if (!empty($cuenta['sin_candado'])) {
    echo "  aviso: no se pudo abrir el candado de la cola; se corrió sin él\n";
}

printf(
    "  intentados=%d enviados=%d pendientes=%d revisar=%d rechazados=%d agotados=%d esperando=%d\n",
    $cuenta['revisados'],
    $cuenta['enviados'],
    $cuenta['pendientes'],
    $cuenta['revisar'],
    $cuenta['rechazados'],
    $cuenta['agotados'],
    $cuenta['esperando']
);

// Se recuenta despues de entregar: lo que acaba de quedar en «revisar» hay que avisarlo ya,
// no en la corrida de dentro de cinco minutos.
$final = $contar($dir);
$paraRevisar = ($final[CRM_REVISAR] ?? 0) + ($final[CRM_RECHAZADO] ?? 0) + ($final[CRM_AGOTADO] ?? 0);
if ($paraRevisar > 0) {
    echo "  $paraRevisar lead(s) necesitan revisión manual: corre con --estado para verlos\n";
}
exit(0);
