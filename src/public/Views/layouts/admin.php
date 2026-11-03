<?php

/**
 * Unified Layout Header
 *
 * Punto de entrada único para header/layout admin/reseller/player.
 * En la fase actual se usa como envoltorio compatible sobre el legacy header.php,
 * para empezar la migración de páginas sin riesgo de regresiones.
 *
 * Parámetros:
 * - $scope: 'admin' | 'reseller' | 'player'
 * - $vars:  conjunto de variables de la página (opcional)
 *
 * Ejemplo:
 *   renderUnifiedLayoutHeader('admin', ['_TITLE' => 'Dashboard']);
 */

if (!function_exists('renderUnifiedLayoutHeader')) {
    function renderUnifiedLayoutHeader($scope = 'admin', array $vars = []) {
        foreach ($vars as $key => $value) {
            if (!array_key_exists($key, $GLOBALS)) {
                $GLOBALS[$key] = $value;
            }
        }

        // Legacy header.php expects these variables in file scope.
        // Since we require it from inside a function, pull them from $GLOBALS.
        foreach ([
            'rUserInfo', 'rSettings', 'rThemes', 'rMobile', 'rHues',
            'db', 'language', 'allServersHealthy', 'rServerError',
            'rServers', 'allServers', 'rUpdate', '_TITLE', 'rModal',
            'rProxyServers', 'rPermissions', '_PAGE', '_SETUP',
        ] as $_g) {
            if (array_key_exists($_g, $GLOBALS)) {
                $$_g = $GLOBALS[$_g];
            }
        }
        unset($_g);

        $rootPath = dirname(__DIR__, 3);

        if ($scope === 'player') {
            require __DIR__ . '/player/header.php';
            return;
        }

        if ($scope === 'reseller') {
            require __DIR__ . '/reseller/header.php';
            return;
        }

        require dirname(__DIR__) . '/admin/header.php';
    }
}
