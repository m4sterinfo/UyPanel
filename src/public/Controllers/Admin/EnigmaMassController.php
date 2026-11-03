<?php
/**
 * Controlador de edición masiva de dispositivos Enigma (admin/enigma_mass.php)
 */
class EnigmaMassController extends BaseAdminController {
    public function index() {
        $this->requirePermission();
        $this->setTitle('Mass Edit Devices');
        $this->render('enigma_mass');
    }
}
