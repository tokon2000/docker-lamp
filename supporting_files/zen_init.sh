#!/bin/bash
#
# Legt beim Containerstart einen Zen-ODBC-DSN an und startet danach
# das normale LAMP-Startskript (/run.sh).
#
# Umgebungsvariablen:
#   ZEN_HOST  Hostname/IP des PervasiveSQL/Zen-Servers   (Pflicht)
#   ZEN_DB    Name der Datenbank auf dem Server         (Pflicht)
#   ZEN_DSN   Name des DSN im Container                 (Default: ZENDB)
#   ZEN_PORT  Port der relationalen Engine              (Default: 1583)

export ACTIANZEN_ROOT="${ACTIANZEN_ROOT:-/usr/local/actianzen}"
export PATH="$PATH:$ACTIANZEN_ROOT/bin"
ZEN_DSN="${ZEN_DSN:-ZENDB}"
ZEN_PORT="${ZEN_PORT:-1583}"

if [ -n "$ZEN_HOST" ] && [ -n "$ZEN_DB" ]; then
    if dsnadd -l 2>/dev/null | grep -qw "$ZEN_DSN"; then
        echo "=> Zen-DSN '$ZEN_DSN' existiert bereits"
    else
        echo "=> Lege Zen-DSN '$ZEN_DSN' an (Server: $ZEN_HOST:$ZEN_PORT, DB: $ZEN_DB)"
        dsnadd -dsn="$ZEN_DSN" -db="$ZEN_DB" -host="$ZEN_HOST" -port="$ZEN_PORT" \
            || echo "!! dsnadd fehlgeschlagen"
    fi
    chmod 644 "$ACTIANZEN_ROOT/etc/odbc.ini" 2>/dev/null
else
    echo "=> ZEN_HOST/ZEN_DB nicht gesetzt - kein Zen-DSN angelegt"
fi

exec /run.sh
