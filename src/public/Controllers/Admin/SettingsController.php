<?php

/**
 * SettingsController — General Settings (admin/settings.php).
 *
 * GET /settings
 * Massive multi-tab form with ~80+ switchery toggles, select2 dropdowns,
 * 9 tabs (General, Security, API, Streaming, MAG, Web Player, Logs, Info, Database).
 */
class SettingsController extends BaseAdminController
{
    public function index(): void
    {
        $this->requirePermission();

        global $db;
        $rSettings = getSettings();
        $rStreamArguments = StreamConfigRepository::getStreamArguments();

        $versionData = json_decode(@file_get_contents(BIN_PATH . 'maxmind/version.json'), true) ?: [];
        $GeoLite2 = $versionData['geolite2_version'] ?? 'N/A';
        $GeoISP   = $versionData['geoisp_version'] ?? 'N/A';
        $rUpdate  = json_decode((string) ($rSettings['update_data'] ?? ''), true) ?: [];

        // MariaDB version
        $MariaDB = 'N/A';
        try {
            if ($db) {
                $db->query('SELECT VERSION() AS ver;');
                $rVer = $db->get_row();
                if (!empty($rVer['ver'])) {
                    $MariaDB = $rVer['ver'];
                }
            }
        } catch (Throwable $e) {}

        // Redis / KeyDB version
        $RedisVer = 'N/A';
        try {
            $redis = new Redis();
            if ($redis->connect('127.0.0.1', 6379, 1.0)) {
                $redisPass = $rSettings['redis_password'] ?? '';
                if (!empty($redisPass)) {
                    $redis->auth($redisPass);
                }
                $info = $redis->info('server');
                if (!empty($info['valkey_version'])) {
                    $RedisVer = 'Valkey ' . $info['valkey_version'];
                } elseif (!empty($info['keydb_version'])) {
                    $RedisVer = 'KeyDB ' . $info['keydb_version'];
                } elseif (!empty($info['redis_version'])) {
                    $RedisVer = 'Redis ' . $info['redis_version'];
                }
            }
        } catch (Throwable $e) {}

        // FFmpeg configured version
        $cpuVer = $rSettings['ffmpeg_cpu'] ?? '4.0';
        $FFmpegVer = 'v' . $cpuVer;

        // Alertas de software desactualizado (pestaña Info)
        $rSoftwareReport = SoftwareVersions::getReport($rSettings, $db);

        // Global lookup arrays from includes/admin.php needed by settings view
        $rTMDBLanguages = $GLOBALS['rTMDBLanguages'] ?? [];
        $rGeoCountries  = $GLOBALS['rGeoCountries'] ?? [];
        $rMAGs          = $GLOBALS['rMAGs'] ?? [];

        $this->setTitle('Settings');
        $this->render('settings', compact(
            'rSettings',
            'rStreamArguments',
            'GeoLite2',
            'GeoISP',
            'Nginx',
            'NginxRTMP',
            'MariaDB',
            'RedisVer',
            'FFmpegVer',
            'rSoftwareReport',
            'rUpdate',
            'rTMDBLanguages',
            'rGeoCountries',
            'rMAGs'
        ));
    }
}
