#!/usr/bin/env php
<?php

/**
 * UyPanel Console — punto de entrada único para comandos CLI y tareas cron.
 *
 * Uso:
 *   php console.php <command> [arguments]
 *   php console.php --list              # lista de todos los comandos
 *   php console.php --help              # ayuda
 *
 * Ejemplos:
 *   php console.php startup             # arranque del demonio startup
 *   php console.php cron:servers        # arranque del cron servers
 *   php console.php watchdog            # arranque del demonio watchdog
 *
 * Los comandos se registran más abajo de forma explícita — añade tu comando
 * en la sección "Register commands" tras crear la clase que implementa CommandInterface.
 */

// ─── Guard: CLI only ─────────────────────────────────────────────

if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	exit;
}

// ─── Bootstrap ───────────────────────────────────────────────────

require_once __DIR__ . '/cli/CommandInterface.php';
require_once __DIR__ . '/cli/CommandRegistry.php';
require_once __DIR__ . '/bootstrap.php';

XC_Bootstrap::boot(XC_Bootstrap::CONTEXT_CLI, [
	'process' => 'UyPanel[Console]',
]);

// ─── Registry ────────────────────────────────────────────────────

$rRegistry = new CommandRegistry();

// ── Auto-discover: core Commands + CronJobs ──────────────────────

$rCommandDirs = [
	__DIR__ . '/cli/Commands',
	__DIR__ . '/cli/CronJobs',
];

foreach ($rCommandDirs as $rDir) {
	if (!is_dir($rDir)) {
		continue;
	}
	foreach (glob($rDir . '/*.php') as $rFile) {
		$rClassName = basename($rFile, '.php');
		require_once $rFile;
		if (class_exists($rClassName, false)) {
			$rReflection = new ReflectionClass($rClassName);
			if (!$rReflection->isAbstract() && $rReflection->implementsInterface('CommandInterface')) {
				$rRegistry->register(new $rClassName());
			}
		}
	}
}

// ── Module commands ──────────────────────────────────────────────
// Los módulos registran sus comandos vía ModuleInterface::registerCommands()
// Sin filesystem scanning — cada módulo conoce explícitamente sus comandos.

$rModuleLoader = new ModuleLoader();
$rModuleLoader->loadAll();
$rModuleLoader->registerAllCommands($rRegistry);

// ─── Dispatch ────────────────────────────────────────────────────

exit($rRegistry->dispatch($argv));
