#!/bin/sh
set -e

# CONTAINER_ROLE: app | worker | scheduler
# Hanya container "app" yang menjalankan migration & storage:link,
# supaya worker/scheduler tidak balapan migrate bersamaan.
ROLE="${CONTAINER_ROLE:-app}"

if [ "$ROLE" = "app" ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
	echo "Waiting for database connection..."
	tries=0
	until php artisan migrate:status >/dev/null 2>&1 || [ "$tries" -ge 30 ]; do
		tries=$((tries + 1))
		sleep 2
	done

	echo "Running migrations..."
	php artisan migrate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

if [ "$ROLE" = "app" ] && [ "${FILESYSTEM_DISK:-local}" != "s3" ]; then
	php artisan storage:link || true
fi

exec "$@"
