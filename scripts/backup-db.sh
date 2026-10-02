#!/usr/bin/env bash
# Backup database (PostgreSQL / MySQL, sesuai DB_CONNECTION di .env) ke
# ./backups/<db>-<tanggal>.sql.gz, simpan KEEP_BACKUPS file terakhir.
# Cron contoh (tiap hari 02:00):
#   0 2 * * * cd /opt/apps/<project> && bash scripts/backup-db.sh >> backups/cron.log 2>&1
set -euo pipefail

cd "$(dirname "$0")/.."

KEEP_BACKUPS="${KEEP_BACKUPS:-14}"
env_value() { grep "^$1=" .env | tail -n1 | cut -d= -f2- | tr -d '"'; }

DB_CONNECTION="$(env_value DB_CONNECTION)"
DB_DATABASE="$(env_value DB_DATABASE)"

mkdir -p backups
file="backups/${DB_DATABASE}-$(date +%F-%H%M).sql.gz"

case "$DB_CONNECTION" in
	pgsql)
		docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > "$file"
		;;
	mysql | mariadb)
		docker compose exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines "$MYSQL_DATABASE"' | gzip > "$file"
		;;
	*)
		echo "✗ DB_CONNECTION=$DB_CONNECTION tidak didukung"
		exit 1
		;;
esac

echo "✓ Backup: $file"

ls -1t backups/"${DB_DATABASE}"-*.sql.gz | tail -n +"$((KEEP_BACKUPS + 1))" | xargs -r rm -f
