#!/bin/bash

# Set output paths
YABS_SCRIPT="/var/www/html/requests/yabs.sh"
YABS_BANDWIDTH="/var/www/html/requests/bandwidth.json"

echo "🚀 Running YABS iperf3 test only..."
chmod +x "$YABS_SCRIPT"
"$YABS_SCRIPT" -f -g -n -w "$YABS_BANDWIDTH"

# Check if yabs output exists
if [ ! -f "$YABS_BANDWIDTH" ]; then
  echo "❌ YABS did not generate bandwidth.json. Exiting."
  exit 1
fi

# Extract max upload and download values
UPLOAD=$(jq -r '[.iperf[].send | sub(" Gbits/sec"; "") | sub(" Mbits/sec"; "") | tonumber * (if test("Gbits") then 1000 else 1 end)] | max' "$YABS_BANDWIDTH")
DOWNLOAD=$(jq -r '[.iperf[].recv | sub(" Gbits/sec"; "") | sub(" Mbits/sec"; "") | tonumber * (if test("Gbits") then 1000 else 1 end)] | max' "$YABS_BANDWIDTH")

UPLOAD=${UPLOAD:-0}
DOWNLOAD=${DOWNLOAD:-0}

echo "📥 Upload max: $UPLOAD Mbps"
echo "📤 Download max: $DOWNLOAD Mbps"


echo "✅ Saved to $OUTPUT_FILE"
