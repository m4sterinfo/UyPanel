<?php
/**
 * ClientLogController — logs de clientes.
 */
class ClientLogController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->render('client_logs');
    }
}
