#!/bin/bash
set -e

echo "🔧 Running init.sh for php..."

# Ensure log directory and file exist
mkdir -p /var/log
touch /var/log/bandwidth.log
chown www-data:www-data /var/log/bandwidth.log

# Setup bandwidth measurement cron job
CRON_JOB="0 2 * * * /var/www/html/measure-bandwidth.sh > /var/log/bandwidth.log 2>&1"
echo "$CRON_JOB" > /etc/cron.d/measure-bandwidth
chmod 0644 /etc/cron.d/measure-bandwidth
crontab /etc/cron.d/measure-bandwidth

# Start cron service
service cron start
echo "✅ Cron service started."

# Start PHP-FPM in foreground to keep container running
echo "✅ Starting PHP-FPM in foreground..."
php-fpm --nodaemonize
