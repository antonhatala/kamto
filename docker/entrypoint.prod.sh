#!/bin/sh
set -eu

APP_DIR=/var/www/html

php "$APP_DIR/bin/migrate.php"

mkdir -p "$APP_DIR/var/sessions"

chown -R www-data:www-data "$APP_DIR/var" "$APP_DIR/temp" "$APP_DIR/log"

exec supervisord -c /etc/supervisor/conf.d/kamto.conf
