<?php

/**
 * MysqlSyslogController — System Logs (admin/mysql_syslog.php).
 *
 * Server-side DataTable con logs de sistema. API block IP.
 *
 * Legacy: admin/mysql_syslog.php (251 líneas)
 * Route:  GET /admin/mysql_syslog → index()
 */
class MysqlSyslogController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->setTitle('System Logs');
        $this->render('mysql_syslog');
    }
}
