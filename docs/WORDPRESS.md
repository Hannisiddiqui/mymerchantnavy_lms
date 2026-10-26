# WordPress (mymerchantnavy.com) ↔ LMS link

A student registers once, on WordPress. The LMS learns about them, and after a successful Razorpay payment they land in
the LMS already signed in, with the course they bought. The email address is the shared identity (the LMS has no passwords).

| On WordPress | What happens in the LMS |
|---|---|
| A student registers (before paying) | An LMS account is created **without any course** |
| A payment succeeds | The account is created if missing, and the course linked to that product is given (the "course assigned" email is sent) |
| The student clicks "Go to my course" (or is redirected there) | They are signed in automatically and see their courses |
| A student who only registered logs in to the LMS later | Normal email + code login; "My Courses" is empty until they buy |

Refunds do **not** remove access yet (decided for later).

Every message is signed with a shared secret (HMAC-SHA256, with a timestamp so old messages are refused), so nobody else can
create students or hand out courses. The sign-in link works once and for 2 minutes. If WordPress cannot reach the LMS, it
retries after 1, 5, 30 minutes, 2 hours and 12 hours.

## 1. LMS server (once)

Update the code and tables (see HOSTINGER.md "Updating later"; it runs `migrate`), then make the secret. Run this yourself in the
server terminal, it prints the secret once:

```bash
bash ~/lms/backend/tools/set-wp-secret.sh
```

## 2. Tell the LMS which course is which product

Admin panel → **Courses** → open a course → **WordPress product ID**: the id your Razorpay checkout uses for that course
(any text or number, e.g. `imu-cet-2027`). Several ids can be separated by commas when more than one product gives the same course.

## 3. WordPress plugin (once)

1. Zip the folder `wordpress-plugin/mmn-lms-sync` and upload it in WordPress → Plugins → Add New → Upload Plugin. Activate it.
2. **Settings → LMS link**: API address `https://api.mymerchantnavy.com/api/v1`, student site `https://lms.mymerchantnavy.com`, and the shared secret from step 1.
   (Safer: put them in `wp-config.php` as `MMN_LMS_API_URL`, `MMN_LMS_APP_URL`, `MMN_LMS_SECRET`.)
3. Press **Send my own account to the LMS**. "Connected" means the link works.

Only students who register from now on are sent to the LMS (existing WordPress users are not imported). Every new registration is sent by itself.

## 4. Registration form and Razorpay checkout (built into the plugin)

No WooCommerce needed. The plugin gives you three shortcodes and a Razorpay checkout:

1. **Razorpay keys.** Razorpay dashboard > Account & Settings > API Keys > generate (use the **Test** keys first). In WordPress: Settings > **LMS link** > Razorpay checkout: paste Key ID and Key Secret.
2. **Courses for sale.** Same screen, "Courses for sale", one line per course: `product id | course title | price in INR`, for example
   `imu-cet-2027 | IMU-CET 2027 Complete Course | 4999`. The product id is the same text you typed on the course in the LMS admin ("WordPress product ID"). The price is read from here by the server, never from the browser.
3. **Registration page.** Create a page "Register" with the shortcode `[mmn_register]`; put its address in "Registration page address". Optionally a page "Login" with `[mmn_login]`.
   Step 1 asks name, email, mobile and password and **emails a 6-digit code** (no account exists yet). Step 2 asks for the code; when it is right the account is created with the email marked verified, the student is logged in and sent back to the course page they came from. Codes work 15 minutes, 5 wrong tries, a new code at most once a minute.
   WordPress sends the email with `wp_mail`: if codes do not arrive, install an SMTP plugin (e.g. **WP Mail SMTP**) and connect Brevo's SMTP (smtp-relay.brevo.com, port 587) so they are not marked as spam. Also switch **off** Settings > General > "Anyone can register", so the verified form is the only way in.
4. **Course page(s).** On each course's sales page put `[mmn_buy product="imu-cet-2027" text="Buy now"]`.
   Not logged in: the button sends the student to the Register page, then back to the course page. Logged in: it opens Razorpay's payment popup.
   **Pricing cards / your own buttons (works on any page, no registration page needed):** give any button the class `mmn-buy` and the product id, for example
   `<button type="button" class="cta-btn mmn-buy" data-product="gp-rating-pass">Get online Pass</button>` (or a link `<a href="?mmn_buy=gp-rating-pass">`, or `[mmn_buy product="..."]`).
   Not logged in: a **registration popup** opens on the same page (name, email, mobile, password, then the emailed code); the moment the code is accepted the Razorpay payment window opens. Logged in: the payment window opens at once.
   After paying, the student is sent into the LMS, signed in, **on the course they bought** (`/my-courses/<id>`; if the product gives several courses, on "My Courses").
   A button with `data-mmn-register` (no product) opens the same popup for registration only. Do not build your own registration popup or pass `name`, `email` or `phone` in a web address: `name` is a reserved WordPress word and WordPress then shows a different page.
5. **Webhook** (so a payment is never lost if the student closes the page): Razorpay dashboard > Account & Settings > Webhooks > Add. URL: the address shown under "Webhook secret" on the LMS link screen (`https://mymerchantnavy.com/wp-json/mmn-lms/v1/razorpay`), a secret you invent (paste the same into WordPress), events **payment.captured** and **order.paid**.
6. **After payment** (Settings > LMS link): "Send the student straight into the LMS, signed in" (recommended), or the normal thank-you page.
7. **Test** with Razorpay test keys (test card/UPI from Razorpay's docs). You should land in the LMS signed in, with the course. Every attempt is listed under **LMS orders** in the WordPress menu (status created / paid / failed).
8. Replace the Test keys and webhook with Live ones when it all works.

If you would rather use your own checkout code, call this after you have verified the Razorpay signature:

```php
do_action('mmn_lms_payment_success', $user_id, $product_id, $razorpay_order_id);
wp_redirect(mmn_lms_login_url($user_id)); exit;
```

## 5. Normal login, other forms and free sign-ups

- **Normal login:** when someone logs in to WordPress the normal way (not through the registration form), they are also registered in the LMS (at most once a day, in the background). Their name from WordPress is used, so **no profile form** is shown on their first LMS visit. Their email counts as *not verified* (only `[mmn_register]` verifies it), so the LMS keeps its safety rule for them.
- **Any other form or feature** (event sign-up, counselling request, webinar...): call this wherever the person is known; no course is given:
  ```php
  do_action('mmn_lms_sync_user', $user_id_or_email, ['name' => 'Riya Shah', 'phone' => '9876543210']);
  ```
  For form plugins, put that line in their "after submit" hook (Contact Form 7 `wpcf7_mail_sent`, WPForms `wpforms_process_complete`, Gravity Forms `gform_after_submission`, Fluent Forms `fluentform/submission_inserted`) and read the email, name and phone from the submitted fields.
- **Free sign-ups** (a free webinar, demo course, free test series): on the "Courses for sale" list use price `0`, for example `free-demo | Free Demo Course | 0`, link that id to a course in the LMS admin, and put `[mmn_buy product="free-demo" text="Register free"]` on the page. A logged-in student gets the course at once with no payment (a not-logged-in visitor is sent to register first). Razorpay keys are not needed for free items.

## Things to know

- Order matters little: if a payment arrives before the registration message, the account is created then.
- A product id with no linked course is reported as an error (the plugin writes it to the WordPress error log), and nobody is created or charged anything in the LMS.
- The student's first LMS visit still shows the short profile form (target exam, attempt year) once.
- **Security rule:** accounts made with `[mmn_register]` have a verified email (the student typed back the emailed code), and the LMS trusts that. A sign-in link from a WordPress account that was made another way (email not verified) never opens an LMS account whose owner has already proved the address with the LMS code. Logging in with the LMS code also ends any session that was started from an unverified WordPress link.
- Switch the link off by emptying `LMS_WP_SECRET` in the LMS `.env`.
- Messages to the LMS: `POST /api/v1/integrations/wordpress/{student|enroll}`, headers `X-MMN-Timestamp` (unix seconds) and `X-MMN-Signature` (hex HMAC-SHA256 of `timestamp + "." + raw body`). The sign-in token is `base64url({"e":email,"x":expiry,"j":random}) + "." + hex HMAC-SHA256(payload)`; the student site opens `/sso#t=<token>`.
