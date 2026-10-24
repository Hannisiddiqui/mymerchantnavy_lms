<?php
/**
 * Registration and login forms:  [mmn_register]  and  [mmn_login]
 *
 * Registration has two steps. Step 1 asks name, email, mobile and password and emails a 6-digit code. NO account exists yet.
 * Step 2 asks for the code; when it is right the account is created (with the email marked verified, which the LMS trusts),
 * the student is logged in and sent on: to ?next=... when given, otherwise to the "redirect" attribute, otherwise home.
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

function mmn_lms_form_styles()
{
    return '<style>.mmn-form{max-width:420px}.mmn-form label{display:block;margin:14px 0 4px;font-weight:600}.mmn-form input[type=text],.mmn-form input[type=email],.mmn-form input[type=tel],.mmn-form input[type=password]{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:16px;box-sizing:border-box}.mmn-form .mmn-code{letter-spacing:.5em;text-align:center;font-size:24px}.mmn-form button,.mmn-form input[type=submit]{margin-top:18px;padding:12px 22px;background:#1d4ed8;color:#fff;border:0;border-radius:8px;font-weight:600;font-size:16px;cursor:pointer}.mmn-form .mmn-link{background:none;color:#1d4ed8;padding:0;margin-top:12px;text-decoration:underline}.mmn-form .mmn-error{background:#fef2f2;color:#b91c1c;padding:10px 12px;border-radius:8px;margin-bottom:8px}.mmn-form .mmn-ok{background:#f0fdf4;color:#15803d;padding:10px 12px;border-radius:8px;margin-bottom:8px}.mmn-form .mmn-hp{position:absolute;left:-9999px}</style>';
}

function mmn_lms_pending($pid)
{
    return $pid !== '' ? get_transient('mmn_pending_' . $pid) : false;
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

    if ($step2) {
        $masked = preg_replace('/(?<=.).(?=[^@]*@)/', '*', $pending['email']); ?>
        <form class="mmn-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php if ($note) : ?><div class="<?php echo $code === 'resent' ? 'mmn-ok' : 'mmn-error'; ?>"><?php echo esc_html($note); ?></div><?php endif; ?>
            <p>We emailed a 6-digit code to <strong><?php echo esc_html($masked); ?></strong>. It works for <?php echo (int) MMN_LMS_CODE_MINUTES; ?> minutes.</p>
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

function mmn_lms_send_code($email, $code)
{
    $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

    return wp_mail(
        $email,
        'Your ' . $site . ' verification code: ' . $code,
        "Your verification code is {$code}\n\nIt works for " . MMN_LMS_CODE_MINUTES . " minutes. If you did not ask for it, you can ignore this email.\n\n{$site}"
    );
}

function mmn_lms_code_hash($code)
{
    return hash_hmac('sha256', (string) $code, wp_salt('auth'));
}

/** Step 1: check the form, email the code. No account is created yet. */
function mmn_lms_handle_register()
{
    $next = isset($_POST['next']) ? esc_url_raw(wp_unslash($_POST['next'])) : '';

    if (! isset($_POST['mmn_nonce']) || ! wp_verify_nonce($_POST['mmn_nonce'], 'mmn_register')) {
        mmn_lms_register_back('nonce', $next);
    }
    if (! empty($_POST['website'])) { // the hidden field only bots fill in
        wp_safe_redirect(home_url('/'));
        exit;
    }
    $ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $key   = 'mmn_reg_' . md5($ip);
    $tries = (int) get_transient($key);
    if ($tries >= 10) {
        mmn_lms_register_back('wait', $next);
    }
    set_transient($key, $tries + 1, HOUR_IN_SECONDS);

    $name  = sanitize_text_field(wp_unslash(isset($_POST['name']) ? $_POST['name'] : ''));
    $email = sanitize_email(wp_unslash(isset($_POST['email']) ? $_POST['email'] : ''));
    $phone = preg_replace('/[^0-9+]/', '', wp_unslash(isset($_POST['phone']) ? $_POST['phone'] : ''));
    $pass  = wp_unslash(isset($_POST['password']) ? $_POST['password'] : '');

    if ($name === '' || ! is_email($email) || strlen(preg_replace('/\D/', '', $phone)) < 10) {
        mmn_lms_register_back('fields', $next);
    }
    if (strlen($pass) < 8) {
        mmn_lms_register_back('password', $next);
    }
    if (email_exists($email)) {
        mmn_lms_register_back('exists', $next);
    }

    $code = (string) random_int(100000, 999999);
    $pid  = strtolower(wp_generate_password(24, false));
    set_transient('mmn_pending_' . $pid, [
        'name' => $name, 'email' => $email, 'phone' => $phone, 'pass' => wp_hash_password($pass),
        'code' => mmn_lms_code_hash($code), 'tries' => 0, 'sends' => 1, 'sent' => time(), 'next' => $next,
    ], MMN_LMS_CODE_MINUTES * MINUTE_IN_SECONDS);

    if (! mmn_lms_send_code($email, $code)) {
        delete_transient('mmn_pending_' . $pid);
        mmn_lms_register_back('mailfail', $next);
    }
    mmn_lms_register_back('', $next, $pid);
}
add_action('admin_post_nopriv_mmn_register', 'mmn_lms_handle_register');
add_action('admin_post_mmn_register', 'mmn_lms_handle_register');

/** "Send the code again" (at most once a minute, five times). */
function mmn_lms_handle_resend()
{
    $pid     = isset($_POST['pid']) ? sanitize_key($_POST['pid']) : '';
    $pending = mmn_lms_pending($pid);
    if (! isset($_POST['mmn_nonce']) || ! wp_verify_nonce($_POST['mmn_nonce'], 'mmn_register_verify') || ! $pending) {
        mmn_lms_register_back('expired');
    }
    if (time() - $pending['sent'] < 60 || $pending['sends'] >= 5) {
        mmn_lms_register_back('cooldown', $pending['next'], $pid);
    }
    $code               = (string) random_int(100000, 999999);
    $pending['code']    = mmn_lms_code_hash($code);
    $pending['tries']   = 0;
    $pending['sends']  += 1;
    $pending['sent']    = time();
    set_transient('mmn_pending_' . $pid, $pending, MMN_LMS_CODE_MINUTES * MINUTE_IN_SECONDS);
    mmn_lms_send_code($pending['email'], $code);
    mmn_lms_register_back('resent', $pending['next'], $pid);
}
add_action('admin_post_nopriv_mmn_register_resend', 'mmn_lms_handle_resend');
add_action('admin_post_mmn_register_resend', 'mmn_lms_handle_resend');

/** Step 2: the right code creates the account (email marked verified), logs the student in and sends them on. */
function mmn_lms_handle_verify()
{
    $pid     = isset($_POST['pid']) ? sanitize_key($_POST['pid']) : '';
    $pending = mmn_lms_pending($pid);
    if (! isset($_POST['mmn_nonce']) || ! wp_verify_nonce($_POST['mmn_nonce'], 'mmn_register_verify') || ! $pending) {
        mmn_lms_register_back('expired');
    }
    $next = $pending['next'];

    $pending['tries']++;
    if ($pending['tries'] > 5) {
        delete_transient('mmn_pending_' . $pid);
        mmn_lms_register_back('toomany', $next);
    }
    set_transient('mmn_pending_' . $pid, $pending, MMN_LMS_CODE_MINUTES * MINUTE_IN_SECONDS);

    $given = preg_replace('/\D/', '', wp_unslash(isset($_POST['code']) ? $_POST['code'] : ''));
    if (! hash_equals($pending['code'], mmn_lms_code_hash($given))) {
        mmn_lms_register_back('badcode', $next, $pid);
    }
    if (email_exists($pending['email'])) {
        delete_transient('mmn_pending_' . $pid);
        mmn_lms_register_back('exists', $next);
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
        mmn_lms_register_back('failed', $next);
    }
    // keep the password the student chose (it was stored only as a hash)
    global $wpdb;
    $wpdb->update($wpdb->users, ['user_pass' => $pending['pass']], ['ID' => $user_id]);
    clean_user_cache($user_id);
    delete_transient('mmn_pending_' . $pid);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
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
