<?php

/**
 * ServerInstallController — instalación/reinstalación de servidor (admin/server_install.php).
 *
 * GET /server_install?proxy (type=1) o GET /server_install (type=2)
 * Opcional: ?id=N (reinstall), ?update (update).
 * Formulario: 1-2 pestañas (Details + Server Coverage para proxy).
 */
class ServerInstallController extends BaseAdminController
{
    public function index(): void
    {
        $this->requirePermission();

        global $allServers, $rProxyServers;

        $rType = isset(\RequestManager::getAll()['proxy']) ? 1 : 2;
        $rServerArr = null;

        if ($this->input('id')) {
            $id = intval($this->input('id'));
            if ($rType === 1) {
                $rServerArr = $rProxyServers[$id] ?? null;
            } else {
                $rServerArr = $allServers[$id] ?? null;
            }

            if (!$rServerArr) {
                $this->redirect('servers');
                return;
            }
        }

        $title = ($rType === 1) ? 'Install Proxy' : 'Install Server';
        $this->setTitle($title);

        $this->render('server_install', compact('rType', 'rServerArr'));
    }
}
