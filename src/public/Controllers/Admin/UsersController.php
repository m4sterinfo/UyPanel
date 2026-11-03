<?php
/**
 * Controlador de lista de usuarios (admin/users.php)
 */
class UsersController extends BaseAdminController {
    public function index() {
        $this->requirePermission();
        $this->setTitle('Users');
        $this->render('users');
    }
}
