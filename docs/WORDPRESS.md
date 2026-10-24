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

## 4. Shop on WordPress with WooCommerce (recommended)

WooCommerce gives registration, checkout, order emails and a reliable "payment complete" signal; the official **Razorpay for
WooCommerce** plugin takes the payment. The LMS plugin already listens to it, nothing to code:

1. Install and activate **WooCommerce** (Plugins > Add New). Run its setup wizard: country India, currency INR.
2. Install and activate **Razorpay for WooCommerce** (by Razorpay). WooCommerce > Settings > Payments > Razorpay: paste the Key ID and Key Secret from the Razorpay dashboard (start with the Test keys), enable it.
3. WooCommerce > Settings > **Accounts & Privacy**: tick "Allow customers to create an account on the My account page" and "...during checkout", and **untick "Allow customers to place orders without an account"**. A student is now always registered before paying.
4. Create one **Product** per course (Products > Add New): type "Simple product", virtual (tick "Virtual"), price, publish. Its **ID** is the number in the address bar when editing (`post=123`) and shown on the Products list when you hover.
5. In the LMS admin, open the matching course and type that number in **WordPress product ID**.
6. Settings > **LMS link**: "After payment" = send the student straight into the LMS.
7. Make a **test purchase** with Razorpay test keys. After paying you should land in the LMS, signed in, with the course.
8. Switch Razorpay to the Live keys when everything works.

What the plugin does by itself: registration (My account or checkout) creates the LMS account with no course; a paid order gives the
course(s) of the products in it; the thank-you step redirects into the LMS signed in; My account shows a "Go to my course" button.

## 4b. Only if you build your own checkout (not WooCommerce): two lines in your Razorpay code

In the place where your code has **verified** a successful payment (after checking the Razorpay signature, never before):

```php
// tell the LMS: this student paid for this product
do_action('mmn_lms_payment_success', $user_id, $product_id, $razorpay_order_id);

// send the student to the LMS, signed in (instead of, or after, your own thank-you redirect)
wp_redirect(mmn_lms_login_url($user_id));
exit;
```

`$user_id` is the WordPress user (id, `WP_User` or email). `$product_id` is the id you typed on the course in step 2.
If you keep your own thank-you page, put the shortcode `[mmn_lms_button text="Go to my course"]` on it (and in "My account"):
it signs the student in at the moment of the click.

## Things to know

- Order matters little: if a payment arrives before the registration message, the account is created then.
- A product id with no linked course is reported as an error (the plugin writes it to the WordPress error log), and nobody is created or charged anything in the LMS.
- The student's first LMS visit still shows the short profile form (target exam, attempt year) once.
- Switch the link off by emptying `LMS_WP_SECRET` in the LMS `.env`.
- Messages to the LMS: `POST /api/v1/integrations/wordpress/{student|enroll}`, headers `X-MMN-Timestamp` (unix seconds) and `X-MMN-Signature` (hex HMAC-SHA256 of `timestamp + "." + raw body`). The sign-in token is `base64url({"e":email,"x":expiry,"j":random}) + "." + hex HMAC-SHA256(payload)`; the student site opens `/sso#t=<token>`.
