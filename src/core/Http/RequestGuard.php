<?php

/**
 * Protección de peticiones HTTP e inicialización del logger
 *
 * Ejecuta:
 *   1. Flood-protection — bloqueo de IPs baneadas
 *   2. Host verification — comprobación del dominio vía caché allowed_domains
 *   3. Carga de ajustes ($rSettings) desde la caché de archivos
 *   4. Determinación de PHP_ERRORS
 *   5. Inicialización del Logger
 *
 * Dependencias:
 *   FLOOD_TMP_PATH, CACHE_TMP_PATH, INCLUDES_PATH (de Paths.php)
 *   DEVELOPMENT (de AppConfig.php)
 *   LOGS_TMP_PATH (de Paths.php)
 *   generateError() (de ErrorHandler.php)
 *
 * Rellena las variables globales:
 *   $rSettings   — array de ajustes del panel (desde la caché de archivos)
 *   $rShowErrors — flag de mostrar errores
 */

$rShowErrors = false;

if (!isset($_SERVER['argc'])) {
    $rIP = $_SERVER['REMOTE_ADDR'];
    if (empty($rIP) || !file_exists(FLOOD_TMP_PATH . 'block_' . $rIP)) {
        define('HOST', trim(explode(':', $_SERVER['HTTP_HOST'])[0]));

        if (file_exists(CACHE_TMP_PATH . 'settings')) {
            $rData = file_get_contents(CACHE_TMP_PATH . 'settings');
            $rSettings = igbinary_unserialize($rData);

            if (is_array($rSettings) && file_exists(CACHE_TMP_PATH . 'allowed_domains') && $rSettings['verify_host']) {
                $rData = file_get_contents(CACHE_TMP_PATH . 'allowed_domains');
                $rAllowedDomains = igbinary_unserialize($rData);

                if (!(is_array($rAllowedDomains) && !in_array(HOST, $rAllowedDomains) && HOST != 'uypanel') || filter_var(HOST, FILTER_VALIDATE_IP)) {
                } else {
                    generateError('INVALID_HOST');
                }
            }

            $rShowErrors = (isset($rSettings['debug_show_errors']) ? $rSettings['debug_show_errors'] : false);
        }
    } else {
        http_response_code(403);

        exit();
    }
}

define('PHP_ERRORS', $rShowErrors);

// ── Logger ─────────────────────────────────────────────────────
// After fixing all the warnings, replace DEVELOPMENT with PHP_ERRORS
require_once INCLUDES_PATH . 'libs/Logger.php';
Logger::init(
    DEVELOPMENT,
    LOGS_TMP_PATH . 'error_log.log'
);
