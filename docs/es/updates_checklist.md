# ✅ Checklist de preparación de un release de UyPanel

<p align="center">
  Este documento describe el proceso de creación de un release de <b>UyPanel</b> — guía paso a paso para desarrolladores: actualizar la versión, compilar los archivos y publicar en GitHub.
</p>

---

## 📚 Navegación

- [🔢 1. Actualizar la versión](#1-actualizar-la-versión)
- [🧹 2. Archivos eliminados](#2-archivos-eliminados)
- [⚙️ 3. Compilación de archivos](#3-compilación-de-archivos)
- [📝 4. Changelog](#4-changelog)
- [🚀 5. Release en GitHub](#5-release-en-github)

---

## 🔢 1. Actualizar la versión

* Establecer el **nuevo valor de `UyPanel_VERSION`** en los siguientes archivos:

**Archivo a editar:**

```
src/core/Config/AppConfig.php
```

**Comando de auto-actualización:**

```bash
sed -i "s/define('UyPanel_VERSION', *'[0-9]\+\.[0-9]\+\.[0-9]\+');/define('UyPanel_VERSION', 'X.Y.Z');/" src/core/Config/AppConfig.php
```

**Commitear el cambio con el mensaje:**

```bash
git add .
git commit -m "Bump version to X.Y.Z"
git push
```

> 💡 **Consejo:** Sustituye `X.Y.Z` por la versión real, p. ej. `1.2.3`.

---

## 🧹 2. Archivos eliminados

* Ejecutar el comando que genera la lista de archivos eliminados:

  ```bash
  make delete_files_list
  ```

* Abrir el archivo `dist/deleted_files.txt`.
* Copiar el contenido en `src/cli/Commands/UpdateCommand.php`, en el array `$rCleanupFiles` dentro de la fase `post-update`.

> ⚠️ **Importante:** Verifica que las rutas son correctas para evitar eliminar archivos importantes.

**Commitear el cambio con el mensaje:**

```bash
git add .
git commit -m "Added deletion of old files before release"
git push
```

---

## ⚙️ 3. Compilación de archivos

> 🤖 **Automático:** El build se ejecuta vía el workflow de GitHub Actions `.github/workflows/build-release.yml` al publicar el release. Los archivos se adjuntan automáticamente al release.

Si necesitas compilar **localmente**, ejecuta:

```bash
make new
make lb
make main
make main_update
make lb_update
```

Tras el build, en el directorio `dist/` debe haber:

  - `loadbalancer.tar.gz` — archivo de instalación del LB
  - `loadbalancer_update.tar.gz` — archivo de actualización del LB
  - `UyPanel.zip` — archivo de instalación de MAIN
  - `update.tar.gz` — archivo de actualización de MAIN
  - `hashes.md5` — archivo con las sumas de hash

---

## 📝 4. Changelog

Obtén el tag del release anterior vía la API de GitHub y genera el changelog:

```bash
PREV_TAG=$(curl -s https://api.github.com/repos/m4sterinfo/UyPanel/releases/latest \
  | grep -Po '"tag_name":\s*"\K[^"]+')
echo "Release anterior: $PREV_TAG"
git log --pretty=format:"- %s (%h)" "$PREV_TAG"..main > dist/changes.md
```

*   **Abre el enlace y añade los cambios del release actual:**
    [https://github.com/m4sterinfo/UyPanel_Update/blob/main/changelog.json](https://github.com/m4sterinfo/UyPanel_Update/blob/main/changelog.json)

* Añade los cambios del release actual en formato JSON:

  ```json
  [
    {
        "version": "X.Y.Z",
        "changes": [
          "Descripción del cambio 1",
          "Descripción del cambio 2"
        ]
    }
  ]
  ```

> 💬 **Recomendación:** Mantén las descripciones breves e informativas, centradas en las mejoras y fixes clave.

---

## 🚀 5. Release en GitHub

* Crear el release nuevo en [GitHub Releases](https://github.com/m4sterinfo/UyPanel/releases) **sin adjuntar archivos**.
* Indicar el changelog en la descripción del release.
* Tras publicar, GitHub Actions compilará y adjuntará automáticamente al release:

  - `loadbalancer.tar.gz` — archivo de instalación del LB
  - `loadbalancer_update.tar.gz` — archivo de actualización del LB
  - `UyPanel.zip` — archivo de instalación de MAIN
  - `update.tar.gz` — archivo de actualización de MAIN
  - `hashes.md5` — archivo con las sumas de hash

> ✅ **Finalización:** Espera a que termine el workflow (pestaña Actions) y comprueba que todos los archivos están disponibles para descarga y que las sumas de hash coinciden.

---
