<?php
/**
 * Controlador de lista de dispositivos MAG (admin/mags.php)
 */
class MagsController extends BaseAdminController {
    public function index() {
        $this->requirePermission();
        $this->setTitle('MAG Devices');
        $this->render('mags');
    }
}
