<?php

/**
 * IpController — Blocked IP's (admin/ips.php).
 *
 * Listado de direcciones IP bloqueadas con soporte flush y delete.
 *
 * Legacy: admin/ips.php (227 líneas)
 * Route:  GET /admin/ips → index()
 */
class IpController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        global $db;

                // Procesamiento del flush antes del render
        if ($this->input('flush') !== null) {
            if (function_exists('flushIPs')) {
                flushIPs();
            }
            $this->redirect('./ips?status=' . STATUS_FLUSH);
        }

        $this->setTitle("Blocked IP's");

        $ips = BlocklistService::getBlockedIPsSimple();

        $this->render('ips', [
            'ips' => $ips,
        ]);
    }
}
