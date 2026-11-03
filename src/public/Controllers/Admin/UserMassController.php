<?php
/**
 * Controlador de edición masiva de usuarios (admin/user_mass.php)
 */
class UserMassController extends BaseAdminController {
    public function index() {
        $this->requirePermission();
        $this->setTitle('Mass Edit Users');
        $this->render('user_mass');
    }
}
