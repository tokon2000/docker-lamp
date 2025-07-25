#!/bin/bash

if [ -f /var/run/apache2/apache2.pid ]; then
    rm -f /var/run/apache2/apache2.pid
fi

source /etc/apache2/envvars
exec apache2 -D FOREGROUND
