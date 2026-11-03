<?php

/**
 * HmacController — HMAC Keys (admin/hmacs.php).
 *
 * Listado de tokens HMAC con edit/delete.
 *
 * Legacy: admin/hmacs.php (273 líneas)
 * Route:  GET /admin/hmacs → index()
 */
class HmacController extends BaseAdminController {
    public function index() {
        $this->requirePermission();

        $this->setTitle('HMAC Keys');

        $hmacs = AuthRepository::getAllHMAC();

        $this->render('hmacs', [
            'hmacs' => $hmacs,
        ]);
    }
}
