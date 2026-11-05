<?php

/**
 * Funcionalidad común para tareas cron.
 *
 * Extrae el boilerplate repetitivo: comprobación de usuario, cron lock,
 * shutdown handler (cierre de BD + eliminación del lock-file).
 */
trait CronTrait {

        /** @var string|null Ruta al lock-file */
    protected $rIdentifier;

    /**
          * Comprobar que el proceso corre como usuario uypanel.
     */
    protected function assertRunAsXcVm(): bool {
        if ((posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'uypanel') {
            echo "Please run as UyPanel!\n";
            return false;
        }
        return true;
    }

    /**
          * Comprobar que el proceso corre como root.
     */
    protected function assertRunAsRoot(): bool {
        if ((posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'root') {
            echo "Please run as root!\n";
            return false;
        }
        return true;
    }

    /**
          * Establecer el título del proceso + time limit.
     */
    protected function setProcessTitle(string $rTitle): void {
        set_time_limit(0);
        cli_set_process_title($rTitle);
    }

    /**
          * Obtener el cron lock (archivo único en CRONS_TMP_PATH).
          * Si el lock ya está ocupado — sale con código 0.
     */
    protected function acquireCronLock(): void {
        $this->rIdentifier = CRONS_TMP_PATH . md5(
            Encryption::generateUniqueCode(SettingsManager::getAll()['live_streaming_pass']) . static::class
        );
        ProcessManager::acquireCronLock($this->rIdentifier);
    }

    /**
     * Lock atómico por pid-file (a prueba de carreras de arranque).
     *
     * Crea el archivo con O_EXCL (fopen 'x'): si existe, comprueba si el pid
     * dueño sigue vivo (php) — si está muerto lo limpia y reintenta. Registra
     * un shutdown que borra el archivo solo si el pid sigue siendo el nuestro.
     *
     * @param string $rPidFile Ruta del pid-file
     * @param string $rExe     Ejecutable esperado del dueño (p. ej. 'php')
     * @return bool true = lock adquirido; false = ya hay un proceso vivo
     */
    protected function acquirePidLock(string $rPidFile, string $rExe = 'php'): bool {
        for ($i = 0; $i < 3; $i++) {
            $rHandle = @fopen($rPidFile, 'x');
            if ($rHandle) {
                fwrite($rHandle, getmypid());
                fclose($rHandle);
                register_shutdown_function(function () use ($rPidFile) {
                    if (intval(@file_get_contents($rPidFile)) === intval(getmypid())) {
                        @unlink($rPidFile);
                    }
                });
                return true;
            }
            $rPrevPID = intval(@file_get_contents($rPidFile));
            if (!$rPrevPID || $rPrevPID === intval(getmypid()) || !ProcessManager::isRunning($rPrevPID, $rExe)) {
                @unlink($rPidFile);
                continue;
            }
            return false;
        }
        return false;
    }

    /**
          * Registrar el shutdown handler: cierre de BD + eliminación del lock.
     */
    protected function registerShutdown(): void {
        $rIdentifier = &$this->rIdentifier;
        register_shutdown_function(static function () use (&$rIdentifier) {
            global $db;
            if (isset($db) && is_object($db)) {
                $db->close_mysql();
            }
            if (!empty($rIdentifier) && file_exists($rIdentifier)) {
                @unlink($rIdentifier);
            }
        });
    }

    /**
          * Inicialización estándar de la tarea cron.
     *
          * @param string $rTitle Título del proceso (p. ej. 'UyPanel[Activity]')
     */
    protected function initCron(string $rTitle): void {
        $this->registerShutdown();
        $this->setProcessTitle($rTitle);
        $this->acquireCronLock();
    }
}
