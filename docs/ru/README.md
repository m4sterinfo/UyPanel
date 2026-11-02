<p align="center">
  <img src="../_media/UyPanel_logo.jpg" width="440" alt="UyPanel Logo"/>
</p>

<h1 align="center">📺 UyPanel IPTV Panel</h1>

<p align="center">
  A modern, open-source IPTV panel inspired by Xtream Codes.<br>
  Lightweight. Fast. Community-driven.
</p>

---

## ❓ Что это такое

**UyPanel** — это современная IPTV-панель, работающая на PHP, Nginx, FFmpeg и MariaDB.

UyPanel помогает разворачивать полноценную IPTV-инфраструктуру:

- Управление Live, Movies и Series  
- Поддержка реселлеров и пользователей  
- Load Balancing  
- EPG и VOD система  
- Инструменты мониторинга и API

💡 *Полностью бесплатная. Без лицензий. Без сервер-локов.*

---

## ⭐ Особенности

- 🚀 Современная архитектура панели IPTV
- 🔀 Поддержка встроенного балансировщика нагрузки
- 🎥 Управление VOD и прямыми трансляциями
- 🧩 API, совместимый с Xtream Codes
- 🔐 Усиленные исправления безопасности
- 📦 Быстрое перекодирование на основе FFmpeg
- 🌍 Многоязычная документация
- 🧭 Простой и интуитивно понятный интерфейс
- 🛠 Модульная конструкция для расширений

---

## 🧰 Технологии

- **Nginx** — reverse proxy & web server  
- **PHP 8.1** — core backend  
- **MariaDB** — database  
- **KeyDB** — cache/session engine  
- **FFmpeg 8.0** — transcoding  
- **yt-dlp** — media acquiring  

UyPanel официально поддерживает Ubuntu 22.04 и тестируется на 24.04.

---

## 🌐 Сообщество

UyPanel — полностью управляемый сообществом проект.

- 💬 Issues: https://github.com/m4sterinfo/UyPanel/issues  
- ⭐ GitHub Stars: поддержите проект звездой  
- 🔧 Pull Requests: принимаются  

Если хочешь внести вклад — прочитай:  
[Contributing Guide](https://github.com/m4sterinfo/UyPanel/blob/master/CONTRIBUTING.md)

---

## 🛠 Установка

Установка на Ubuntu 22.04+:

```bash
sudo apt update && sudo apt full-upgrade -y
sudo apt install -y python3-pip unzip

latest_version=$(curl -s https://api.github.com/repos/m4sterinfo/UyPanel/releases/latest | grep '"tag_name":' | cut -d '"' -f 4)
wget "https://github.com/m4sterinfo/UyPanel/releases/download/${latest_version}/UyPanel.zip"

unzip UyPanel.zip
sudo python3 install
```

---

## ⚠️ Отказ от ответственности

> Вы несете полную ответственность за собственное использование программного обеспечения.