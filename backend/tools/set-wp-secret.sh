#!/usr/bin/env bash
# Makes the secret that links the LMS with the WordPress site, saves it in .env and shows it ONCE so you can paste it
# into WordPress (Settings > LMS link). Run it yourself on the server:  bash ~/lms/backend/tools/set-wp-secret.sh
# Running it again makes a NEW secret (then paste the new one into WordPress too).
set -e
cd "$(dirname "$0")/.."
SECRET="$(head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 48)"
sed -i '/^LMS_WP_SECRET=/d' .env
printf '\nLMS_WP_SECRET=%s\n' "$SECRET" >> .env
/opt/alt/php83/usr/bin/php artisan config:cache >/dev/null
echo
echo "Done. Paste this into WordPress > Settings > LMS link > Shared secret (then clear your screen):"
echo
echo "    $SECRET"
echo
