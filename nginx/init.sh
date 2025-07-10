#!/bin/bash
set -e


chown www-data:www-data /var/www/html
chmod 755 /var/www/html

echo "🌀 Rendering Nginx config from template..."

# Replace ${NODE_URL} using envsubst
envsubst '${NODE_URL}' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf

echo "✅ Starting Nginx..."
nginx -g 'daemon off;'
