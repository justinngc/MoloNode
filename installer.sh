#!/bin/bash
set -e  # Exit on any error

echo "🌐 Welcome to the 5GB Node Installer"
echo

# 1. Load existing .env if present
if [ -f .env ]; then
  echo "📦 Found existing .env file. Loading values..."
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

    # Ensure SECRET is mapped properly
    NODE_SECRET="$SECRET"
  else
    echo "✏️  Overwriting .env file..."
  fi
fi

# 2. Ask for values if not skipping
if [ "$skip_env" != true ]; then
  read -p "📍 Node Name (max 60 chars) [${NODE_NAME:-}]: " input
  NODE_NAME=${input:-$NODE_NAME}

  read -p "🌐 Domain (A/AAAA record required) [${NODE_URL:-}]: " input
  NODE_URL=${input:-$NODE_URL}

  read -p "💸 Ethereum/ERC20 Wallet Address (optional) [${REWARD_ADDRESS:-}]: " input
  REWARD_ADDRESS=${input:-$REWARD_ADDRESS}

  # 3. Generate new secret only if one doesn't already exist
  if [ -z "$SECRET" ]; then
    NODE_SECRET=$(openssl rand -hex 16)
  else
    NODE_SECRET="$SECRET"
  fi

  # 4. Write new .env
  cat <<EOF > .env
NODE_NAME="$NODE_NAME"
NODE_URL="$NODE_URL"
REWARD_ADDRESS="$REWARD_ADDRESS"
SECRET="$NODE_SECRET"
EOF

  echo "✅ New .env file created."
fi

# 5. Register the node
echo
echo "📡 Registering node with 5GB.io..."

# Build JSON payload
payload="{
  \"name\": \"$NODE_NAME\",
  \"url\": \"https://$NODE_URL\",
  \"secret\": \"$NODE_SECRET\""

if [ -n "$REWARD_ADDRESS" ]; then
  payload+=", \"reward_address\": \"$REWARD_ADDRESS\""
fi

payload+="}"

# Send registration request
response=$(curl -s -X POST https://usemolo.com/api/nodes/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$payload")

echo
echo "📝 Registration response:"
echo "$response"

# Abort if registration failed
if ! echo "$response" | grep -q '"success":true'; then
  echo "❌ Node registration failed. Aborting installation."
  exit 1
fi

# 6. Ask if user wants to fully rebuild Docker
echo
read -p "🔁 Do you want to rebuild the Docker image from scratch? (y/N): " do_rebuild

if [[ "$do_rebuild" =~ ^[Yy]$ ]]; then
  echo "🧹 Cleaning up previous Docker containers and volumes..."
  docker-compose down
  docker container prune -f
  echo "🔨 Rebuilding Docker image from scratch..."
  docker-compose build --no-cache
else
  echo "🚀 Skipping rebuild. Starting containers normally..."
fi

# 7. Start containers
echo
echo "🚀 Starting Docker containers..."
docker-compose up -d

echo
echo "✅ Node '$NODE_NAME' is now live at: https://$NODE_URL"
