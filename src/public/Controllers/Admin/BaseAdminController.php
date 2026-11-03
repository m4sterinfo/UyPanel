<?php

/**
 * BaseAdminController — controlador base para las páginas admin.
 *
 * Encapsula el render-flow común:
 *   1. renderUnifiedLayoutHeader('admin')
 *   2. require Views/admin/{view}.php — contenido HTML
 *   3. renderUnifiedLayoutFooter('admin')
 *   4. require Views/admin/{view}.scripts.php — page-specific JS
 *
 * Controlador heredero:
 *   - Invoca requirePermission() para comprobar el acceso
 *   - Establece setTitle() para $_TITLE
 *   - Invoca render('view_name', $data) para la renderización
 *
 * @see public/Views/layouts/admin.php  — renderUnifiedLayoutHeader()
 * @see public/Views/layouts/footer.php — renderUnifiedLayoutFooter()
 * @see core/Http/Router.php                     — callHandler() → new Controller()->method()
 */
class BaseAdminController
{
        /** @var string Scope: 'admin' o 'reseller' */
    protected $scope = 'admin';

        /** @var string Título de la página */
    protected $title = '';

    /**
          * Establecer el título de la página ($_TITLE para el header legacy).
     */
    protected function setTitle($title)
    {
        $this->title = $title;
        $GLOBALS['_TITLE'] = $title;
    }

    /**
          * Comprobación de permisos generales (checkPermissions).
          * En caso de denegación — goHome() + exit.
     */
    protected function requirePermission()
    {
        if (function_exists('checkPermissions') && !checkPermissions()) {
            if (function_exists('goHome')) {
                goHome();
            }
            exit;
        }
    }

    /**
          * Comprobación de permisos extendidos (hasPermissions).
          * En caso de denegación — goHome() + exit.
     *
          * @param string $type Tipo de permiso ('adv', etc.)
          * @param string $key  Clave del permiso ('edit_user', etc.)
     */
    protected function requireAdvPermission($type, $key)
    {
        if (class_exists('Authorization') && !Authorization::check($type, $key)) {
            if (function_exists('goHome')) {
                goHome();
            }
            exit;
        }
    }

    /**
     * Render: header → view → footer → scripts.
     *
          * @param string $view Nombre del archivo view (sin .php), p. ej. 'ips'
          * @param array  $data Datos para la view (extract'd al scope)
     */
    protected function render($view, array $data = [])
    {
        // Layout functions
        require_once MAIN_HOME . 'public/Views/layouts/admin.php';
        require_once MAIN_HOME . 'public/Views/layouts/footer.php';

                // Variables globales necesarias para las plantillas view y los archivos legacy.
                // Conjunto completo, incluidas las variables del bootstrap, functions.php
                // y admin_constants.php, para que el body code legacy pueda usarlas.
        $viewGlobals = [
            // Core rendering
            'language', 'db', 'rSettings', 'rMobile', 'rUserInfo',
            'rPermissions', '_TITLE', '_STATUS', '_PAGE', 'rRequest',
            // Theme/UI
            'rThemes', 'rHues',
            // Servers
            'rServers', 'allServers', 'rProxyServers',
            'rServerError', 'allServersHealthy', 'updateRequired',
            // Locale/Geo
            'allowedLangs', 'rTMDBLanguages', 'rGeoCountries',
            'rCountryCodes', 'rCountries',
            // Devices & constants
            'rMAGs', 'rTimezones',
            // Status arrays (admin_constants.php)
            'rStatusArray', 'rSearchStatusArray', 'rVODStatusArray',
            'rWatchStatusArray', 'rFailureStatusArray', 'rStreamLogsArray',
            'rResellerActions', 'rClientFilters',
            // Permissions
            'rPermissionKeys', 'rAdvPermissions',
            // Misc from bootstrap
            'rDetect', 'rTimeout', 'rProtocol',
            // Reseller-specific
            'rGenTrials',
        ];
        foreach ($viewGlobals as $_g) {
            if (array_key_exists($_g, $GLOBALS) && !array_key_exists($_g, $data)) {
                $data[$_g] = $GLOBALS[$_g];
            }
        }
        unset($_g);

        extract($data);

        $__viewsDir = MAIN_HOME . 'public/Views/' . $this->scope . '/';

        // 1. Header
        renderUnifiedLayoutHeader($this->scope);

        // Header may define new globals (e.g. reseller header sets rGenTrials)
        foreach ($viewGlobals as $_g) {
            if (!isset($$_g) && array_key_exists($_g, $GLOBALS)) {
                $$_g = $GLOBALS[$_g];
            }
        }
        unset($_g);

        // 2. View content
        $__viewFile = $__viewsDir . $view . '.php';
        $__scriptsFile = $__viewsDir . $view . '.scripts.php';
        $__hasScriptsFile = file_exists($__scriptsFile);

        if (file_exists($__viewFile)) {
            $__viewMode = true;
            require $__viewFile;
        }

        // Split mode: separate .scripts.php exists → footer + scripts handled here.
        // Clean mode: no .scripts.php + no $__viewMode → controller renders footer.
        // Unified (proxy) mode: no .scripts.php + $__viewMode set → proxy view handles footer itself.
        if ($__hasScriptsFile) {
            renderUnifiedLayoutFooter($this->scope);
            require $__scriptsFile;
        } elseif (!isset($__viewMode)) {
            renderUnifiedLayoutFooter($this->scope);
        }
    }

    /**
          * Respuesta JSON.
     */
    protected function json(array $data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
          * Redirect.
     */
    protected function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    /**
          * Obtener un parámetro de la petición (GET/POST/RequestManager::getAll()).
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function input($key, $default = null)
    {
                // Prioridad: RequestManager → $_REQUEST
        if (isset(RequestManager::getAll()[$key])) {
            return RequestManager::getAll()[$key];
        }
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
    }

    /**
          * Obtener el status actual de la petición (?status=...).
     *
     * @return mixed|null
     */
    protected function getStatus()
    {
        return isset($GLOBALS['_STATUS']) ? $GLOBALS['_STATUS'] : $this->input('status');
    }
}
