FROM phusion/baseimage:jammy-1.0.5
MAINTAINER Matthew Rayner <hello@rayner.io>
ENV REFRESHED_AT 2021-09-07

# based on dgraziotin/lamp
# MAINTAINER Daniel Graziotin <daniel@ineed.coffee>
# updated for Ubuntu 20.04 LTS/PHP 7.4/PHP 8.0 Ferdinand Kasper <fkasper@modus-operandi.at>
# erweitert um Actian Zen (PervasiveSQL) Client + PHP-ODBC

ENV DOCKER_USER_ID 501
ENV DOCKER_USER_GID 20

ENV BOOT2DOCKER_ID 1000
ENV BOOT2DOCKER_GID 50

ENV PHPMYADMIN_VERSION=5.2.3
ENV SUPERVISOR_VERSION=4.3.0

ARG PHP_VERSION
ENV PHP_VERSION=$PHP_VERSION

# Name des Zen-Client-Archivs (liegt im Build-Kontext unter ./zen/)
ARG ZEN_CLIENT_TGZ=Zen-Client-linux-x86_64-16.11.012.000.tar.gz
# Die Zen-Installation verlangt die Zustimmung zur Actian-Lizenz (Datei LICENSE im Archiv).
# Mit --build-arg ZEN_ACCEPT_LICENSE=yes stimmst du ihr zu.
ARG ZEN_ACCEPT_LICENSE=no
ENV ACTIANZEN_ROOT=/usr/local/actianzen
ENV PATH=${PATH}:/usr/local/actianzen/bin

# Tweaks to give Apache/PHP write permissions to the app
RUN usermod -u ${BOOT2DOCKER_ID} www-data && \
    usermod -G staff www-data && \
    useradd -r mysql && \
    usermod -G staff mysql && \
    groupmod -g $(($BOOT2DOCKER_GID + 10000)) $(getent group $BOOT2DOCKER_GID | cut -d: -f1) && \
    groupmod -g ${BOOT2DOCKER_GID} staff

# Install packages
ENV DEBIAN_FRONTEND noninteractive
RUN LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php && \
  apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 4F4EA0AAE5267A6C && \
  apt-get update && \
  apt-get -y upgrade && \
  apt-get -y install nano postfix python3-setuptools wget git apache2 php${PHP_VERSION}-xdebug libapache2-mod-php${PHP_VERSION} mysql-server php${PHP_VERSION}-mysql pwgen php${PHP_VERSION}-apcu php${PHP_VERSION}-gd php${PHP_VERSION}-xml php${PHP_VERSION}-mbstring zip unzip php${PHP_VERSION}-zip curl php${PHP_VERSION}-curl && \
  apt-get -y autoremove && \
  apt-get -y clean && \
  echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ---------------------------------------------------------------------------
# Actian Zen Client (PervasiveSQL) + ODBC fuer PHP
# ---------------------------------------------------------------------------
RUN apt-get update && \
  apt-get -y install unixodbc php${PHP_VERSION}-odbc && \
  apt-get -y clean && \
  rm -rf /var/lib/apt/lists/*

COPY zen/${ZEN_CLIENT_TGZ} /usr/local/
RUN set -e; \
  [ "${ZEN_ACCEPT_LICENSE}" = "yes" ] || { echo "Zen-Lizenz: --build-arg ZEN_ACCEPT_LICENSE=yes setzen"; exit 1; }; \
  cd /usr/local && tar -zxf "${ZEN_CLIENT_TGZ}" && rm -f "${ZEN_CLIENT_TGZ}"; \
  # install.sh fragt nach der Lizenz -> automatisch mit "y" beantworten
  cd ${ACTIANZEN_ROOT}/etc; \
  yes y | sh ./install.sh | tee /tmp/zen-install.log || true; \
  grep -q "successfully completed" /tmp/zen-install.log; \
  # odbcinst.ini verweist auf libodbcci.so - sicherstellen, dass der Link existiert
  [ -e ${ACTIANZEN_ROOT}/lib64/libodbcci.so ] || ln -s libodbcci.so.16 ${ACTIANZEN_ROOT}/lib64/libodbcci.so; \
  # Zen bringt aeltere libstdc++/libgcc_s mit, die z.B. php-gd brechen -> System-Versionen nutzen
  mkdir -p ${ACTIANZEN_ROOT}/lib64.bundled-disabled; \
  mv ${ACTIANZEN_ROOT}/lib64/libstdc++.so.6 ${ACTIANZEN_ROOT}/lib64/libgcc_s.so.1 ${ACTIANZEN_ROOT}/lib64.bundled-disabled/; \
  # Zen-Bibliotheken systemweit bekannt machen
  echo "${ACTIANZEN_ROOT}/lib64" > /etc/ld.so.conf.d/actianzen.conf; \
  ldconfig; \
  # unixODBC nutzt die ini-Dateien von Zen
  touch ${ACTIANZEN_ROOT}/etc/odbc.ini; \
  ln -sf ${ACTIANZEN_ROOT}/etc/odbc.ini /etc/odbc.ini; \
  ln -sf ${ACTIANZEN_ROOT}/etc/odbcinst.ini /etc/odbcinst.ini; \
  # Apache-User darf auf Zen zugreifen
  usermod -aG zen-data www-data; \
  echo "export ACTIANZEN_ROOT=${ACTIANZEN_ROOT}" >> /etc/apache2/envvars

# Install supervisor 4
RUN curl -L https://pypi.io/packages/source/s/supervisor/supervisor-${SUPERVISOR_VERSION}.tar.gz | tar xvz && \
  cd supervisor-${SUPERVISOR_VERSION}/ && \
  python3 setup.py install

# Add image configuration and scripts
ADD supporting_files/start-apache2.sh /start-apache2.sh
ADD supporting_files/start-mysqld.sh /start-mysqld.sh
ADD supporting_files/run.sh /run.sh
ADD supporting_files/zen_init.sh /zen_init.sh
RUN chmod 755 /*.sh
ADD supporting_files/supervisord-apache2.conf /etc/supervisor/conf.d/supervisord-apache2.conf
ADD supporting_files/supervisord-mysqld.conf /etc/supervisor/conf.d/supervisord-mysqld.conf
ADD supporting_files/supervisord.conf /etc/supervisor/supervisord.conf

# Remove pre-installed database
RUN rm -rf /var/lib/mysql

# Add MySQL utils
ADD supporting_files/mysql_init.sh /mysql_init.sh
RUN chmod 755 /*.sh

# Add phpmyadmin
RUN wget -O /tmp/phpmyadmin.tar.gz https://files.phpmyadmin.net/phpMyAdmin/${PHPMYADMIN_VERSION}/phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages.tar.gz
RUN tar xfvz /tmp/phpmyadmin.tar.gz -C /var/www
RUN ln -s /var/www/phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages /var/www/phpmyadmin
RUN mv /var/www/phpmyadmin/config.sample.inc.php /var/www/phpmyadmin/config.inc.php

# Add composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer

ENV MYSQL_PASS:-$(pwgen -s 12 1)
# config to enable .htaccess
ADD supporting_files/apache_default /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Configure /app folder with sample app
RUN mkdir -p /app && rm -fr /var/www/html && ln -s /app /var/www/html
ADD app/ /app

#Environment variables to configure php
ENV PHP_UPLOAD_MAX_FILESIZE 100M
ENV PHP_POST_MAX_SIZE 120M

# Zen-DSN (wird beim Containerstart von /zen_init.sh angelegt, wenn ZEN_HOST und ZEN_DB gesetzt sind)
ENV ZEN_DSN=ZENDB
ENV ZEN_PORT=1583

# Add volumes for the app and MySql
VOLUME  ["/var/lib/mysql", "/app" ]

EXPOSE 80 3306
CMD ["/zen_init.sh"]
