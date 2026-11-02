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
- **PHP 8.1** — core backend  
- **MariaDB** — database  
- **KeyDB** — cache/session engine  
- **FFmpeg 8.0** — transcoding  
- **yt-dlp** — media acquiring  

UyPanel officially supports Ubuntu 22.04 and is tested on 24.04.

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

Installation on Ubuntu 22.04+:

```bash
sudo apt update && sudo apt full-upgrade -y
sudo apt install -y python3-pip unzip

latest_version=$(curl -s https://api.github.com/repos/m4sterinfo/UyPanel/releases/latest | grep '"tag_name":' | cut -d '"' -f 4)
wget "https://github.com/m4sterinfo/UyPanel/releases/download/${latest_version}/UyPanel.zip"

unzip UyPanel.zip
sudo python3 install
````

---

## ⚠️ Disclaimer

> You are fully responsible for your own use of this software.