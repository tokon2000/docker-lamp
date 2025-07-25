#!/bin/bash

# Alte PID-Datei entfernen falls vorhanden
if [ -f /var/run/mysqld/mysqld.pid ]; then
    rm -f /var/run/mysqld/mysqld.pid
fi

# Alte Socket-Dateien entfernen falls vorhanden
if [ -S /var/run/mysqld/mysqld.sock ]; then
    rm -f /var/run/mysqld/mysqld.sock
fi

if [ -S /var/run/mysqld/mysqlx.sock ]; then
    rm -f /var/run/mysqld/mysqlx.sock
fi

# Kurze Pause um sicherzustellen, dass andere Prozesse nicht kollidieren
sleep 2

# MySQL starten
exec /usr/local/bin/pidproxy /var/run/mysqld/mysqld.pid /usr/bin/mysqld_safe
