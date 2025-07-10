#!/bin/bash
set -e

echo "🌐 Welcome to the UseMolo Node Installer"
echo

# Check and install jq
if ! command -v jq &> /dev/null; then
  echo "⚙️ jq not found. Installing..."
  apt update && apt install -y jq
  echo "✅ jq installed."
else
  echo "✅ jq found."
fi

# Check and install docker
if ! command -v docker &> /dev/null; then
  echo "⚙️ Docker not found. Installing..."
  apt update && apt install -y docker.io
  systemctl enable docker
  systemctl start docker
  echo "✅ Docker installed."
else
  echo "✅ Docker found."
fi

# Check and install docker-compose
if ! command -v docker-compose &> /dev/null; then
  echo "⚙️ docker-compose not found. Installing..."
  apt update && apt install -y docker-compose
  echo "✅ docker-compose installed."
else
  echo "✅ docker-compose found."
fi

# Load existing .env if present
if [ -f .env ]; then
  echo "📄 Found existing .env file. Loading values..."
  source .env
  echo
  echo "Current configuration:"
  echo "  Node Name     : ${NODE_NAME}"
  echo "  Domain (URL)  : ${NODE_URL}"
  echo "  Wallet Address: ${REWARD_ADDRESS:-Not Set}"
  echo

  read -p "⚠️  Do you want to overwrite this .env file? (y/N): " confirm
  if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo "✅ Keeping existing .env file."
    skip_env=true
    NODE_SECRET="$SECRET"
  else
    echo "✏️  Overwriting .env file..."
  fi
fi

if [ "$skip_env" != true ]; then
  read -p "📍 Node Name (max 60 chars) [${NODE_NAME:-}]: " input
  NODE_NAME=${input:-$NODE_NAME}

  read -p "🌐 Domain (A/AAAA record required) [${NODE_URL:-}]: " input
  NODE_URL=${input:-$NODE_URL}

  read -p "💸 Ethereum/ERC20 Wallet Address (optional) [${REWARD_ADDRESS:-}]: " input
  REWARD_ADDRESS=${input:-$REWARD_ADDRESS}

  if [ -z "$SECRET" ]; then
    NODE_SECRET=$(openssl rand -hex 16)
  else
    NODE_SECRET="$SECRET"
  fi

  cat <<EOF > .env
NODE_NAME="$NODE_NAME"
NODE_URL="$NODE_URL"
REWARD_ADDRESS="$REWARD_ADDRESS"
SECRET="$NODE_SECRET"
EOF

  echo "✅ New .env file created."
fi

echo
echo "📡 Registering node with UseMolo.com..."

payload="{
  \"name\": \"$NODE_NAME\",
  \"url\": \"https://$NODE_URL\",
  \"secret\": \"$NODE_SECRET\""

if [ -n "$REWARD_ADDRESS" ]; then
  payload+=", \"reward_address\": \"$REWARD_ADDRESS\""
fi

payload+="}"

response=$(curl -s -X POST https://usemolo.com/api/nodes/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$payload")

success=$(echo "$response" | jq -r '.success')
message=$(echo "$response" | jq -r '.message')

echo
echo "📝 Server response: $message"

if [[ "$success" != "true" ]]; then
  echo "❌ Node registration failed. Aborting installation."
  exit 1
fi

# Generate updated configs
echo
echo "⚙️  Updating Caddyfile and Nginx configs..."

cat <<EOCADDY > ./caddy/Caddyfile
$NODE_URL {
  reverse_proxy molo_nginx:80
}
EOCADDY

echo "✅ Caddyfile updated."

cat <<EONGINX > ./nginx/conf.d/default.conf
server {
    listen 80;
    server_name $NODE_URL;
    root /var/www/html;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass molo_php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }

    location /transmission/ {
        proxy_pass http://molo_transmission:9091/transmission/;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    }
}
EONGINX

echo "✅ Nginx config updated."

# Ask if user wants to rebuild
echo
read -p "🔁 Do you want to rebuild Docker images from scratch? (y/N): " do_rebuild

if [[ "$do_rebuild" =~ ^[Yy]$ ]]; then
  echo "🧹 Stopping and cleaning previous Docker containers and volumes..."
  docker-compose down -v
  docker container prune -f
  docker volume prune -f
  docker network prune -f
  echo "🔨 Rebuilding Docker images..."
  docker-compose build --no-cache
else
  echo "🚀 Skipping rebuild. Starting containers normally..."
fi

echo "🔧 Making scripts executable..."
chmod +x ./app/measure-bandwidth.sh
chmod +x ./app/yabs.sh
sudo chown www-data:www-data ./app/bandwidth.json
sudo chmod 664 ./app/bandwidth.json
chmod +x ./nginx/init.sh
chmod +x ./php/init.sh

# Start containers
echo
echo "🚀 Starting Docker containers..."
docker-compose up -d

sleep 2
docker-compose stop transmission
sleep 2 
echo "🛠️  Deleting old Transmission settings.json"
rm -rf ./transmission_config/settings.json
sleep 2
echo "🛠️  Generating updated Transmission settings.json"

# Copy and replace placeholder every time
sed "s/___SECRET___/$SECRET/g" ./transmission_config/settings.json.template > ./transmission_config/settings.json

echo "✅ settings.json created with updated RPC secret."
docker-compose start transmission

echo
echo "✅ Node '$NODE_NAME' is now live at: https://$NODE_URL"
