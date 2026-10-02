# syntax=docker/dockerfile:1

# Versi PHP image — dideteksi install.php dari composer.json/composer.lock.
ARG PHP_VERSION=8.4

########################################
# 1. Build: composer deps + Vite assets
#
# Plugin Vite wayfinder menjalankan `php artisan wayfinder:generate`
# saat `npm run build`, jadi PHP + Laravel harus ada di stage yang sama
# dengan build frontend.
########################################
FROM dunglas/frankenphp:1-php${PHP_VERSION}-alpine AS build

RUN apk add --no-cache nodejs npm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install \
	--no-dev \
	--no-interaction \
	--no-scripts \
	--no-progress \
	--prefer-dist \
	--optimize-autoloader \
	--ignore-platform-req='ext-*' \
	&& php artisan package:discover --ansi
# ↑ Extension PHP baru di-install di stage runtime; stage build cukup
#   menghasilkan vendor/ dan public/build/.

# Env khusus build supaya app bisa boot (mis. untuk wayfinder:generate).
# Tidak ikut ke image runtime (hanya vendor/ dan public/build/ yang dicopy).
RUN cp .env.example .env \
	&& php artisan key:generate --ansi

RUN npm ci && npm run build

########################################
# Runtime (FrankenPHP, single container) — stage terakhir = target default
########################################
FROM dunglas/frankenphp:1-php${PHP_VERSION}-alpine AS runtime

# Dideteksi install.php (driver DB + ext-* dari composer.lock + paket gambar/PDF).
RUN install-php-extensions \
	bcmath \
	gd \
	opcache \
	pcntl \
	pdo_mysql \
	zip

WORKDIR /app

COPY . .
COPY --from=build /app/vendor ./vendor
COPY --from=build /app/public/build ./public/build

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
	&& mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
	&& chown -R www-data:www-data storage bootstrap/cache

# Traefik yang pegang TLS di VPS; container ini cuma serve HTTP polos
# di network Docker internal.
ENV SERVER_NAME=:80

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
