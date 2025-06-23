#!/bin/bash

# Load .env if exists
if [ -f "/var/www/html/.env" ]; then
  export $(grep -v '^#' /var/www/html/.env | xargs)
fi

# Start PHP-FPM with SECRET in env
echo "env[SECRET] = $SECRET" >> /etc/php/8.1/fpm/pool.d/www.conf
service php8.1-fpm start

# Wait for PHP socket to exist
while [ ! -S /run/php/php8.1-fpm.sock ]; do
  echo "Waiting for PHP-FPM socket..."
  sleep 0.5
done

# Start Nginx
service nginx start

# Set rpc-password in Transmission config
SETTINGS_FILE="/var/lib/transmission-daemon/info/settings.json"

if [ -n "$SECRET" ] && [ -f "$SETTINGS_FILE" ]; then
  echo " ^=^t^p Setting plain RPC password (Transmission will hash it)..."

  jq --arg pwd "$SECRET" '
    .["rpc-authentication-required"] = true |
    .["rpc-username"] = "node" |
    .["rpc-password"] = $pwd
  ' "$SETTINGS_FILE" > /tmp/settings.json && mv /tmp/settings.json "$SETTINGS_FILE"
fi

# Start Transmission
transmission-daemon -f -g /var/lib/transmission-daemon/info &

# Start Caddy
caddy run --config /etc/caddy/Caddyfile --adapter caddyfile &

# Setup bandwidth measurement cron job
CRON_JOB="0 2 * * * /var/www/html/requests/measure-bandwidth.sh >> /var/log/bandwidth.log 2>&1"
echo "$CRON_JOB" > /etc/cron.d/measure-bandwidth
chmod 0644 /etc/cron.d/measure-bandwidth
crontab /etc/cron.d/measure-bandwidth
chmod +x /var/www/html/requests/yabs.sh
chmod +x /var/www/html/requests/bandwidth.json
service cron start
echo "Running initial bandwidth measurement..."
/var/www/html/requests/measure-bandwidth.sh

# Fix file permissions
chown -R www-data:www-data /var/www/html/files
chmod -R 775 /var/www/html/files
chmod -R 755 /var/lib/transmission-daemon

mkdir -p /var/log
touch /var/log/bandwidth.log

# Keep container alive
tail -f /dev/null
