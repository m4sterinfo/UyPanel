<?php

/**
 * PlayerEmbedController — Reproductor integrado para ver streams.
 *
 * El reproductor se abre desde la UI admin (stream_view, live_connections, etc.)
 * en popup/iframe. Tiene su propio documento HTML (sin layout header/footer).
 */
class PlayerEmbedController extends BaseAdminController
{
	public function index()
	{
		$this->requirePermission();
		@chdir(MAIN_HOME . 'public/Views/admin/');
		require MAIN_HOME . 'public/Views/admin/player.php';
	}
}
