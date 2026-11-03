<?php

/**
 * BasePlayerController — controlador base para las páginas player.
 *
 * Hereda BaseAdminController, redefiniendo:
 *   - $scope = 'player' (para renderUnifiedLayout y las rutas de views)
 *   - requirePermission() — vacío (player no usa RBAC)
 *   - render() — soporta las variables globales específicas del player
 *
 * Player layout:
 *   1. renderUnifiedLayoutHeader('player') → player/header.php
 *   2. requiere Views/player/{view}.php — contenido HTML
 *   3. renderUnifiedLayoutFooter('player') → player/footer.php
 *
 * @see BaseAdminController
 */
class BasePlayerController extends BaseAdminController
{
    /** @var string Scope: 'player' */
    protected $scope = 'player';

    /**
          * Player no usa RBAC — la comprobación no es necesaria.
     */
    protected function requirePermission()
    {
                // La autorización del player la procesa el bootstrap (player_session.php).
                // Si el usuario llegó hasta el controlador — ya está autenticado.
    }

    /**
     * Render: header → view → footer.
     *
          * Redefine el render admin para las globales específicas del player.
          * El footer.php del player contiene JS específico de página, por lo que
          * todas las globales deben estar disponibles en el momento de su invocación.
     *
          * @param string $view Nombre del archivo view (sin .php)
          * @param array  $data Datos para la view (extract'd al scope)
     */
    protected function render($view, array $data = [])
    {
        require_once MAIN_HOME . 'public/Views/layouts/admin.php';
        require_once MAIN_HOME . 'public/Views/layouts/footer.php';

                // Globales específicas del player (para header.php y footer.php)
        $viewGlobals = [
            // Core
            'db', 'rSettings', 'rUserInfo', '_TITLE', '_PAGE',
            // Player data
            'rStreamIDs', 'rFilterBy', 'rSortArray', 'rFilterArray',
            'rSearchBy', 'rURLs', 'rSubtitles', 'rLegacy', 'rSeries',
            'rYearStart', 'rYearEnd', 'rRatingStart', 'rRatingEnd',
        ];
        foreach ($viewGlobals as $_g) {
            if (array_key_exists($_g, $GLOBALS) && !array_key_exists($_g, $data)) {
                $data[$_g] = $GLOBALS[$_g];
            }
        }
        unset($_g);

                // Exportamos $data a $GLOBALS para que footer.php pueda verlos
        foreach ($data as $key => $value) {
            $GLOBALS[$key] = $value;
        }

        extract($data);

        $__viewsDir = MAIN_HOME . 'public/Views/' . $this->scope . '/';

        // 1. Header
        renderUnifiedLayoutHeader($this->scope);

        // 2. View content
        $__viewFile = $__viewsDir . $view . '.php';
        if (file_exists($__viewFile)) {
            require $__viewFile;
        }

                // 3. Footer (el footer.php del player contiene JS específico de página)
        renderUnifiedLayoutFooter($this->scope);
    }

    /**
          * Redirigir al home del player.
     */
    protected function goPlayerHome()
    {
        header('Location: index');
        exit;
    }
}
