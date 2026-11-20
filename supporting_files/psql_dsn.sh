#!/bin/bash
#
# Schreibt beim Containerstart die ODBC-DSN fuer PervasiveSQL / Actian Zen
# anhand von Umgebungsvariablen:
#
#   PSQL_HOST   IP oder Hostname des PSQL-Servers   (Pflicht, sonst wird nichts angelegt)
#   PSQL_DB     Datenbankname auf dem Server       (Pflicht)
#   PSQL_DSN    Name der DSN fuer odbc_connect()   (Standard: MEINEDB)
#   PSQL_PORT   Port der Relational Engine         (Standard: 1583)
#   PSQL_UID    Benutzer, falls DB-Sicherheit aktiv (optional)
#   PSQL_PWD    Passwort, falls DB-Sicherheit aktiv (optional)

ODBC_INI=/usr/local/psql/etc/odbc.ini
DRIVER=/usr/local/psql/lib64/libodbcci.so

PSQL_DSN=${PSQL_DSN:-MEINEDB}
PSQL_PORT=${PSQL_PORT:-1583}

if [ -z "$PSQL_HOST" ] || [ -z "$PSQL_DB" ]; then
    echo "PSQL: PSQL_HOST oder PSQL_DB nicht gesetzt - keine DSN angelegt"
    exit 0
fi

echo "PSQL: lege DSN '${PSQL_DSN}' an -> ${PSQL_HOST}:${PSQL_PORT}/${PSQL_DB}"

cat > "$ODBC_INI" <<EOF
[ODBC Data Sources]
${PSQL_DSN}=Pervasive ODBC Interface

[${PSQL_DSN}]
Driver=${DRIVER}
Description=Pervasive ODBC Interface: ${PSQL_HOST}:${PSQL_PORT}/${PSQL_DB}
DBQ=${PSQL_DB}
ServerName=${PSQL_HOST}:${PSQL_PORT}
UID=${PSQL_UID}
PWD=${PSQL_PWD}
OpenMode=0
PvTranslate=auto
EOF

chmod 644 "$ODBC_INI"
ln -sf "$ODBC_INI" /etc/odbc.ini
