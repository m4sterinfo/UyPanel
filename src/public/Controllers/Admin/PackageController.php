<?php

/**
 * PackageController — Packages (admin/packages.php).
 *
 * Listado de paquetes (sin addons) con edit/delete.
 *
 * Legacy: admin/packages.php (239 líneas)
 * Route:  GET /admin/packages → index()
 */
class PackageController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();

        $this->setTitle('Packages');

        $allPackages = function_exists('getPackages') ? getPackages() : [];
                // Filtramos los paquetes addon (mostramos solo los normales)
        $packages = array_filter($allPackages, function ($p) {
            return empty($p['is_addon']);
        });

        $this->render('packages', [
            'packages' => $packages,
        ]);
    }
}
