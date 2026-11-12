<?php
/**
 * Internal Admin Authentication Gate (auth_request para Nginx)
 *
 * Verifica que la petición provenga de un Administrador con sesión activa.
 * Respuestas:
 *   HTTP 200 -> Autorizado.
 *   HTTP 403 -> Denegado (sin sesión, expirada o usuario no administrador).
 */

// Si no hay cookie de sesión, rechazar de inmediato con 403 (cero overhead de BD)
if (empty($_COOKIE['PHPSESSID'])) {
    http_response_code(403);
    exit;
}

if (!defined('TMP_PATH')) {
    define('TMP_PATH', '/home/uypanel/tmp/');
}
if (!defined('MAIN_HOME')) {
    define('MAIN_HOME', '/home/uypanel/');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rSessionTimeout = 60; // Minutos

if (empty($_SESSION['hash']) || empty($_SESSION['last_activity']) || empty($_SESSION['verify'])) {
    http_response_code(403);
    exit;
}

// Comprobar expiración por inactividad
if (($rSessionTimeout * 60) < (time() - $_SESSION['last_activity'])) {
    http_response_code(403);
    exit;
}

require_once MAIN_HOME . 'includes/admin.php';

$userId = intval($_SESSION['hash']);
$rUserInfo = UserRepository::getRegisteredUserById($userId);

if (!$rUserInfo) {
    http_response_code(403);
    exit;
}

// Validar hash de verificación de sesión
$expectedVerify = md5($rUserInfo['username'] . '||' . $rUserInfo['password']);
if ($_SESSION['verify'] !== $expectedVerify) {
    http_response_code(403);
    exit;
}

// Validar permisos de administrador
$rPermissions = getPermissions($rUserInfo['member_group_id']);
if (empty($rPermissions) || empty($rPermissions['is_admin'])) {
    http_response_code(403);
    exit;
}

// Autorizado exitosamente
http_response_code(200);
exit;
