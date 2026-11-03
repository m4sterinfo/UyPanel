<h1 align="center">🧭 Guía de migración de UyPanel</h1>

<p align="center">
  Migración segura desde sistemas IPTV compatibles usando las herramientas de migración integradas de UyPanel.
</p>

---

## 📚 Navegación

* [⚠️ Aviso crítico de migración](#️-aviso-crítico-de-migración)
* [⚙️ Antes de empezar](#️-antes-de-empezar)
* [🚀 Pasos de la migración](#-pasos-de-la-migración)
* [🔑 Recuperación del acceso tras la migración](#-recuperación-del-acceso-tras-la-migración)
* [🖥️ Preparación de los balanceadores de carga](#️-preparación-de-los-balanceadores-de-carga)
* [🧩 Acciones post-migración (obligatorias)](#-acciones-post-migración-obligatorias)
* [❗ Problemas frecuentes tras la migración](#-problemas-frecuentes-tras-la-migración)
* [✅ Resumen](#-resumen)

---

## ⚠️ Aviso crítico de migración

> **Léelo obligatoriamente antes de empezar la migración.**

La migración de UyPanel traslada **solo los datos**.
**Toda la configuración se excluye deliberadamente del proceso de migración.**

Esto incluye (sin limitarse a):

* Claves de API (p. ej. **TMDb**)
* Credenciales de servicios externos
* Ajustes dependientes del entorno
* Configuración del panel y del sistema
* Estado de ejecución y estado de los streams

Todos estos valores **hay que configurarlos a mano tras la migración**.

Es una **decisión arquitectónica deliberada**, no una limitación ni un error.
Saltarse la reconfiguración **provocará que la obtención de metadatos, la actualización de nombres de streams y otras funciones relacionadas no funcionen**.

---

## ⚙️ Antes de empezar

> 💡 **Recomendación:**
> Ejecuta la migración sobre una **instalación limpia de UyPanel**.

> ⚠️ **Importante:**
> Los ajustes del sistema y del panel **NO se trasladan**.
> Solo se trasladan los datos de la BD soportados por el mecanismo de migración.

Si ejecutas la migración sobre una **instalación existente**, ten en cuenta:

* UyPanel **eliminará todas las tablas** de la base de datos principal que coincidan con los datos de la base de migración.
* **Las copias de seguridad son obligatorias.** No hay rollback automático.

---

## 🚀 Pasos de la migración

### 1. Subir el backup

Sube la copia de seguridad de la base de datos existente al servidor UyPanel vía **SFTP**.

Ruta de ejemplo:

```text
/tmp/backup.sql
```

---

### 2. Restaurar el backup en la base de migración

Limpia la base de migración y restaura el backup:

```bash
sudo /home/uypanel/bin/php/bin/php /home/uypanel/console.php tools migration "/tmp/backup.sql"
```

Antes de continuar, asegúrate de que la restauración terminó **sin errores**.

---

### 3. Lanzar la migración

Tras una restauración exitosa, lanza la migración de una de estas formas.

#### 🧩 Opción 1 — Línea de comandos (recomendada)

```bash
/home/uypanel/bin/php/bin/php /home/uypanel/console.php migrate
```

#### 🌐 Opción 2 — Instalador web

* Vuelve al **instalador web** (el enlace se muestra al configurar el panel)
* Elige la opción **Migration**
* Sigue las instrucciones en pantalla

Verás el progreso de la migración en tiempo real.
Al terminar, el sistema quedará disponible.

---

## 🔑 Recuperación del acceso tras la migración

Si no puedes entrar por faltar credenciales o código de acceso, usa las herramientas de rescate.

### Crear un código de acceso de respaldo

```bash
/home/uypanel/tools access
```

### Crear un administrador

```bash
/home/uypanel/tools user
```

> ⚠️ Tras recuperar el acceso, **cambia de inmediato** el código de acceso y las credenciales del administrador.

---

## 🖥️ Preparación de los balanceadores de carga

Los balanceadores de carga **no se trasladan**.

* Si hace falta, reinstala el sistema operativo
* Reconfigura la red y el enrutado
* Conéctalos de nuevo al servidor principal

---

## 🧩 Acciones post-migración (obligatorias)

Tras la migración, el sistema **no está listo para producción** hasta completar estos pasos.

Saltárselos provocará un **comportamiento esperado pero no funcional**.

---

### 1. Inicialización del estado de ejecución

* Arranca todos los streams a mano
* Asegúrate de que los streams están disponibles y funcionan con estabilidad

> El estado de ejecución de los streams **nunca se guarda** en la migración.

---

### 2. Reconfiguración del sistema

Revisa y restaura todos los ajustes dependientes del entorno:

* Rutas de archivos
* Límites y cuotas
* Configuración de red y reverse proxy
* Parámetros de rendimiento

> **No se asume** que los valores por defecto coincidan con tu configuración anterior.
> Los valores por defecto se aplican deliberadamente.

---

### 3. Restauración de claves de API y providers

#### Las claves de API nunca se trasladan

Lo siguiente **hay que configurarlo a mano**:

* **TMDb API key**

Es un **comportamiento esperado**.

> Si tras la migración no se cargan los metadatos,
> comprueba que la clave de API se añadió de nuevo y el provider está activado.
> **No es un error de la migración.**

---

## ❗ Problemas frecuentes tras la migración

### Los metadatos no se cargan (TMDb)

**Causa:**
La clave de API de TMDb y la configuración del provider no se restauraron.

**Solución:**
Vuelve a añadir la clave de API de TMDb y activa el provider en los ajustes del servidor principal.

---

## ✅ Resumen

* La migración traslada **solo los datos principales de la aplicación**
* La configuración **se excluye deliberadamente**
* Las claves de API y los ajustes de entorno **deben restaurarse a mano**
* La falta de funcionalidad tras la migración **es esperada hasta completar la reconfiguración**

---
