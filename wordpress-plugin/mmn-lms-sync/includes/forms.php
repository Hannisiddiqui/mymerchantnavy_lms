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
        'invalid_phone'     => 'Please enter a valid 10-digit Indian mobile number (e.g. 9876543210 starting with 6, 7, 8, or 9).',
        'password'          => 'Your password must be at least 8 characters.',
        'exists'            => 'An account with this email address already exists. Please proceed to payment or use your registered credentials.',
        'exists_wrong_pass' => 'An account with this email address already exists. Please enter your correct password or proceed directly to payment.',
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

function mmn_lms_send_code($email, $code)
{
    $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

    return wp_mail(
        $email,
        'Your ' . $site . ' verification code: ' . $code,
        "Your verification code is {$code}\n\nIt works for " . MMN_LMS_CODE_MINUTES . " minutes. If you did not ask for it, you can ignore this email.\n\n{$site}"
    );
}

function mmn_lms_mask_email($email)
{
    return preg_replace('/(?<=.).(?=[^@]*@)/', '*', $email);
}

/** Step 1. Returns ['pid' => ..., 'email' => masked], ['logged_in' => true], ['account_exists' => true], or ['error' => code]. */
function mmn_lms_registration_begin($name, $email, $phone, $pass, $next = '', $product = '')
{
    $ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $key   = 'mmn_reg_' . md5($ip);
    $tries = (int) get_transient($key);
    if ($tries >= 20) {
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

    // 1. Check if user already exists or already purchased on LMS
    if (function_exists('mmn_lms_ready') && mmn_lms_ready()) {
        $check_res = mmn_lms_send('check', [
            'email'      => $email,
            'product_id' => $product,
        ]);
        if ($check_res[0] && is_array($check_res[1])) {
            $lms_data = $check_res[1];
            if (! empty($lms_data['already_purchased'])) {
                return [
                    'already_purchased' => true,
                    'message'           => 'You already own this course! Redirecting to your LMS...',
                    'redirect'          => rtrim(mmn_lms_setting('app_url'), '/') . '/my-courses',
                ];
            }
            if (! empty($lms_data['exists'])) {
                // Ensure WP user exists as well for local checkout session
                $existing_user = get_user_by('email', $email);
                if (! $existing_user) {
                    $new_id = wp_create_user($email, $pass, $email);
                    if (! is_wp_error($new_id)) {
                        wp_update_user([
                            'ID'           => $new_id,
                            'display_name' => $name ?: ($lms_data['name'] ?? $name),
                            'first_name'   => $name ?: ($lms_data['name'] ?? $name),
                        ]);
                        update_user_meta($new_id, 'phone', '+91' . $clean_phone);
                        $existing_user = get_user_by('id', $new_id);
                    }
                }
                if ($existing_user) {
                    wp_set_current_user($existing_user->ID);
                    wp_set_auth_cookie($existing_user->ID, true);
                    return [
                        'account_exists' => true,
                        'logged_in'      => true,
                        'email'          => $email,
                        'user_id'        => $existing_user->ID,
                        'message'        => 'Account already exists. Proceeding directly to payment...',
                    ];
                }
            }
        }
    }

    // 2. Check if local WordPress account already exists
    if ($existing_user_id = email_exists($email)) {
        $auth_user = wp_authenticate($email, $pass);
        if (! is_wp_error($auth_user)) {
            wp_set_current_user($auth_user->ID);
            wp_set_auth_cookie($auth_user->ID, true);

            return [
                'account_exists' => true,
                'logged_in'      => true,
                'user_id'        => $auth_user->ID,
                'next'           => $next,
                'message'        => 'Account already exists. Proceeding to payment...',
            ];
        }

        // Even if password was different, recognize that account exists and let them proceed to payment
        $existing_user = get_user_by('id', $existing_user_id);
        wp_set_current_user($existing_user->ID);
        wp_set_auth_cookie($existing_user->ID, true);

        return [
            'account_exists' => true,
            'logged_in'      => true,
            'email'          => $email,
            'user_id'        => $existing_user_id,
            'message'        => 'Account already exists with this email. Proceeding directly to payment...',
        ];
    }

    $phone_formatted = '+91' . $clean_phone;
    $code = (string) random_int(100000, 999999);
    $pid  = strtolower(wp_generate_password(24, false));
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
 * Returns ['user_id' => id, 'next' => url] or ['error' => code, 'keep' => bool].
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

        return ['user_id' => $existing_id, 'next' => $pending['next']];
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

    return ['user_id' => $user_id, 'next' => $pending['next']];
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
    isset($result['error']) ? mmn_lms_ajax_fail($result['error']) : wp_send_json_success($result);
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
    isset($result['error']) ? mmn_lms_ajax_fail($result['error']) : wp_send_json_success($result);
}
add_action('wp_ajax_nopriv_mmn_reg_verify', 'mmn_lms_handle_ajax_reg_verify');
add_action('wp_ajax_mmn_reg_verify', 'mmn_lms_handle_ajax_reg_verify');

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
                <input id="mmn_phone" type="tel" name="phone" required maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" placeholder="9876543210" style="padding-left:46px;" autocomplete="tel">
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
