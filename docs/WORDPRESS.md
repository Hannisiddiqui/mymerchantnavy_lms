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
   The form asks name, email, mobile and password, creates the account, logs the student in and sends them back to the course page they came from.
4. **Course page(s).** On each course's sales page put `[mmn_buy product="imu-cet-2027" text="Buy now"]`.
   Not logged in: the button sends the student to the Register page, then back to the course page. Logged in: it opens Razorpay's payment popup.
5. **Webhook** (so a payment is never lost if the student closes the page): Razorpay dashboard > Account & Settings > Webhooks > Add. URL: the address shown under "Webhook secret" on the LMS link screen (`https://mymerchantnavy.com/wp-json/mmn-lms/v1/razorpay`), a secret you invent (paste the same into WordPress), events **payment.captured** and **order.paid**.
6. **After payment** (Settings > LMS link): "Send the student straight into the LMS, signed in" (recommended), or the normal thank-you page.
7. **Test** with Razorpay test keys (test card/UPI from Razorpay's docs). You should land in the LMS signed in, with the course. Every attempt is listed under **LMS orders** in the WordPress menu (status created / paid / failed).
8. Replace the Test keys and webhook with Live ones when it all works.

If you would rather use your own checkout code, call this after you have verified the Razorpay signature:

```php
do_action('mmn_lms_payment_success', $user_id, $product_id, $razorpay_order_id);
wp_redirect(mmn_lms_login_url($user_id)); exit;
```

## Things to know

- Order matters little: if a payment arrives before the registration message, the account is created then.
- A product id with no linked course is reported as an error (the plugin writes it to the WordPress error log), and nobody is created or charged anything in the LMS.
- The student's first LMS visit still shows the short profile form (target exam, attempt year) once.
- Switch the link off by emptying `LMS_WP_SECRET` in the LMS `.env`.
- Messages to the LMS: `POST /api/v1/integrations/wordpress/{student|enroll}`, headers `X-MMN-Timestamp` (unix seconds) and `X-MMN-Signature` (hex HMAC-SHA256 of `timestamp + "." + raw body`). The sign-in token is `base64url({"e":email,"x":expiry,"j":random}) + "." + hex HMAC-SHA256(payload)`; the student site opens `/sso#t=<token>`.
