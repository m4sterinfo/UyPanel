<?php
/**
 * Controlador de lista de dispositivos Enigma (admin/enigmas.php)
 */
class EnigmasController extends BaseAdminController {
    public function index() {
        $this->requirePermission();
        $this->setTitle('Enigma Devices');
        $this->render('enigmas');
    }
}
