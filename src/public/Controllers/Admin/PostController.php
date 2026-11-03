<?php

/**
 * PostController — procesador POST de los formularios del panel admin.
 *
 * Todos los formularios envían los datos vía submitForm() → POST post.php?action=xxx.
 * Este controlador procesa las peticiones POST directas a /post.
 *
 * IMPORTANTE: post.php tiene lógica dual-mode basada en el count de get_included_files():
 *   - Cuando $rICount > 1 (included from footer) → emite JavaScript
 *   - Cuando $rICount == 1 (direct access) → procesa el POST
 *
 * Vía FC post.php llega aquí tras el bootstrap, por lo que $rICount > 1.
 * Para activar el procesamiento POST, forzamos la redefinición de la condición.
 */
class PostController extends BaseAdminController
{
	public function index()
	{
		@chdir(MAIN_HOME . 'public/Views/admin/');

				// Forzamos $rICount = 1 para que post.php procese el POST,
				// y no emita JavaScript. El include count ya es > 1 por el bootstrap del FC.
		$GLOBALS['__forcePostMode'] = true;

		require MAIN_HOME . 'public/Views/admin/post.php';
	}
}
