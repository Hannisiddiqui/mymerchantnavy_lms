<?php
/**
 * Registration, in two places that share one engine:
 *   - the page form   [mmn_register]  (and [mmn_login])
 *   - the popup that opens on ANY page (see includes/razorpay.php): a button with class "mmn-buy" or attribute data-mmn-register
 *
 * Registration has two steps. Step 1 asks name, email, mobile and password and emails a 6-digit code. NO account exists yet.
 * Step 2 asks for the code; when it is right the account is created (with the email marked verified, which the LMS trusts)
 * and the student is logged in.
 */

if (! defined('ABSPATH')) {
    exit;
}

const MMN_LMS_CODE_MINUTES = 15;

function mmn_lms_form_message($code)
{
    $messages = [
        'fields'   => 'Please fill in your name, a valid email address, your mobile number and a password.',
        'password' => 'Your password must be at least 8 characters.',
        'exists'   => 'This email is already registered. Please log in instead.',
        'wait'     => 'Too many attempts. Please try again in a while.',
        'failed'   => 'We could not create your account. Please try again.',
        'nonce'    => 'The page expired. Please reload it and try again.',
        'mailfail' => 'We could not send the code. Please check the email address and try again.',
        'expired'  => 'That code has expired. Please start again.',
        'badcode'  => 'That code is not right. Please check your email and try again.',
        'toomany'  => 'Too many wrong codes. Please start again.',
        'cooldown' => 'Please wait a minute before asking for another code.',
        'resent'   => 'A new code has been sent.',
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

/** Step 1. Returns ['pid' => ..., 'email' => masked] or ['error' => code]. */
function mmn_lms_registration_begin($name, $email, $phone, $pass, $next = '')
{
    $ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $key   = 'mmn_reg_' . md5($ip);
    $tries = (int) get_transient($key);
    if ($tries >= 10) {
        return ['error' => 'wait'];
    }
    set_transient($key, $tries + 1, HOUR_IN_SECONDS);

    $name  = sanitize_text_field($name);
    $email = sanitize_email($email);
    $phone = preg_replace('/[^0-9+]/', '', (string) $phone);

    if ($name === '' || ! is_email($email) || strlen(preg_replace('/\D/', '', $phone)) < 10) {
        return ['error' => 'fields'];
    }
    if (strlen((string) $pass) < 8) {
        return ['error' => 'password'];
    }
    if (email_exists($email)) {
        return ['error' => 'exists'];
    }

    $code = (string) random_int(100000, 999999);
    $pid  = strtolower(wp_generate_password(24, false));
    set_transient('mmn_pending_' . $pid, [
        'name' => $name, 'email' => $email, 'phone' => $phone, 'pass' => wp_hash_password($pass),
        'code' => mmn_lms_code_hash($code), 'tries' => 0, 'sends' => 1, 'sent' => time(), 'next' => $next,
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
 * Returns ['user_id' => id, 'next' => url] or ['error' => code, 'keep' => bool] (keep: the same code form can be shown again).
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
    if (email_exists($pending['email'])) {
        delete_transient('mmn_pending_' . $pid);

        return ['error' => 'exists'];
    }

    // Name, phone and "verified" are saved before WordPress announces the new user, so the LMS gets them straight away.
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
    // keep the password the student chose (it was stored only as a hash)
    global $wpdb;
    $wpdb->update($wpdb->users, ['user_pass' => $pending['pass']], ['ID' => $user_id]);
    clean_user_cache($user_id);
    delete_transient('mmn_pending_' . $pid);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    return ['user_id' => $user_id, 'next' => $pending['next']];
}

// ---------------------------------------------------------------------------------------------------- popup (AJAX) endpoints

function mmn_lms_ajax_fail($code)
{
    wp_send_json_error(['code' => $code, 'message' => mmn_lms_form_message($code)]);
}

function mmn_lms_post($key)
{
    return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
}

add_action('wp_ajax_nopriv_mmn_reg_start', function () {
    if (mmn_lms_post('website') !== '') { // the hidden field only bots fill in
        mmn_lms_ajax_fail('failed');
    }
    $result = mmn_lms_registration_begin(mmn_lms_post('name'), mmn_lms_post('email'), mmn_lms_post('phone'), mmn_lms_post('password'));
    isset($result['error']) ? mmn_lms_ajax_fail($result['error']) : wp_send_json_success($result);
});

add_action('wp_ajax_nopriv_mmn_reg_resend', function () {
    $result = mmn_lms_registration_resend(sanitize_key(mmn_lms_post('pid')));
    isset($result['error']) ? mmn_lms_ajax_fail($result['error']) : wp_send_json_success(['message' => mmn_lms_form_message('resent')]);
});

add_action('wp_ajax_nopriv_mmn_reg_verify', function () {
    $result = mmn_lms_registration_finish(sanitize_key(mmn_lms_post('pid')), mmn_lms_post('code'));
    isset($result['error']) ? mmn_lms_ajax_fail($result['error']) : wp_send_json_success(['ok' => true]);
});

/** After the popup has logged the student in, the page asks for a fresh security token (the old one belonged to a visitor). */
add_action('wp_ajax_mmn_nonce', function () {
    wp_send_json_success(wp_create_nonce('mmn_rzp'));
});

// ---------------------------------------------------------------------------------------------------- the page form

function mmn_lms_form_styles()
{
    return '<style>.mmn-form{max-width:420px}.mmn-form label{display:block;margin:14px 0 4px;font-weight:600}.mmn-form input[type=text],.mmn-form input[type=email],.mmn-form input[type=tel],.mmn-form input[type=password]{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:16px;box-sizing:border-box}.mmn-form .mmn-code{letter-spacing:.5em;text-align:center;font-size:24px}.mmn-form button,.mmn-form input[type=submit]{margin-top:18px;padding:12px 22px;background:#1d4ed8;color:#fff;border:0;border-radius:8px;font-weight:600;font-size:16px;cursor:pointer}.mmn-form .mmn-link{background:none;color:#1d4ed8;padding:0;margin-top:12px;text-decoration:underline}.mmn-form .mmn-error{background:#fef2f2;color:#b91c1c;padding:10px 12px;border-radius:8px;margin-bottom:8px}.mmn-form .mmn-ok{background:#f0fdf4;color:#15803d;padding:10px 12px;border-radius:8px;margin-bottom:8px}.mmn-form .mmn-hp{position:absolute;left:-9999px}</style>';
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
            <div class="mmn-hp"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <label for="mmn_name">Full name</label>
            <input id="mmn_name" type="text" name="name" required autocomplete="name">
            <label for="mmn_email">Email address</label>
            <input id="mmn_email" type="email" name="email" required autocomplete="email">
            <label for="mmn_phone">Mobile number</label>
            <input id="mmn_phone" type="tel" name="phone" required autocomplete="tel">
            <label for="mmn_pass">Password</label>
            <input id="mmn_pass" type="password" name="password" required minlength="8" autocomplete="new-password">
            <button type="submit">Send me a verification code</button>
            <p>Already registered? <a href="<?php echo esc_url(wp_login_url($next)); ?>">Log in</a></p>
        </form>
        <?php
    }

    return ob_get_clean();
}
add_shortcode('mmn_register', 'mmn_lms_register_shortcode');

/** Back to the registration page with a message (and the step/ids to keep). */
function mmn_lms_register_back($code, $next = '', $pid = '')
{
    $back = wp_get_referer() ? wp_get_referer() : home_url('/');
    $args = ['mmn_err' => $code];
    if ($next) {
        $args['next'] = rawurlencode($next);
    }
    if ($pid) {
        $args['mmn_step'] = 'verify';
        $args['pid']      = $pid;
    }
    wp_safe_redirect(add_query_arg($args, remove_query_arg(['mmn_err', 'next', 'mmn_step', 'pid'], $back)));
    exit;
}

/** Page form, step 1. */
function mmn_lms_handle_register()
{
    $next = isset($_POST['next']) ? esc_url_raw(wp_unslash($_POST['next'])) : '';
    if (! isset($_POST['mmn_nonce']) || ! wp_verify_nonce($_POST['mmn_nonce'], 'mmn_register')) {
        mmn_lms_register_back('nonce', $next);
    }
    if (mmn_lms_post('website') !== '') {
        wp_safe_redirect(home_url('/'));
        exit;
    }
    $result = mmn_lms_registration_begin(mmn_lms_post('name'), mmn_lms_post('email'), mmn_lms_post('phone'), mmn_lms_post('password'), $next);
    if (isset($result['error'])) {
        mmn_lms_register_back($result['error'], $next);
    }
    mmn_lms_register_back('', $next, $result['pid']);
}
add_action('admin_post_nopriv_mmn_register', 'mmn_lms_handle_register');
add_action('admin_post_mmn_register', 'mmn_lms_handle_register');

function mmn_lms_handle_resend()
{
    $pid     = isset($_POST['pid']) ? sanitize_key($_POST['pid']) : '';
    $pending = mmn_lms_pending($pid);
    if (! isset($_POST['mmn_nonce']) || ! wp_verify_nonce($_POST['mmn_nonce'], 'mmn_register_verify') || ! $pending) {
        mmn_lms_register_back('expired');
    }
    $result = mmn_lms_registration_resend($pid);
    mmn_lms_register_back(isset($result['error']) ? $result['error'] : 'resent', $pending['next'], $pid);
}
add_action('admin_post_nopriv_mmn_register_resend', 'mmn_lms_handle_resend');
add_action('admin_post_mmn_register_resend', 'mmn_lms_handle_resend');

/** Page form, step 2. */
function mmn_lms_handle_verify()
{
    $pid     = isset($_POST['pid']) ? sanitize_key($_POST['pid']) : '';
    $pending = mmn_lms_pending($pid);
    if (! isset($_POST['mmn_nonce']) || ! wp_verify_nonce($_POST['mmn_nonce'], 'mmn_register_verify') || ! $pending) {
        mmn_lms_register_back('expired');
    }
    $result = mmn_lms_registration_finish($pid, mmn_lms_post('code'));
    if (isset($result['error'])) {
        mmn_lms_register_back($result['error'], $pending['next'], ! empty($result['keep']) ? $pid : '');
    }
    $next = $result['next'];
    wp_safe_redirect($next ? wp_validate_redirect($next, home_url('/')) : home_url('/'));
    exit;
}
add_action('admin_post_nopriv_mmn_register_verify', 'mmn_lms_handle_verify');
add_action('admin_post_mmn_register_verify', 'mmn_lms_handle_verify');

/** [mmn_login redirect="/courses/"] */
function mmn_lms_login_shortcode($atts)
{
    $atts = shortcode_atts(['redirect' => ''], $atts);
    if (is_user_logged_in()) {
        return '<p>You are logged in.</p>';
    }
    $next = isset($_GET['next']) ? esc_url_raw(wp_unslash($_GET['next'])) : ($atts['redirect'] ? $atts['redirect'] : home_url('/'));

    return mmn_lms_form_styles() . '<div class="mmn-form">' . wp_login_form(['echo' => false, 'redirect' => $next, 'label_username' => 'Email address']) . '</div>';
}
add_shortcode('mmn_login', 'mmn_lms_login_shortcode');
