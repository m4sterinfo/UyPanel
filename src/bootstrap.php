<?php

/**
 * Punto único de inicialización (bootstrap)
 *
 * Sustituye tres archivos bootstrap duplicados:
 *   1. includes/admin.php       (líneas 1-100: session, defines, DB, API init)
 *   2. www/init.php             (DB + CoreUtilities para crons y www)
 *   3. www/stream/init.php      (constants + LegacyInitializer para streaming)
 *
 * Cada uno de estos archivos duplica: carga de constantes, conexión a BD,
 * flood-protection, Logger, funciones de error. bootstrap.php unifica la parte
 * común y proporciona inicialización dependiente del contexto.
 *
 * ──────────────────────────────────────────────────────────────────
 * Contextos de inicialización:
 * ──────────────────────────────────────────────────────────────────
 *
 *   CONTEXT_MINIMAL  — solo autoload + constantes + config + Logger.
 *                      Sin conexión a BD. Para scripts que solo
 *                      necesitan rutas y configuración.
 *
 *   CONTEXT_CLI      — + Database + CoreUtilities::init().
 *                      Para tareas cron y scripts CLI.
 *
 *   CONTEXT_STREAM   — + Database + LegacyInitializer (camino ligero).
 *                      Para endpoints de streaming (live, vod, timeshift).
 *                      No carga admin_api, Translator, etc.
 *
 *   CONTEXT_ADMIN    — + Database + CoreUtilities + API + ResellerAPI
 *                      + Translator + MobileDetect + session.
 *                      Inicialización completa para el panel admin/reseller.
 *
 * ──────────────────────────────────────────────────────────────────
 * Uso:
 * ──────────────────────────────────────────────────────────────────
 *
 *   // En un controlador admin:
 *   require_once '/home/uypanel/bootstrap.php';
 *   XC_Bootstrap::boot(XC_Bootstrap::CONTEXT_ADMIN);
 *
 *   // En una tarea cron:
 *   require_once '/home/uypanel/bootstrap.php';
 *   XC_Bootstrap::boot(XC_Bootstrap::CONTEXT_CLI);
 *
 *   // En un endpoint de streaming:
 *   require_once '/home/uypanel/bootstrap.php';
 *   XC_Bootstrap::boot(XC_Bootstrap::CONTEXT_STREAM, ['cached' => true]);
 *
 *   // Solo constantes (sin BD):
 *   require_once '/home/uypanel/bootstrap.php';
 *   XC_Bootstrap::boot(XC_Bootstrap::CONTEXT_MINIMAL);
 *
 * ──────────────────────────────────────────────────────────────────
 * Compatibilidad hacia atrás:
 * ──────────────────────────────────────────────────────────────────
 *
 *   $db sigue siendo una variable global.
 *   Todas las propiedades estáticas de CoreUtilities
 *   se inicializan como antes.
 *   Los archivos antiguos (admin.php, init.php, stream/init.php) siguen
 *   funcionando — bootstrap.php se usa en paralelo, no en su lugar.
 *   A medida que avance la migración, los bootstrap antiguos delegarán aquí.
 */

declare(strict_types=0);

// ─────────────────────────────────────────────────────────────────
//  1. Autocargador de clases
// ─────────────────────────────────────────────────────────────────

require_once __DIR__ . '/autoload.php';
// Tras esto: MAIN_HOME definido, XC_Autoloader inicializado


// ─────────────────────────────────────────────────────────────────
//  2. Polyfills (necesarios antes de cualquier procesamiento HTTP)
// ─────────────────────────────────────────────────────────────────

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }
}


// ─────────────────────────────────────────────────────────────────
//  3. Clase XC_Bootstrap
// ─────────────────────────────────────────────────────────────────

class XC_Bootstrap {

        // ── Contextos ─────────────────────────────────────────────
    const CONTEXT_MINIMAL  = 'minimal';
    const CONTEXT_CLI      = 'cli';
    const CONTEXT_STREAM   = 'stream';
    const CONTEXT_ADMIN    = 'admin';

        // ── Estado interno ────────────────────────────────────────
    private static $booted = false;
    private static $context = null;
    private static $options = [];

        // ── Flags de inicialización de subsistemas ───────────────
    private static $constantsLoaded  = false;
    private static $configLoaded     = false;
    private static $loggerStarted    = false;
    private static $databaseReady    = false;
    private static $coreReady        = false;
    private static $adminReady       = false;
    private static $sessionStarted   = false;
    private static $redisReady       = false;

    /**
          * Punto de entrada principal.
     *
          * @param string $context  Contexto: CONTEXT_MINIMAL | CONTEXT_CLI | CONTEXT_STREAM | CONTEXT_ADMIN
          * @param array  $options  Parámetros adicionales:
          *   'cached'      => bool   Usar caché de ajustes (para stream/cli, default: false)
          *   'redis'       => bool   Conectar Redis (default: true para admin, false para el resto)
          *   'process'     => string Nombre del proceso para cli_set_process_title()
          *   'shutdown'    => callable Callback al apagar (sustituye a register_shutdown_function)
     */
    public static function boot(string $context = self::CONTEXT_CLI, array $options = []): void {
        if (self::$booted) {
            return;
        }

        self::$context = $context;
        self::$options = array_merge(self::defaults($context), $options);

                // ── Creación del contenedor ─────────────────────────────
        $container = ServiceContainer::getInstance();
        $container->set('context', $context);
        $container->set('options', self::$options);

                // ── Común a todos los contextos ────────────────────────

                // Constantes y rutas (MAIN_HOME, INCLUDES_PATH, CONFIG_PATH, ...) + $_INFO + Logger + funciones de error
        self::loadConstants();

                // Registramos la configuración en el contenedor
        global $_INFO;
        $container->set('config', $_INFO);

                // ── Flood-protection (solo HTTP) ───────────────────────
        if (!self::isCli()) {
            self::floodProtection();
            self::hostVerification();
        }

                // ── Inicialización según contexto ──────────────────────

        switch ($context) {
            case self::CONTEXT_MINIMAL:
                                // Solo constantes + config. Listo.
                break;

            case self::CONTEXT_CLI:
                self::initDatabase(self::$options['cached']);
                self::initCoreUtilities(self::$options['cached']);
                if (self::$options['redis']) {
                    self::initRedis();
                }
                if (!empty(self::$options['process'])) {
                    cli_set_process_title(self::$options['process']);
                }
                break;

            case self::CONTEXT_STREAM:
                self::initDatabase(true);
                break;

            case self::CONTEXT_ADMIN:
                self::initSession();
                self::initDatabase(false);
                self::initCoreUtilities(false);
                self::initRedis();
                self::initAdminAPI();
                self::initTranslator();
                self::registerAdminShutdown();
                break;
        }

                // ── Registro de servicios en el contenedor ─────────────
        self::populateContainer($container);

        self::$booted = true;
    }

    // ─────────────────────────────────────────────────────────
        //  Getters públicos
    // ─────────────────────────────────────────────────────────

    /**
          * Contexto boot actual
     */
    public static function getContext(): ?string {
        return self::$context;
    }

    /**
          * Indica si el bootstrap se ha ejecutado
     */
    public static function isBooted(): bool {
        return self::$booted;
    }

    /**
          * Referencia a Database (compatibilidad hacia atrás)
     */
    public static function getDatabase(): ?Database {
        global $db;
        return $db;
    }

    /**
          * Obtener el ServiceContainer.
     *
     * @return ServiceContainer
     */
    public static function getContainer(): ServiceContainer {
        return ServiceContainer::getInstance();
    }

    /**
          * Comprueba: ¿se ejecuta en modo CLI?
     */
    public static function isCli(): bool {
        return php_sapi_name() === 'cli' || defined('STDIN');
    }

    /**
          * Reset forzado (para pruebas)
     */
    public static function reset(): void {
        self::$booted          = false;
        self::$context         = null;
        self::$options         = [];
        self::$constantsLoaded = false;
        self::$configLoaded    = false;
        self::$loggerStarted   = false;
        self::$databaseReady   = false;
        self::$coreReady       = false;
        self::$adminReady      = false;
        self::$sessionStarted  = false;
        self::$redisReady      = false;

        ServiceContainer::resetInstance();
    }

    // ─────────────────────────────────────────────────────────
        //  Inicialización de subsistemas (se invocan máx. 1 vez)
    // ─────────────────────────────────────────────────────────

    /**
          * Carga de constantes, rutas, $_INFO, Logger, funciones de error.
     *
          * Delega en www/constants.php — fachada que incluye:
     *   core/Error/ErrorCodes.php      — $rErrorCodes
     *   core/Error/ErrorHandler.php    — generateError(), generate404()
          *   core/Config/Paths.php          — constantes *_PATH
          *   core/Config/AppConfig.php      — versión, Git, flags
     *   core/Config/Binaries.php       — FFMPEG, FFPROBE, GeoIP
          *   core/Config/ConfigLoader.php   — $_INFO desde config.ini
     *   core/Http/RequestGuard.php     — flood/host check + Logger
     *
          * autoload.php ya se cargó en bootstrap.php más arriba,
          * constants.php volverá a llamar require_once — no se cargará de nuevo.
     */
    private static function loadConstants(): void {
        if (self::$constantsLoaded) {
            return;
        }

        require_once MAIN_HOME . 'www/constants.php';

        self::$constantsLoaded = true;
        self::$configLoaded    = true;  // $_INFO cargado dentro de ConfigLoader.php
        self::$loggerStarted   = true;  // Logger::init() invocado dentro de RequestGuard.php
    }

    /**
          * Flood-protection: bloqueo de IPs baneadas.
     *
          * Se invoca solo para contextos HTTP.
          * Lógica de constants.php / stream/init.php — comprobación del archivo block_{IP}.
     */
    private static function floodProtection(): void {
        if (self::isCli()) {
            return;
        }

        $rIP = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($rIP) && file_exists(FLOOD_TMP_PATH . 'block_' . $rIP)) {
            http_response_code(403);
            exit();
        }
    }

    /**
          * Comprobación de host: la petición llegó desde un dominio permitido.
     *
          * Lógica de constants.php / stream/init.php — verify_host + allowed_domains.
     */
    private static function hostVerification(): void {
        if (self::isCli()) {
            return;
        }

        if (!defined('HOST')) {
            $host = trim(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
            define('HOST', $host);
        }

                // Comprobación del dominio vía caché de ajustes
        if (file_exists(CACHE_TMP_PATH . 'settings')) {
            $rData = @file_get_contents(CACHE_TMP_PATH . 'settings');
            if ($rData !== false) {
                $rSettings = @igbinary_unserialize($rData);
                if (is_array($rSettings) && !empty($rSettings['verify_host'])) {
                    if (file_exists(CACHE_TMP_PATH . 'allowed_domains')) {
                        $rDomains = @igbinary_unserialize(@file_get_contents(CACHE_TMP_PATH . 'allowed_domains'));
                        if (
                            is_array($rDomains) && count($rDomains) > 0
                            && !in_array(HOST, $rDomains) && HOST !== 'uypanel'
                            && !filter_var(HOST, FILTER_VALIDATE_IP)
                        ) {
                            generateError('INVALID_HOST');
                        }
                    }
                }
            }
        }
    }

    /**
          * Inicio de sesión PHP con parámetros seguros.
     *
          * Lógica de admin.php líneas 3-8.
          * Solo para contextos HTTP (admin/reseller).
     */
    private static function initSession(): void {
        if (self::$sessionStarted || self::isCli()) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            $rParams = session_get_cookie_params() ?: [];
            $rParams['samesite'] = 'Strict';
            session_set_cookie_params($rParams);
            session_start();
        }

        self::$sessionStarted = true;
    }

    /**
          * Conexión a MySQL/MariaDB.
     *
          * Crea la variable global $db (compatibilidad hacia atrás).
     *
          * @param bool $cached Si es true, CoreUtilities::init(true) usará
          *                     caché de archivos en lugar de consultas SQL para los ajustes.
     */
    private static function initDatabase(bool $cached = false): void {
        if (self::$databaseReady) {
            return;
        }

        global $db, $_INFO;

        require_once MAIN_HOME . 'core/Database/DatabaseHandler.php';

        $db = new DatabaseHandler(
            $_INFO['username'],
            $_INFO['password'],
            $_INFO['database'],
            $_INFO['hostname'],
            $_INFO['port']
        );

        self::$databaseReady = true;
    }

    /**
          * Inicialización de CoreUtilities.
     *
          * Limpia las globales ($_GET, $_POST, $_SESSION, $_COOKIE),
          * parsea la config, determina SERVER_ID, elige los binarios FFmpeg,
          * carga los ajustes (desde BD o caché).
     *
          * @param bool $cached Usar caché para los ajustes (para rutas de alta carga)
     */
    private static function initCoreUtilities(bool $cached = false): void {
        if (self::$coreReady) {
            return;
        }

        global $db;

        require_once MAIN_HOME . 'core/Init/LegacyInitializer.php';

        DatabaseFactory::set($db);
        LegacyInitializer::initCore($cached);

                // Si se usó caché y la caché está incompleta — reconectar a la BD
        if ($cached && !SettingsManager::getAll()['enable_cache']) {
            global $_INFO;
            $db = new DatabaseHandler(
                $_INFO['username'],
                $_INFO['password'],
                $_INFO['database'],
                $_INFO['hostname'],
                $_INFO['port']
            );
            DatabaseFactory::set($db);
        }

        self::$coreReady = true;
    }

    /**
          * Conexión a Redis.
     */
    private static function initRedis(): void {
        if (self::$redisReady) {
            return;
        }

        RedisManager::ensureConnected();
        self::$redisReady = true;
    }

    /**
          * Inicialización de Admin API + Reseller API.
     *
          * Carga reseller_api.php,
          * inicializa la clase ResellerAPI y la info del admin user.
     */
    private static function initAdminAPI(): void {
        if (self::$adminReady) {
            return;
        }

        global $db;

        require_once INCLUDES_PATH . 'reseller_api.php';

        // Admin user info (formerly API::$rUserInfo)
        if (isset($_SESSION['hash'])) {
            $GLOBALS['rAdminUserInfo'] = UserRepository::getRegisteredUserById($_SESSION['hash']);
        }

        ResellerAPI::init();

        self::$adminReady = true;
    }

    /**
          * Inicialización del Translator (multilenguaje).
     */
    private static function initTranslator(): void {
        require_once INCLUDES_PATH . 'libs/Translator.php';

        $language = Translator::class;
        $language::init(MAIN_HOME . 'includes/langs/');
    }

    /**
          * Registro de la función shutdown para el contexto admin.
     *
          * Cierra la conexión MySQL al finalizar el script.
     */
    private static function registerAdminShutdown(): void {
        register_shutdown_function(function () {
            global $db;
            if (is_object($db)) {
                $db->close_mysql();
            }
        });
    }

    /**
          * Rellenar el contenedor con los servicios inicializados.
     *
          * Se invoca al final de boot() — todos los subsistemas ya están en marcha,
          * se puede referenciar con seguridad $db, CoreUtilities, etc.
     *
          * El contenedor almacena:
          *   'db'           => Database       — envoltorio PDO
          *   'config'       => array          — $_INFO desde config.ini
          *   'settings'     => array          — ajustes del panel
          *   'redis'        => Redis|null     — conexión a Redis
          *   'servers'      => array          — lista de servidores
          *   'bouquets'     => array          — bouquets
          *   'categories'   => array          — categorías
          *   'translator'   => string         — clase Translator
     *
     * @param ServiceContainer $container
     */
    private static function populateContainer(ServiceContainer $container): void {
                // Base de datos
        if (self::$databaseReady) {
            global $db;
            $container->set('db', $db);
        }

                // Ajustes y datos de CoreUtilities
        if (self::$coreReady) {
            $container->set('settings',   SettingsManager::getAll());
            $container->set('servers',    ServerRepository::getAll());
            $container->set('bouquets',   BouquetService::getAll());
            $container->set('categories', CategoryService::getFromDatabase());

            if (self::$redisReady && RedisManager::isConnected()) {
                $container->set('redis', RedisManager::instance());
            }
        }

        // Translator
        if (class_exists('Translator', false) && Translator::available()) {
            $container->set('translator', Translator::class);
        }
    }

    /**
          * Valores por defecto de las opciones para cada contexto.
     *
     * @param string $context
     * @return array
     */
    private static function defaults(string $context): array {
        switch ($context) {
            case self::CONTEXT_ADMIN:
                return [
                    'cached'   => false,
                    'redis'    => true,
                    'process'  => '',
                    'shutdown' => null,
                ];

            case self::CONTEXT_STREAM:
                return [
                    'cached'   => true,
                    'redis'    => false,
                    'process'  => '',
                    'shutdown' => null,
                ];

            case self::CONTEXT_CLI:
                return [
                    'cached'   => false,
                    'redis'    => false,
                    'process'  => '',
                    'shutdown' => null,
                ];

            case self::CONTEXT_MINIMAL:
            default:
                return [
                    'cached'   => false,
                    'redis'    => false,
                    'process'  => '',
                    'shutdown' => null,
                ];
        }
    }

    // ─────────────────────────────────────────────────────────
        //  Constantes de estado auxiliares (de admin.php)
    // ─────────────────────────────────────────────────────────

    /**
          * Definir las constantes de status (STATUS_FAILURE, STATUS_SUCCESS, ...).
     *
          * Estas constantes están definidas en admin.php (líneas 10-55) y se usan
          * por todas partes en admin_api.php / reseller_api.php.
          * Se invoca automáticamente con CONTEXT_ADMIN.
          * Puede invocarse manualmente si es necesario.
     */
    public static function defineStatusConstants(): void {
                // Protección contra redefinición
        if (defined('STATUS_FAILURE')) {
            return;
        }

        define('STATUS_FAILURE', 0);
        define('STATUS_SUCCESS', 1);
        define('STATUS_SUCCESS_MULTI', 2);
        define('STATUS_CODE_LENGTH', 3);
        define('STATUS_NO_SOURCES', 4);
        define('STATUS_DISABLED', 5);
        define('STATUS_NOT_ADMIN', 6);
        define('STATUS_INVALID_EMAIL', 7);
        define('STATUS_INVALID_PASSWORD', 8);
        define('STATUS_INVALID_IP', 9);
        define('STATUS_INVALID_PLAYLIST', 10);
        define('STATUS_INVALID_NAME', 11);
        define('STATUS_INVALID_CAPTCHA', 12);
        define('STATUS_INVALID_CODE', 13);
        define('STATUS_INVALID_DATE', 14);
        define('STATUS_INVALID_FILE', 15);
        define('STATUS_INVALID_GROUP', 16);
        define('STATUS_INVALID_DATA', 17);
        define('STATUS_INVALID_DIR', 18);
        define('STATUS_INVALID_MAC', 19);
        define('STATUS_EXISTS_CODE', 20);
        define('STATUS_EXISTS_NAME', 21);
        define('STATUS_EXISTS_USERNAME', 22);
        define('STATUS_EXISTS_MAC', 23);
        define('STATUS_EXISTS_SOURCE', 24);
        define('STATUS_EXISTS_IP', 25);
        define('STATUS_EXISTS_DIR', 26);
        define('STATUS_SUCCESS_REPLACE', 27);
        define('STATUS_FLUSH', 28);
        define('STATUS_TOO_MANY_RESULTS', 29);
        define('STATUS_SPACE_ISSUE', 30);
        define('STATUS_INVALID_USER', 31);
        define('STATUS_CERTBOT', 32);
        define('STATUS_CERTBOT_INVALID', 33);
        define('STATUS_INVALID_INPUT', 34);
        define('STATUS_NOT_RESELLER', 35);
        define('STATUS_NO_TRIALS', 36);
        define('STATUS_INSUFFICIENT_CREDITS', 37);
        define('STATUS_INVALID_PACKAGE', 38);
        define('STATUS_INVALID_TYPE', 39);
        define('STATUS_INVALID_USERNAME', 40);
        define('STATUS_INVALID_SUBRESELLER', 41);
        define('STATUS_NO_DESCRIPTION', 42);
        define('STATUS_NO_KEY', 43);
        define('STATUS_EXISTS_HMAC', 44);
        define('STATUS_CERTBOT_RUNNING', 45);
        define('STATUS_RESERVED_CODE', 46);
        define('STATUS_NO_TITLE', 47);
        define('STATUS_NO_SOURCE', 48);
    }
}
