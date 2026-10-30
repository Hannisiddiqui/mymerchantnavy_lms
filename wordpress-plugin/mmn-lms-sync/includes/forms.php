<?php
/**
 * Registration, in two places that share one engine:
 *   - the page form   [mmn_register]  (and [mmn_login])
 *   - the popup that opens on ANY page (see includes/razorpay.php): a button with class "mmn-buy" or attribute data-mmn-register
 *
 * Registration has two steps. Step 1 asks name, email, mobile and password and emails a 6-digit code.
 * Step 2 asks for the code; when it is right the account is created (with the email marked verified)
 * and the student is logged in.
 */

if (! defined('ABSPATH')) {
    exit;
}

const MMN_LMS_CODE_MINUTES = 15;

function mmn_lms_form_message($code)
{
    $messages = [
        'fields'            => 'All fields are compulsory. Please fill in your name, email, 10-digit mobile number, and password.',
        'invalid_phone'     => 'Please enter a valid 10-digit Indian mobile number (starting with 6, 7, 8, or 9).',
        'password'          => 'Your password must be at least 8 characters.',
        'exists'            => 'An account with this email or mobile number already exists. Please log in to complete your payment.',
        'exists_wrong_pass' => 'An account is already registered with these details. Please enter your correct password or proceed to payment.',
        'wait'              => 'Too many attempts. Please try again in a while.',
        'failed'            => 'We could not complete your registration. Please try again.',
        'nonce'             => 'The page expired. Please reload it and try again.',
        'mailfail'          => 'We could not send the verification code. Please check your email address and try again.',
        'expired'           => 'That verification code has expired. Please request a new one.',
        'badcode'           => 'That verification code is incorrect. Please check your email and try again.',
        'toomany'           => 'Too many wrong attempts. Please start again.',
        'cooldown'          => 'Please wait a minute before requesting another code.',
        'resent'            => 'A new verification code has been sent to your email.',
    ];

    return isset($messages[$code]) ? $messages[$code] : '';
}

// ---------------------------------------------------------------------------------------------------- the engine

function mmn_lms_pending($pid)
{
    return $pid !== '' ? get_transient('mmn_pending_' . $pid) : false;
}

function mmn_lms_code_hash($code)
{
    return hash_hmac('sha256', (string) $code, wp_salt('auth'));
}

/** Sends a beautifully designed, responsive HTML email highlighting the 6-digit verification code. */
function mmn_lms_send_code($email, $code)
{
    $site     = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $site_url = home_url('/');
    $subject  = 'Your Verification Code: ' . $code . ' - ' . $site;

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . esc_html($subject) . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 32px 16px;">
    <tr>
      <td align="center">
        <!-- Card Container -->
        <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 560px; background-color: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04); border: 1px solid #e2e8f0;">
          
          <!-- Deep Maritime Header -->
          <tr>
            <td style="background: linear-gradient(135deg, #0b192c 0%, #1e3a8a 100%); padding: 32px 30px; text-align: center;">
              <table role="presentation" border="0" cellspacing="0" cellpadding="0" style="margin: 0 auto;">
                <tr>
                  <td style="vertical-align: middle; padding-right: 12px;">
                    <span style="font-size: 32px; line-height: 1;">⚓</span>
                  </td>
                  <td style="vertical-align: middle; text-align: left;">
                    <div style="font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: 0.5px; text-transform: uppercase;">' . esc_html($site) . '</div>
                    <div style="font-size: 12px; color: #93c5fd; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;">Premier Maritime Academy &amp; LMS</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body Content -->
          <tr>
            <td style="padding: 36px 32px 28px 32px;">
              <h1 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 800; color: #0f172a; text-align: center;">Confirm Your Email Address</h1>
              <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #475569; text-align: center;">
                Welcome aboard! Use the single-use verification code below to verify your email and complete your course registration.
              </p>

              <!-- Prominent OTP Code Box -->
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin: 0 0 24px 0;">
                <tr>
                  <td style="background: #f0f7ff; border: 2px dashed #3b82f6; border-radius: 14px; padding: 24px 16px; text-align: center;">
                    <div style="font-size: 12px; font-weight: 700; color: #2563eb; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 8px;">Your 6-Digit Verification Code</div>
                    <div style="font-family: \'SF Mono\', Monaco, Consolas, \'Courier New\', monospace; font-size: 44px; font-weight: 900; color: #1e3a8a; letter-spacing: 14px; margin: 8px 0 12px 0; padding-left: 14px;">' . esc_html($code) . '</div>
                    <div style="display: inline-block; background-color: #dbeafe; color: #1e40af; font-size: 12px; font-weight: 600; padding: 5px 14px; border-radius: 9999px;">
                      ⏱ Valid for next ' . (int) MMN_LMS_CODE_MINUTES . ' minutes
                    </div>
                  </td>
                </tr>
              </table>

              <!-- Security Notice Callout -->
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; border-radius: 10px; border-left: 4px solid #f59e0b; margin-bottom: 24px;">
                <tr>
                  <td style="padding: 14px 16px;">
                    <div style="font-size: 13px; color: #334155; line-height: 1.5;">
                      <strong>Security Note:</strong> Never share this verification code or your password with anyone. Academy counselors will never ask for your verification code.
                    </div>
                  </td>
                </tr>
              </table>

              <!-- LMS Highlights Checklist -->
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-top: 1px solid #e2e8f0; padding-top: 20px;">
                <tr>
                  <td style="font-size: 13px; font-weight: 700; color: #334155; padding-bottom: 8px;">Instant LMS access includes:</td>
                </tr>
                <tr>
                  <td style="font-size: 13px; color: #64748b; line-height: 1.8;">
                    ✓ Chapter-wise theory notes &amp; formula cheat sheets<br>
                    ✓ 10,000+ practice questions &amp; full mock test series<br>
                    ✓ Automatic sync across desktop and mobile browsers
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 22px 30px; text-align: center;">
              <p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                If you did not make this request, you can safely disregard this email.
              </p>
              <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                &copy; ' . gmdate('Y') . ' <a href="' . esc_url($site_url) . '" style="color: #2563eb; text-decoration: none; font-weight: 600;">' . esc_html($site) . '</a>. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site . ' <' . (get_option('admin_email') ?: 'support@mymerchantnavy.com') . '>',
    ];

    return wp_mail($email, $subject, $html, $headers);
}

function mmn_lms_mask_email($email)
{
    return preg_replace('/(?<=.).(?=[^@]*@)/', '*', $email);
}

/**
 * Step 1.
 * Returns:
 *   ['pid' => ..., 'email' => masked] on new registration,
 *   ['already_purchased' => true, 'redirect' => ...] if course is already owned,
 *   ['account_exists' => true, 'logged_in' => true, 'nonce' => ...] if existing user and password matches,
 *   ['account_exists' => true, 'logged_in' => false, 'matched_by' => 'phone'|'email'|'both', 'identifier' => ...] if password needed,
 *   or ['error' => code].
 */
function mmn_lms_registration_begin($name, $email, $phone, $pass, $next = '', $product = '')
{
    $ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $key   = 'mmn_reg_' . md5($ip);
    $tries = (int) get_transient($key);
    if ($tries >= 30) {
        return ['error' => 'wait'];
    }
    set_transient($key, $tries + 1, HOUR_IN_SECONDS);

    $name  = sanitize_text_field(trim((string) $name));
    $email = sanitize_email(trim((string) $email));
    $pass  = (string) $pass;

    // Strictly validate Indian mobile number (10 digits starting with 6, 7, 8, or 9)
    $clean_phone = preg_replace('/\D/', '', (string) $phone);
    if (strlen($clean_phone) === 12 && substr($clean_phone, 0, 2) === '91') {
        $clean_phone = substr($clean_phone, 2);
    } elseif (strlen($clean_phone) === 11 && substr($clean_phone, 0, 1) === '0') {
        $clean_phone = substr($clean_phone, 1);
    }

    // All fields are strictly compulsory
    if ($name === '' || ! is_email($email) || empty($pass) || empty($clean_phone)) {
        return ['error' => 'fields'];
    }
    if (! preg_match('/^[6-9]\d{9}$/', $clean_phone)) {
        return ['error' => 'invalid_phone'];
    }
    if (strlen($pass) < 8) {
        return ['error' => 'password'];
    }

    $matched_existing_user = null;
    $matched_by            = null;
    $already_purchased     = false;

    // 1. Check duplicate on LMS backend (both email AND phone)
    if (function_exists('mmn_lms_ready') && mmn_lms_ready()) {
        $check_res = mmn_lms_send('check', [
            'email'      => $email,
            'phone'      => $clean_phone,
            'product_id' => $product,
        ]);
        if ($check_res[0] && is_array($check_res[1])) {
            $lms_data = $check_res[1];
            if (! empty($lms_data['already_purchased'])) {
                $already_purchased = true;
            }
            if (! empty($lms_data['exists'])) {
                $matched_by = ! empty($lms_data['matched_by']) ? $lms_data['matched_by'] : 'account';
            }
        }
    }

    // 2. Also check local WordPress database for matching user by email OR phone
    $wp_user_by_email = get_user_by('email', $email);
    $wp_user_by_phone = null;

    $users_with_phone = get_users([
        'meta_query' => [
            'relation' => 'OR',
            ['key' => 'phone', 'value' => '+91' . $clean_phone],
            ['key' => 'phone', 'value' => $clean_phone],
            ['key' => 'billing_phone', 'value' => '+91' . $clean_phone],
            ['key' => 'billing_phone', 'value' => $clean_phone],
        ],
        'number' => 1,
    ]);
    if (! empty($users_with_phone)) {
        $wp_user_by_phone = $users_with_phone[0];
    }

    if ($wp_user_by_email && $wp_user_by_phone && $wp_user_by_email->ID === $wp_user_by_phone->ID) {
        $matched_existing_user = $wp_user_by_email;
        $matched_by            = 'both';
    } elseif ($wp_user_by_email) {
        $matched_existing_user = $wp_user_by_email;
        $matched_by            = $matched_by ?: 'email';
    } elseif ($wp_user_by_phone) {
        $matched_existing_user = $wp_user_by_phone;
        $matched_by            = $matched_by ?: 'phone';
    }

    // If user already owns this course, direct them straight to the LMS
    if ($already_purchased) {
        if ($matched_existing_user) {
            wp_set_current_user($matched_existing_user->ID);
            wp_set_auth_cookie($matched_existing_user->ID, true);
        }

        return [
            'already_purchased' => true,
            'message'           => 'You already own this course! Taking you to your LMS...',
            'redirect'          => rtrim(mmn_lms_setting('app_url'), '/') . '/my-courses',
        ];
    }

    // If account already exists (either on LMS or locally on WordPress)
    if ($matched_existing_user || $matched_by) {
        // First check if the entered password correctly authenticates the local user
        $authenticated_user = null;
        if ($matched_existing_user) {
            $auth = wp_authenticate($matched_existing_user->user_login, $pass);
            if (! is_wp_error($auth)) {
                $authenticated_user = $auth;
            }
        }

        // If local user was authenticated or if we can authenticate with email/username:
        if (! $authenticated_user) {
            $auth_by_email = wp_authenticate($email, $pass);
            if (! is_wp_error($auth_by_email)) {
                $authenticated_user = $auth_by_email;
            }
        }

        if ($authenticated_user) {
            wp_set_current_user($authenticated_user->ID);
            wp_set_auth_cookie($authenticated_user->ID, true);

            return [
                'account_exists' => true,
                'logged_in'      => true,
                'nonce'          => wp_create_nonce('mmn_rzp'),
                'user_id'        => $authenticated_user->ID,
                'matched_by'     => $matched_by,
                'message'        => 'Account verified and signed in! Proceeding to payment...',
            ];
        }

        // The account exists, but the user must provide their password to log in and pay
        $identifier_label = ($matched_by === 'phone') ? ('+91 ' . $clean_phone) : $email;

        return [
            'account_exists' => true,
            'logged_in'      => false,
            'matched_by'     => $matched_by,
            'identifier'     => $identifier_label,
            'message'        => 'An account is already registered with this ' . ($matched_by === 'phone' ? 'mobile number' : 'email address') . '. Please log in to complete your purchase.',
        ];
    }

    // 3. New Student: Create transient and dispatch verification email
    $phone_formatted = '+91' . $clean_phone;
    $code            = (string) random_int(100000, 999999);
    $pid             = strtolower(wp_generate_password(24, false));

    set_transient('mmn_pending_' . $pid, [
        'name'    => $name,
        'email'   => $email,
        'phone'   => $phone_formatted,
        'pass'    => wp_hash_password($pass),
        'code'    => mmn_lms_code_hash($code),
        'product' => $product,
        'tries'   => 0,
        'sends'   => 1,
        'sent'    => time(),
        'next'    => $next,
    ], MMN_LMS_CODE_MINUTES * MINUTE_IN_SECONDS);

    if (! mmn_lms_send_code($email, $code)) {
        delete_transient('mmn_pending_' . $pid);

        return ['error' => 'mailfail'];
    }

    return ['pid' => $pid, 'email' => mmn_lms_mask_email($email)];
}

/** "Send the code again" (at most once a minute, five times). Returns ['ok' => true] or ['error' => code]. */
function mmn_lms_registration_resend($pid)
{
    $pending = mmn_lms_pending($pid);
    if (! $pending) {
        return ['error' => 'expired'];
    }
    if (time() - $pending['sent'] < 60 || $pending['sends'] >= 5) {
        return ['error' => 'cooldown'];
    }
    $code              = (string) random_int(100000, 999999);
    $pending['code']   = mmn_lms_code_hash($code);
    $pending['tries']  = 0;
    $pending['sends'] += 1;
    $pending['sent']   = time();
    set_transient('mmn_pending_' . $pid, $pending, MMN_LMS_CODE_MINUTES * MINUTE_IN_SECONDS);
    mmn_lms_send_code($pending['email'], $code);

    return ['ok' => true];
}

/**
 * Step 2. The right code creates the account (email marked verified) and logs the student in.
 * Returns ['user_id' => id, 'next' => url, 'nonce' => ...] or ['error' => code, 'keep' => bool].
 */
function mmn_lms_registration_finish($pid, $given)
{
    $pending = mmn_lms_pending($pid);
    if (! $pending) {
        return ['error' => 'expired'];
    }
    $pending['tries']++;
    if ($pending['tries'] > 5) {
        delete_transient('mmn_pending_' . $pid);

        return ['error' => 'toomany'];
    }
    set_transient('mmn_pending_' . $pid, $pending, MMN_LMS_CODE_MINUTES * MINUTE_IN_SECONDS);

    if (! hash_equals($pending['code'], mmn_lms_code_hash(preg_replace('/\D/', '', (string) $given)))) {
        return ['error' => 'badcode', 'keep' => true];
    }

    if ($existing_id = email_exists($pending['email'])) {
        delete_transient('mmn_pending_' . $pid);
        wp_set_current_user($existing_id);
        wp_set_auth_cookie($existing_id, true);

        return [
            'user_id' => $existing_id,
            'next'    => $pending['next'],
            'nonce'   => wp_create_nonce('mmn_rzp'),
        ];
    }

    $user_id = wp_insert_user([
        'user_login'   => $pending['email'],
        'user_email'   => $pending['email'],
        'user_pass'    => wp_generate_password(32),
        'first_name'   => $pending['name'],
        'display_name' => $pending['name'],
        'role'         => 'subscriber',
        'meta_input'   => ['phone' => $pending['phone'], 'mmn_email_verified' => time()],
    ]);
    if (is_wp_error($user_id)) {
        return ['error' => 'failed'];
    }

    global $wpdb;
    $wpdb->update($wpdb->users, ['user_pass' => $pending['pass']], ['ID' => $user_id]);
    clean_user_cache($user_id);
    delete_transient('mmn_pending_' . $pid);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    return [
        'user_id' => $user_id,
        'next'    => $pending['next'],
        'nonce'   => wp_create_nonce('mmn_rzp'),
    ];
}

// ---------------------------------------------------------------------------------------------------- popup (AJAX) endpoints

function mmn_lms_ajax_fail($code, $custom_msg = '')
{
    $msg = $custom_msg !== '' ? $custom_msg : mmn_lms_form_message($code);
    wp_send_json_error(['code' => $code, 'message' => $msg]);
}

function mmn_lms_post($key)
{
    return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
}

function mmn_lms_handle_ajax_reg_start()
{
    if (mmn_lms_post('mmn_hp') !== '') {
        mmn_lms_ajax_fail('failed');
    }
    $result = mmn_lms_registration_begin(
        mmn_lms_post('name'),
        mmn_lms_post('email'),
        mmn_lms_post('phone'),
        mmn_lms_post('password'),
        mmn_lms_post('next'),
        mmn_lms_post('product')
    );
    if (isset($result['error'])) {
        mmn_lms_ajax_fail($result['error']);
    } else {
        if (! empty($result['logged_in']) && empty($result['nonce'])) {
            $result['nonce'] = wp_create_nonce('mmn_rzp');
        }
        wp_send_json_success($result);
    }
}
add_action('wp_ajax_nopriv_mmn_reg_start', 'mmn_lms_handle_ajax_reg_start');
add_action('wp_ajax_mmn_reg_start', 'mmn_lms_handle_ajax_reg_start');

function mmn_lms_handle_ajax_reg_resend()
{
    $result = mmn_lms_registration_resend(sanitize_key(mmn_lms_post('pid')));
    isset($result['error']) ? mmn_lms_ajax_fail($result['error']) : wp_send_json_success(['message' => mmn_lms_form_message('resent')]);
}
add_action('wp_ajax_nopriv_mmn_reg_resend', 'mmn_lms_handle_ajax_reg_resend');
add_action('wp_ajax_mmn_reg_resend', 'mmn_lms_handle_ajax_reg_resend');

function mmn_lms_handle_ajax_reg_verify()
{
    $result = mmn_lms_registration_finish(sanitize_key(mmn_lms_post('pid')), mmn_lms_post('code'));
    if (isset($result['error'])) {
        mmn_lms_ajax_fail($result['error']);
    } else {
        if (empty($result['nonce'])) {
            $result['nonce'] = wp_create_nonce('mmn_rzp');
        }
        wp_send_json_success($result);
    }
}
add_action('wp_ajax_nopriv_mmn_reg_verify', 'mmn_lms_handle_ajax_reg_verify');
add_action('wp_ajax_mmn_reg_verify', 'mmn_lms_handle_ajax_reg_verify');

/** AJAX Login for existing account during checkout */
function mmn_lms_handle_ajax_login_and_pay()
{
    $identifier = trim(sanitize_text_field(mmn_lms_post('identifier')));
    $password   = (string) mmn_lms_post('password');

    if (empty($identifier) || empty($password)) {
        wp_send_json_error(['message' => 'Please enter your password.']);
    }

    $clean_phone = preg_replace('/\D/', '', $identifier);
    if (strlen($clean_phone) === 12 && substr($clean_phone, 0, 2) === '91') {
        $clean_phone = substr($clean_phone, 2);
    } elseif (strlen($clean_phone) === 11 && substr($clean_phone, 0, 1) === '0') {
        $clean_phone = substr($clean_phone, 1);
    }

    $user = null;
    if (is_email($identifier)) {
        $user = get_user_by('email', $identifier);
    }
    if (! $user && strlen($clean_phone) === 10) {
        $users_by_phone = get_users([
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'phone', 'value' => '+91' . $clean_phone],
                ['key' => 'phone', 'value' => $clean_phone],
                ['key' => 'billing_phone', 'value' => '+91' . $clean_phone],
                ['key' => 'billing_phone', 'value' => $clean_phone],
            ],
            'number' => 1,
        ]);
        if (! empty($users_by_phone)) {
            $user = $users_by_phone[0];
        }
    }

    if (! $user) {
        $user = get_user_by('login', $identifier);
    }

    if (! $user) {
        wp_send_json_error(['message' => 'No account found with these details. Please complete registration.']);
    }

    $auth = wp_authenticate($user->user_login, $password);
    if (is_wp_error($auth)) {
        wp_send_json_error(['message' => 'Incorrect password. Please try again.']);
    }

    wp_set_current_user($auth->ID);
    wp_set_auth_cookie($auth->ID, true);

    wp_send_json_success([
        'nonce'   => wp_create_nonce('mmn_rzp'),
        'user_id' => $auth->ID,
        'message' => 'Signed in successfully! Opening payment window...',
    ]);
}
add_action('wp_ajax_nopriv_mmn_login_and_pay', 'mmn_lms_handle_ajax_login_and_pay');
add_action('wp_ajax_mmn_login_and_pay', 'mmn_lms_handle_ajax_login_and_pay');

/** Supplies a fresh mmn_rzp nonce to the browser */
function mmn_lms_handle_ajax_nonce()
{
    wp_send_json_success(wp_create_nonce('mmn_rzp'));
}
add_action('wp_ajax_mmn_nonce', 'mmn_lms_handle_ajax_nonce');
add_action('wp_ajax_nopriv_mmn_nonce', 'mmn_lms_handle_ajax_nonce');

// ---------------------------------------------------------------------------------------------------- Shortcodes

function mmn_lms_form_styles()
{
    return '<style>
        .mmn-form { max-width: 440px; margin: 24px auto; padding: 28px; background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .mmn-form label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin: 12px 0 4px; }
        .mmn-form input { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .mmn-form button[type="submit"] { width: 100%; margin-top: 16px; padding: 12px; background: #1d4ed8; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; }
        .mmn-form button.mmn-link { background: none; border: none; color: #1d4ed8; cursor: pointer; text-decoration: underline; font-size: 13px; margin-top: 10px; width: auto; padding: 0; }
        .mmn-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; font-weight: 600; }
        .mmn-ok { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; font-weight: 600; }
    </style>';
}

/** [mmn_register redirect="/courses/"] */
function mmn_lms_register_shortcode($atts)
{
    $atts = shortcode_atts(['redirect' => ''], $atts);
    if (is_user_logged_in()) {
        return '<p>You are already registered and logged in.</p>';
    }
    $next  = isset($_GET['next']) ? esc_url_raw(wp_unslash($_GET['next'])) : $atts['redirect'];
    $code  = isset($_GET['mmn_err']) ? sanitize_key($_GET['mmn_err']) : '';
    $note  = mmn_lms_form_message($code);
    $pid   = isset($_GET['pid']) ? sanitize_key($_GET['pid']) : '';
    $step2 = isset($_GET['mmn_step']) && $_GET['mmn_step'] === 'verify' && ($pending = mmn_lms_pending($pid));

    ob_start();
    echo mmn_lms_form_styles();

    if ($step2) { ?>
        <form class="mmn-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php if ($note) : ?><div class="<?php echo $code === 'resent' ? 'mmn-ok' : 'mmn-error'; ?>"><?php echo esc_html($note); ?></div><?php endif; ?>
            <p>We emailed a 6-digit code to <strong><?php echo esc_html(mmn_lms_mask_email($pending['email'])); ?></strong>. It works for <?php echo (int) MMN_LMS_CODE_MINUTES; ?> minutes.</p>
            <input type="hidden" name="action" value="mmn_register_verify">
            <input type="hidden" name="pid" value="<?php echo esc_attr($pid); ?>">
            <?php wp_nonce_field('mmn_register_verify', 'mmn_nonce'); ?>
            <label for="mmn_code">Verification code</label>
            <input id="mmn_code" class="mmn-code" type="text" name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
            <button type="submit">Verify and create my account</button>
            <button type="submit" class="mmn-link" name="action" value="mmn_register_resend" formnovalidate>Send the code again</button>
        </form>
        <?php
    } else { ?>
        <form class="mmn-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php if ($note) : ?><div class="mmn-error"><?php echo esc_html($note); ?></div><?php endif; ?>
            <input type="hidden" name="action" value="mmn_register">
            <input type="hidden" name="next" value="<?php echo esc_attr($next); ?>">
            <?php wp_nonce_field('mmn_register', 'mmn_nonce'); ?>
            <div class="mmn-hp" style="display:none !important;"><label>Leave empty <input type="text" name="mmn_hp" tabindex="-1" autocomplete="off"></label></div>
            <label for="mmn_name">Full name <span style="color:#ef4444">*</span></label>
            <input id="mmn_name" type="text" name="name" required autocomplete="name">
            <label for="mmn_email">Email address <span style="color:#ef4444">*</span></label>
            <input id="mmn_email" type="email" name="email" required autocomplete="email">
            <label for="mmn_phone">Mobile number (10-digit Indian) <span style="color:#ef4444">*</span></label>
            <div style="position:relative;display:flex;align-items:center;">
                <span style="position:absolute;left:12px;font-weight:700;color:#374151;font-size:14px;pointer-events:none;">+91</span>
                <input id="mmn_phone" type="tel" name="phone" required maxlength="15" inputmode="numeric" pattern="[6-9][0-9]{9}" placeholder="9876543210" style="padding-left:46px;" autocomplete="tel">
            </div>
            <label for="mmn_pass">Password (8+ characters) <span style="color:#ef4444">*</span></label>
            <input id="mmn_pass" type="password" name="password" required minlength="8" autocomplete="new-password">
            <button type="submit">Send me a verification code</button>
        </form>
        <?php
    }

    return ob_get_clean();
}
add_shortcode('mmn_register', 'mmn_lms_register_shortcode');
