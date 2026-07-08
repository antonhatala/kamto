# Kamto — PHP-FPM image. One image for local dev (docker compose, source bind-mounted
# over it) and later Bunny Magic Containers (Fáze 6, where the source is copied in at
# build time). Kept intentionally simple for Fáze 0 — no multi-stage build.
#
# Nette's hard extension requirements (ctype, tokenizer, json, session, simplexml,
# mbstring, iconv, fileinfo) already ship in this base image — verified with `php -m`.
# pdo_sqlite/sqlite3 are present too (→ PdoSqliteDb works out of the box).
FROM php:8.5-fpm

# The base image ships no active php.ini at all (only the *-development/-production
# templates) — notably output_buffering=Off. That breaks Nette/Latte: the template
# starts streaming output before Http\Session can still touch session ini settings,
# producing "ini_set(): Session ini settings cannot be changed after headers have
# already been sent". Baseline is the PRODUCTION template (display_errors=Off,
# expose_php=Off, output_buffering=4096): the same image goes to Bunny in Fáze 6, so it
# must be safe by default — and local dev loses nothing, Tracy renders errors itself
# whenever APP_ENV != production (see app/Bootstrap.php). The security-relevant
# directives are additionally pinned in docker/php.ini so they don't silently regress
# if this template choice ever changes.
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Kamto overrides + hardening (timezone, display_errors, output_buffering, ...).
COPY docker/php.ini /usr/local/etc/php/conf.d/kamto.ini

# libSQL extension (tursodatabase/turso-client-php) — ODLOŽENO, stav k 2026-07-07:
# poslední release `turso-php-extension-v1.6.2` (2025-07-07) má artefakty jen pro
# PHP 8.1–8.4 a Linux buildy jen x86_64 (žádný aarch64 → na Apple Silicon by lokálně
# nešel ani ten). Pro PHP 8.5 tedy není co instalovat; appka jede na PdoSqliteDb
# (config.neon database.driver: pdo-sqlite) a LibsqlDb má runtime guard.
# Až artefakt pro 8.5 vyjde (https://github.com/tursodatabase/turso-client-php/releases),
# stačí build s --build-arg LIBSQL_EXT_URL=<url .tar.gz artefaktu> — tarball obsahuje
# jeden adresář s `liblibsql_php.so` + PHP stubs (ověřeno na 8.4 artefaktu). Default ""
# nic nestahuje: build je deterministický a kvůli libSQL nikdy nespadne.
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
