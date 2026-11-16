<p align="center">
  <img src="../_media/UyPanel_logo.jpg" width="440" alt="UyPanel Logo"/>
</p>

<h1 align="center">📺 UyPanel IPTV Panel</h1>

<p align="center">
  A modern, open-source IPTV panel inspired by Xtream Codes.<br>
  Lightweight. Fast. Community-driven.
</p>

---

## ❓ What Is It?

**UyPanel** is a modern IPTV panel built on PHP, Nginx, FFmpeg, and MariaDB.

UyPanel helps you deploy a full IPTV infrastructure:

- Management of Live TV, Movies, and Series  
- Reseller and user management  
- Load Balancing  
- EPG and VOD system  
- Monitoring tools and API

💡 *Completely free. No licenses. No server locks.*

---

## ⭐ Features

- 🚀 Modern IPTV panel architecture  
- 🔀 Built-in load balancer support  
- 🎥 Management of VOD and live streams  
- 🧩 API compatible with Xtream Codes  
- 🔐 Enhanced security fixes  
- 📦 Fast FFmpeg-based transcoding  
- 🌍 Multi-language documentation  
- 🧭 Simple and intuitive interface  
- 🛠 Modular extension system  

---

## 🧰 Technologies

- **Nginx** — reverse proxy & web server  
- **PHP 8.3** — core backend  
- **MariaDB** — database  
- **Valkey** — cache/session engine (Redis-compatible)  
- **FFmpeg 8.0** — transcoding  
- **yt-dlp** — media acquiring  

UyPanel officially supports **Debian 13 (recommended)** and **Ubuntu 24.04 LTS**. Since 2026-11-15, older releases (Ubuntu 22.04/20.04, Debian 12) are **NO LONGER SUPPORTED**: they are EOL or ship vulnerable system libraries, and the current binaries (nginx_rtmp requires GLIBC 2.38) do not run on them — the installer rejects them.

---

## 🌐 Community

UyPanel is a fully community-driven project.

- 💬 Issues: https://github.com/m4sterinfo/UyPanel/issues  
- ⭐ GitHub Stars: support the project by giving it a star  
- 🔧 Pull Requests: welcome  

If you want to contribute, read the:  
[Contributing Guide](https://github.com/m4sterinfo/UyPanel/blob/master/CONTRIBUTING.md)

---

## 🛠 Installation

Installation on Debian 13 (recommended) or Ubuntu 24.04:

```bash
sudo apt update && sudo apt full-upgrade -y
sudo apt install -y unzip

latest_version=$(curl -s https://api.github.com/repos/m4sterinfo/UyPanel/releases/latest | grep '"tag_name":' | cut -d '"' -f 4)
wget "https://github.com/m4sterinfo/UyPanel/releases/download/${latest_version}/UyPanel.zip"

unzip UyPanel.zip
sudo python3 install
````

---

## ⚠️ Disclaimer

> You are fully responsible for your own use of this software.