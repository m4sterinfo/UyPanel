FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV container=docker
ENV LC_ALL=C
ENV TZ=UTC

# systemd — necesario para el instalador (systemctl enable/start uypanel)
RUN apt-get update && apt-get install -y --no-install-recommends \
    systemd systemd-sysv dbus \
    lsb-release sudo python3 python3-pip wget curl unzip \
    iproute2 net-tools apt-utils ca-certificates gnupg \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Eliminamos solo las unidades realmente innecesarias en el contenedor
# NO tocamos sysinit.target.wants (journald, tmpfiles, sysctl — son necesarios)
RUN rm -f /lib/systemd/system/multi-user.target.wants/* \
    /lib/systemd/system/local-fs.target.wants/* \
    /lib/systemd/system/sockets.target.wants/*udev* \
    /lib/systemd/system/sockets.target.wants/*initctl* \
    /lib/systemd/system/anaconda.target.wants/* \
    /lib/systemd/system/plymouth* \
    /lib/systemd/system/systemd-update-utmp*

STOPSIGNAL SIGRTMIN+3

WORKDIR /opt/xcvm-install

# Script de instalación automática (responde a las preguntas interactivas)
COPY auto_install.sh /opt/xcvm-install/auto_install.sh
RUN chmod +x /opt/xcvm-install/auto_install.sh

# Punto de entrada — systemd (PID 1), la instalación se lanza a mano o vía script
CMD ["/sbin/init"]
