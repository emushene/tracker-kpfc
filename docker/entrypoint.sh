#!/bin/sh
set -e

# Synchronize fresh public build assets to shared volume if mounted
if [ -d "/var/www/html/public-template" ]; then
    echo "Synchronizing public build assets to shared volume..."
    cp -a /var/www/html/public-template/. /var/www/html/public/
fi

exec "$@"
