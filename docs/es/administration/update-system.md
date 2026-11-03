<h1 align="center">⚙️ Mecanismo de actualizaciones en UyPanel</h1>

<p align="center">
  El sistema de actualizaciones de <b>UyPanel</b> está implementado como un proceso multicapa — desde la interfaz web hasta los scripts del sistema. 
  Este enfoque garantiza <b>fiabilidad, automatización e integridad de los datos</b> al actualizar el panel.
</p>

---

## 📚 Navegación

* [🔁 1. Iniciación de la actualización](#-1-iniciación-de-la-actualización)
* [🕒 2. Lanzamiento vía CRON](#-2-lanzamiento-vía-cron)
* [🧠 3. Gestión de la actualización (nivel PHP)](#-3-gestión-de-la-actualización-nivel-php)
* [🐍 4. Actualización del sistema (nivel Python)](#-4-actualización-del-sistema-nivel-python)
* [🔄 5. Finalización de la actualización](#-5-finalización-de-la-actualización)
* [✅ 6. Esquema final de funcionamiento](#-6-esquema-final-de-funcionamiento)
* [🧩 Características clave](#-características-clave)

---

## 🔁 1. Iniciación de la actualización

El proceso empieza con **el botón "Actualizar"** en la interfaz web del panel.

* Se crea la señal `update`, que se escribe en la tabla `signals` de la base de datos.
* Esta señal actúa como **disparador** del proceso de actualización posterior.

---

## 🕒 2. Lanzamiento vía CRON

Cada **minuto** se ejecuta la tarea CRON del sistema:

```bash
/home/uypanel/bin/php/bin/php /home/uypanel/console.php cron:root_signals
```

La tarea cron `root_signals` comprueba si hay señales nuevas y, al detectar `update`, lanza:

```bash
/home/uypanel/bin/php/bin/php /home/uypanel/console.php update update
```

> ⚙️ Este mecanismo permite ejecutar las actualizaciones **automáticamente**, sin intervención manual del administrador.

---

## 🧠 3. Gestión de la actualización (nivel PHP)

La lógica principal está en la clase `UpdateCommand`:

```
src/cli/Commands/UpdateCommand.php
```

En esta etapa se realizan las siguientes acciones:

1. Determinación de la **versión actual del panel** (`MAIN` o `LB`).
2. Obtención de los datos de la nueva versión desde **GitHub**:

   * Enlace al archivo de actualización.
   * Suma de comprobación (hash) para verificar la integridad.
3. Descarga del archivo a un directorio temporal.
4. Verificación de la coincidencia del hash.
5. Lanzamiento de la actualización del sistema con Python:

```bash
sudo /usr/bin/python3 /home/uypanel/update "/home/uypanel/tmp/.update.tar.gz" "HASH" > /dev/null 2>&1 &
```

---

## 🐍 4. Actualización del sistema (nivel Python)

Tras la llamada, el control pasa al script:

```
/home/uypanel/update
```

Ejecuta las operaciones a nivel de sistema:

1. **Nueva verificación de la suma de comprobación** del archivo.
2. **Parada del panel**, para evitar conflictos durante la actualización.
3. **Descompresión** de la actualización en el directorio:

   ```bash
   /home/uypanel/
   ```
4. **Ajuste de permisos:**

   ```bash
   chown -R uypanel:uypanel /home/uypanel/
   ```
5. **Lanzamiento del post-update:**

   ```bash
   /home/uypanel/bin/php/bin/php /home/uypanel/console.php update post-update
   ```
6. **Reinicio del panel** en modo operativo.

---

## 🔄 5. Finalización de la actualización

Las acciones finales se ejecutan en la fase `post-update` del comando `UpdateCommand`:

1. Comprobación de si está **activada la auto-actualización de los LB**:

   * Si se actualizó el `MAIN`, se crean señales de actualización para todos los Load Balancers.
2. Actualización de la **versión del panel** en la base de datos.
3. Eliminación de los archivos obsoletos.
4. Re-alineación de permisos:

   ```bash
   chown -R uypanel:uypanel /home/uypanel/
   ```
5. Recarga de los daemons del sistema:

   ```bash
   sudo systemctl daemon-reload
   ```
6. Comprobación del estado del panel:

   ```bash
   sudo /home/uypanel/status
   ```
7. Finalización del proceso de actualización.

---

## ✅ 6. Esquema final de funcionamiento

```text
[ Interfaz web ]
        │
        ▼
[ BD: señal update ]
        │
        ▼
[ CRON → console.php cron:root_signals ]
        │
        ▼
[ update.php (PHP) ]
        │
        ▼
[ update (Python) ]
        │
        ▼
[ post-update → update.php ]
        │
        ▼
[ Finalización, reinicio de daemons, actualización de versión ]
```

---

## 🧩 Características clave

* 🔒 **Doble verificación de integridad** (a nivel PHP y Python).
* 🧠 **Distribución automática de actualizaciones** entre MAIN y los LB.
* 🧹 **Limpieza de archivos obsoletos** y alineación de permisos.
* 🔄 **Reinicio seguro del panel** tras la instalación.
* ⚙️ **Flexibilidad y autonomía** gracias a CRON y las señales.

---
