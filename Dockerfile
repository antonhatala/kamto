# Kamto — vícestupňový build. Lokální dev jede na stage `development` (zdroj se bind-mountuje
# přes něj, viz docker-compose.yml `target: development`); produkční image pro Bunny Magic
# Containers (Fáze 6) je stage `production` — zdroj, vendor i buildnuté CSS zapečené uvnitř
# (`docker build --target production`, viz .github/workflows/deploy.yml). Stage `base` sdílí obě větve, takže
# runtime (PHP, ini, libSQL hook) je v dev i produkci identický.

# ---- Stage: assets — Tailwind CSS build (jen buildtime, do runtime image nejde node) ----
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
# Tailwind v4 skenuje utility třídy ze šablon (@source "../../app" v src/css/app.css).
COPY src ./src
COPY app ./app
RUN npm run css   # → www/css/app.css (minified)

# ---- Stage: vendor — Composer, jen produkční závislosti ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# --no-dev: bez PHPStan/Testeru; --no-scripts: skripty (phpstan/tester) se v produkci nespouští.
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist

# ---- Base runtime — sdílený dev i produkcí ----
# Nette tvrdě vyžaduje ctype, tokenizer, json, session, simplexml, mbstring, iconv, fileinfo —
# všechny už v base image jsou (ověřeno `php -m`). pdo_sqlite/sqlite3 taky → PdoSqliteDb funguje.
FROM php:8.5-fpm AS base

# Base image nemá aktivní php.ini (jen *-development/-production šablony) — notably
# output_buffering=Off, což rozbije Nette/Latte session ini ("headers already sent"). Baseline
# je PRODUKČNÍ šablona (display_errors=Off, expose_php=Off, output_buffering=4096): stejný image
# jde na Bunny, musí být bezpečný by default — a dev nic neztrácí, Tracy renderuje chyby sám,
# když APP_ENV != production (viz app/Bootstrap.php). Bezpečnostní direktivy jsou navíc pinnuté
# v docker/php.ini, ať tiše neregresují, kdyby se šablona někdy změnila.
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Kamto overrides + hardening (timezone, display_errors, output_buffering, ...).
COPY docker/php.ini /usr/local/etc/php/conf.d/kamto.ini

# libSQL extension (tursodatabase/turso-client-php) — STÁLE ODLOŽENO, stav k 2026-07-08:
# poslední release `turso-php-extension-v1.6.2` (2025-07-07) má artefakty jen pro PHP 8.1–8.4
# a Linux jen x86_64 (žádný aarch64). Pro PHP 8.5 tedy pořád není co instalovat; appka jede na
# PdoSqliteDb (DATABASE_DRIVER=pdo-sqlite) a LibsqlDb má runtime guard. Cesty pro Bunny, až přijde
# na řadu: (a) artefakt pro 8.5 vyjde → build s --build-arg LIBSQL_EXT_URL=<url .tar.gz> níže;
# (b) pure-PHP HTTP klient proti Turso/libSQL (nová Db implementace, žádná extension); (c) VPS
# + lokální SQLite (PdoSqliteDb beze změny, ale potřebuje perzistentní volume — Magic Containers
# jsou stateless). Volba závisí na tom, co Bunny reálně nabízí (řeší se s uživatelem ve Fázi 6).
# Tarball obsahuje jeden adresář s `liblibsql_php.so` + PHP stubs (ověřeno na 8.4 artefaktu).
# Default "" nic nestahuje: build je deterministický a kvůli libSQL nikdy nespadne.
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

# ---- Dev target — lokální docker-compose přes něj bind-mountuje zdroj i vendor ----
FROM base AS development

# ---- Produkční target — self-serving image (nginx + php-fpm), Bunny Magic Containers ----
# Magic Containers routují HTTP na port kontejneru, takže image musí sám servírovat HTTP — ne jen
# FastCGI :9000. Proto tu (jen v produkci, ne v base/dev) přibývá nginx + supervisord; hardened
# vhost (docroot www/) cestuje s artefaktem, ne jen s dev compose.
FROM base AS production

# nginx + supervisor jen pro produkční image (dev používá samostatný nginx compose kontejner).
RUN apt-get update \
	&& apt-get install -y --no-install-recommends nginx supervisor \
	&& rm -rf /var/lib/apt/lists/*

COPY docker/nginx.prod.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/kamto.conf
# fpm pool override — bind jen na loopback (nginx je ve stejném kontejneru). Jen produkce:
# dev potřebuje fpm na 0.0.0.0 pro cross-container přístup. Viz docker/php-fpm.prod.conf.
COPY docker/php-fpm.prod.conf /usr/local/etc/php-fpm.d/zz-kamto.conf
COPY docker/entrypoint.prod.sh /usr/local/bin/entrypoint.prod.sh
RUN chmod +x /usr/local/bin/entrypoint.prod.sh

# COPY respektuje .dockerignore (bez vendoru, node_modules, tajností, testů, git historie).
# bin/ + migrations/ se do image DOSTANOU (migrace pouští entrypoint při startu proti volume DB).
COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=assets --chown=www-data:www-data /app/www/css/app.css /var/www/html/www/css/app.css
# Runtime adresáře musí být zapisovatelné workery FPM (běží jako www-data). var/ je typicky mount
# perzistentního volume — entrypoint po migraci srovná vlastnictví ještě jednou za běhu.
RUN chown -R www-data:www-data /var/www/html/temp /var/www/html/log /var/www/html/var

# Unprivileged HTTP port (nginx nepotřebuje root pro bind). Na Bunny se sem namapuje HTTP služba.
EXPOSE 8080

# Liveness: 302 z / prochází nginxem i fpm (curl -f bere <400 jako OK). Když si Bunny dělá vlastní
# HTTP probing, je to redundantní, ale neškodí (curl je v image).
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s \
	CMD curl -fsS http://127.0.0.1:8080/ >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.prod.sh"]
