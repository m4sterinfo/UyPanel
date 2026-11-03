<?php

/**
 * RedisManager — gestión del ciclo de vida de la conexión Redis.
 *
 * El singleton guarda la instancia activa de Redis.
 */
class RedisManager {
			/** @var Redis|null Instancia singleton */
	private static $instance = null;

	// ──────── Singleton API ────────

	/**
	 		 * Devuelve el Redis activo, conectándose si es necesario.
	 * @return Redis|null
	 */
	public static function instance() {
		if (!is_object(self::$instance)) {
			self::ensureConnected();
		}
		return self::$instance;
	}

	/**
	 		 * Se conecta a Redis si aún no hay conexión.
	 * @return bool
	 */
	public static function ensureConnected() {
		self::$instance = self::connect(self::$instance, ConfigReader::getAll(), SettingsManager::getAll());
		return is_object(self::$instance);
	}

	/**
	 		 * Cierra la conexión singleton.
	 * @return bool
	 */
	public static function closeInstance() {
		self::$instance = self::close(self::$instance);
		return true;
	}

	/**
	 		 * Comprueba si el singleton está conectado.
	 * @return bool
	 */
	public static function isConnected() {
		return is_object(self::$instance);
	}

			// ──────── API de bajo nivel (sin singleton) ────────

	public static function setSignal($rKey, $rData) {
		file_put_contents(SIGNALS_TMP_PATH . 'cache_' . md5($rKey), json_encode(array($rKey, $rData)));
	}

	public static function connect($rRedis, $rConfig, $rSettings) {
		if (is_object($rRedis)) {
			return $rRedis;
		}

		if (empty($rConfig['hostname']) || empty($rSettings['redis_password'])) {
			return null;
		}

		try {
			$rRedis = new Redis();
			$rRedis->connect($rConfig['hostname'], 6379);
			$rRedis->auth($rSettings['redis_password']);
			return $rRedis;
		} catch (Exception $e) {
			return null;
		}
	}

	public static function close($rRedis) {
		if (is_object($rRedis)) {
			$rRedis->close();
		}
		return null;
	}
}
