<h1 align="center">🔒 Generación de certificado SSL para Nginx en UyPanel</h1>

<p align="center">
  Este documento describe el proceso de <b>creación de un certificado SSL autofirmado. Sigue los pasos para configurar una conexión HTTPS segura.
</p>

---

## 📚 Navegación

* [📘 Introducción](#-introducción)
* [🧭 Ubicación de la configuración](#-ubicación-de-la-configuración)
* [🧱 Paso 1. Creación de la clave privada](#-paso-1-creación-de-la-clave-privada)
* [⚙️ Paso 2. Creación del archivo de configuración servercnf](#️-paso-2-creación-del-archivo-de-configuración-servercnf)
* [🔑 Paso 3. Generación del certificado SSL autofirmado](#-paso-3-generación-del-certificado-ssl-autofirmado)
* [📂 Archivos resultantes](#-archivos-resultantes)
* [✅ Resultado](#-resultado)
* [⚠️ Notas](#️-notas)

---

## 📘 Introducción

**SSL (Secure Sockets Layer)** se usa para cifrar las conexiones entre cliente y servidor, garantizando la seguridad de los datos y la confianza de los usuarios.
Esta instrucción muestra cómo crear un **certificado SSL autofirmado** para el **Nginx** integrado en el proyecto **UyPanel**.

---

## 🧭 Ubicación de la configuración

Todos los archivos SSL (clave, certificado y configuración) se guardan en el directorio:

```bash
/home/uypanel/bin/nginx/conf
```

Antes de empezar, entra en él:

```bash
cd /home/uypanel/bin/nginx/conf
```

---

## 🧱 Paso 1. Creación de la clave privada

Genera la **clave privada RSA** (2048 bits):

```bash
openssl genrsa -out server.key 2048
```

📄 Al ejecutarlo aparecerá el archivo `server.key`, que contiene la **clave privada**.
Guárdala **en secreto** — este archivo se usa para firmar el certificado SSL.

---

## ⚙️ Paso 2. Creación del archivo de configuración `server.cnf`

Crea el archivo de configuración con los parámetros del certificado:

```bash
cat > server.cnf << EOF
[req]
distinguished_name = req_distinguished_name
x509_extensions = v3_req
prompt = no

[req_distinguished_name]
C = RU
ST = Moscow
L = Moscow
O = UyPanel
OU = UyPanel
CN = UyPanel

[v3_req]
keyUsage = keyEncipherment, dataEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names

[alt_names]
DNS.1 = UyPanel
EOF
```

📘 **Descripción de los parámetros:**

| Campo   | Valor    | Propósito                       |
| ------- | -------- | ------------------------------- |
| `C`     | RU       | País (Country)                  |
| `ST`    | Moscow   | Región (State)                  |
| `L`     | Moscow   | Ciudad (Locality)               |
| `O`     | UyPanel    | Organización                    |
| `OU`    | UyPanel    | Departamento / unidad           |
| `CN`    | UyPanel    | Nombre de host (Common Name)    |
| `DNS.1` | UyPanel    | Nombre de dominio alternativo (SAN) |

> 💡 **Consejo:** puedes indicar un dominio real, p. ej. `DNS.1 = example.com`, para evitar avisos del navegador.

---

## 🔑 Paso 3. Generación del certificado SSL autofirmado

Genera el certificado con la clave y la configuración creadas:

```bash
openssl req -new -x509 -key server.key -out server.crt -days 3650 -config server.cnf
```

📆 **Aclaraciones:**

* `-new -x509` — crea un certificado autofirmado nuevo.
* `-days 3650` — validez del certificado (10 años).
* `-config server.cnf` — uso del archivo de configuración.
* El resultado es el archivo `server.crt`.

---

## 📂 Archivos resultantes

Tras todos los pasos, en `/home/uypanel/bin/nginx/conf` deben estar:

| Archivo      | Propósito                      |
| ------------ | ------------------------------ |
| `server.key` | Clave privada                  |
| `server.crt` | Certificado SSL autofirmado    |
| `server.cnf` | Configuración del certificado  |

---

## ✅ Resultado

Tu **servidor Nginx de UyPanel** ya está disponible por **HTTPS**, usando el certificado autofirmado creado.
Los navegadores pueden avisar de "certificado no confiable" — es normal en SSL autofirmados.

---

## ⚠️ Notas

* Los certificados autofirmados sirven **para uso interno o pruebas**.
* Para dominios públicos usa certificados de **autoridades confiables** (p. ej. [Let's Encrypt](https://letsencrypt.org/)).
* Si cambias el nombre de dominio (`CN` o `DNS.1`) hay que **generar el certificado de nuevo**.
* Para inspeccionar el certificado, ejecuta:

```bash
openssl x509 -in server.crt -text -noout
```

---