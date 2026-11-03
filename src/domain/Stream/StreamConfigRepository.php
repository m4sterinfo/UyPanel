<?php

/**
 * StreamConfigRepository — argumentos de streams y perfiles de transcodificación.
 */
class StreamConfigRepository {
	/**
	 		 * Obtener todos los argumentos de streams (streams_arguments), indexados por argument_key.
	 */
	public static function getStreamArguments() {
		global $db;
		$rReturn = array();
		$db->query('SELECT * FROM `streams_arguments` ORDER BY `id` ASC;');

		if ($db->num_rows() > 0) {
			foreach ($db->get_rows() as $rRow) {
				$rReturn[$rRow['argument_key']] = $rRow;
			}
		}

		return $rReturn;
	}

	/**
	 		 * Obtener todos los perfiles de transcodificación (profiles).
	 */
	public static function getTranscodeProfiles() {
		global $db;
		$rReturn = array();
		$db->query('SELECT * FROM `profiles` ORDER BY `profile_id` ASC;');

		if ($db->num_rows() > 0) {
			foreach ($db->get_rows() as $rRow) {
				$rReturn[] = $rRow;
			}
		}

		return $rReturn;
	}
}
