# Security (api.mymerchantnavy.com, lms.mymerchantnavy.com)

What protects the platform, and what the Super Admin must keep in place. Open **Super Admin > Security** in the admin panel for a
live checklist of the server settings below.

## Built in

- **Two-step verification for admins.** Optional for everyone: after logging in, open the top-right menu > **Profile** > Two-factor
  authentication > Set up (needs your password, then a code from the authenticator app; recovery codes are shown once). The Super Admin
  can force it for all other admins under Super Admin > Security. Lost phone: Settings > Admins > **Reset two-step**.
  Forgot your password? While logged in: Profile > New password > Save. Otherwise reset it on the server:
  `php artisan lms:create-admin support.mymerchantnavy@gmail.com --password='NewStrongPassword'`.
- **Student (view only) role.** Create an admin with this role (Settings > Admins). They can only open **View as student**: the student
  site opens signed in as a private preview student with that one course. Preview students are left out of all counts, purchases, results and rankings.
- **Optional two-step verification for students.** Under My Profile on the student site; the Super Admin can switch the feature off or
  reset one student (Students > Reset two-step). Codes work once; recovery codes work once; 5 wrong tries end the login attempt.
- **Only the Super Admin can delete** courses, tests, questions, batches, pages, imports, queries and students (deleting is permanent),
  change a course's validity or WordPress product id, change a student's login email, manage admins, coupons, Razorpay, security,
  purchase emails, session limits and notification settings.
- **HTML is cleaned twice.** The backend removes scripts, event handlers and bad links from questions, notes, assignment text and custom
  pages before saving; the student site cleans it again before showing it. Only YouTube/Vimeo iframes are allowed.
- **Security headers** on the API and admin (no framing, no sniffing, strict referrer, HSTS on HTTPS, no caching of login pages) and
  on the student site (including a content security policy in the live build).
- **Support history is private.** `support/my-queries` only returns the logged-in student's own queries. The public query form is
  limited to 6 per minute.
- **Login codes:** hashed, 10-minute life, 5 wrong tries per code, 30 s between requests, throttled per address and IP. The test
  shortcut `OTP_FIXED_CODE` is ignored unless `APP_ENV` is local/testing.
- **Secrets never reach logs:** the audit log records only that a password / 2FA secret changed. Razorpay secret and two-step secrets
  are stored encrypted.
- **WordPress link:** every message is HMAC-signed with a timestamp (5-minute window), sign-in links work once.

## Server settings you must keep (Hostinger `.env`)

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.mymerchantnavy.com
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_LIFETIME=60
QUEUE_CONNECTION=database
# TRUSTED_PROXIES=<your host's proxy addresses>   ('*' lets people fake their IP and dodge limits)
```

Also: HTTPS everywhere (force it in hPanel), keep `.env` outside the web root (the guide already does), PHP 8.3, daily database backups
(hPanel > Backups), a unique long `LMS_WP_SECRET`, and never share the `.env`, SSH key or Razorpay secret in chat or email.

## Not covered by code (do these yourself)

- A web application firewall / DDoS shield (Cloudflare free plan in front of both sites is the simplest).
- Hostinger account: strong unique password and two-step verification on the Hostinger, GitHub, Razorpay and email accounts.
- GitHub: turn on two-step verification, protect the `main` branch (Settings > Branches), and keep the repository private if it is not meant to be public.
- The student login token is kept in the browser's storage (a common design). The HTML cleaning above is what protects it from scripts.

## After deploying this update

1. `php artisan migrate --force` (adds the two-step columns). The deploy workflow does this.
2. The Super Admin signs in, opens Profile and sets up the authenticator app (optional but recommended). Save the recovery codes.
3. Rebuild the student site in hPanel (Node.js Web App) so students get the two-step screens and the new protections.
