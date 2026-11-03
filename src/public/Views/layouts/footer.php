<?php

/**
 * Unified Layout Footer
 *
 * Punto de entrada único para footer/layout admin/reseller/player.
 * En la fase actual se usa como envoltorio compatible sobre el legacy footer.php,
 * para empezar la migración de páginas sin riesgo de regresiones.
 *
 * Parámetros:
 * - $scope: 'admin' | 'reseller' | 'player'
 * - $vars:  conjunto de variables de la página (opcional)
 *
 * Ejemplo:
 *   renderUnifiedLayoutFooter('admin');
 */

if (!function_exists('renderUnifiedLayoutFooter')) {
    function renderUnifiedLayoutFooter($scope = 'admin', array $vars = []) {
        foreach ($vars as $key => $value) {
            if (!array_key_exists($key, $GLOBALS)) {
                $GLOBALS[$key] = $value;
            }
        }

        // Legacy footer.php expects these variables in file scope.
        foreach ([
            'rUserInfo', 'rSettings', 'rThemes', 'rMobile', 'rHues',
            'db', 'language', 'rServers', 'allServers', 'rUpdate',
            '_TITLE', 'rModal', 'rProxyServers', 'rPermissions',
            'rServerError', 'allServersHealthy', '_PAGE', '_SETUP',
            'rStreamIDs', 'rFilterBy', 'rSortArray', 'rFilterArray',
            'rSearchBy', 'rURLs', 'rSubtitles', 'rLegacy', 'rSeries',
            'rYearStart', 'rYearEnd', 'rRatingStart', 'rRatingEnd',
        ] as $_g) {
            if (array_key_exists($_g, $GLOBALS)) {
                $$_g = $GLOBALS[$_g];
            }
        }
        unset($_g);

        $rootPath = dirname(__DIR__, 3);

        if ($scope === 'player') {
            require __DIR__ . '/player/footer.php';
            return;
        }

        if ($scope === 'reseller') {
            require __DIR__ . '/reseller/footer.php';
            return;
        }

        require dirname(__DIR__) . '/admin/footer.php';
    }
}
