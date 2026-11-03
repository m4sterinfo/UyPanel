<?php

/**
 * Configuración de la aplicación
 *
 * Versión, repositorios Git, flags de desarrollo y demás
 * constantes application-level.
 *
 * No contiene rutas (ver Paths.php) ni binarios (ver Binaries.php).
 */

define('AUTO_RESTART_MARIADB', true);  // Test function
define('DEVELOPMENT', false);          // It will be deleted in the future.

// ── Versión y Git ──────────────────────────────────────────────
define('UyPanel_VERSION', '2.0.1');
define('GIT_OWNER',       'm4sterinfo');
define('GIT_REPO_MAIN',   'UyPanel');
define('GIT_REPO_UPDATE', 'UyPanel_Update');

// ── Varios ────────────────────────────────────────────────────
define('MONITOR_CALLS', 3);
define('OPENSSL_EXTRA', 'fNiu3XD448xTDa27xoY4');
