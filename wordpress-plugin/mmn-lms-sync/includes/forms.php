<?php
/**
 * Registration and login forms:  [mmn_register]  and  [mmn_login]
 *
 * The registration form creates the WordPress account (which the sync then sends to the LMS), logs the student in and
 * sends them on: to ?next=... when given, otherwise to the "redirect" attribute, otherwise the home page.
 */

if (! defined('ABSPATH')) {
    exit;
}

function mmn_lms_form_message($code)
{
    $messages = [
        'fields'   => 'Please fill in your name, a valid email address, your mobile number and a password.',
        'password' => 'Your password must be at least 8 characters.',
        'exists'   => 'This email is already registered. Please log in instead.',
        'wait'     => 'Too many attempts. Please try again in a while.',
        'failed'   => 'We could not create your account. Please try again.',
        'nonce'    => 'The page expired. Please reload it and try again.',
    ];

    return isset($messages[$code]) ? $messages[$code] : '';
}

function mmn_lms_form_styles()
{
    return '<style>.mmn-form{max-width:420px}.mmn-form label{display:block;margin:14px 0 4px;font-weight:600}.mmn-form input[type=text],.mmn-form input[type=email],.mmn-form input[type=tel],.mmn-form input[type=password]{width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:16px;box-sizing:border-box}.mmn-form button,.mmn-form input[type=submit]{margin-top:18px;padding:12px 22px;background:#1d4ed8;color:#fff;border:0;border-radius:8px;font-weight:600;font-size:16px;cursor:pointer}.mmn-form .mmn-error{background:#fef2f2;color:#b91c1c;padding:10px 12px;border-radius:8px;margin-bottom:8px}.mmn-form .mmn-hp{position:absolute;left:-9999px}</style>';
}

/** [mmn_register redirect="/courses/"] */
function mmn_lms_register_shortcode($atts)
{
    $atts = shortcode_atts(['redirect' => ''], $atts);
    if (is_user_logged_in()) {
        return '<p>You are already registered and logged in.</p>';
    }
    $next  = isset($_GET['next']) ? esc_url_raw(wp_unslash($_GET['next'])) : $atts['redirect'];
    $error = isset($_GET['mmn_err']) ? mmn_lms_form_message(sanitize_key($_GET['mmn_err'])) : '';

    ob_start();
    echo mmn_lms_form_styles(); ?>
    <form class="mmn-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php if ($error) : ?><div class="mmn-error"><?php echo esc_html($error); ?></div><?php endif; ?>
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
        <button type="submit">Create my account</button>
        <p>Already registered? <a href="<?php echo esc_url(wp_login_url($next)); ?>">Log in</a></p>
    </form>
    <?php

    return ob_get_clean();
}
add_shortcode('mmn_register', 'mmn_lms_register_shortcode');

function mmn_lms_register_back($code, $next)
{
    $back = wp_get_referer() ? wp_get_referer() : home_url('/');
    $args = ['mmn_err' => $code];
    if ($next) {
        $args['next'] = rawurlencode($next);
    }
    wp_safe_redirect(add_query_arg($args, remove_query_arg(['mmn_err', 'next'], $back)));
    exit;
}

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
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $key = 'mmn_reg_' . md5($ip);
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

    // Name and phone are saved before WordPress announces the new user, so the LMS gets them straight away.
    $user_id = wp_insert_user([
        'user_login'   => $email,
        'user_email'   => $email,
        'user_pass'    => $pass,
        'first_name'   => $name,
        'display_name' => $name,
        'role'         => 'subscriber',
        'meta_input'   => ['phone' => $phone],
    ]);
    if (is_wp_error($user_id)) {
        mmn_lms_register_back('failed', $next);
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);
    wp_safe_redirect($next ? wp_validate_redirect($next, home_url('/')) : home_url('/'));
    exit;
}
add_action('admin_post_nopriv_mmn_register', 'mmn_lms_handle_register');
add_action('admin_post_mmn_register', 'mmn_lms_handle_register');

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
