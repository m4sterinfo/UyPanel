<?php

/**
 * MagEventController — MAG Events (admin/mag_events.php).
 *
 * Server-side DataTable con eventos MAG. API delete.
 *
 * Legacy: admin/mag_events.php (176 líneas)
 * Route:  GET /admin/mag_events → index()
 */
class MagEventController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->setTitle('MAG Events');
        $this->render('mag_events');
    }
}
