# Kamto — PHP-FPM image. One image for local dev (docker compose, source bind-mounted
# over it) and later Bunny Magic Containers (Fáze 6, where the source is copied in at
# build time). Kept intentionally simple for Fáze 0 — no multi-stage build.
#
# Nette's hard extension requirements (ctype, tokenizer, json, session, simplexml,
# mbstring, iconv, fileinfo) already ship in this base image — verified with `php -m`.
# pdo_sqlite/sqlite3 are present too; the libSQL extension needed for Fáze 1 is not
# addressed here.
FROM php:8.5-fpm

# The base image ships no active php.ini at all (only the *-development/-production
# templates) — notably output_buffering=Off. That breaks Nette/Latte: the template
# starts streaming output before Http\Session can still touch session ini settings,
# producing "ini_set(): Session ini settings cannot be changed after headers have
# already been sent". The development template (output_buffering=4096, display_errors
# on, ...) is the right baseline here; Tracy takes over error display regardless.
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

# Europe/Prague throughout the app (CLAUDE.md) — avoids PHP date() falling back to UTC.
COPY docker/php.ini /usr/local/etc/php/conf.d/kamto.ini

WORKDIR /var/www/html
