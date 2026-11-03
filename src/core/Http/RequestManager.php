<?php

/**
 * RequestManager — almacén singleton de los parámetros parseados de la petición.
 *
 * El entry point — LegacyInitializer::initCore() — invoca set().
 * Los consumidores usan getAll() o get().
 */
class RequestManager {
	/** @var array */
	private static $request = array();

	/**
	 		 * Guarda el array completo de parámetros de la petición.
	 */
	public static function set(array $request): void {
		self::$request = $request;
	}

	/**
	 		 * Devuelve el array completo de parámetros de la petición.
	 */
	public static function getAll(): array {
		return self::$request;
	}

	/**
	 		 * Devuelve el valor por clave.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get(string $key, $default = null) {
		return self::$request[$key] ?? $default;
	}

	/**
	 		 * Actualiza una clave individual.
	 */
	public static function update(string $key, $value): void {
		self::$request[$key] = $value;
	}
}
