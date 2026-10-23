# Going live: setup guide

This is the list of things **you** need to arrange, then the exact steps to put the site on a server.
Files referenced live in `deploy/`.

## 1. What you need to arrange yourself

| # | Item | Why | Notes |
|---|------|-----|-------|
| 1 | **A server** (Ubuntu 22.04/24.04, 2 vCPU / 4 GB RAM is enough for ~1000 test takers) | Runs everything | Needs PHP 8.3 (+ extensions), PostgreSQL 16/17, Python 3 with `pip install pymupdf`, Node 20+, Nginx, Composer, certbot. Poppler (`pdftoppm`) is optional. |
| 2 | **Three DNS records** pointing to the server | `api.`, `admin.`, `dashboard.` + `mymerchantnavy.com` | All three can use the same IP. Then run certbot for HTTPS. |
| 3 | **Brevo account** (email) | The emailed login code, and course-assigned, result and class reminder emails | In Brevo: **SMTP & API → SMTP**. Put the host `smtp-relay.brevo.com`, port `587`, your SMTP login and an SMTP key into `MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD`, and set `OTP_EMAIL_DRIVER=mail`. `MAIL_FROM_ADDRESS` must be a sender you verified in Brevo (**Senders, Domains & dedicated IPs**), or Brevo refuses the mail. Add SPF/DKIM for your domain so codes do not land in spam. |
| 4 | **Twilio (SMS), optional** | Only if you switch the SMS login back on (`LMS_LOGIN_METHOD=phone`) | Needs `TWILIO_SID`, `TWILIO_AUTH_TOKEN`, a sender number; in India also a DLT sender ID. Not needed while students log in by email. |
| 5 | **AI key (optional)**: Gemini or OpenAI or Anthropic | "Read with AI" for PDFs with hard formulas | Put one key in `.env`. The automatic (no-AI) reader works without it. |
| 6 | **Logo file** | Branding | Save as `student-app/public/brand/logo.png`. |
| 7 | **Video hosting decision** | Recorded lessons | The app stores video *links* (YouTube unlisted, Vimeo, etc.). Choose where videos live. |
| 8 | **Live classes (later)** | Agora account | Not built yet; live classes currently hold a join link. |
| 9 | **Backups copied off the server** | Safety | `deploy/backup.sh` makes nightly copies; also copy them elsewhere. |

## 2. Server preparation (once)

```bash
sudo apt update
sudo apt install -y nginx postgresql php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath composer python3-pip nodejs npm certbot python3-certbot-nginx
pip3 install pymupdf
```

PHP limits: in `/etc/php/8.3/fpm/php.ini` set `upload_max_filesize = 64M`, `post_max_size = 64M`,
`max_execution_time = 300`, then `sudo systemctl restart php8.3-fpm`.

Database:

```bash
sudo -u postgres psql -c "CREATE USER lms WITH PASSWORD 'choose-a-strong-password';"
sudo -u postgres psql -c "CREATE DATABASE mmp_lms OWNER lms;"
```

## 3. Install the app

```bash
sudo mkdir -p /var/www/lms && sudo chown $USER /var/www/lms
# copy the project to /var/www/lms (git clone or upload), then:
cd /var/www/lms/backend
cp .env.production.example .env        # fill in every blank value
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache

cd ../student-app
cp .env.production.example .env.production
npm ci && npm run build
```

## 4. Create your admin account

The demo seed does **not** run in production. Create a real admin:

```bash
cd /var/www/lms/backend
php artisan lms:create-admin you@yourdomain.com --name="Your Name"
```

(it asks for a password of at least 12 characters). More admins can be added from the admin panel.

## 5. Web server, services, schedule

```bash
sudo cp deploy/nginx/backend.conf /etc/nginx/sites-available/lms-backend
sudo cp deploy/nginx/student.conf /etc/nginx/sites-available/lms-student
sudo ln -s /etc/nginx/sites-available/lms-backend /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/lms-student /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d api.mymerchantnavy.com -d admin.mymerchantnavy.com -d dashboard.mymerchantnavy.com

sudo cp deploy/systemd/*.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now lms-queue lms-student
```

Add the scheduler (auto-submit of timed-out attempts, live class reminders) and backups:

```bash
sudo crontab -u www-data -e
# add:
* * * * * cd /var/www/lms/backend && php artisan schedule:run >> /dev/null 2>&1
30 2 * * * bash /var/www/lms/deploy/backup.sh >> /var/log/lms-backup.log 2>&1
```

## 6. Check it works

1. `https://admin.mymerchantnavy.com` – log in with the admin you created.
2. Create a course, a student (real mobile number), assign the course.
3. `https://dashboard.mymerchantnavy.com` – log in with that student's email; you should get a real email with the code.
4. Open a PDF as the student; the watermark must show the student's name/number.
5. Upload a question PDF in the admin (Question imports) and check the review list.

## 7. Updating later

```bash
cd /var/www/lms && bash deploy/deploy.sh
```

## 8. Not verified in the build environment

These were built and covered by automated tests with simulated responses, but could not be exercised
against the real service here, so test them yourself on first launch:

- Real email delivery through Brevo (the login code and the notification emails).
- AI reading with a real Gemini / OpenAI / Anthropic key.
- File-chooser upload and mouse drag-and-drop in the admin builder (the underlying actions are tested).
- Behaviour behind your real domain/HTTPS (cookies, CORS). If the student site cannot reach the API, check
  `CORS_ALLOWED_ORIGINS` / `STUDENT_APP_URL` in `.env`.
- Live classes via Agora (planned for later).
