<?php

require_once MAIN_HOME . 'cli/CronTrait.php';

class WatchCronJob implements CommandInterface {
    use CronTrait;

    public function getName(): string {
        return 'cron:watch';
    }

    public function getDescription(): string {
        return 'Cron: Watch Folder — automatic media scanning';
    }

    public function execute(array $rArgs): int {
        if (!$this->assertRunAsXcVm()) {
            return 1;
        }

        ini_set('memory_limit', -1);
        setlocale(LC_ALL, 'en_US.UTF-8');
        putenv('LC_ALL=en_US.UTF-8');

        $this->registerShutdown();

        require_once __DIR__ . '/WatchCron.php';

        $rForce = null;
        if (!empty($rArgs[0])) {
            $rForce = intval($rArgs[0]);
        }

        // Lock atómico SIEMPRE (force incluido): dos escaneos concurrentes
        // construyen el mismo dedup antes de que el otro inserte → duplicados.
        if (!$this->acquirePidLock(CACHE_TMP_PATH . 'watch_pid')) {
            echo 'Watch folder is already running. Please wait until it finishes.' . "\n";
            return 0;
        }
        $this->setProcessTitle('UyPanel[Watch Folder]');

        set_time_limit(0);
        if (strlen(SettingsManager::getAll()['tmdb_api_key']) != 0) {
            // WatchCron::run() lee la configuración como globales (patrón del
            // cron legacy) — publicarlas; sin esto el escaneo no encuentra
            // folders ($rScanOffset/$rForce nulos) y los items van en serie.
            $rSettings = SettingsManager::getAll();
            $GLOBALS['rForce'] = $rForce;
            $GLOBALS['rScanOffset'] = intval($rSettings['scan_seconds']);
            $GLOBALS['rThreadCount'] = intval($rSettings['thread_count']);
            $GLOBALS['rMaxItems'] = intval($rSettings['max_items']);
            WatchCron::run();
        } else {
            echo 'No TMDb API key.' . "\n";
            return 1;
        }

        return 0;
    }
}
