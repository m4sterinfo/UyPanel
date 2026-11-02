<?php

/**
 * Конфигурация приложения
 *
 * Версия, Git-репозитории, флаги разработки и прочие
 * application-level константы.
 *
 * Не содержит путей (см. Paths.php) и бинарников (см. Binaries.php).
 */

define('AUTO_RESTART_MARIADB', true);  // Test function
define('DEVELOPMENT', false);          // It will be deleted in the future.

// ── Версия и Git ──────────────────────────────────────────────
define('UyPanel_VERSION', '2.0.1');
define('GIT_OWNER',       'm4sterinfo');
define('GIT_REPO_MAIN',   'UyPanel');
define('GIT_REPO_UPDATE', 'UyPanel_Update');

// ── Разное ────────────────────────────────────────────────────
define('MONITOR_CALLS', 3);
define('OPENSSL_EXTRA', 'fNiu3XD448xTDa27xoY4');
