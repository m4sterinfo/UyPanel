<?php

/**
 * Funcionalidad común para demonios CLI (signals, watchdog, queue, cache_handler).
 *
 * Extrae el boilerplate repetitivo: comprobación de usuario, matado de
 * instancias previas, detección de cambios de archivo, auto-restart vía console.php.
 */
trait DaemonTrait {

			/** @var string MD5 del archivo del comando al arrancar */
	protected $rDaemonMD5;

			/** @var int|null Último timestamp de actualización de los ajustes */
	protected $rLastCheck;

			/** @var int Intervalo de actualización de los ajustes (segundos) */
	protected $rRefreshInterval = 60;

	/**
	 		 * Comprobar que el proceso corre como usuario uypanel.
	 */
	protected function assertRunAsXcVm(): bool {
		if (posix_getpwuid(posix_geteuid())['name'] !== 'uypanel') {
			echo "Please run as UyPanel!\n";
			return false;
		}
		return true;
	}

	/**
	 		 * Matar las instancias previas de este demonio.
	 *
	 		 * @param string $rPattern Patrón grep para buscar procesos
	 */
	protected function killStaleProcesses(string $rPattern): void {
		$rPID = intval(getmypid());
		$rPids = trim(@shell_exec('ps aux | grep ' . escapeshellarg($rPattern) . ' | grep -v grep | grep -v ' . $rPID . " | awk '{print \$2}'") ?? '');
		if ($rPids !== '') {
			@shell_exec('kill -9 ' . $rPids . ' > /dev/null 2>&1');
		}
	}

	/**
	 		 * Recordar el MD5 del archivo del comando para detectar actualizaciones.
	 */
	protected function initDaemonMD5(): void {
		$this->rDaemonMD5 = md5_file((new ReflectionClass($this))->getFileName());
	}

	/**
	 		 * Comprobar si el archivo del comando cambió desde el arranque.
	 */
	protected function hasFileChanged(): bool {
		return md5_file((new ReflectionClass($this))->getFileName()) !== $this->rDaemonMD5;
	}

	/**
	 		 * Comprobar si hay que actualizar los ajustes (por temporizador).
	 */
	protected function shouldRefreshSettings(): bool {
		if ($this->rLastCheck && $this->rRefreshInterval > time() - $this->rLastCheck) {
			return false;
		}
		return true;
	}

	/**
	 		 * Actualizar ajustes y servidores, comprobar nginx y el MD5.
	 *
	 		 * @return bool true = todo OK, false = hay que hacer break (el archivo cambió o nginx no funciona)
	 */
	protected function refreshOrBreak(): bool {
		if (!$this->shouldRefreshSettings()) {
			return true;
		}

		if (!ProcessManager::isNginxRunning()) {
			echo "Not running! Break.\n";
			return false;
		}

		if ($this->hasFileChanged()) {
			echo "File changed! Break.\n";
			return false;
		}

		SettingsManager::set(SettingsRepository::getAll(true));
		$this->rLastCheck = time();
		return true;
	}

	/**
	 		 * Cerrar la BD y reiniciar el demonio vía console.php.
	 *
	 		 * @param string $rCommandName Nombre del comando (p. ej. 'signals', 'watchdog')
	 */
	protected function restartDaemon(string $rCommandName): void {
		global $db;
		if (is_object($db)) {
			$db->close_mysql();
		}
		shell_exec('(sleep 1; ' . PHP_BIN . ' ' . MAIN_HOME . 'console.php ' . escapeshellarg($rCommandName) . ') > /dev/null 2>/dev/null &');
	}

	/**
	 		 * Establecer el título del proceso.
	 *
	 		 * @param string $rTitle Por ejemplo 'UyPanel[Signals]'
	 */
	protected function setProcessTitle(string $rTitle): void {
		set_time_limit(0);
		cli_set_process_title($rTitle);
	}

	/**
	 		 * Inicializar Redis si está activado en los ajustes.
	 */
	protected function initRedisIfEnabled(): void {
		if (SettingsManager::getAll()['redis_handler'] ?? false) {
			RedisManager::ensureConnected();
		}
	}

	/**
	 		 * Comprobar que Redis está vivo (si está activado).
	 *
	 		 * @return bool true = OK o Redis no se usa, false = conexión perdida
	 */
	protected function checkRedisHealth(): bool {
		if (!(SettingsManager::getAll()['redis_handler'] ?? false)) {
			return true;
		}
		if (!RedisManager::instance() || !RedisManager::instance()->ping()) {
			echo "Redis connection lost\n";
			return false;
		}
		return true;
	}
}
