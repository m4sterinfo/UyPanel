<?php

/**
 * BouquetOrderController — Bouquet Order (admin/bouquet_order.php).
 *
 * Ordenación de bouquets por drag & drop. El procesamiento POST es legacy post.php.
 *
 * Legacy: admin/bouquet_order.php (167 líneas)
 * Route:  GET /admin/bouquet_order → index()
 */
class BouquetOrderController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->setTitle('Bouquet Order');

        $bouquets = BouquetService::getAllSimple();

        $this->render('bouquet_order', [
            'bouquets' => $bouquets,
        ]);
    }
}
