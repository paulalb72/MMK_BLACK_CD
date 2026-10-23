#!/bin/sh
set -e
# Datenordner (in Coolify als Volume eingehängt) für Apache beschreibbar machen
for d in /var/www/html/api/daten /var/www/html/statistik/daten; do
  mkdir -p "$d"
  chown -R www-data:www-data "$d"
done
exec docker-php-entrypoint "$@"
