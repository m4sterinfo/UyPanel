<?php

/**
 * Watch Module Controller
 *
 * Procesa todas las rutas del módulo Watch:
 * - Lista de Watch Folders (index)
 * - Añadir/editar (add)
 * - Ajustes de Watch (settings)
 * - Logs de Watch (output)
 * - Grabación (record)
 * - API: enable/disable/kill/folder actions
 *
 * ──────────────────────────────────────────────────────────────────
 * Sustituye:
 * ──────────────────────────────────────────────────────────────────
 *
 *   admin/watch.php          → index()
 *   admin/watch_add.php      → add()
 *   admin/settings_watch.php → settings()
 *   admin/watch_output.php   → output()
 *   admin/record.php         → record()
 *   admin/api.php actions:
 *     enable_watch  → apiEnable()
 *     disable_watch → apiDisable()
 *     kill_watch    → apiKill()
 *     folder (sub=delete|force) → apiFolder()
 *
 * ──────────────────────────────────────────────────────────────────
 * Uso (vía Router):
 * ──────────────────────────────────────────────────────────────────
 *
 *   $router->group('watch', function(Router $r) {
 *       $r->get('',         [WatchController::class, 'index']);
 *       $r->get('add',      [WatchController::class, 'add']);
 *       $r->get('settings', [WatchController::class, 'settings']);
 *       $r->get('output',   [WatchController::class, 'output']);
 *       $r->get('record',   [WatchController::class, 'record']);
 *   });
 *
 * @see WatchService
 * @see RecordingService
 * @see WatchModule
 */

class WatchController {

    /**
          * Ruta al directorio views del módulo
     * @var string
     */
    protected $viewsPath;

    public function __construct() {
        $this->viewsPath = dirname(__DIR__) . '/modules/watch/views';
    }

    // ───────────────────────────────────────────────────────────
        //  Páginas (GET)
    // ───────────────────────────────────────────────────────────

    /**
          * Lista de Watch Folders
     *
          * Sustituye admin/watch.php
     */
    public function index() {
        include $this->viewsPath . '/watch.php';
    }

    /**
          * Formulario de añadir/editar Watch Folder
     *
          * Sustituye admin/watch_add.php
          * Prepara los datos: $rFolder (en edit), $rBouquets
     */
    public function add() {
        global $db;

        if (isset(RequestManager::getAll()['id'])) {
            $rFolder = getWatchFolder(RequestManager::getAll()['id']);
            if (!$rFolder) {
                goHome();
                return;
            }
        }

        $rBouquets = BouquetService::getAllSimple();
        $_TITLE = isset($rFolder) ? 'Edit Folder' : 'Add Folder';

        include 'header.php';
        include $this->viewsPath . '/watch_add.php';
    }

    /**
          * Ajustes de Watch Folder
     *
          * Sustituye admin/settings_watch.php
          * Prepara: $rBouquets, categorías
     */
    public function settings() {
        $rBouquets = BouquetService::getAllSimple();
        $_TITLE = 'Watch Settings';

        include 'header.php';
        include $this->viewsPath . '/settings_watch.php';
    }

    /**
          * Logs de Watch Folder / Plex Sync
     *
          * Sustituye admin/watch_output.php
     */
    public function output() {
        $_TITLE = 'Watch Folder Logs';

        include 'header.php';
        include $this->viewsPath . '/watch_output.php';
    }

    /**
          * Formulario de grabación (Recording)
     *
          * Sustituye admin/record.php
          * Prepara: $rStream, $rProgramme, $rAvailableServers
     */
    public function record() {
        global $db;

        $rAvailableServers = $rServers = array();
        $rStream = $rProgramme = null;

        if (isset(RequestManager::getAll()['id'])) {
            $rStream = StreamRepository::getById(RequestManager::getAll()['id']);
            $rProgramme = EpgService::getProgramme(
                RequestManager::getAll()['id'],
                RequestManager::getAll()['programme']
            );
            if (!$rStream || $rStream['type'] != 1 || !$rProgramme) {
                goHome();
                return;
            }
        } elseif (isset(RequestManager::getAll()['archive'])) {
            $rArchive = json_decode(base64_decode(RequestManager::getAll()['archive']), true);
            $rStream = StreamRepository::getById($rArchive['stream_id']);
            $rProgramme = [
                'start'       => $rArchive['start'],
                'end'         => $rArchive['end'],
                'title'       => $rArchive['title'],
                'description' => $rArchive['description'],
                'archive'     => true,
            ];
            if (!$rStream || $rStream['type'] != 1 || !$rProgramme) {
                goHome();
                return;
            }
        } elseif (isset(RequestManager::getAll()['stream_id'])) {
            $rStream = StreamRepository::getById(RequestManager::getAll()['stream_id']);
            $rProgramme = [
                'start'       => strtotime(RequestManager::getAll()['start_date']),
                'end'         => strtotime(RequestManager::getAll()['start_date']) + intval(RequestManager::getAll()['duration']) * 60,
                'title'       => '',
                'description' => '',
            ];
            if (!$rStream || $rStream['type'] != 1 || !$rProgramme || $rProgramme['end'] < time()) {
                header('Location: record');
                return;
            }
        }

        if ($rStream) {
            $rBitrate = null;
            $db->query('SELECT `server_id`, `bitrate` FROM `streams_servers` WHERE `stream_id` = ?;', $rStream['id']);
            foreach ($db->get_rows() as $rRow) {
                $rAvailableServers[] = $rRow['server_id'];
                if ((!$rBitrate && $rRow['bitrate']) || ($rRow['bitrate'] && $rBitrate < $rRow['bitrate'])) {
                    $rBitrate = $rRow['bitrate'];
                }
            }
        }

        $_TITLE = 'Schedule Recording';

        include 'header.php';
        include $this->viewsPath . '/record.php';
    }

    // ───────────────────────────────────────────────────────────
        //  Acciones API (JSON)
    // ───────────────────────────────────────────────────────────

    /**
          * Activar todos los Watch Folders
     *
          * Sustituye admin/api.php action=enable_watch
     */
    public function apiEnable() {
        global $db;
        WatchService::enableWatch();
        echo json_encode(['result' => true]);
        exit();
    }

    /**
          * Desactivar todos los Watch Folders
     *
          * Sustituye admin/api.php action=disable_watch
     */
    public function apiDisable() {
        global $db;
        WatchService::disableWatch();
        echo json_encode(['result' => true]);
        exit();
    }

    /**
          * Matar todos los procesos Watch Folder
     *
          * Sustituye admin/api.php action=kill_watch
     */
    public function apiKill() {
        global $db;
        WatchService::killWatch();
        echo json_encode(['result' => true]);
        exit();
    }

    /**
          * Acciones sobre un Folder concreto (delete / force)
     *
          * Sustituye admin/api.php action=folder&sub=delete|force
     */
    public function apiFolder() {
        global $db;
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
                // Si ya hay un escaneo en marcha no lanzar otro (el cron igual
                // lo rechazaría) — el UI muestra el toast de error.
                $rPidFile = CACHE_TMP_PATH . 'watch_pid';
                if (file_exists($rPidFile)) {
                    $rPrevPID = intval(file_get_contents($rPidFile));
                    if ($rPrevPID && ProcessManager::isRunning($rPrevPID, 'php')) {
                        echo json_encode(['result' => false]);
                        exit();
                    }
                }
                WatchService::forceWatch($rFolder['server_id'], $rFolder['id']);
                echo json_encode(['result' => true]);
                exit();
            }
        }

        echo json_encode(['result' => false]);
        exit();
    }
}
