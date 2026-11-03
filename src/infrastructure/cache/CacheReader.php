<?php

/**
 * CacheReader — lectura de archivos de caché binarios (igbinary).
 */
class CacheReader {
	/**
	 		 * Lee y deserializa un archivo igbinary desde CACHE_TMP_PATH.
	 *
	 		 * @param string $rCache Nombre del archivo de caché (sin ruta)
	 * @return mixed|null
	 */
	public static function get($rCache) {
		$rPath = CACHE_TMP_PATH . $rCache;
		if (!is_file($rPath)) {
			return null;
		}
		$rData = file_get_contents($rPath);
		return $rData !== false ? igbinary_unserialize($rData) : null;
	}

	/**
	 		 * Comprueba si la caché está activada y completamente lista.
	 *
	 		 * @param array $rSettings Ajustes de la aplicación
	 * @return bool
	 */
	public static function isReady($rSettings) {
		if (!$rSettings['enable_cache']) {
			return false;
		}
		return file_exists(CACHE_TMP_PATH . 'cache_complete');
	}
}
