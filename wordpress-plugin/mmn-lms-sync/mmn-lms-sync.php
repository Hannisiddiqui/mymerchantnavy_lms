<?php
/**
 * Plugin Name: MMN LMS Sync
 * Description: Sends new WordPress students and successful payments to the My Merchant Navy LMS, and signs the student in to the LMS after paying.
 * Version:     1.0.0
 * Requires PHP: 7.4
 *
 * Setup and the two lines to add to your Razorpay success code: see docs/WORDPRESS.md in the LMS repository.
 */

if (! defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------------------------------- settings

/**
 * Settings come from Settings > LMS link, or from wp-config.php (which wins, and keeps the secret out of the database):
 *   define('MMN_LMS_API_URL', 'https://api.mymerchantnavy.com/api/v1');
 *   define('MMN_LMS_APP_URL', 'https://lms.mymerchantnavy.com');
 *   define('MMN_LMS_SECRET',  '...same value as LMS_WP_SECRET on the LMS server...');
 */
function mmn_lms_setting($key)
{
    $constant = 'MMN_LMS_' . strtoupper($key);
    if (defined($constant) && constant($constant) !== '') {
        return constant($constant);
    }

    return (string) get_option('mmn_lms_' . $key, '');
}

function mmn_lms_ready()
{
    return mmn_lms_setting('api_url') !== '' && mmn_lms_setting('secret') !== '' && mmn_lms_setting('app_url') !== '';
}

// ---------------------------------------------------------------------------------------------------- talking to the LMS

/** Sends one signed message. Returns [true, data] or [false, "what went wrong"]. */
function mmn_lms_send($path, array $payload)
{
    if (! mmn_lms_ready()) {
        return [false, 'The LMS link is not set up (Settings > LMS link).'];
    }
    $body      = wp_json_encode($payload);
    $timestamp = (string) time();
    $response  = wp_remote_post(rtrim(mmn_lms_setting('api_url'), '/') . '/integrations/wordpress/' . $path, [
        'timeout' => 20,
        'headers' => [
            'Content-Type'      => 'application/json',
            'Accept'            => 'application/json',
            'X-MMN-Timestamp'   => $timestamp,
            'X-MMN-Signature'   => hash_hmac('sha256', $timestamp . '.' . $body, mmn_lms_setting('secret')),
        ],
        'body'    => $body,
    ]);
    if (is_wp_error($response)) {
        return [false, $response->get_error_message()];
    }
    $code = wp_remote_retrieve_response_code($response);
    $json = json_decode(wp_remote_retrieve_body($response), true);
    if ($code >= 200 && $code < 300) {
        return [true, isset($json['data']) ? $json['data'] : []];
    }

    // 4xx means our message is wrong (retrying will not help); 5xx and timeouts are worth retrying.
    $message = isset($json['message']) ? $json['message'] : 'HTTP ' . $code;

    return [false, $message, $code >= 500 || $code === 429];
}

/** Sends now; when the LMS is unreachable, tries again later (1, 5, 30, 120, 720 minutes). */
function mmn_lms_send_with_retry($path, array $payload, $attempt = 0)
{
    $result = mmn_lms_send($path, $payload);
    if ($result[0]) {
        return $result;
    }
    error_log('[MMN LMS] ' . $path . ' failed: ' . $result[1]);
    $retryable = ! isset($result[2]) || $result[2];
    $delays    = [60, 300, 1800, 7200, 43200];
    if ($retryable && isset($delays[$attempt])) {
        wp_schedule_single_event(time() + $delays[$attempt], 'mmn_lms_retry', [$path, $payload, $attempt + 1]);
    }

    return $result;
}
add_action('mmn_lms_retry', 'mmn_lms_send_with_retry', 10, 3);

function mmn_lms_user($user)
{
    if ($user instanceof WP_User) {
        return $user;
    }
    if (is_numeric($user)) {
        return get_user_by('id', (int) $user);
    }

    return is_email($user) ? get_user_by('email', $user) : false;
}

function mmn_lms_student_payload(WP_User $user)
{
    $phone = get_user_meta($user->ID, 'billing_phone', true);
    $phone = $phone ? $phone : get_user_meta($user->ID, 'phone', true);
    $name  = trim($user->first_name . ' ' . $user->last_name);

    return [
        'email'      => $user->user_email,
        'name'       => $name !== '' ? $name : $user->display_name,
        'phone'      => (string) $phone,
        'wp_user_id' => $user->ID,
    ];
}

// ---------------------------------------------------------------------------------------------------- 1. registration

/** Every new WordPress account also gets an LMS account (without any course). */
function mmn_lms_on_register($user_id)
{
    $user = get_user_by('id', $user_id);
    if ($user && mmn_lms_ready()) {
        mmn_lms_send_with_retry('student', mmn_lms_student_payload($user));
    }
}
add_action('user_register', 'mmn_lms_on_register', 20);

// ---------------------------------------------------------------------------------------------------- 2. payment

/**
 * Call this when a payment has been VERIFIED as successful (after checking the Razorpay signature):
 *
 *     do_action('mmn_lms_payment_success', $user_id, $product_id, $order_id);
 *
 * $user_id: WordPress user id, WP_User or email. $product_id: the id you typed on the course in the LMS admin.
 * $order_id: optional, only for the record.
 */
function mmn_lms_payment_success($user, $product_id, $order_id = '')
{
    $wp_user = mmn_lms_user($user);
    if (! $wp_user) {
        error_log('[MMN LMS] payment for an unknown user');

        return false;
    }

    return mmn_lms_send_with_retry('enroll', mmn_lms_student_payload($wp_user) + [
        'product_id' => (string) $product_id,
        'order_id'   => (string) $order_id,
    ]);
}
add_action('mmn_lms_payment_success', 'mmn_lms_payment_success', 10, 3);

// ---------------------------------------------------------------------------------------------------- 3. signing the student in to the LMS

/** A one-time link that signs this student in to the LMS (valid for 2 minutes, works once). */
function mmn_lms_login_url($user)
{
    $wp_user = mmn_lms_user($user);
    if (! $wp_user || ! mmn_lms_ready()) {
        return rtrim(mmn_lms_setting('app_url'), '/') . '/login';
    }
    $payload = rtrim(strtr(base64_encode(wp_json_encode([
        'e' => strtolower($wp_user->user_email),
        'x' => time() + 120,
        'j' => bin2hex(random_bytes(8)),
    ])), '+/', '-_'), '=');

    return rtrim(mmn_lms_setting('app_url'), '/') . '/sso#t=' . $payload . '.' . hash_hmac('sha256', $payload, mmn_lms_setting('secret'));
}

/** A link for the logged-in student that creates the sign-in link at the moment it is clicked (so it never expires on the page). */
function mmn_lms_go_url()
{
    return wp_nonce_url(add_query_arg('mmn_lms_go', '1', home_url('/')), 'mmn_lms_go');
}

function mmn_lms_handle_go()
{
    if (! isset($_GET['mmn_lms_go'])) {
        return;
    }
    if (! is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(mmn_lms_go_url()));
        exit;
    }
    if (! wp_verify_nonce(isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '', 'mmn_lms_go')) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
    // wp_redirect (not wp_safe_redirect): the LMS is a different site, on purpose.
    wp_redirect(mmn_lms_login_url(wp_get_current_user()));
    exit;
}
add_action('template_redirect', 'mmn_lms_handle_go');

/** [mmn_lms_button text="Go to my course"] for the thank-you page, "My account" and emails. */
function mmn_lms_button_shortcode($atts)
{
    $atts = shortcode_atts(['text' => 'Go to my course'], $atts);
    if (! is_user_logged_in() || ! mmn_lms_ready()) {
        return '';
    }

    return '<a class="button mmn-lms-button" style="display:inline-block;padding:12px 22px;background:#1d4ed8;color:#fff;border-radius:8px;text-decoration:none;font-weight:600" href="'
        . esc_url(mmn_lms_go_url()) . '">' . esc_html($atts['text']) . '</a>';
}
add_shortcode('mmn_lms_button', 'mmn_lms_button_shortcode');

// ---------------------------------------------------------------------------------------------------- admin screen

add_action('admin_menu', function () {
    add_options_page('LMS link', 'LMS link', 'manage_options', 'mmn-lms-sync', 'mmn_lms_settings_page');
});

add_action('admin_init', function () {
    foreach (['api_url', 'app_url', 'secret'] as $key) {
        register_setting('mmn_lms', 'mmn_lms_' . $key, ['sanitize_callback' => 'sanitize_text_field']);
    }
});

add_action('admin_post_mmn_lms_test', function () {
    if (! current_user_can('manage_options')) {
        wp_die('Not allowed');
    }
    check_admin_referer('mmn_lms_test');
    $me     = wp_get_current_user();
    $result = mmn_lms_send('student', mmn_lms_student_payload($me));
    $message = $result[0] ? 'Connected. Your account (' . $me->user_email . ') is now in the LMS.' : 'Failed: ' . $result[1];
    wp_safe_redirect(add_query_arg(['page' => 'mmn-lms-sync', 'mmn_msg' => rawurlencode($message)], admin_url('options-general.php')));
    exit;
});

function mmn_lms_settings_page()
{
    ?>
    <div class="wrap">
        <h1>LMS link</h1>
        <?php if (isset($_GET['mmn_msg'])) : ?>
            <div class="notice notice-info"><p><?php echo esc_html(wp_unslash(rawurldecode($_GET['mmn_msg']))); ?></p></div>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php settings_fields('mmn_lms'); ?>
            <table class="form-table">
                <tr><th>LMS API address</th><td><input class="regular-text" name="mmn_lms_api_url" value="<?php echo esc_attr(get_option('mmn_lms_api_url', 'https://api.mymerchantnavy.com/api/v1')); ?>"></td></tr>
                <tr><th>LMS student site</th><td><input class="regular-text" name="mmn_lms_app_url" value="<?php echo esc_attr(get_option('mmn_lms_app_url', 'https://lms.mymerchantnavy.com')); ?>"></td></tr>
                <tr><th>Shared secret</th><td><input class="regular-text" type="password" name="mmn_lms_secret" value="<?php echo esc_attr(get_option('mmn_lms_secret')); ?>" autocomplete="off">
                    <p class="description">The same value as LMS_WP_SECRET on the LMS server. Better: put it in wp-config.php as MMN_LMS_SECRET.</p></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <hr>
        <h2>Test the connection</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mmn_lms_test"><?php wp_nonce_field('mmn_lms_test'); submit_button('Send my own account to the LMS', 'secondary'); ?>
        </form>
    </div>
    <?php
}
