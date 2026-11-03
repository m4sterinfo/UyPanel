<?php

/**
 * Front Controller
 *
 * Punto de entrada único para admin/reseller.
 * Sustituye el patrón «cada página — un archivo PHP aparte» por
 * un dispatch centralizado a través del Router.
 *
 * ──────────────────────────────────────────────────────────────────
 * Cómo funciona:
 * ──────────────────────────────────────────────────────────────────
 *
 *   1. nginx redirige la petición aquí (vía access code o directamente)
 *   2. Se determina el scope (admin|reseller) y el pageName:
 *      — Vía access code: nginx pasa XC_SCOPE y XC_CODE mediante fastcgi_param
 *      — Directo: URL /admin/... o /reseller/... → scope desde la ruta
 *   3. Se carga el bootstrap (session → functions → includes/admin)
 *   4. Se cargan las rutas de public/routes/{scope}.php
 *   5. El Router intenta dispatch(pageName, method):
 *      — Si la ruta está registrada → invoca el Controller
 *      — Si no → fallback: include del archivo legacy admin/{pageName}.php
 *
 * ──────────────────────────────────────────────────────────────────
 * Access Codes (bin/nginx/conf/codes/):
 * ──────────────────────────────────────────────────────────────────
 *
 *   El panel no se accede por /admin/, sino por /RANDOMCODE/.
 *   Los códigos se generan a partir de la plantilla bin/nginx/conf/codes/template:
 *
 *     location ^~ /#CODE# {
 *         alias /home/uypanel/#TYPE#;
 *         try_files $uri $uri.html $uri/ @extensionless-php;
 *         ...
 *     }
 *
 *   Tipos de códigos (#TYPE#):
 *     0 → admin           3 → includes/api/admin
 *     1 → reseller         4 → includes/api/reseller
 *     2 → ministra         5 → ministra/new
 *                          6 → player
 *
 *   Para el Front Controller la plantilla pasa el scope vía fastcgi_param:
 *     fastcgi_param XC_SCOPE admin;   (o reseller)
 *     fastcgi_param XC_CODE  xxxxxx;  (access code)
 *
 * ──────────────────────────────────────────────────────────────────
 * Activación (futura plantilla template):
 * ──────────────────────────────────────────────────────────────────
 *
 *   location ^~ /#CODE# {
 *       alias /home/uypanel/#TYPE#;
 *       index index.php;
 *       try_files $uri $uri.html $uri/ @fc_#CODE#;
 *       location ~ \.php$ { ... }
 *   }
 *   location @fc_#CODE# {
 *       fastcgi_param XC_SCOPE #TYPE#;
 *       fastcgi_param XC_CODE  #CODE#;
 *       fastcgi_param SCRIPT_FILENAME /home/uypanel/public/index.php;
 *       fastcgi_pass php;
 *       include fastcgi_params;
 *   }
 *
 * ──────────────────────────────────────────────────────────────────
 * Compatibilidad hacia atrás:
 * ──────────────────────────────────────────────────────────────────
 *
 *   Mientras la plantilla nginx no se actualice — las URLs antiguas vía access codes
 *   (/MYCODE/dashboard → admin/dashboard.php) siguen funcionando.
 *   El Front Controller se activa solo tras actualizar template.
 *
 *   Mecanismo de fallback: si el Router no conoce la ruta, el controlador
 *   incluye el archivo PHP legacy directamente (include admin/dashboard.php).
 *
 * @see core/Http/Router.php
 * @see domain/Auth/CodeRepository.php
 * @see bin/nginx/conf/codes/template
 * @see public/routes/admin.php
 * @see public/routes/api.php
 */

// ─────────────────────────────────────────────────────────────────
//  1. Definimos MAIN_HOME antes que todo lo demás
// ─────────────────────────────────────────────────────────────────

if (!defined('MAIN_HOME')) {
        // public/index.php → 1 nivel hasta la raíz (src/)
    define('MAIN_HOME', dirname(__DIR__) . '/');
}

// ─────────────────────────────────────────────────────────────────
//  1b. Autoloader — lo cargamos ANTES de todo el código de dispatch
// ─────────────────────────────────────────────────────────────────
//  Garantiza la disponibilidad de las clases (Router, Controllers, Domain)
//  en TODOS los caminos de ejecución: 3a REST, 3b Streaming, 4a noBootstrap, 4b full.
// ─────────────────────────────────────────────────────────────────

require_once MAIN_HOME . 'autoload.php';

// ─────────────────────────────────────────────────────────────────
//  2. Parseo de URL → scope + pageName
// ─────────────────────────────────────────────────────────────────
//
//  Dos modos:
//
//  A) Access Code (principal): nginx pasa XC_SCOPE y XC_CODE
//     URL: /MYCODE/dashboard → scope='admin', pageName='dashboard'
//     el alias de nginx dirige al directorio correcto
//
//  B) Acceso directo (fallback/dev): la URL contiene /admin/ o /reseller/
//     URL: /admin/dashboard → scope='admin', pageName='dashboard'
// ─────────────────────────────────────────────────────────────────

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$urlPath    = parse_url($requestUri, PHP_URL_PATH);
$urlPath    = '/' . ltrim($urlPath, '/');

$scope      = 'admin';
$pageName   = '';
$accessCode = null;
$rawScope   = null;

// Modo A: access code — nginx pasa el scope vía fastcgi_param
if (!empty($_SERVER['XC_SCOPE'])) {
        // Scope desde nginx: 'admin', 'reseller', 'ministra', etc.
    $rawScope   = $_SERVER['XC_SCOPE'];
    $accessCode = $_SERVER['XC_CODE'] ?? null;

        // Mapeo #TYPE# → scope para el enrutado
    $scopeMap = [
        'admin'                => 'admin',
        'reseller'             => 'reseller',
        'ministra'             => 'ministra',
        'ministra/new'         => 'ministra',
        'includes/api/admin'   => 'admin',
        'includes/api/reseller' => 'reseller',
        'player'               => 'player',
    ];

    $scope = $scopeMap[$rawScope] ?? 'admin';

        // pageName: quitamos el prefijo del access code de la URL
        // URL: /MYCODE/dashboard → tras quitar el code → 'dashboard'
    if ($accessCode && preg_match('#^/' . preg_quote($accessCode, '#') . '(?:/(.*))?$#', $urlPath, $m)) {
        $pageName = isset($m[1]) ? trim($m[1], '/') : '';
    } else {
                // Fallback: último segmento
        $pageName = trim($urlPath, '/');
                // Quitamos el primer segmento (es el access code)
        $parts = explode('/', $pageName, 2);
        $pageName = $parts[1] ?? '';
    }
}
// Modo B: URL directa /admin/... o /reseller/...
elseif (preg_match('#^/(admin|reseller)(?:/(.*))?$#', $urlPath, $m)) {
    $scope    = $m[1];
    $pageName = isset($m[2]) ? trim($m[2], '/') : '';
}
// Modo C: access code sin XC_SCOPE (template antiguo)
// Determinamos el scope vía PHP_SELF (como hace AuthRepository::getCurrentCode)
else {
    $selfDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));

        // Si PHP_SELF contiene un código (no 'admin'/'reseller'),
        // se necesita información adicional para determinar el scope.
        // Sin XC_SCOPE asumimos admin (fallback seguro).
    if (!in_array($selfDir, ['admin', 'reseller'], true)) {
        $accessCode = $selfDir;
    } else {
        $scope = $selfDir;
    }

        // Extraemos el pageName de la URI
    $parts = explode('/', trim($urlPath, '/'));
        // El primer segmento es el access code o el scope, el resto es el pageName
    array_shift($parts);
    $pageName = implode('/', $parts);
}

// Quitamos la extensión .php si existe
$pageName = preg_replace('/\.php$/', '', $pageName);

// Página vacía → index (redirect al login)
if ($pageName === '') {
    $pageName = 'index';
}

// Raíz del access code (/CODE o /CODE/) → redirect al login.
// Sin redirect el navegador se queda en /CODE, y los assets relativos
// se resuelven desde / en vez de /CODE/ — todos los CSS/JS/images se rompen.
if ($pageName === 'index' && $accessCode && in_array($scope, ['admin', 'reseller'], true)
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Location: /' . $accessCode . '/login');
    exit;
}

// Hacemos el nombre de página disponible globalmente para las funciones auxiliares legacy
// (p. ej., getPageName() en includes/admin.php), que si no solo ven index.php.
if (!defined('PAGE_NAME')) {
    define('PAGE_NAME', $pageName);
}

// ─────────────────────────────────────────────────────────────────
//  3. Recursos estáticos — los saltamos (no deberían llegar aquí,
//     pero por si acaso la configuración de nginx es incorrecta)
// ─────────────────────────────────────────────────────────────────

$ext = pathinfo($pageName, PATHINFO_EXTENSION);
if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map'], true)) {
    http_response_code(404);
    exit;
}

// ─────────────────────────────────────────────────────────────────
//  3a. REST API — admin/reseller external API dispatch
// ─────────────────────────────────────────────────────────────────
//
//  Access code type 3/4 → nginx fallback @fc_{code} → FC.
//  index.php eliminado de includes/api/, bootstrap + controller directamente.
// ─────────────────────────────────────────────────────────────────

if (isset($rawScope) && in_array($rawScope, ['includes/api/admin', 'includes/api/reseller'], true)) {
	require_once MAIN_HOME . 'includes/admin.php';
	if ($rawScope === 'includes/api/admin') {
		$controller = new AdminApiController();
	} else {
		$controller = new ResellerRestApiController();
	}
	$controller->index();
	exit;
}

// ─────────────────────────────────────────────────────────────────
//  3b. Streaming API — player_api, enigma2, epg, playlist, etc.
// ─────────────────────────────────────────────────────────────────
//
//  la location de nginx intercepta /{endpoint}.php → FC con XC_SCOPE=api,
//  XC_API={endpoint}. Cargamos el bootstrap de streaming, dispatch del controller.
// ─────────────────────────────────────────────────────────────────

if (isset($rawScope) && $rawScope === 'api' && !empty($_SERVER['XC_API'])) {
	$rApiName = $_SERVER['XC_API'];
	$rApiControllerMap = [
		'player_api' => 'PlayerApiController',
		'enigma2'    => 'Enigma2ApiController',
		'xplugin'    => 'XPluginApiController',
		'epg'        => 'EpgApiController',
		'playlist'   => 'PlaylistApiController',
		'internal'   => 'InternalApiController',
	];

	if (!isset($rApiControllerMap[$rApiName])) {
		http_response_code(404);
		exit;
	}

	// $rFilename se define directamente — init.php/stream/init.php
	// comprueban isset($rFilename) y no lo calculan desde basename.
	$rFilename = ($rApiName === 'internal') ? 'api' : $rApiName;

	chdir(MAIN_HOME . 'www/');
	if ($rApiName === 'player_api') {
		require MAIN_HOME . 'www/stream/init.php';
	} else {
		require MAIN_HOME . 'www/init.php';
	}

	$rControllerClass = $rApiControllerMap[$rApiName];
	$controller = new $rControllerClass();
	register_shutdown_function([$controller, 'shutdown']);
	$controller->index();
	exit;
}

// ─────────────────────────────────────────────────────────────────
//  4. Bootstrap — sesión + datos globales (cadena legacy)
// ─────────────────────────────────────────────────────────────────

// Directorio de páginas del scope actual.
// Los archivos admin se movieron a public/Views/admin/ (Phase 10.6).
if ($scope === 'admin') {
    $adminDir = MAIN_HOME . 'public/Views/admin/';
} else {
    $adminDir = MAIN_HOME . $scope . '/';
}
$cwdTarget = is_dir($adminDir) ? $adminDir : MAIN_HOME;

// Mantenemos los includes relativos legacy dentro de los archivos de páginas
// (algunas páginas aún usan include 'header.php', etc.).
@chdir($cwdTarget);

// Para el scope reseller: bootstrap desde infrastructure/bootstrap/
if ($scope === 'reseller') {
    $sessionFile    = MAIN_HOME . 'infrastructure/bootstrap/reseller_session.php';
    $functionsFile  = MAIN_HOME . 'infrastructure/bootstrap/reseller_functions.php';
} elseif ($scope === 'player') {
    $sessionFile    = MAIN_HOME . 'infrastructure/bootstrap/player_session.php';
    $functionsFile  = MAIN_HOME . 'infrastructure/bootstrap/player_functions.php';
} else {
    $sessionFile    = MAIN_HOME . 'infrastructure/bootstrap/admin_session_fc.php';
    $functionsFile  = MAIN_HOME . 'infrastructure/bootstrap/admin_functions_fc.php';
}

// Algunas páginas se saltan el bootstrap (login, setup, database)
// Para el scope player: solo login (index = home page, no login)
if ($scope === 'player') {
    $noBootstrapPages = ['login'];
} else {
    $noBootstrapPages = ['login', 'setup', 'database', 'index'];
}

// ─────────────────────────────────────────────────────────────────
//  4a. Páginas sin bootstrap
// ─────────────────────────────────────────────────────────────────
//
//  login, setup, database, index — tienen su propio bootstrap.
//  Todos los scope (admin, reseller, player) se enrutan a través del Router.
//  Los controladores de noBootstrapPages delegan directamente en los archivos legacy.
//
//  IMPORTANTE: ¡NO cargamos includes/admin.php aquí!
//  Si se carga includes/admin.php con require_once, cuando
//  el archivo legacy intente cargarlo de nuevo (vía functions.php),
//  require_once saltará la carga y las variables ($db, $rMobile, etc.)
//  no quedarán definidas en el scope del controlador.
// ─────────────────────────────────────────────────────────────────

if (in_array($pageName, $noBootstrapPages, true)) {
    $router = Router::getInstance();
    require_once __DIR__ . '/routes/' . $scope . '.php';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($router->dispatch($pageName, $method)) {
        exit;
    }
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

// ─────────────────────────────────────────────────────────────────
//  4b. Bootstrap — sesión + datos globales (cadena legacy)
// ─────────────────────────────────────────────────────────────────
//
//  session.php → functions.php → includes/admin.php → autoload.php
//  Tras esto quedan disponibles: Router, Request, Response, todas las clases domain.
// ─────────────────────────────────────────────────────────────────

if (file_exists($sessionFile)) {
    require $sessionFile;
}

if (file_exists($functionsFile)) {
    require $functionsFile;
}

// ─────────────────────────────────────────────────────────────────
//  5. Carga de rutas
// ─────────────────────────────────────────────────────────────────

$router = Router::getInstance();
$routesDir = __DIR__ . '/routes/';

// Cargamos las rutas del scope actual
$routeFile = $routesDir . $scope . '.php';
if (file_exists($routeFile)) {
    require_once $routeFile;
}

// Rutas API (comunes para admin y reseller)
$apiRouteFile = $routesDir . 'api.php';
if (file_exists($apiRouteFile)) {
    require_once $apiRouteFile;
}

// ─────────────────────────────────────────────────────────────────
//  6. Dispatch a través del Router
// ─────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$useLegacyFallback = ($rSettings['use_legacy_fallback'] ?? true);

// Rutas de páginas (reseller: api/table/post y demás van aquí)
if ($router->dispatch($pageName, $method)) {
        exit; // El Router procesó la petición
}

// Peticiones API: /admin/api?action=... se procesan aparte (admin legacy)
// Nota: si la ruta 'api' está registrada en el Router (Phase 10.4),
// el dispatch() de arriba ya procesó la petición y no llegamos aquí.
if ($pageName === 'api' && !empty($_REQUEST['action'])) {
    $action = $_REQUEST['action'];

    if ($router->dispatchApi($action)) {
                exit; // El Router procesó la petición API
    }

        // Fallback: pasamos al legacy admin/api.php (feature-flagged)
    if ($useLegacyFallback) {
        $legacyApi = $adminDir . 'api.php';
        if (file_exists($legacyApi)) {
            require $legacyApi;
            exit;
        }
    }

    Response::jsonError('Unknown action: ' . $action, 404);
}

// ─────────────────────────────────────────────────────────────────
//  7. Fallback — legacy include (feature-flagged)
// ─────────────────────────────────────────────────────────────────
//
//  Si el Router no conoce la ruta, incluimos el archivo PHP legacy.
//  El bootstrap ya está cargado (paso 4b), así que session/functions están disponibles.
//
//  Feature flag: use_legacy_fallback (default: true).
//  Cuando todas las páginas estén registradas en el Router, cambiar a false
//  para desactivar totalmente el fallback.
// ─────────────────────────────────────────────────────────────────

if ($useLegacyFallback) {
    $legacyFile = $adminDir . $pageName . '.php';
    if (file_exists($legacyFile)) {
                // Evitamos un bootstrap repetido — el FC ya cargó sesión y funciones (paso 4b).
        $__viewMode = true;
        require $legacyFile;
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────
//  8. 404 — página no encontrada
// ─────────────────────────────────────────────────────────────────

http_response_code(404);

if (function_exists('generate404')) {
    generate404();
} else {
    echo '404 Not Found';
}
