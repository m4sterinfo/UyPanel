<?php
/**
 * Controlador de edición de usuario (admin/user.php)
 */

class UserController extends BaseAdminController {
    public function index() {
        $this->requirePermission();

        global $db;

        $rUser = isset(RequestManager::getAll()['id']) ? UserRepository::getRegisteredUserById(RequestManager::getAll()['id']) : null;
        if ($rUser === false || ($rUser && !Authorization::check('user', $rUser['id']))) {
            $this->redirect('users');
            return;
        }

        $rPackages = $rUser ? getPackages($rUser['member_group_id']) : [];

        $this->setTitle('User');
        $this->render('user', compact('rUser', 'rPackages'));
    }
}
