<?php

/**
 * Fachada de configuración (constants.php)
 *
 * Este archivo sigue siendo el punto de inclusión único por compatibilidad hacia atrás.
 * Toda la lógica está repartida entre módulos:
 *
 *   core/Error/ErrorCodes.php      — array $rErrorCodes
 *   core/Error/ErrorHandler.php    — generateError(), generate404()
 *   core/Config/Paths.php          — constantes de rutas (*_PATH, *_TMP_PATH)
 *   core/Config/AppConfig.php      — versión, Git, flags de la aplicación
 *   core/Config/Binaries.php       — rutas a FFmpeg, FFprobe, GeoIP, PHP CLI
 *   core/Config/ConfigLoader.php   — carga de $_INFO desde config.ini
 *   core/Http/RequestGuard.php     — flood-protection, host verify, Logger init
 *
 * El orden de carga importa:
 *   1. autoload.php     → MAIN_HOME + XC_Autoloader
 *   2. ErrorCodes       → $rErrorCodes (necesario para ErrorHandler)
 *   3. ErrorHandler     → generate404() (necesario para el access guard de abajo)
 *   4. Paths            → todas las constantes *_PATH (necesarias para Binaries, ConfigLoader)
 *   5. AppConfig        → UyPanel_VERSION, DEVELOPMENT, etc.
 *   6. Binaries         → FFMPEG_*, FFPROBE_*, GeoIP, PHP_BIN
 *   7. ConfigLoader     → $_INFO desde config.ini
 *   8. RequestGuard     → flood/host check + Logger::init()
 */

// ─────────────────────────────────────────────────────────────────
//  1. Autocargador (define MAIN_HOME, registra spl_autoload)
// ─────────────────────────────────────────────────────────────────

require_once dirname(__DIR__) . '/autoload.php';

// ─────────────────────────────────────────────────────────────────
//  2. Errores (necesarios antes del access guard)
// ─────────────────────────────────────────────────────────────────

require_once MAIN_HOME . 'core/Error/ErrorCodes.php';
require_once MAIN_HOME . 'core/Error/ErrorHandler.php';

// ─────────────────────────────────────────────────────────────────
//  3. Protección contra acceso directo
// ─────────────────────────────────────────────────────────────────

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    generate404();
}

// ─────────────────────────────────────────────────────────────────
//  4. Ajustes globales de PHP
// ─────────────────────────────────────────────────────────────────

@ini_set('user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.61 Safari/537.36');
@ini_set('default_socket_timeout', 5);

// ─────────────────────────────────────────────────────────────────
//  5. Constantes: rutas, aplicación, binarios
// ─────────────────────────────────────────────────────────────────

require_once MAIN_HOME . 'core/Config/Paths.php';
require_once MAIN_HOME . 'core/Config/AppConfig.php';
require_once MAIN_HOME . 'core/Config/Binaries.php';

// ─────────────────────────────────────────────────────────────────
//  6. Carga de la configuración desde config.ini
// ─────────────────────────────────────────────────────────────────

require_once MAIN_HOME . 'core/Config/ConfigLoader.php';

// ─────────────────────────────────────────────────────────────────
//  7. HTTP: flood protection, host verification, Logger init
// ─────────────────────────────────────────────────────────────────

require_once MAIN_HOME . 'core/Http/RequestGuard.php';
