<?php

/**
 * ServerListController — lista de servidores (admin/servers.php).
 *
 * GET /servers
 * Client-side DataTable — PHP renderiza el <tbody>.
 * Datos: ServerRepository::getAll(true).
 */
class ServerListController extends BaseAdminController
{
    public function index(): void
    {
        $this->requirePermission();
        $this->setTitle('Servers');

        \ServerRepository::getAll(true);

        $this->render('servers');
    }
}
