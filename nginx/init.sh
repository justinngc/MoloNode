#!/bin/bash
set -e

echo "🌀 Rendering Nginx config from template..."

# Replace ${NODE_URL} using envsubst
envsubst '${NODE_URL}' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf

echo "✅ Starting Nginx..."
nginx -g 'daemon off;'
