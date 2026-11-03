<?php

/**
 * Registro de comandos CLI.
 *
 * Guarda el mapeo nombre → instancia de CommandInterface.
 * Dispatch: busca el comando por nombre desde $argv e invoca execute().
 *
 * Los comandos se detectan automáticamente: console.php escanea
 * cli/Commands/ y cli/CronJobs/ y registra todas las clases
 * que implementan CommandInterface.
 */
class CommandRegistry {

	/** @var CommandInterface[] name → command */
	private $rCommands = [];

	/**
	 		 * Registrar un comando.
	 */
	public function register(CommandInterface $rCommand): void {
		$this->rCommands[$rCommand->getName()] = $rCommand;
	}

	/**
	 		 * Dispatch: encontrar el comando por $argv y ejecutarlo.
	 *
	 		 * @param array $rArgv  $argv completo desde CLI
	 * @return int  Exit code
	 */
	public function dispatch(array $rArgv): int {
		$rCommandName = $rArgv[1] ?? null;

		if ($rCommandName === null || $rCommandName === '--help' || $rCommandName === '-h') {
			$this->printHelp();
			return 0;
		}

		if ($rCommandName === '--list') {
			$this->printList();
			return 0;
		}

		if (!isset($this->rCommands[$rCommandName])) {
			fwrite(STDERR, "Unknown command: {$rCommandName}\n");
			fwrite(STDERR, "Run with --list to see available commands.\n");
			return 1;
		}

		$rArgs = array_slice($rArgv, 2);
		return $this->rCommands[$rCommandName]->execute($rArgs);
	}

	/**
	 		 * Obtener un comando por nombre (para tests y comprobaciones).
	 */
	public function get(string $rName): ?CommandInterface {
		return $this->rCommands[$rName] ?? null;
	}

	/**
	 		 * Todos los comandos registrados.
	 *
	 * @return CommandInterface[]
	 */
	public function getAll(): array {
		return $this->rCommands;
	}

	private function printHelp(): void {
		echo "UyPanel Console\n";
		echo "\n";
		echo "Usage: php console.php <command> [arguments]\n";
		echo "\n";
		echo "  --list    Show all available commands\n";
		echo "  --help    Show this help message\n";
		echo "\n";
	}

	private function printList(): void {
		echo "Available commands:\n\n";

		$rGrouped = [];
		foreach ($this->rCommands as $rName => $rCommand) {
			$rParts = explode(':', $rName, 2);
			$rGroup = count($rParts) > 1 ? $rParts[0] : '';
			$rGrouped[$rGroup][$rName] = $rCommand->getDescription();
		}

		ksort($rGrouped);

		foreach ($rGrouped as $rGroup => $rItems) {
			if ($rGroup !== '') {
				echo " {$rGroup}\n";
			}
			ksort($rItems);
			foreach ($rItems as $rName => $rDesc) {
				echo sprintf("  %-30s %s\n", $rName, $rDesc);
			}
			echo "\n";
		}
	}
}
