<?php

/**
 * Acceso a config.ini (caché singleton)
 *
 * Lee y cachea config.ini una vez por proceso.
 */
class ConfigReader {
	private static $config = null;

	/**
	 		 * @return array Array completo de config.ini
	 */
	public static function getAll() {
		if (self::$config === null) {
			self::$config = parse_ini_file(CONFIG_PATH . 'config.ini');
		}
		return self::$config;
	}

	/**
	 		 * @param string $key Clave de configuración
	 		 * @param mixed $default Valor por defecto
	 * @return mixed
	 */
	public static function get($key, $default = null) {
		$config = self::getAll();
		return $config[$key] ?? $default;
	}
}
