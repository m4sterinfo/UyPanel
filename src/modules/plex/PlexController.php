<?php

/**
 * Plex Module Controller
 *
 * Procesa todas las rutas del módulo Plex:
 * - Lista de servidores Plex Sync (index)
 * - Añadir/editar biblioteca (add)
 * - Ajustes de Plex (settings)
 * - API: enable/disable/kill/library/sections actions
 *
 * ──────────────────────────────────────────────────────────────────
 * Sustituye:
 * ──────────────────────────────────────────────────────────────────
 *
 *   admin/plex.php           → index()
 *   admin/plex_add.php       → add()
 *   admin/settings_plex.php  → settings()
 *   admin/api.php actions:
 *     enable_plex  → apiEnable()
 *     disable_plex → apiDisable()
 *     kill_plex    → apiKill()
 *     library (sub=delete|force) → apiLibrary()
 *     plex_sections → apiSections()
 *
 * ──────────────────────────────────────────────────────────────────
 * Uso (vía Router):
 * ──────────────────────────────────────────────────────────────────
 *
 *   $router->group('plex', function(Router $r) {
 *       $r->get('',         [PlexController::class, 'index']);
 *       $r->get('add',      [PlexController::class, 'add']);
 *       $r->get('settings', [PlexController::class, 'settings']);
 *   });
 *
 * @see PlexService
 * @see PlexRepository
 * @see PlexModule
 */

class PlexController {

    /**
          * Ruta al directorio views del módulo
     * @var string
     */
    protected $viewsPath;

    public function __construct() {
        $this->viewsPath = dirname(__DIR__) . '/modules/plex/views';
    }

    // ───────────────────────────────────────────────────────────
        //  Páginas (GET)
    // ───────────────────────────────────────────────────────────

    /**
          * Lista de servidores Plex Sync
     *
          * Sustituye admin/plex.php
          * Prepara: $rPlexServers
     */
    public function index() {
        $rPlexServers = PlexRepository::getPlexServers();
        $_TITLE = 'Plex Sync';

        include 'header.php';
        include $this->viewsPath . '/index.php';
        include 'footer.php';
        include $this->viewsPath . '/library_scripts.php';
    }

    /**
          * Añadir/editar biblioteca Plex
     *
          * Sustituye admin/plex_add.php
          * Prepara: $rFolder (en edit), $rBouquets
     */
    public function add() {
        if (isset(RequestManager::getAll()['id'])) {
            $rFolder = getWatchFolder(RequestManager::getAll()['id']);
            if (!$rFolder) {
                goHome();
                return;
            }
        }

        $rBouquets = BouquetService::getAllSimple();
        $_TITLE = isset($rFolder) ? 'Edit Library' : 'Add Library';

        include 'header.php';
        include $this->viewsPath . '/library_edit.php';
        include 'footer.php';
        include $this->viewsPath . '/library_edit_scripts.php';
    }

    /**
          * Ajustes de Plex Sync
     *
          * Sustituye admin/settings_plex.php
          * Prepara: $rBouquets
     */
    public function settings() {
        $rBouquets = BouquetService::getAllSimple();
        $_TITLE = 'Plex Settings';

        include 'header.php';
        include $this->viewsPath . '/settings.php';
        include 'footer.php';
        include $this->viewsPath . '/settings_scripts.php';
    }

    // ───────────────────────────────────────────────────────────
        //  Acciones API (JSON)
    // ───────────────────────────────────────────────────────────

    /**
          * Activar todos los servidores Plex Sync
     *
          * Sustituye admin/api.php action=enable_plex
     */
    public function apiEnable() {
        global $db;
        $db->query("UPDATE `watch_folders` SET `active` = 1 WHERE `type` = 'plex';");
        echo json_encode(['result' => true]);
        exit();
    }

    /**
          * Desactivar todos los servidores Plex Sync
     *
          * Sustituye admin/api.php action=disable_plex
     */
    public function apiDisable() {
        global $db;
        $db->query("UPDATE `watch_folders` SET `active` = 0 WHERE `type` = 'plex';");
        echo json_encode(['result' => true]);
        exit();
    }

    /**
          * Matar todos los procesos Plex Sync
     *
          * Sustituye admin/api.php action=kill_plex
     */
    public function apiKill() {
        killPlexSync();
        echo json_encode(['result' => true]);
        exit();
    }

    /**
          * Acciones sobre una biblioteca concreta (delete / force)
     *
          * Sustituye admin/api.php action=library&sub=delete|force
     */
    public function apiLibrary() {
        $rSub = RequestManager::getAll()['sub'] ?? '';
        $rFolderID = RequestManager::getAll()['folder_id'] ?? 0;

        if ($rSub === 'delete') {
            deleteWatchFolder($rFolderID);
            echo json_encode(['result' => true]);
            exit();
        }

        if ($rSub === 'force') {
            $rFolder = getWatchFolder($rFolderID);
            if ($rFolder) {
                // Si ya hay un Plex Sync en marcha no lanzar otro (el cron
                // igual lo rechazaría) — el UI muestra el toast de error.
                $rPidFile = CACHE_TMP_PATH . 'plex_pid';
                if (file_exists($rPidFile)) {
                    $rPrevPID = intval(file_get_contents($rPidFile));
                    if ($rPrevPID && ProcessManager::isRunning($rPrevPID, 'php')) {
                        echo json_encode(['result' => false]);
                        exit();
                    }
                }
                PlexService::forcePlex($rFolder['server_id'], $rFolder['id']);
                echo json_encode(['result' => true]);
                exit();
            }
        }

        echo json_encode(['result' => false]);
        exit();
    }

    /**
          * Obtener las secciones del servidor Plex (AJAX)
     *
          * Sustituye admin/api.php action=plex_sections
     */
    public function apiSections() {
        $rIP       = RequestManager::getAll()['ip'] ?? '';
        $rPort     = RequestManager::getAll()['port'] ?? '';
        $rUsername  = RequestManager::getAll()['username'] ?? '';
        $rPassword = RequestManager::getAll()['password'] ?? '';

        $rToken = PlexAuth::getPlexToken($rIP, $rPort, $rUsername, $rPassword);
        $rSections = PlexRepository::getPlexSections($rIP, $rPort, $rToken);

        if ($rSections && count($rSections) > 0) {
            echo json_encode(['result' => true, 'data' => $rSections]);
        } else {
            echo json_encode(['result' => false]);
        }
        exit();
    }
}
