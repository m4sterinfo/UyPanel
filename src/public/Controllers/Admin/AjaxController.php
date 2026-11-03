<?php

/**
 * AjaxController — proxy para admin/api.php
 *
 * Controlador fino: delega toda la lógica en el legacy admin/api.php.
 * api.php contiene ~90 secciones action (~4500 líneas) — su refactor
 * en métodos independientes está previsto en las siguientes fases.
 *
 * @see admin/api.php
 * @since Phase 10.4
 */
class AjaxController extends BaseAdminController {

    public function index() {
        $adminDir = MAIN_HOME . 'public/Views/admin/';
        chdir($adminDir);
        require $adminDir . 'api.php';
    }
}
