#!/usr/bin/env bash
# Saves an AI key in .env without it ever being shown on screen. Run it yourself on the server:
#   bash ~/lms/backend/tools/set-ai-key.sh              (Google Vertex AI key, the default)
#   bash ~/lms/backend/tools/set-ai-key.sh GEMINI_API_KEY   (or OPENAI_API_KEY, ANTHROPIC_API_KEY)
set -e
cd "$(dirname "$0")/.."
NAME="${1:-VERTEX_API_KEY}"
case "$NAME" in VERTEX_API_KEY|GEMINI_API_KEY|OPENAI_API_KEY|ANTHROPIC_API_KEY) ;; *) echo "Unknown key name $NAME"; exit 1;; esac
read -r -s -p "Paste the $NAME and press Enter (nothing is shown): " KEY; echo
KEY="$(printf '%s' "$KEY" | tr -d '[:space:]')"
[ -n "$KEY" ] || { echo "Nothing entered."; exit 1; }
sed -i "/^$NAME=/d" .env
printf '%s=%s\n' "$NAME" "$KEY" >> .env
/opt/alt/php83/usr/bin/php artisan config:cache >/dev/null
echo "Saved $NAME and refreshed the settings. Now try 'Parse PDF with AI' in the admin panel."
