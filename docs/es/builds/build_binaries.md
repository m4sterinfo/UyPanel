<h1 align="center">🧱 UyPanel — Compilación de binarios</h1>

<p align="center">
  Esta guía te ayuda a compilar los binarios para UyPanel. El proceso está pensado para desarrolladores y administradores que quieran adaptar el sistema a sus necesidades.
</p>

---

## 📚 Navegación

- [🔧 Preparación previa](#preparación-previa)
- [🌐 Compilación de NGINX](#compilación-de-nginx)
- [📺 Compilación de NGINX con RTMP](#compilación-de-nginx-con-rtmp)
- [🐘 Compilación de PHP-FPM](#compilación-de-php-fpm)
- [📦 Instalación de extensiones PHP](#instalación-de-extensiones-php)
- [⚙️ Compilación del binario network](#compilación-del-binario-network)
- [✅ Conclusión](#conclusión)

---

## 🔧 Preparación previa

> ⚠️ **Atención:** Todo se hace **en el servidor** donde está instalado `uypanel`.

### 1. Instala las dependencias

Instala los paquetes necesarios para compilar. Así tendrás todas las herramientas y librerías.

```bash
sudo apt-get install -y \
  build-essential libpcre3 libpcre3-dev zlib1g zlib1g-dev \
  libssl-dev libgd-dev libxml2 libxml2-dev uuid-dev libxslt1-dev \
  unzip wget curl git
```

---

## 🌐 Compilación de NGINX

### 1. Descarga las fuentes

#### OpenSSL

```bash
wget https://github.com/openssl/openssl/releases/download/openssl-3.5.1/openssl-3.5.1.tar.gz
tar -xzvf openssl-3.5.1.tar.gz
```

#### Zlib

```bash
wget https://zlib.net/zlib-1.3.1.tar.gz
tar -xzvf zlib-1.3.1.tar.gz
```

#### PCRE

```bash
wget https://sourceforge.net/projects/pcre/files/pcre/8.45/pcre-8.45.tar.gz
tar -xzvf pcre-8.45.tar.gz
```

#### Módulo FLV

```bash
wget https://github.com/winshining/nginx-http-flv-module/archive/refs/tags/v1.2.12.zip
unzip v1.2.12.zip
```

#### Nginx

```bash
wget https://nginx.org/download/nginx-1.28.0.tar.gz
tar -zxvf nginx-1.28.0.tar.gz
cd nginx-1.28.0
```

### 2. Configuración del build

Configura la compilación estática con los módulos necesarios.

```bash
./configure \
  --prefix=/home/uypanel/bin/nginx \
  --with-compat \
  --with-http_auth_request_module \
  --with-file-aio \
  --with-threads \
  --with-http_gzip_static_module \
  --with-http_realip_module \
  --with-http_flv_module \
  --with-http_mp4_module \
  --with-http_secure_link_module \
  --with-http_slice_module \
  --with-http_ssl_module \
  --with-http_stub_status_module \
  --with-http_sub_module \
  --with-http_v2_module \
  --with-cc-opt='-static -static-libgcc -O2 -g -pipe -Wall -Wp,-D_FORTIFY_SOURCE=2 -fexceptions -fstack-protector --param=ssp-buffer-size=4 -m64 -mtune=generic -fPIC' \
  --with-ld-opt='-static -Wl,-z,relro -Wl,-z,now -pie' \
  --with-pcre=../pcre-8.45 \
  --with-pcre-jit \
  --with-zlib=../zlib-1.3.1 \
  --with-openssl=../openssl-3.5.1 \
  --with-openssl-opt=no-nextprotoneg
```

### 3. Compilación e instalación

```bash
make
make install
```

---

## 📺 Compilación de NGINX con RTMP

### 1. Descarga el módulo RTMP

```bash
wget https://github.com/arut/nginx-rtmp-module/archive/refs/tags/v1.2.2.tar.gz
tar -xzvf v1.2.2.tar.gz
```

### 2. Configura el build

```bash
cd nginx-1.28.0
./configure \
  --prefix=/home/uypanel/bin/nginx_rtmp \
  --add-module=../nginx-http-flv-module-1.2.12 \
  --with-compat \
  --with-http_auth_request_module \
  --with-file-aio \
  --with-threads \
  --with-http_gzip_static_module \
  --with-http_realip_module \
  --with-http_flv_module \
  --with-http_mp4_module \
  --with-http_secure_link_module \
  --with-http_slice_module \
  --with-http_ssl_module \
  --with-http_stub_status_module \
  --with-http_sub_module \
  --with-http_v2_module \
  --with-cc-opt='-static -static-libgcc -O2 -g -pipe -Wall -Wp,-D_FORTIFY_SOURCE=2 -fexceptions -fstack-protector --param=ssp-buffer-size=4 -m64 -mtune=generic -fPIC' \
  --with-ld-opt='-static -Wl,-z,relro -Wl,-z,now -pie'
```

### 3. Compilación e instalación

```bash
make
make install
```

### 4. Renombra el ejecutable

```bash
mv /home/uypanel/bin/nginx_rtmp/sbin/nginx /home/uypanel/bin/nginx_rtmp/sbin/nginx_rtmp
```

---

## 🐘 Compilación de PHP-FPM

### 1. Instala las dependencias

```bash
sudo apt-get install -y \
  libcurl4-gnutls-dev libbz2-dev libzip-dev autoconf automake \
  libtool m4 gcc make pkg-config libmaxminddb-dev libssh2-1-dev
```

### 2. Descarga las fuentes

```bash
wget -O php-8.1.33.tar.gz http://php.net/get/php-8.1.33.tar.gz/from/this/mirror
tar -xzvf php-8.1.33.tar.gz
cd php-8.1.33
```

### 3. Configuración del build

```bash
./configure \
  --prefix=/home/uypanel/bin/php \
  --with-fpm-user=uypanel \
  --with-fpm-group=uypanel \
  --enable-gd \
  --with-jpeg \
  --with-freetype \
  --enable-static \
  --disable-shared \
  --enable-opcache \
  --enable-fpm \
  --without-sqlite3 \
  --without-pdo-sqlite \
  --enable-mysqlnd \
  --with-mysqli \
  --with-curl \
  --disable-cgi \
  --with-zlib \
  --enable-sockets \
  --with-openssl \
  --enable-shmop \
  --enable-sysvsem \
  --enable-sysvshm \
  --enable-sysvmsg \
  --enable-calendar \
  --disable-rpath \
  --enable-inline-optimization \
  --enable-pcntl \
  --enable-mbregex \
  --enable-exif \
  --enable-bcmath \
  --with-mhash \
  --with-gettext \
  --with-xmlrpc \
  --with-xsl \
  --with-libxml \
  --with-pdo-mysql \
  --disable-mbregex \
  --with-pear \
  --enable-mbstring
```

### 4. Compilación e instalación

```bash
make
make install
```

---

## 📦 Instalación de extensiones PHP

Usa el `pecl` incluido en tu build de PHP para instalar las extensiones.

```bash
/home/uypanel/bin/php/bin/pecl install maxminddb
/home/uypanel/bin/php/bin/pecl install ssh2
/home/uypanel/bin/php/bin/pecl install igbinary
/home/uypanel/bin/php/bin/pecl install redis
```

### Configuración de la extensión Redis

Durante la instalación, PECL hace preguntas:

```
enable igbinary serializer support? [no] : yes
enable lzf compression support? [no] :
enable zstd compression support? [no] :
enable msgpack serializer support? [no] :
enable lz4 compression? [no] :
use system liblz4? [yes] :
```

Respuestas recomendadas: `yes` en igbinary, el resto a gusto.

---

## ⚙️ Compilación del binario network

### 1. Instala `PyInstaller` (una sola vez)

```bash
pip install -U pyinstaller
```

### 2. Compila el binario

```bash
cd /home/uypanel/bin
pyinstaller --onefile network.py
```

Tras compilar, el ejecutable aparecerá en:

```bash
/home/uypanel/bin/dist/network
```

### 3. Mueve el binario a su sitio

```bash
mv /home/uypanel/bin/dist/network /home/uypanel/bin/network
```

### 4. Limpia los directorios temporales

```bash
rm -rf build dist network.spec __pycache__
```

---

## ✅ Conclusión

Al terminar todos los pasos tendrás tus propios binarios compilados estáticamente:

- `nginx`
- `nginx_rtmp`
- `php-fpm`
- extensiones `pecl`
- `network` (binario del script Python)

> 💡 **Consejo:** Si tienes problemas, revisa los logs del build o consulta los [Issues](https://github.com/m4sterinfo/UyPanel/issues).

---
