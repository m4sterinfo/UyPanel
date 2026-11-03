<?php

/**
 * PanelLogController — Panel Errors (admin/panel_logs.php).
 *
 * Server-side DataTable con los logs de errores del panel. Download/Clear logs.
 *
 * Legacy: admin/panel_logs.php (292 líneas)
 * Route:  GET /admin/panel_logs → index()
 */
class PanelLogController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->setTitle('Panel Errors');
        $this->render('panel_logs');
    }
}
