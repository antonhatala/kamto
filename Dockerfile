FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY src ./src
COPY app ./app
RUN npm run css

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist

FROM php:8.5-fpm AS base

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

COPY docker/php.ini /usr/local/etc/php/conf.d/kamto.ini

ARG LIBSQL_EXT_URL=""
RUN if [ -n "$LIBSQL_EXT_URL" ]; then \
		set -eux; \
		curl -fsSL "$LIBSQL_EXT_URL" -o /tmp/libsql.tar.gz; \
		mkdir /tmp/libsql; \
		tar -xzf /tmp/libsql.tar.gz -C /tmp/libsql --strip-components=1; \
		cp /tmp/libsql/liblibsql_php.so "$(php-config --extension-dir)/"; \
		echo 'extension=liblibsql_php.so' > /usr/local/etc/php/conf.d/libsql.ini; \
		rm -rf /tmp/libsql /tmp/libsql.tar.gz; \
		php -r 'exit(class_exists(LibSQL::class) ? 0 : 1);'; \
	fi

WORKDIR /var/www/html

FROM base AS development

FROM base AS production

RUN apt-get update \
	&& apt-get install -y --no-install-recommends nginx supervisor \
	&& rm -rf /var/lib/apt/lists/*

COPY docker/nginx.prod.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/kamto.conf
COPY docker/php-fpm.prod.conf /usr/local/etc/php-fpm.d/zz-kamto.conf
COPY docker/entrypoint.prod.sh /usr/local/bin/entrypoint.prod.sh
RUN chmod +x /usr/local/bin/entrypoint.prod.sh

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=assets --chown=www-data:www-data /app/www/css/app.css /var/www/html/www/css/app.css
RUN chown -R www-data:www-data /var/www/html/temp /var/www/html/log /var/www/html/var

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s \
	CMD curl -fsS http://127.0.0.1:8080/ >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.prod.sh"]
