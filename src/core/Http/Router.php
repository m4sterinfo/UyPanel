<?php

/**
 * HTTP Router
 *
 * Enrutador de peticiones. Sustituye el patrón switch($rAction) en admin/api.php
 * y los include directos en las páginas admin. Soporta rutas modulares.
 *
 * ──────────────────────────────────────────────────────────────────
 * Uso:
 * ──────────────────────────────────────────────────────────────────
 *
 *   $router = new Router();
 *
 *   // Registro de rutas directo
 *   $router->get('watch', [WatchController::class, 'index']);
 *   $router->get('watch/add', [WatchController::class, 'add']);
 *   $router->post('watch/save', [WatchController::class, 'save']);
 *
 *   // Agrupación con prefijo
 *   $router->group('plex', function(Router $r) {
 *       $r->get('', [PlexController::class, 'index']);
 *       $r->get('add', [PlexController::class, 'add']);
 *       $r->post('save', [PlexController::class, 'save']);
 *   });
 *
 *   // Rutas API (JSON)
 *   $router->api('watch/enable', [WatchController::class, 'apiEnable']);
 *   $router->api('watch/disable', [WatchController::class, 'apiDisable']);
 *
 *   // Dispatch (determina la ruta por URL e invoca el handler)
 *   $router->dispatch($page, $method);
 *
 * ──────────────────────────────────────────────────────────────────
 * Registro modular:
 * ──────────────────────────────────────────────────────────────────
 *
 *   // En el módulo (implementación de ModuleInterface::registerRoutes):
 *   class WatchModule implements ModuleInterface {
 *       public function registerRoutes(Router $router): void {
 *           $router->group('watch', function(Router $r) {
 *               $r->get('', [WatchController::class, 'index']);
 *               $r->get('add', [WatchController::class, 'add']);
 *               $r->post('settings', [WatchController::class, 'saveSettings']);
 *               $r->api('enable', [WatchController::class, 'apiEnable']);
 *           });
 *       }
 *   }
 *
 * ──────────────────────────────────────────────────────────────────
 * Compatibilidad hacia atrás:
 * ──────────────────────────────────────────────────────────────────
 *
 *   Mientras admin/api.php use switch($rAction), los módulos pueden
 *   registrar rutas API a través del Router, y el código legacy invoca
 *   $router->dispatchApi($action) como fallback al final de la cadena switch.
 *
 * @see core/Http/Request.php
 * @see core/Http/Response.php
 * @see ModuleInterface::registerRoutes()
 */

class Router {

    /**
          * Rutas registradas para páginas (GET)
          * Formato: ['route/path' => ['handler' => callable, 'middleware' => [...], 'permission' => [...]]]
     * @var array
     */
    protected $getRoutes = [];

    /**
          * Rutas registradas para POST
     * @var array
     */
    protected $postRoutes = [];

    /**
          * Rutas API (respuesta JSON)
          * Formato: ['action_name' => ['handler' => callable, 'permission' => [...]]]
     * @var array
     */
    protected $apiRoutes = [];

    /**
          * Prefijo de grupo actual
     * @var string
     */
    protected $groupPrefix = '';

    /**
          * Conjunto de middleware actual para el grupo
     * @var array
     */
    protected $groupMiddleware = [];

    /**
          * Conjunto de permissions actual para el grupo
     * @var array
     */
    protected $groupPermission = [];

    /**
     * Singleton instance
     * @var Router|null
     */
    protected static $instance = null;

    /**
          * Obtener el singleton
     *
     * @return Router
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
          * Reset (para tests)
     */
    public static function resetInstance() {
        self::$instance = null;
    }

    // ───────────────────────────────────────────────────────────
        //  Registro de rutas
    // ───────────────────────────────────────────────────────────

    /**
          * Registrar una ruta GET (página)
     *
          * @param string $route Ruta (p. ej. 'watch', 'watch/add')
          * @param callable|array $handler Handler: [ClassName, 'method'] o callable
          * @param array $options Opciones extra: 'permission' => ['type', 'key'], 'middleware' => [...]
     * @return $this
     */
    public function get($route, $handler, array $options = []) {
        $fullRoute = $this->buildRoute($route);
        $this->getRoutes[$fullRoute] = $this->buildRouteEntry($handler, $options);
        return $this;
    }

    /**
          * Registrar una ruta POST (procesamiento de formulario)
     *
          * @param string $route Ruta
          * @param callable|array $handler Handler
          * @param array $options Opciones extra
     * @return $this
     */
    public function post($route, $handler, array $options = []) {
        $fullRoute = $this->buildRoute($route);
        $this->postRoutes[$fullRoute] = $this->buildRouteEntry($handler, $options);
        return $this;
    }

    /**
          * Registrar una ruta para GET y POST a la vez
     *
          * @param string $route Ruta
          * @param callable|array $handler Handler
          * @param array $options Opciones extra
     * @return $this
     */
    public function any($route, $handler, array $options = []) {
        $this->get($route, $handler, $options);
        $this->post($route, $handler, $options);
        return $this;
    }

    /**
          * Registrar una ruta API (respuesta JSON vía action=...)
     *
          * Las rutas API se procesan a través de admin/api.php por nombre de action.
          * Al invocar $router->dispatchApi('watch_enable') el Router busca
          * la ruta registrada e invoca su handler.
     *
          * @param string $action Nombre de la acción (p. ej. 'enable_watch', 'disable_plex')
          * @param callable|array $handler Handler
          * @param array $options Opciones extra: 'permission' => ['type', 'key']
     * @return $this
     */
    public function api($action, $handler, array $options = []) {
        $fullAction = $this->groupPrefix ? $this->groupPrefix . '_' . $action : $action;
        $this->apiRoutes[$fullAction] = $this->buildRouteEntry($handler, $options);
        return $this;
    }

    /**
          * Agrupación de rutas con prefijo, middleware y permissions comunes
     *
          * @param string $prefix Prefijo (p. ej. 'watch', 'plex')
          * @param callable $callback function(Router $router) — registra las rutas dentro del grupo
          * @param array $options Opciones del grupo: 'middleware' => [...], 'permission' => [...]
     * @return $this
     */
    public function group($prefix, callable $callback, array $options = []) {
                // Guardamos el contexto actual
        $prevPrefix     = $this->groupPrefix;
        $prevMiddleware = $this->groupMiddleware;
        $prevPermission = $this->groupPermission;

                // Establecemos el nuevo contexto
        $this->groupPrefix     = $prevPrefix ? $prevPrefix . '/' . $prefix : $prefix;
        $this->groupMiddleware = array_merge($prevMiddleware, $options['middleware'] ?? []);
        $this->groupPermission = $options['permission'] ?? $prevPermission;

                // Invocamos el callback, que registra las rutas
        $callback($this);

                // Restauramos el contexto
        $this->groupPrefix     = $prevPrefix;
        $this->groupMiddleware = $prevMiddleware;
        $this->groupPermission = $prevPermission;

        return $this;
    }

    // ───────────────────────────────────────────────────────────
    //  Dispatch
    // ───────────────────────────────────────────────────────────

    /**
          * Resolver la ruta de una página e invocar el handler
     *
          * @param string $page Nombre de página desde la URL (p. ej. 'watch', 'plex_add' → 'plex/add')
          * @param string $method Método HTTP ('GET' o 'POST')
          * @return bool true si la ruta se encontró y ejecutó, false — no se encontró
     */
    public function dispatch($page, $method = 'GET') {
                // Normalización: 'plex_add' → 'plex/add', 'watch' → 'watch'
        $route = $this->normalizePage($page);

                // Elegimos el conjunto de rutas según el método
        $routes = ($method === 'POST') ? $this->postRoutes : $this->getRoutes;

                // Fallback: si la ruta POST no se encuentra, buscamos en GET
        if ($method === 'POST' && !isset($routes[$route]) && isset($this->getRoutes[$route])) {
            $routes = $this->getRoutes;
        }

        if (!isset($routes[$route])) {
            return false;
        }

        $entry = $routes[$route];

                // Comprobación de permisos
        if (!$this->checkPermission($entry)) {
            $this->denyAccess();
            return true;
        }

                // Ejecución del middleware
        foreach ($entry['middleware'] as $mw) {
            if (is_callable($mw)) {
                $result = call_user_func($mw);
                if ($result === false) {
                                        return true; // el middleware detuvo la ejecución
                }
            }
        }

                // Invocación del handler
        $this->callHandler($entry['handler']);
        return true;
    }

    /**
          * Resolver una ruta API e invocar el handler
     *
          * Se usa en admin/api.php como fallback para acciones modulares.
          * Ejemplo: si action='enable_watch' está registrado por un módulo,
          * el Router invocará [WatchController::class, 'apiEnable'].
     *
          * @param string $action Nombre de la acción (de $_GET['action'])
          * @return bool true si la ruta se encontró y ejecutó, false — no se encontró
     */
    public function dispatchApi($action) {
        if (!isset($this->apiRoutes[$action])) {
            return false;
        }

        $entry = $this->apiRoutes[$action];

                // Comprobación de permisos
        if (!$this->checkPermission($entry)) {
            echo json_encode(['result' => false]);
            exit();
        }

                // Invocación del handler
        $this->callHandler($entry['handler']);
        return true;
    }

    /**
          * Comprobar si una ruta de página está registrada
     *
          * @param string $page Nombre de página
     * @return bool
     */
    public function hasRoute($page) {
        $route = $this->normalizePage($page);
        return isset($this->getRoutes[$route]) || isset($this->postRoutes[$route]);
    }

    /**
          * Comprobar si una ruta API está registrada
     *
          * @param string $action Nombre de la acción
     * @return bool
     */
    public function hasApiRoute($action) {
        return isset($this->apiRoutes[$action]);
    }

    /**
          * Obtener todas las rutas registradas (para depuración)
     *
     * @return array ['get' => [...], 'post' => [...], 'api' => [...]]
     */
    public function getRoutes() {
        return [
            'get'  => array_keys($this->getRoutes),
            'post' => array_keys($this->postRoutes),
            'api'  => array_keys($this->apiRoutes),
        ];
    }

    // ───────────────────────────────────────────────────────────
    //  Internal Helpers
    // ───────────────────────────────────────────────────────────

    /**
          * Construir la ruta completa teniendo en cuenta groupPrefix
     *
     * @param string $route
     * @return string
     */
    protected function buildRoute($route) {
        if ($this->groupPrefix && $route !== '') {
            $full = $this->groupPrefix . '/' . $route;
        } else {
            $full = $this->groupPrefix ?: $route;
        }
                // Normalizamos al registrar, para que dispatch() encuentre por la misma clave
        return $this->normalizePage($full);
    }

    /**
          * Formar la entrada de ruta
     *
     * @param callable|array $handler
     * @param array $options
     * @return array
     */
    protected function buildRouteEntry($handler, array $options) {
        return [
            'handler'    => $handler,
            'middleware'  => array_merge($this->groupMiddleware, $options['middleware'] ?? []),
            'permission' => $options['permission'] ?? $this->groupPermission,
        ];
    }

    /**
          * Normalizar el nombre de página a ruta
     *
          * Convierte nombres legacy (estilo admin) al formato de ruta:
     *   'watch'          → 'watch'
     *   'watch_add'      → 'watch/add'
     *   'settings_watch' → 'settings/watch'
     *   'plex_add'       → 'plex/add'
     *   'settings_plex'  → 'settings/plex'
     *
     * @param string $page
     * @return string
     */
    protected function normalizePage($page) {
                // Quitamos la extensión .php si existe
        $page = preg_replace('/\.php$/', '', $page);
                // Convertimos _ en /
        return str_replace('_', '/', $page);
    }

    /**
          * Comprobar permisos para la ruta
     *
          * @param array $entry Entrada de ruta
     * @return bool
     */
    protected function checkPermission(array $entry) {
        if (empty($entry['permission'])) {
            return true;
        }

        $perm = $entry['permission'];

                // Soporte del formato ['type', 'key'] para Authorization::check()
        if (is_array($perm) && count($perm) === 2 && is_string($perm[0])) {
            if (class_exists('Authorization')) {
                return Authorization::check($perm[0], $perm[1]);
            }
                        return true; // fallback si la clase no está disponible
        }

                // Callable arbitrario
        if (is_callable($perm)) {
            return call_user_func($perm);
        }

        return true;
    }

    /**
          * Invocar el handler de la ruta
     *
          * Soporta:
     *   - [ClassName::class, 'method'] → new ClassName()->method()
     *   - callable (closure)
     *   - [object, 'method']
     *
     * @param callable|array $handler
     */
    protected function callHandler($handler) {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
                        // [ClassName, 'method'] → instanciamos (vía contenedor DI si está disponible)
            $class  = $handler[0];
            $method = $handler[1];

                        // Intento de obtener la instancia vía ServiceContainer (DI)
            if (class_exists('ServiceContainer', false)) {
                $container = ServiceContainer::getInstance();
                try {
                    $obj = $container->get($class);
                } catch (\Throwable $e) {
                                        // Fallback: constructor sin parámetros
                    $obj = new $class();
                }
            } else {
                $obj = new $class();
            }

            $obj->$method();
        } elseif (is_callable($handler)) {
            call_user_func($handler);
        }
    }

    /**
          * Enviar la respuesta "acceso denegado"
     */
    protected function denyAccess() {
        if (function_exists('goHome')) {
            goHome();
        } else {
            http_response_code(403);
            echo 'Access denied';
            exit();
        }
    }
}
