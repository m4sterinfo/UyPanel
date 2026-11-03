<?php

/**
 * AdminLogoutController — Destrucción de la sesión + redirect al login.
 */
class AdminLogoutController extends BaseAdminController
{
	public function index()
	{
		if (function_exists('destroySession')) {
			destroySession();
		}
		$this->redirect('./login');
	}
}
