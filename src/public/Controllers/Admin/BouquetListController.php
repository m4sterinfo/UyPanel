<?php

/**
 * BouquetListController — Bouquets listing (admin/bouquets.php).
 *
 * Listado de bouquets con acciones delete.
 *
 * Legacy: admin/bouquets.php (163 líneas)
 * Route:  GET /admin/bouquets → index()
 */
class BouquetListController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->setTitle('Bouquets');

        $rBouquets = BouquetService::getAllSimple();

        $this->render('bouquets', compact('rBouquets'));
    }
}
