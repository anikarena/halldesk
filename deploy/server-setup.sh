#!/bin/bash
# One-time server setup for HallDesk on Ubuntu 24.04 (run as root).
# Installs PHP 8.3 FPM with the extensions Laravel needs, keeps PHP-FPM light
# (processes start on demand), and enables the nginx site.
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath unzip rsync composer >/dev/null

# Start PHP workers only when requests come in, at most 4, so idle RAM stays near zero
POOL=/etc/php/8.3/fpm/pool.d/www.conf
sed -i 's/^pm = .*/pm = ondemand/' "$POOL"
sed -i 's/^pm.max_children = .*/pm.max_children = 4/' "$POOL"
sed -i 's/^;\?pm.process_idle_timeout = .*/pm.process_idle_timeout = 30s/' "$POOL"
sed -i 's/^;\?pm.max_requests = .*/pm.max_requests = 500/' "$POOL"

# Production PHP settings
INI=/etc/php/8.3/fpm/conf.d/99-hall.ini
cat > "$INI" <<'EOF'
expose_php = Off
memory_limit = 256M
upload_max_filesize = 2M
post_max_size = 4M
opcache.enable = 1
opcache.memory_consumption = 64
opcache.validate_timestamps = 1
opcache.revalidate_freq = 60
date.timezone = Asia/Dhaka
EOF

systemctl enable --now php8.3-fpm
systemctl restart php8.3-fpm

mkdir -p /opt/hall /opt/hall-upload
install -m 644 /opt/hall-upload/nginx-hall.conf /etc/nginx/sites-available/hall
ln -sf /etc/nginx/sites-available/hall /etc/nginx/sites-enabled/hall
nginx -t
systemctl reload nginx

php -v | head -1
echo "server setup done"
