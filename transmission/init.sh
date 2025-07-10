#!/bin/bash

# Ensure Transmission data and config dirs are owned by the abc user
chown -R abc:abc /data/completed
chown -R abc:abc /config

# Set permissions: directories are 755, files are 644
find /data/completed -type d -exec chmod 755 {} +
find /data/completed -type f -exec chmod 644 {} +

find /config -type d -exec chmod 755 {} +
find /config -type f -exec chmod 644 {} +

# Optional: allow write access for group, if you need it (uncomment to use)
# chmod -R 775 /data/completed
# chmod -R 775 /config

echo "Permissions set for /data/completed and /config"

# Run Transmission or your CMD as normal, e.g.:
# exec su abc -c "/usr/bin/transmission-daemon -g /config -f"
