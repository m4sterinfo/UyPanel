<?php

/**
 * IspController — Blocked ISP's (admin/isps.php).
 *
 * Listado de ISP bloqueados con edit/delete.
 *
 * Legacy: admin/isps.php (264 líneas)
 * Route:  GET /admin/isps → index()
 */
class IspController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();

        $this->setTitle("Blocked ISP's");

        $isps = function_exists('getISPs') ? getISPs() : [];

        $this->render('isps', [
            'isps' => $isps,
        ]);
    }
}
