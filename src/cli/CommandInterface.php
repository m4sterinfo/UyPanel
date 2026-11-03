<?php

/**
 * Contrato para comandos CLI y tareas cron.
 *
 * Cada comando es una clase que implementa esta interfaz.
 * Se registra en CommandRegistry por nombre (p. ej. 'startup', 'cron:servers').
 */
interface CommandInterface {

	/**
	 		 * Nombre único del comando.
	 *
	 		 * Formato: 'name' para comandos CLI, 'cron:name' para tareas cron.
	 		 * Se usa al invocar: php console.php <name>
	 */
	public function getName(): string;

	/**
	 		 * Descripción breve (una línea). Se muestra en --help.
	 */
	public function getDescription(): string;

	/**
	 		 * Ejecución del comando.
	 *
	 		 * @param array $rArgs  Argumentos de la línea de comandos ($argv sin los dos primeros elementos)
	 		 * @return int  Exit code: 0 = éxito, >0 = error
	 */
	public function execute(array $rArgs): int;
}
