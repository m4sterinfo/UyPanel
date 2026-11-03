<?php

/**
 * SetupController — Configuración inicial del sistema + MySQL admin.
 *
 * setup.php y database.php funcionan antes del bootstrap completo (puede no haber BD).
 * Ambos archivos contienen sus propios documentos HTML.
 */
class SetupController extends BaseAdminController
{
	/**
	 		 * Página de configuración inicial.
	 */
	public function index()
	{
		@chdir(MAIN_HOME . 'public/Views/admin/');
		require MAIN_HOME . 'public/Views/admin/setup.php';
	}

	/**
	 		 * PHP Mini MySQL Admin — gestión de la BD.
	 */
	public function database()
	{
		@chdir(MAIN_HOME . 'public/Views/admin/');
		require MAIN_HOME . 'public/Views/admin/database.php';
	}
}
