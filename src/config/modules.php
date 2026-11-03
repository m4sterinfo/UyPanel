<?php

/**
 * Overrides de módulos
 *
 * ModuleLoader detecta automáticamente todos los módulos desde modules/<name>/module.json.
 * Por defecto todos los módulos detectados están activados.
 *
 * Este archivo contiene solo las redefiniciones:
 *   - Desactivar un módulo:  'module-name' => ['enabled' => false]
 *   - Clase propia de módulo:  'module-name' => ['class' => 'CustomModule']
 *
 * Ejemplo:
 *   return [
 *       'theft-detection' => ['enabled' => false],
 *       'custom-module'   => ['class' => 'MyCustomModule'],
 *   ];
 *
 * Si el array está vacío — se cargarán todos los módulos detectados.
 *
 * @see ModuleInterface
 * @see ModuleLoader
 */

return [
        // Ejemplo de desactivación de un módulo:
    // 'theft-detection' => ['enabled' => false],
];
