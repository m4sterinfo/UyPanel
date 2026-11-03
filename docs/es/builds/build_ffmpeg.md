<h1 align="center">🚀 UyPanel — Compilación de FFmpeg</h1>

<p align="center">
  Guía para compilar FFmpeg con aceleración por hardware NVIDIA (NVENC/CUVID) e integración estática de librerías, para portabilidad total dentro del proyecto UyPanel.
</p>

---

## 📚 Navegación

- [📋 Requisitos](#requisitos)
- [🔧 Configuración](#configuración)
- [🛠 Instalación de herramientas de build](#instalación-de-herramientas-de-build)
- [🎶 Compilación e inclusión de códecs](#compilación-e-inclusión-de-códecs)
- [🖥 Instalación de NVIDIA y CUDA](#instalación-de-nvidia-y-cuda)
- [🔨 Compilación de FFmpeg](#compilación-de-ffmpeg)
- [📦 Instalación](#instalación)
- [✅ Verificación](#verificación)
- [🧾 Notas](#notas)
- [🔄 Compatibilidad de versiones](#compatibilidad-de-versiones)

---

## 📋 Requisitos

* **Ubuntu 22.04 o superior** — SO recomendado para un build estable.
* Tarjeta gráfica NVIDIA con soporte **NVENC** (opcional, pero recomendado para aceleración por hardware).
* ~15 GB de espacio libre en disco para fuentes y archivos temporales.
* Conexión a internet estable para descargar dependencias.

> 💡 **Consejo:** Asegúrate de que el sistema está actualizado antes de empezar para evitar conflictos de paquetes.

---

## 🔧 Configuración

Define las variables de entorno para personalizar el build. Esto facilita cambiar versiones y rutas.

```bash
# Versión de FFmpeg a compilar
export FFMPEG_VERSION="8.0"

# Directorio de instalación
export INSTALL_DIR="/home/uypanel/bin/ffmpeg_bin"

# Directorio de compilación
export BUILD_DIR="$HOME/ffmpeg_sources"

# Versión de CUDA
export CUDA_VERSION="12-2"

# Versión del driver NVIDIA
export NVIDIA_DRIVER_VERSION="535"
```

---

## 🛠 Instalación de herramientas de build

Actualiza el sistema e instala los paquetes base de desarrollo. Así tendrás todas las herramientas necesarias.

```bash
sudo apt-get update -qq && sudo apt-get -y install \
  autoconf automake build-essential cmake git-core \
  libass-dev libfreetype6-dev libgnutls28-dev libmp3lame-dev \
  libsdl2-dev libtool libva-dev libvdpau-dev libvorbis-dev \
  libxcb1-dev libxcb-shm0-dev libxcb-xfixes0-dev \
  meson ninja-build pkg-config texinfo wget yasm \
  zlib1g-dev mercurial nasm libssl-dev software-properties-common
```

---

## 🎶 Compilación e inclusión de códecs

Compila las librerías desde las fuentes para integrarlas estáticamente en FFmpeg. Esto da soporte de los formatos populares sin dependencias externas.

### 2.1 Creación del directorio de build

```bash
mkdir -p ${BUILD_DIR:-~/ffmpeg_sources} && cd ${BUILD_DIR:-~/ffmpeg_sources}
```

### 2.2 H.264 (libx264)

```bash
git clone https://code.videolan.org/videolan/x264.git
cd x264
./configure --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" --enable-static --disable-shared --enable-pic
make -j$(nproc)
sudo make install
cd ..
```

### 2.3 H.265 (libx265)

```bash
git clone https://bitbucket.org/multicoreware/x265_git.git
cd x265_git/build
cmake ../source -DCMAKE_INSTALL_PREFIX="${INSTALL_DIR:-$HOME/ffmpeg_build}" -DENABLE_SHARED=OFF
make -j$(nproc)
sudo make install
cd ../..
```

### 2.4 VP8/VP9 (libvpx)

```bash
git clone https://chromium.googlesource.com/webm/libvpx
cd libvpx
./configure --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" --enable-static --disable-shared --enable-pic
make -j$(nproc)
sudo make install
cd ..
```

### 2.5 Audio Opus (libopus)

```bash
wget https://downloads.xiph.org/releases/opus/opus-1.5.2.tar.gz
tar -xvzf opus-1.5.2.tar.gz
cd opus-1.5.2
./configure --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" --enable-static --disable-shared
make -j$(nproc)
sudo make install
cd ..
```

### 2.6 Librerías adicionales

- **libass** (subtítulos):

```bash
git clone https://github.com/libass/libass.git
cd libass
./autogen.sh
./configure --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" --enable-static --disable-shared
make -j$(nproc)
sudo make install
cd ..
```

- **libfreetype** (fuentes):

```bash
wget https://download.savannah.gnu.org/releases/freetype/freetype-2.13.2.tar.gz
tar -xvzf freetype-2.13.2.tar.gz
cd freetype-2.13.2
./configure --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" --enable-static --disable-shared
make -j$(nproc)
sudo make install
cd ..
```

- **libvorbis** (audio):

```bash
wget https://downloads.xiph.org/releases/vorbis/libvorbis-1.3.7.tar.xz
tar -xvf libvorbis-1.3.7.tar.xz
cd libvorbis-1.3.7
./configure --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" --enable-static --disable-shared
make -j$(nproc)
sudo make install
cd ..
```

- **libmp3lame** (MP3):

```bash
wget https://downloads.sourceforge.net/project/lame/lame/3.100/lame-3.100.tar.gz
tar -xvzf lame-3.100.tar.gz
cd lame-3.100
./configure --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" --enable-static --disable-shared
make -j$(nproc)
sudo make install
cd ..
```

- **libtheora** (Theora):

```bash
git clone https://github.com/xiph/theora.git
cd theora
./autogen.sh
./configure --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" --enable-static --disable-shared
make -j$(nproc)
sudo make install
cd ..
```

- **librtmp** (RTMP):

```bash
git clone git://git.ffmpeg.org/rtmpdump
cd rtmpdump
make SYS=posix -j$(nproc)
sudo make prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" install
cd ..
```

- **libunistring** (para gnutls y librtmp):

```bash
wget https://ftp.gnu.org/gnu/libunistring/libunistring-1.2.tar.gz
tar -xvzf libunistring-1.2.tar.gz
cd libunistring-1.2
./configure --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" --enable-static --disable-shared
make -j$(nproc)
sudo make install
cd ..
```

- **bzip2**
```bash
cd ${BUILD_DIR:-~/ffmpeg_sources}
wget https://sourceware.org/pub/bzip2/bzip2-1.0.8.tar.gz
tar -xvzf bzip2-1.0.8.tar.gz
cd bzip2-1.0.8
make -f Makefile-libbz2_so CFLAGS="-fPIC" -j$(nproc)
make install PREFIX="${INSTALL_DIR:-$HOME/ffmpeg_build}"
cd ..
```

---

## 🖥 Instalación de NVIDIA y CUDA

Para la aceleración por hardware, instala los drivers y herramientas de NVIDIA.

### 3.1 Instalación de los drivers NVIDIA

```bash
sudo add-apt-repository ppa:graphics-drivers/ppa
sudo apt update
sudo apt install nvidia-driver-${NVIDIA_DRIVER_VERSION:-535}
```

> ⚠️ **Importante:** Reinicia el sistema tras la instalación y comprueba la compatibilidad con tu tarjeta gráfica.

### 3.2 Instalación de CUDA Toolkit

```bash
wget https://developer.download.nvidia.com/compute/cuda/repos/ubuntu2204/x86_64/cuda-keyring_1.0-1_all.deb
sudo dpkg -i cuda-keyring_1.0-1_all.deb
sudo apt update
sudo apt install -y cuda-toolkit-${CUDA_VERSION:-12-2}
```

### 3.3 Instalación de las cabeceras NVENC

```bash
cd ${BUILD_DIR:-~/ffmpeg_sources}
git clone https://git.videolan.org/git/ffmpeg/nv-codec-headers.git
cd nv-codec-headers
make
sudo make PREFIX="${INSTALL_DIR:-$HOME/ffmpeg_build}" install
cd ..
```

### 3.4 Herramientas NVIDIA opcionales

```bash
sudo apt install -y nvidia-cuda-toolkit nvidia-cuda-dev
```

---

## 🔨 Compilación de FFmpeg

### 4.1 Descarga del código fuente

```bash
cd ${BUILD_DIR:-~/ffmpeg_sources}
wget https://ffmpeg.org/releases/ffmpeg-${FFMPEG_VERSION:-8.0}.tar.bz2
tar xjvf ffmpeg-${FFMPEG_VERSION:-8.0}.tar.bz2
cd ffmpeg-${FFMPEG_VERSION:-8.0}
```

### 4.2 Configuración

```bash
export PATH="${INSTALL_DIR:-$HOME/bin}:$PATH"
export PKG_CONFIG_PATH="${INSTALL_DIR:-$HOME/ffmpeg_build}/lib/pkgconfig"

./configure \
  --prefix="${INSTALL_DIR:-$HOME/ffmpeg_build}" \
  --pkg-config-flags="--static" \
  --extra-cflags="-I${INSTALL_DIR:-$HOME/ffmpeg_build}/include -I/usr/local/cuda/include" \
  --extra-ldflags="-L${INSTALL_DIR:-$HOME/ffmpeg_build}/lib -L/usr/local/cuda/lib64 -Wl,-Bstatic -lcrypto -lssl -Wl,-Bdynamic" \
  --extra-version=XCVM \
  --extra-libs="-lsupc++ -lgmp -lz -lunistring -lpthread -lm -lrt -ldl" \
  --bindir="${INSTALL_DIR:-$HOME/bin}" \
  --enable-gpl \
  --enable-gnutls \
  --enable-libass \
  --enable-libfreetype \
  --enable-libmp3lame \
  --enable-libopus \
  --enable-libvorbis \
  --enable-libvpx \
  --enable-libx264 \
  --enable-libx265 \
  --enable-librtmp \
  --enable-libtheora \
  --enable-bzlib \
  --enable-fontconfig \
  --enable-zlib \
  --enable-nvenc \
  --enable-ffnvcodec \
  --enable-cuvid \
  --enable-version3 \
  --enable-nonfree \
  --enable-pthreads \
  --enable-runtime-cpudetect \
  --enable-gray \
  --disable-alsa \
  --disable-indev=alsa \
  --disable-outdev=alsa \
  --disable-ffplay \
  --disable-doc \
  --disable-debug \
  --disable-autodetect \
  --enable-static \
  --enable-muxer=hls \
  --enable-muxer=dash \
  --enable-demuxer=hls \
  --extra-cflags=--static \
  --target-os=linux
```

### 4.3 Compilación

```bash
make -j$(nproc)
```

---

## 📦 Instalación

```bash
mkdir -p ${INSTALL_DIR:-/home/uypanel/bin/ffmpeg_bin}/${FFMPEG_VERSION:-8.0}/
cp ffmpeg ffprobe ${INSTALL_DIR:-/home/uypanel/bin/ffmpeg_bin}/${FFMPEG_VERSION:-8.0}/
```

---

## ✅ Verificación

Comprueba la versión y el soporte NVIDIA tras el build.

```bash
${INSTALL_DIR:-/home/uypanel/bin/ffmpeg_bin}/${FFMPEG_VERSION:-8.0}/ffmpeg -version
${INSTALL_DIR:-/home/uypanel/bin/ffmpeg_bin}/${FFMPEG_VERSION:-8.0}/ffprobe -version
```

Comprobación del soporte NVIDIA:

```bash
${INSTALL_DIR:-/home/uypanel/bin/ffmpeg_bin}/${FFMPEG_VERSION:-8.0}/ffmpeg -encoders | grep nvenc
${INSTALL_DIR:-/home/uypanel/bin/ffmpeg_bin}/${FFMPEG_VERSION:-8.0}/ffmpeg -decoders | grep cuvid
```

---

## 🧾 Notas

* Todas las librerías se compilan estáticamente y quedan incluidas en el binario FFmpeg, lo que garantiza portabilidad total.
* El driver NVIDIA debe ser compatible con tu tarjeta gráfica.
* Tras instalar los drivers puede hacer falta **reiniciar**.
* La compilación de FFmpeg consume bastantes recursos de CPU y memoria.
* El binario final será grande por incluir todas las dependencias.
* Ajusta las variables de entorno según tu sistema.
* Para distintas versiones de FFmpeg pueden requerirse ajustes en los flags de configuración.

> 💬 **Consejo:** Si tienes problemas, revisa los logs del build o abre un issue en el [repositorio](https://github.com/m4sterinfo/UyPanel/issues).

---

## 🔄 Compatibilidad de versiones

| Versión FFmpeg | CUDA recomendada | Notas |
|---------------|--------------------|------------|
| 8.x           | 12.2+              | Funciones más recientes |
| 7.x           | 12.0+              | Estable |
| 6.x           | 11.8+              | Antigua |

Consulta la [documentación de FFmpeg](https://ffmpeg.org/) para los requisitos concretos de cada versión.

---
