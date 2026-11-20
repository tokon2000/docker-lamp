#!/bin/bash
# Einstiegspunkt: erst die PSQL-DSN schreiben, dann den normalen LAMP-Start.
/psql_dsn.sh
exec /run.sh
