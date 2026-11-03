<?php

/**
 * BaseResellerController — controlador base para las páginas reseller.
 *
 * Hereda BaseAdminController, redefiniendo:
 *   - $scope = 'reseller' (para renderUnifiedLayout y las rutas de views)
 *   - requirePermission() → checkResellerPermissions()
 *
 * @see BaseAdminController
 */
class BaseResellerController extends BaseAdminController
{
    /** @var string Scope: 'reseller' */
    protected $scope = 'reseller';

    /**
          * Comprobación de permisos del reseller (checkResellerPermissions).
          * En caso de denegación — goHome() + exit.
     */
    protected function requirePermission()
    {
        if (function_exists('checkResellerPermissions') && !checkResellerPermissions()) {
            if (function_exists('goHome')) {
                goHome();
            }
            exit;
        }
    }

    /**
          * Comprobación de permisos extendidos del reseller (hasPermissions).
          * Los permisos reseller se cargan vía getGroupPermissions().
     *
          * @param string $type Tipo de permiso
          * @param string $key  Clave del permiso
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
}
