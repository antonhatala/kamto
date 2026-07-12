#!/bin/sh
# Kamto — produkční entrypoint (Bunny Magic Containers). Před startem serverů zmigruje DB na
# perzistentním volume (mount → /var/www/html/var). Migrace jsou idempotentní (_migration tabulka)
# a při 1 replice (single-user, žádné škálování) bez race. Volba varianty A: DATABASE_DRIVER
# prázdný/pdo-sqlite → SQLite soubor na volume, žádný libSQL.
set -eu

APP_DIR=/var/www/html

# migrate.php běží jako root (entrypoint) → vytvoří var/kamto.db i zkompiluje DI kontejner do
# temp/cache jako root. fpm workeři (www-data) by pak do var/ ani temp/cache nezapsali (Nette si
# tvoří .lock při čtení kontejneru) → 500. Proto po migraci srovnat vlastnictví všech zapisovatelných
# adresářů. temp/cache zůstane zkompilovaný (shodný config hash) → fpm ho rovnou převezme, bez rekompilace.
php "$APP_DIR/bin/migrate.php"
chown -R www-data:www-data "$APP_DIR/var" "$APP_DIR/temp" "$APP_DIR/log"

# Předání řízení supervisoru (php-fpm + nginx) jako PID 1 (správné doručení signálů z Bunny).
exec supervisord -c /etc/supervisor/conf.d/kamto.conf
