<?php

/**
 * EpgListController — EPG Files (admin/epgs.php).
 *
 * Listado de archivos EPG con DataTable del cliente.
 * API: delete, reload, force_epg.
 *
 * Legacy: admin/epgs.php (233 líneas)
 * Route:  GET /admin/epgs → index()
 */
class EpgListController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->setTitle('EPG Files');

        $epgs = function_exists('getEPGs') ? getEPGs() : [];

        $this->render('epgs', [
            'epgs' => $epgs,
        ]);
    }
}
