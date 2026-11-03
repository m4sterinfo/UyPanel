<?php

/**
 * LoginLogController — Login Logs (admin/login_logs.php).
 *
 * Server-side DataTable con los logs de acceso. API block IP.
 *
 * Legacy: admin/login_logs.php (257 líneas)
 * Route:  GET /admin/login_logs → index()
 */
class LoginLogController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->setTitle('Login Logs');
        $this->render('login_logs');
    }
}
