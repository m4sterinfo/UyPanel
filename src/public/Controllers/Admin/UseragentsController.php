<?php
/**
 * Controlador de lista de User-Agent bloqueados (admin/useragents.php)
 */
class UseragentsController extends BaseAdminController {
    public function index() {
        $this->requirePermission();
        $this->setTitle('Blocked User-Agents');
        $this->render('useragents');
    }
}
