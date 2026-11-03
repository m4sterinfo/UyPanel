<?php

/**
 * SettingsManager — almacén singleton de los ajustes de la aplicación.
 *
 * Los entry points invocan set(), los consumidores — getAll() o get().
 */
class SettingsManager {
	/** @var array */
	private static $settings = array();

	/**
	 		 * Guarda el array completo de ajustes.
	 */
	public static function set(array $settings): void {
		self::$settings = $settings;
	}

	/**
	 		 * Devuelve el array completo de ajustes.
	 */
	public static function getAll(): array {
		return self::$settings;
	}

	/**
	 		 * Devuelve el valor por clave.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get(string $key, $default = null) {
		return self::$settings[$key] ?? $default;
	}

	/**
	 		 * Actualiza una clave individual de los ajustes.
	 */
	public static function update(string $key, $value): void {
		self::$settings[$key] = $value;
	}
}
