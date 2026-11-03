<?php

/**
 * Cargador de configuración
 *
 * Lee config.ini y rellena el array global $_INFO.
 * Dependencias: MAIN_HOME, CONFIG_PATH (de Paths.php).
 *
 * $_INFO contiene:
 *   'username'  — usuario de MySQL
 *   'password'  — contraseña de MySQL
 *   'database'  — nombre de la base de datos
 *   'hostname'  — host de MySQL
 *   'port'      — puerto de MySQL
 */

global $_INFO;
$_INFO = array();

if (file_exists(MAIN_HOME . 'config')) {
    $_INFO = parse_ini_file(CONFIG_PATH . 'config.ini');
} else {
    die('no config found');
}
