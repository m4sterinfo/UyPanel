<?php

/**
 * Repositorio de formatos de salida (output_formats).
 */
class OutputFormatRepository {
	/**
	 		 * Obtener todos los formatos de salida, ordenados por access_output_id.
	 *
	 * @return array
	 */
	public static function getAll() {
		global $db;
		$db->query('SELECT * FROM `output_formats` ORDER BY `access_output_id` ASC;');
		return (0 < $db->num_rows() ? $db->get_rows() : array());
	}
}
