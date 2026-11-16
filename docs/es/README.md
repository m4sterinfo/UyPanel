<p align="center">
  <img src="../_media/UyPanel_logo.jpg" width="440" alt="UyPanel Logo"/>
</p>

<h1 align="center">📺 UyPanel IPTV Panel</h1>

<p align="center">
  Un panel IPTV moderno, open-source e inspirado en Xtream Codes.<br>
  Ligero. Rápido. Dirigido por la comunidad.
</p>

---

## ❓ Qué es

**UyPanel** es un panel IPTV moderno que funciona sobre PHP, Nginx, FFmpeg y MariaDB.

UyPanel ayuda a desplegar una infraestructura IPTV completa:

- Gestión de Live, Movies y Series  
- Soporte de resellers y usuarios  
- Load Balancing  
- Sistema de EPG y VOD  
- Herramientas de monitoreo y API

💡 *Completamente gratis. Sin licencias. Sin bloqueos de servidor.*

---

## ⭐ Características

- 🚀 Arquitectura moderna para un panel IPTV
- 🔀 Soporte de balanceador de carga integrado
- 🎥 Gestión de VOD y transmisiones en directo
- 🧩 API compatible con Xtream Codes
- 🔐 Parches de seguridad reforzados
- 📦 Transcodificación rápida basada en FFmpeg
- 🌍 Documentación multiidioma
- 🧭 Interfaz simple e intuitiva
- 🛠 Diseño modular para extensiones

---

## 🧰 Tecnologías

- **Nginx** — reverse proxy & web server  
- **PHP 8.3** — backend core  
- **MariaDB** — base de datos  
- **Valkey** — motor de caché/sesiones (compatible Redis)  
- **FFmpeg 8.0** — transcodificación  
- **yt-dlp** — adquisición de medios  

UyPanel soporta oficialmente **Debian 13 (recomendado)** y **Ubuntu 24.04 LTS**. Desde el 2026-11-15 las versiones anteriores (Ubuntu 22.04/20.04, Debian 12) **ya NO tienen soporte**: están EOL o traen librerías del sistema vulnerables, y los binarios actuales (nginx_rtmp exige GLIBC 2.38) no funcionan en ellas — el instalador las rechaza.

---

## 🌐 Comunidad

UyPanel es un proyecto completamente gestionado por la comunidad.

- 💬 Issues: https://github.com/m4sterinfo/UyPanel/issues  
- ⭐ GitHub Stars: apoya el proyecto con una estrella  
- 🔧 Pull Requests: se aceptan  

Si quieres contribuir, lee:  
[Guía de contribución](https://github.com/m4sterinfo/UyPanel/blob/master/CONTRIBUTING.md)

---

## 🛠 Instalación

Instalación en Debian 13 (recomendado) o Ubuntu 24.04:

```bash
sudo apt update && sudo apt full-upgrade -y
sudo apt install -y unzip

latest_version=$(curl -s https://api.github.com/repos/m4sterinfo/UyPanel/releases/latest | grep '"tag_name":' | cut -d '"' -f 4)
wget "https://github.com/m4sterinfo/UyPanel/releases/download/${latest_version}/UyPanel.zip"

unzip UyPanel.zip
sudo python3 install
```

---

## ⚠️ Aviso legal

> Eres el único responsable de tu propio uso del software.
