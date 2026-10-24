<?php
/**
 * Razorpay checkout:  [mmn_buy product="imu-cet-2027" text="Buy now"]
 *
 * Flow: the button asks the server to create a Razorpay order (the price comes from Settings > LMS link, never from the
 * browser), Razorpay's popup takes the payment, the server verifies Razorpay's signature, gives the course in the LMS and
 * sends the student into it. A Razorpay webhook does the same if the student closes the page after paying.
 */

if (! defined('ABSPATH')) {
    exit;
}

// ---------------------------------------------------------------------------------------------------- products and orders

/** Settings lines "product-id | Course title | price in INR" => ['product-id' => ['id', 'title', 'price']] */
function mmn_lms_products()
{
    $products = [];
    foreach (preg_split('/\r\n|\r|\n/', mmn_lms_setting('products')) as $line) {
        $part = array_map('trim', explode('|', $line));
        if (count($part) >= 3 && $part[0] !== '' && is_numeric($part[2]) && (float) $part[2] > 0) {
            $products[$part[0]] = ['id' => $part[0], 'title' => $part[1], 'price' => (float) $part[2]];
        }
    }

    return $products;
}

function mmn_lms_razorpay_ready()
{
    return mmn_lms_setting('rzp_key_id') !== '' && mmn_lms_setting('rzp_key_secret') !== '' && mmn_lms_products();
}

/** Every payment attempt is kept as a private "LMS order" (visible in the admin menu). */
add_action('init', function () {
    register_post_type('mmn_order', [
        'label'        => 'LMS orders',
        'labels'       => ['name' => 'LMS orders', 'singular_name' => 'LMS order'],
        'public'       => false,
        'show_ui'      => true,
        'menu_icon'    => 'dashicons-cart',
        'supports'     => ['title'],
        'capabilities' => ['create_posts' => 'do_not_allow'],
        'map_meta_cap' => true,
    ]);
});

add_filter('manage_mmn_order_posts_columns', function ($columns) {
    return ['cb' => $columns['cb'], 'title' => 'Order', 'mmn_status' => 'Status', 'mmn_amount' => 'Amount', 'mmn_payment' => 'Razorpay payment', 'date' => 'Date'];
});
add_action('manage_mmn_order_posts_custom_column', function ($column, $post_id) {
    if ($column === 'mmn_status') {
        echo esc_html(get_post_meta($post_id, 'status', true));
    } elseif ($column === 'mmn_amount') {
        echo esc_html('INR ' . number_format(((int) get_post_meta($post_id, 'amount', true)) / 100, 2));
    } elseif ($column === 'mmn_payment') {
        echo esc_html(get_post_meta($post_id, 'payment_id', true));
    }
}, 10, 2);

function mmn_lms_order_by_razorpay_id($rzp_order_id)
{
    $found = get_posts(['post_type' => 'mmn_order', 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => 'rzp_order_id', 'meta_value' => $rzp_order_id]);

    return $found ? $found[0] : null;
}

/** Marks the order paid and gives the course in the LMS. Safe to call twice (the LMS ignores repeats). */
function mmn_lms_order_paid($post_id, $payment_id)
{
    if (get_post_meta($post_id, 'status', true) === 'paid') {
        return;
    }
    update_post_meta($post_id, 'status', 'paid');
    update_post_meta($post_id, 'payment_id', sanitize_text_field($payment_id));
    do_action('mmn_lms_payment_success', (int) get_post_field('post_author', $post_id), get_post_meta($post_id, 'product', true), 'mmn-' . $post_id);
}

// ---------------------------------------------------------------------------------------------------- the button

function mmn_lms_buy_shortcode($atts)
{
    $atts     = shortcode_atts(['product' => '', 'text' => 'Buy now'], $atts);
    $products = mmn_lms_products();
    if (! isset($products[$atts['product']]) || ! mmn_lms_razorpay_ready()) {
        return current_user_can('manage_options') ? '<p><em>[mmn_buy]: finish Settings > LMS link (Razorpay keys and this product).</em></p>' : '';
    }
    $style = 'display:inline-block;padding:12px 22px;background:#1d4ed8;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;border:0;cursor:pointer;font-size:16px';

    if (! is_user_logged_in()) {
        $register = mmn_lms_setting('register_url') !== '' ? mmn_lms_setting('register_url') : wp_login_url();
        $here     = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        return '<a class="mmn-buy-login" style="' . esc_attr($style) . '" href="' . esc_url(add_query_arg('next', rawurlencode($here), $register)) . '">' . esc_html($atts['text']) . '</a>';
    }

    add_action('wp_footer', 'mmn_lms_buy_script');
    wp_enqueue_script('razorpay-checkout', 'https://checkout.razorpay.com/v1/checkout.js', [], null, true);

    return '<button type="button" class="mmn-buy" style="' . esc_attr($style) . '" data-product="' . esc_attr($atts['product']) . '">' . esc_html($atts['text']) . '</button>';
}
add_shortcode('mmn_buy', 'mmn_lms_buy_shortcode');

function mmn_lms_buy_script()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $cfg  = wp_json_encode(['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('mmn_rzp')]);
    ?>
    <script>
    (function () {
        var cfg = <?php echo $cfg; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
        function post(data) {
            var f = new FormData();
            Object.keys(data).forEach(function (k) { f.append(k, data[k]); });
            return fetch(cfg.ajax, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        }
        document.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('.mmn-buy') : null;
            if (!b || b.dataset.busy) { return; }
            e.preventDefault();
            var label = b.textContent;
            function reset() { b.dataset.busy = ''; b.textContent = label; }
            b.dataset.busy = '1';
            b.textContent = 'Please wait…';
            post({ action: 'mmn_rzp_create', nonce: cfg.nonce, product: b.dataset.product }).then(function (d) {
                if (!d.success) { throw new Error(d.data || 'Could not start the payment.'); }
                var o = d.data;
                new Razorpay({
                    key: o.key, amount: o.amount, currency: 'INR', name: o.site, description: o.title, order_id: o.order_id,
                    prefill: o.prefill, theme: { color: '#1d4ed8' },
                    modal: { ondismiss: reset },
                    handler: function (r) {
                        b.textContent = 'Confirming your payment…';
                        post({ action: 'mmn_rzp_verify', nonce: cfg.nonce, razorpay_order_id: r.razorpay_order_id, razorpay_payment_id: r.razorpay_payment_id, razorpay_signature: r.razorpay_signature })
                            .then(function (v) {
                                if (v.success) { window.location.href = v.data.redirect; return; }
                                alert(v.data || 'Your payment was received, but we could not confirm it yet. Please contact support; do not pay again.');
                                reset();
                            });
                    }
                }).open();
            }).catch(function (err) { alert(err.message); reset(); });
        });
    })();
    </script>
    <?php
}

// ---------------------------------------------------------------------------------------------------- server side of the button

add_action('wp_ajax_mmn_rzp_create', function () {
    check_ajax_referer('mmn_rzp', 'nonce');
    $products = mmn_lms_products();
    $product  = sanitize_text_field(wp_unslash(isset($_POST['product']) ? $_POST['product'] : ''));
    if (! isset($products[$product]) || ! mmn_lms_razorpay_ready()) {
        wp_send_json_error('This course cannot be bought right now.');
    }
    $user   = wp_get_current_user();
    $amount = (int) round($products[$product]['price'] * 100);

    $post_id = wp_insert_post([
        'post_type'   => 'mmn_order',
        'post_status' => 'private',
        'post_title'  => $products[$product]['title'] . ' - ' . $user->user_email,
        'post_author' => $user->ID,
        'meta_input'  => ['product' => $product, 'amount' => $amount, 'status' => 'created'],
    ]);
    $response = wp_remote_post('https://api.razorpay.com/v1/orders', [
        'timeout' => 20,
        'headers' => ['Authorization' => 'Basic ' . base64_encode(mmn_lms_setting('rzp_key_id') . ':' . mmn_lms_setting('rzp_key_secret')), 'Content-Type' => 'application/json'],
        'body'    => wp_json_encode(['amount' => $amount, 'currency' => 'INR', 'receipt' => 'mmn-' . $post_id, 'notes' => ['email' => $user->user_email, 'product' => $product]]),
    ]);
    $order = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);
    if (! $order || empty($order['id'])) {
        error_log('[MMN LMS] Razorpay order failed: ' . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)));
        update_post_meta($post_id, 'status', 'failed');
        wp_send_json_error('We could not start the payment. Please try again in a minute.');
    }
    update_post_meta($post_id, 'rzp_order_id', $order['id']);

    $payload = mmn_lms_student_payload($user);
    wp_send_json_success([
        'key'      => mmn_lms_setting('rzp_key_id'),
        'order_id' => $order['id'],
        'amount'   => $amount,
        'title'    => $products[$product]['title'],
        'site'     => get_bloginfo('name'),
        'prefill'  => ['name' => $payload['name'], 'email' => $payload['email'], 'contact' => $payload['phone']],
    ]);
});

add_action('wp_ajax_mmn_rzp_verify', function () {
    check_ajax_referer('mmn_rzp', 'nonce');
    $order_id   = sanitize_text_field(wp_unslash(isset($_POST['razorpay_order_id']) ? $_POST['razorpay_order_id'] : ''));
    $payment_id = sanitize_text_field(wp_unslash(isset($_POST['razorpay_payment_id']) ? $_POST['razorpay_payment_id'] : ''));
    $signature  = sanitize_text_field(wp_unslash(isset($_POST['razorpay_signature']) ? $_POST['razorpay_signature'] : ''));

    $order = mmn_lms_order_by_razorpay_id($order_id);
    $valid = hash_equals(hash_hmac('sha256', $order_id . '|' . $payment_id, mmn_lms_setting('rzp_key_secret')), strtolower($signature));
    if (! $order || (int) $order->post_author !== get_current_user_id() || ! $valid) {
        wp_send_json_error('We could not verify this payment. If money was deducted, please contact support; do not pay again.');
    }
    mmn_lms_order_paid($order->ID, $payment_id);

    $user     = wp_get_current_user();
    $redirect = get_option('mmn_lms_auto_redirect', 'yes') === 'yes'
        ? mmn_lms_login_url($user)
        : (mmn_lms_setting('thanks_url') !== '' ? mmn_lms_setting('thanks_url') : home_url('/'));
    wp_send_json_success(['redirect' => $redirect]);
});

// ---------------------------------------------------------------------------------------------------- webhook (when the student closes the page after paying)

add_action('rest_api_init', function () {
    register_rest_route('mmn-lms/v1', '/razorpay', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) {
            $secret = mmn_lms_setting('rzp_webhook_secret');
            if ($secret === '') {
                return new WP_REST_Response(['message' => 'Webhook secret not set'], 503);
            }
            $body = $request->get_body();
            if (! hash_equals(hash_hmac('sha256', $body, $secret), (string) $request->get_header('x-razorpay-signature'))) {
                return new WP_REST_Response(['message' => 'Bad signature'], 401);
            }
            $event = json_decode($body, true);
            if (isset($event['event']) && in_array($event['event'], ['payment.captured', 'order.paid'], true)) {
                $payment = isset($event['payload']['payment']['entity']) ? $event['payload']['payment']['entity'] : [];
                $order   = isset($payment['order_id']) ? mmn_lms_order_by_razorpay_id($payment['order_id']) : null;
                if ($order && (int) $payment['amount'] === (int) get_post_meta($order->ID, 'amount', true)) {
                    mmn_lms_order_paid($order->ID, $payment['id']);
                }
            }

            return new WP_REST_Response(['ok' => true], 200);
        },
    ]);
});
