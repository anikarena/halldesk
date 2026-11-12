#!/bin/bash
# Put a new release live (run as root on the server).
# Expects /opt/hall-upload/hall.tgz made by deploy/deploy.ps1.
# Keeps .env, the database and storage between releases.
set -euo pipefail

APP=/opt/hall
SRC=/opt/hall-upload/release

rm -rf "$SRC" && mkdir -p "$SRC"
tar -xzf /opt/hall-upload/hall.tgz -C "$SRC"

rsync -a --delete \
    --exclude=.env --exclude=/vendor/ --exclude=/storage/ \
    --exclude='/database/*.db' --exclude='/database/*.db-journal' \
    "$SRC"/ "$APP"/

mkdir -p "$APP"/storage/{app/public,framework/{cache/data,sessions,views},logs} "$APP"/bootstrap/cache
chown -R www-data:www-data "$APP"/storage "$APP"/bootstrap/cache "$APP"/database
cd "$APP"

# First release: production settings, a new app key and a random demo password
if [ ! -f .env ]; then
    cp .env.example .env
    sed -i \
        -e 's/^APP_ENV=.*/APP_ENV=production/' \
        -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
        -e 's#^APP_URL=.*#APP_URL=https://hall.saveio.online#' \
        -e 's/^LOG_LEVEL=.*/LOG_LEVEL=warning/' \
        -e 's/^CACHE_STORE=.*/CACHE_STORE=file/' \
        -e 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' \
        -e 's/^HALL_DEMO_LOGINS=.*/HALL_DEMO_LOGINS=false/' \
        -e "s/^HALL_DEMO_PASSWORD=.*/HALL_DEMO_PASSWORD=$(openssl rand -hex 6)/" \
        .env
    echo "DB_DATABASE=$APP/database/hall.db" >> .env
    NEW_ENV=1
fi

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --quiet
if [ "${NEW_ENV:-0}" = "1" ]; then
    php artisan key:generate --force
fi
# Push alerts: make the key pair once and keep it, new keys would cut off every device
if ! grep -q '^HALL_PUSH_PUBLIC_KEY=.' .env; then
    sed -i '/^HALL_PUSH_\(PUBLIC\|PRIVATE\)_KEY=/d' .env
    php artisan push:keys | grep '^HALL_PUSH_' >> .env
fi
chown root:www-data .env && chmod 640 .env
# Anything the root steps above wrote must stay writable for the web server
chown -R www-data:www-data storage bootstrap/cache database

run() { sudo -u www-data php artisan "$@"; }

if [ ! -f "$APP/database/hall.db" ]; then
    sudo -u www-data touch "$APP/database/hall.db"
    FIRST=1
fi

if [ "${SEED:-0}" = "1" ]; then
    # Explicit request: wipe everything and load the demo hall again
    run migrate:fresh --seed --force
elif [ "${FIRST:-0}" = "1" ]; then
    run migrate --seed --force
else
    run migrate --force
fi
run optimize
systemctl reload php8.3-fpm

# Ask AI's rulebook search and the hall office bot pick up the new release
run hall:knowledge || true
systemctl restart hall-telegram 2>/dev/null || true

echo "release live: $(cat "$SRC/REVISION" 2>/dev/null || echo unknown)"
