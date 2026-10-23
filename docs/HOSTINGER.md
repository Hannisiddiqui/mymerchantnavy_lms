# Pilot on Hostinger Business hosting

Two websites: the student site (`lms.`, a Node.js Web App) and the backend (`api.`, a PHP website with the API and the admin
panel at `/admin`). The database is Hostinger's MySQL/MariaDB (the hosting has no PostgreSQL driver, so Supabase cannot be used
from here; the app runs on both, all tests pass on MySQL 8.4 and PostgreSQL 17).

PHP on the command line must be 8.3: use `/opt/alt/php83/usr/bin/php` (the plain `php` is 8.1, too old). In hPanel set the
backend website's PHP version to 8.3 as well.

## Backend (api.)

```bash
# code (new folder in the home directory, outside the web root)
git clone https://github.com/Hannisiddiqui/mymerchantnavy_lms.git ~/lms

# packages
cd ~/lms/backend && /opt/alt/php83/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction

# PDF reader (Python 3.11 + PyMuPDF; the newest PyMuPDF is too heavy for the hosting limits, so it is pinned)
/opt/alt/python311/bin/python3.11 -m venv ~/lms-venv && ~/lms-venv/bin/pip install pymupdf==1.26.4

# settings: copy the example, fill in the blanks (database name/user/password, Brevo login and key)
cp .env.hostinger.example .env && nano .env

# create the tables, storage link, first admin
/opt/alt/php83/usr/bin/php artisan key:generate --force
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan storage:link
/opt/alt/php83/usr/bin/php artisan lms:create-admin lmsadmin@mmn.com --name="LMS Admin"
chmod -R 775 storage bootstrap/cache
/opt/alt/php83/usr/bin/php artisan config:cache && /opt/alt/php83/usr/bin/php artisan route:cache && /opt/alt/php83/usr/bin/php artisan view:cache
```

The website folder must serve Laravel's `public` folder (never the project folder: it holds `.env`):

```bash
mv ~/domains/api.mymerchantnavy.com/public_html ~/domains/api.mymerchantnavy.com/public_html.old
ln -s ~/lms/backend/public ~/domains/api.mymerchantnavy.com/public_html
```

Cron jobs (hPanel > Advanced > Cron Jobs), each every minute:

```
/opt/alt/php83/usr/bin/php /home/u563028037/lms/backend/artisan schedule:run
/opt/alt/php83/usr/bin/php /home/u563028037/lms/backend/artisan queue:work --stop-when-empty --max-time=55
```

Updating later: `cd ~/lms && git pull && cd backend && /opt/alt/php83/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader && /opt/alt/php83/usr/bin/php artisan migrate --force && /opt/alt/php83/usr/bin/php artisan config:cache && /opt/alt/php83/usr/bin/php artisan route:cache && /opt/alt/php83/usr/bin/php artisan view:cache`

## Student site (lms.)
hPanel > Websites > Node.js Web App: import the repository, root directory `student-app`, build `npm run build`, start `npm run start`.
Environment (set before the first build, they are read at build time):

```
NEXT_PUBLIC_USE_MOCK=false
NEXT_PUBLIC_LOGIN_METHOD=email
NEXT_PUBLIC_API_URL=https://api.mymerchantnavy.com/api/v1
```

`https://lms.mymerchantnavy.com/admin` redirects to `https://api.mymerchantnavy.com/admin`.
