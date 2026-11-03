<?php

/**
 * LoginController — Página de autorización del panel admin.
 *
 * Login tiene su propio documento HTML (no usa el layout header/footer).
 * El archivo admin/login.php contiene el bootstrap completo vía functions.php.
 * El controlador delega directamente en el archivo legacy.
 */
class LoginController extends BaseAdminController
{
	public function index()
	{
		@chdir(MAIN_HOME . 'public/Views/admin/');
		require MAIN_HOME . 'public/Views/admin/login.php';
	}
}
