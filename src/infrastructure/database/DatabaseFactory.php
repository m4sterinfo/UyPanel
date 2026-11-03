<?php

/**
 * DatabaseFactory — creación, almacenamiento y cierre de la conexión global a la BD.
 *
 * Registro singleton: los entry points invocan set($db), los consumidores — get().
 */
class DatabaseFactory {
	/** @var DatabaseHandler|null */
	private static $instance = null;

	/**
	 		 * Guarda la instancia de DatabaseHandler en el registro singleton.
	 */
	public static function set(DatabaseHandler $db): void {
		self::$instance = $db;
	}

	/**
	 		 * Devuelve el DatabaseHandler actual.
	 */
	public static function get(): ?DatabaseHandler {
		return self::$instance;
	}

	/**
	 		 * Crea el DatabaseHandler desde config.ini y lo coloca en global $db + singleton.
	 */
	public static function connect() {
		global $db;
		$_INFO = array();

		if (file_exists(MAIN_HOME . 'config')) {
			$_INFO = parse_ini_file(CONFIG_PATH . 'config.ini');
		} else {
			die('no config found');
		}

		$db = new DatabaseHandler($_INFO['username'], $_INFO['password'], $_INFO['database'], $_INFO['hostname'], $_INFO['port']);
		self::$instance = $db;
	}

	/**
	 		 * Cierra la conexión global a la BD.
	 */
	public static function close() {
		global $db;
		if ($db) {
			$db->close_mysql();
			$db = null;
		}
		self::$instance = null;
	}
}
