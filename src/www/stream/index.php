<?php

/**
 * Micro-enrutador de streaming (preparatorio).
 *
 * La configuración actual de nginx dirige las peticiones directamente a archivos concretos
 * (live.php, vod.php, etc.). Este archivo se usa como fallback en el acceso directo
 * a /stream/ y está listo para el cambio opcional de nginx a un punto
 * entry point.
 *
 * Overhead: un array lookup (< 0.1ms).
 */

$rHandler = isset($_GET['handler']) ? $_GET['handler'] : null;

$rMap = [
    'live'      => 'live.php',
    'vod'       => 'vod.php',
    'segment'   => 'segment.php',
    'key'       => 'key.php',
    'timeshift' => 'timeshift.php',
    'thumb'     => 'thumb.php',
    'subtitle'  => 'subtitle.php',
    'rtmp'      => 'rtmp.php',
    'auth'      => 'auth.php',
];

if ($rHandler && isset($rMap[$rHandler])) {
    require __DIR__ . '/' . $rMap[$rHandler];
} else {
    http_response_code(404);
    echo "<html>\r\n<head><title>404 Not Found</title></head>\r\n<body>\r\n<center><h1>404 Not Found</h1></center>\r\n<hr><center>nginx</center>\r\n</body>\r\n</html>\r\n";
}
