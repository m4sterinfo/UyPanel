<h1 align="center">🔄 Guía de actualización de UyPanel</h1>

<p align="center">
  Este documento describe el proceso de actualización de <b>UyPanel</b> desde el panel de control web. Sigue los pasos para actualizar el sistema de forma segura y evitar errores.
</p>

---

## 📚 Navegación

- [⚙️ Antes de empezar](#antes-de-empezar)
- [🪜 Instrucción paso a paso](#instrucción-paso-a-paso)
- [🧠 Notas y recomendaciones](#notas-y-recomendaciones)

---

## ⚙️ Antes de empezar

Antes de actualizar, asegúrate de tener todos los recursos necesarios. Esto ayuda a evitar problemas y pérdida de datos.

- 🔑 **Acceso de administrador** al panel de control.  
- 🌐 **Conexión a internet estable**.  
- 💾 **Copia de seguridad de los datos del servidor** *(muy recomendable crearla antes de empezar)*.

> ⚠️ **Importante:** No interrumpas el proceso de actualización para evitar dañar el sistema.

---

## 🪜 Instrucción paso a paso

Sigue estos pasos para actualizar desde el panel. Cada etapa viene ilustrada con una captura.

### 1️⃣ Ve a la sección **"Servers"**

- Autentícate en el panel de control.  
- Selecciona la sección **Servers** en el menú principal.  

  ![Servers](../../_media/update1.png)

### 2️⃣ Selecciona **"Manage Servers"**

- En la sección **Servers**, pulsa **Manage Servers** para abrir la lista de servidores disponibles.  

  ![Manage Servers](../../_media/update2.png)

### 3️⃣ Abre el menú **"Actions"**

- Encuentra el servidor que quieres actualizar.  
- Pulsa el botón **Actions** — normalmente un menú desplegable o un icono junto al servidor.  

  ![Actions Menu](../../_media/update3.png)

### 4️⃣ Ve a la sección **"Server Tools"**

- En el menú **Actions**, elige **Server Tools** para abrir las herramientas de gestión del servidor.  

  ![Server Tools](../../_media/update4.png)

### 5️⃣ Lanza **"Update Server"**

- En la sección **Server Tools**, pulsa **Update Server**.  
- Confirma la acción si se solicita (puede pedirse la contraseña).  
- Espera a que termine la actualización — **no interrumpas el proceso** para evitar errores.  

  ![Update Server](../../_media/update5.png)

---

## 🧠 Notas y recomendaciones

> 🕒 **Tiempo de actualización**  
> Depende del tamaño del servidor y de la velocidad de conexión. Suele llevar de varios minutos a una hora.

> ✅ **Comprobación tras la actualización**  
> Al terminar, revisa el estado del servidor, reinicia los servicios y verifica que todo funciona correctamente.

> ⚠️ **Errores durante la actualización**  
> Si hay errores, revisa los logs del servidor (p. ej. en `/home/uypanel/logs/`). Si el problema persiste, abre un issue en el [repositorio](https://github.com/m4sterinfo/UyPanel/issues).

---