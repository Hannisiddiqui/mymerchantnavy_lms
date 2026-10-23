#!/usr/bin/env bash
# One-shot installer for the LMS on an Ubuntu 24.04 VPS that already hosts other apps.
#
# It only ADDS things: its own folder (/var/www/lms), database, PHP pool, nginx site files, services (lms-*)
# and a student-site port (3010). Existing nginx sites, ports and databases are never edited.
#
#   BASE_DOMAIN=example.com REPO_URL=https://github.com/Chota-Thanos/Merchant-Navy.git sudo -E bash setup-vps.sh
#
# Hosts default to lms-api.$BASE_DOMAIN, lms-admin.$BASE_DOMAIN and lms.$BASE_DOMAIN (override API_HOST/ADMIN_HOST/APP_HOST).
# TEST_MODE=1 (default) runs as APP_ENV=staging with the fixed OTP 123456 -- for private testing only.
# TEST_MODE=0 runs as production and needs real Twilio keys in backend/.env afterwards.
set -euo pipefail

: "${BASE_DOMAIN:?Set BASE_DOMAIN, e.g. example.com}"
: "${REPO_URL:?Set REPO_URL (add a token in the URL if the repo is private)}"
API_HOST="${API_HOST:-lms-api.$BASE_DOMAIN}"
ADMIN_HOST="${ADMIN_HOST:-lms-admin.$BASE_DOMAIN}"
APP_HOST="${APP_HOST:-lms.$BASE_DOMAIN}"
TEST_MODE="${TEST_MODE:-1}"
APP_DIR=/var/www/lms
PHP_SOCK=/run/php/lms-fpm.sock
CREDS=/root/lms-credentials.txt
export COMPOSER_ALLOW_SUPERUSER=1

say() { printf '\n==> %s\n' "$*"; }

# ---------- 0. safety checks ----------
[ "$(id -u)" -eq 0 ] || { echo "Run as root"; exit 1; }
command -v php >/dev/null || { echo "PHP is missing. Run the step-1 install first."; exit 1; }
[ -x /opt/lms-venv/bin/python ] || { echo "PDF reader missing. Run the step-1 install first."; exit 1; }
nginx -t >/dev/null 2>&1 || { echo "nginx config is not valid before we start; fix that first."; exit 1; }
if ss -tln | grep -q ':3010 '; then echo "Port 3010 is already in use; edit PORT in this script."; exit 1; fi
for h in "$API_HOST" "$ADMIN_HOST" "$APP_HOST"; do
  ip=$(getent hosts "$h" | awk '{print $1}' | head -1 || true)
  [ -n "$ip" ] || { echo "DNS for $h does not resolve yet. Create the A records first."; exit 1; }
done

apt-get install -y -q fonts-dejavu-core poppler-utils >/dev/null 2>&1 || true

# ---------- 1. code ----------
say "Downloading the code"
if [ -d "$APP_DIR/.git" ]; then
  git -C "$APP_DIR" pull --ff-only
else
  mkdir -p /var/www && git clone "$REPO_URL" "$APP_DIR"
fi

# ---------- 2. database (its own user + database) ----------
say "Database"
if [ ! -f "$CREDS" ]; then
  DB_PASS=$(openssl rand -hex 16)
  echo "DB_PASSWORD=$DB_PASS" > "$CREDS"; chmod 600 "$CREDS"
  sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE USER lms WITH PASSWORD '$DB_PASS' CONNECTION LIMIT 40;" 2>/dev/null || \
    sudo -u postgres psql -c "ALTER USER lms WITH PASSWORD '$DB_PASS' CONNECTION LIMIT 40;"
  sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='mmp_lms'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE mmp_lms OWNER lms;"
fi
DB_PASS=$(grep '^DB_PASSWORD=' "$CREDS" | cut -d= -f2)

# ---------- 3. backend ----------
say "Backend settings"
if [ "$TEST_MODE" = "1" ]; then APP_ENV_VAL=staging; OTP_LINES=$'LMS_LOGIN_METHOD=email\nOTP_EMAIL_DRIVER=log\nOTP_DRIVER=log\nOTP_FIXED_CODE=123456'; else APP_ENV_VAL=production; OTP_LINES=$'LMS_LOGIN_METHOD=email\nOTP_EMAIL_DRIVER=mail\nOTP_DRIVER=twilio\nTWILIO_SID=\nTWILIO_AUTH_TOKEN=\nTWILIO_FROM='; fi

if [ ! -f "$APP_DIR/backend/.env" ]; then
cat > "$APP_DIR/backend/.env" <<ENV
APP_NAME="My Merchant Navy"
APP_ENV=$APP_ENV_VAL
APP_DEBUG=false
APP_KEY=
APP_URL=https://$API_HOST
ADMIN_DOMAIN=$ADMIN_HOST
STUDENT_APP_URL=https://$APP_HOST
CORS_ALLOWED_ORIGINS=https://$APP_HOST
TRUSTED_PROXIES=*
LOG_CHANNEL=stack
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mmp_lms
DB_USERNAME=lms
DB_PASSWORD=$DB_PASS
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
$OTP_LINES
MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@$BASE_DOMAIN"
MAIL_FROM_NAME="My Merchant Navy"
LMS_MAX_DEVICES=0
LMS_PYTHON=/opt/lms-venv/bin/python
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.8-flash
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5-mini
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-opus-5-5
AI_PROVIDER=auto
ENV
fi

say "Installing backend packages"
cd "$APP_DIR/backend"
composer install --no-dev --optimize-autoloader --no-interaction -q
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan filament:optimize || true

# ---------- 4. student website ----------
say "Student website (this takes a few minutes)"
cd "$APP_DIR/student-app"
printf 'NEXT_PUBLIC_USE_MOCK=false\nNEXT_PUBLIC_LOGIN_METHOD=email\nNEXT_PUBLIC_API_URL=https://%s/api/v1\n' "$API_HOST" > .env.production
npm ci --no-audit --no-fund
npm run build
chown -R www-data:www-data "$APP_DIR/student-app"

# ---------- 5. its own PHP pool (limited workers) ----------
say "PHP pool"
cat > /etc/php/8.3/fpm/pool.d/lms.conf <<POOL
[lms]
user = www-data
group = www-data
listen = $PHP_SOCK
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 8
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 4
pm.max_requests = 500
php_admin_value[memory_limit] = 256M
POOL
php-fpm8.3 -t
systemctl reload php8.3-fpm

# ---------- 6. services with resource caps ----------
say "Services"
cat > /etc/systemd/system/lms-queue.service <<UNIT
[Unit]
Description=LMS queue worker
After=network.target postgresql.service
[Service]
User=www-data
WorkingDirectory=$APP_DIR/backend
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=2 --timeout=3600 --max-time=3600
Restart=always
RestartSec=5
Nice=10
MemoryMax=512M
CPUQuota=50%
[Install]
WantedBy=multi-user.target
UNIT
cat > /etc/systemd/system/lms-student.service <<UNIT
[Unit]
Description=LMS student website
After=network.target
[Service]
User=www-data
WorkingDirectory=$APP_DIR/student-app
Environment=NODE_ENV=production
Environment=PORT=3010
ExecStart=$(command -v npm) run start -- -p 3010
Restart=always
RestartSec=5
Nice=5
MemoryMax=768M
CPUQuota=100%
[Install]
WantedBy=multi-user.target
UNIT
echo "* * * * * www-data cd $APP_DIR/backend && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/lms
systemctl daemon-reload
systemctl enable --now lms-queue lms-student

# ---------- 7. nginx: NEW site files only ----------
say "nginx"
cat > /etc/nginx/sites-available/lms-backend <<NGX
server {
    listen 80;
    server_name $API_HOST $ADMIN_HOST;
    root $APP_DIR/backend/public;
    index index.php;
    client_max_body_size 64M;
    client_body_timeout 300s;
    add_header X-Content-Type-Options "nosniff" always;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300s;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGX
cat > /etc/nginx/sites-available/lms-student <<NGX
server {
    listen 80;
    server_name $APP_HOST;
    add_header X-Content-Type-Options "nosniff" always;
    location / {
        proxy_pass http://127.0.0.1:3010;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
NGX
ln -sf /etc/nginx/sites-available/lms-backend /etc/nginx/sites-enabled/lms-backend
ln -sf /etc/nginx/sites-available/lms-student /etc/nginx/sites-enabled/lms-student
if nginx -t; then
  systemctl reload nginx
else
  rm -f /etc/nginx/sites-enabled/lms-backend /etc/nginx/sites-enabled/lms-student
  echo "nginx test failed; our site files were removed again and nothing was reloaded."; exit 1
fi

# ---------- 8. HTTPS (certbot edits only the server blocks for our hosts) ----------
say "HTTPS certificates"
certbot --nginx --non-interactive --agree-tos --register-unsure-about-email \
  -d "$API_HOST" -d "$ADMIN_HOST" --cert-name lms-backend --redirect || echo "certbot failed for api/admin hosts"
certbot --nginx --non-interactive --agree-tos --register-unsure-about-email \
  -d "$APP_HOST" --cert-name lms-student --redirect || echo "certbot failed for the student host"
nginx -t && systemctl reload nginx

say "Done"
cat <<MSG
Student site : https://$APP_HOST
Admin panel  : https://$ADMIN_HOST
API          : https://$API_HOST/api/v1

Create your admin login now:
  cd $APP_DIR/backend && php artisan lms:create-admin you@example.com --name="Your Name"
MSG
[ "$TEST_MODE" = "1" ] && echo "TEST MODE: students log in with any phone number and code 123456. Not for real students."
exit 0
