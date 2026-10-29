<?php
/**
 * Buying, from any page.
 *
 * Any button or link can start a purchase:
 *     <button class="mmn-buy" data-product="gp-rating-pass">Get online Pass</button>      (or [mmn_buy product="..." text="..."])
 *     <a href="?mmn_buy=gp-rating-pass">Get online Pass</a>
 * A visitor who is not logged in first sees the registration popup (details, emailed code); right after the code is accepted
 * the Razorpay payment window opens. After a successful payment the student is sent into the LMS, signed in, on the course
 * they bought. A button with data-mmn-register (and no product) opens the same popup for registration only.
 *
 * Server side: the price comes from Settings > LMS link, never from the browser; Razorpay's signature is verified; a Razorpay
 * webhook does the same if the student closes the page after paying.
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
        if (count($part) >= 3 && $part[0] !== '' && is_numeric($part[2]) && (float) $part[2] >= 0) {
            $products[$part[0]] = ['id' => $part[0], 'title' => $part[1], 'price' => (float) $part[2]];
        }
    }

    return $products;
}

function mmn_lms_razorpay_ready()
{
    return mmn_lms_setting('rzp_key_id') !== '' && mmn_lms_setting('rzp_key_secret') !== '' && mmn_lms_products();
}

/** Where the student goes after paying (or after a free sign-up): into the LMS, on the course they bought. */
function mmn_lms_after_payment_url(WP_User $user, $order_post_id = 0)
{
    if (get_option('mmn_lms_auto_redirect', 'yes') === 'yes') {
        $path = $order_post_id ? (string) get_post_meta($order_post_id, 'lms_path', true) : '';

        return mmn_lms_login_url($user, $path);
    }

    return mmn_lms_setting('thanks_url') !== '' ? mmn_lms_setting('thanks_url') : home_url('/');
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

/**
 * Marks the order paid and gives the course in the LMS. Safe to call twice (the LMS ignores repeats).
 * The LMS answers with the course(s) the product gives; the student is later sent to that course.
 */
function mmn_lms_order_paid($post_id, $payment_id)
{
    if (get_post_meta($post_id, 'status', true) === 'paid') {
        return;
    }
    update_post_meta($post_id, 'status', 'paid');
    update_post_meta($post_id, 'payment_id', sanitize_text_field($payment_id));

    // The confirmed payment time, amount (rupees) and payment id go with the message, so the LMS dates the course from the real purchase.
    $extra = [
        'amount'        => round(((int) get_post_meta($post_id, 'amount', true)) / 100, 2),
        'payment_id'    => ($payment_id === 'free') ? null : sanitize_text_field($payment_id),
        'purchase_date' => gmdate('c'),
    ];
    $result = mmn_lms_payment_success((int) get_post_field('post_author', $post_id), get_post_meta($post_id, 'product', true), 'mmn-' . $post_id, $extra);
    $path   = '/my-courses';
    if ($result && $result[0] && ! empty($result[1]['courses']) && count($result[1]['courses']) === 1) {
        $path = '/my-courses/' . (int) $result[1]['courses'][0]['id'];
    }
    update_post_meta($post_id, 'lms_path', $path);
}

// ---------------------------------------------------------------------------------------------------- the button

/** [mmn_buy product="id" text="Buy now"]: the same as writing <button class="mmn-buy" data-product="id">. */
function mmn_lms_buy_shortcode($atts)
{
    $atts     = shortcode_atts(['product' => '', 'text' => 'Buy now'], $atts);
    $products = mmn_lms_products();
    if (! isset($products[$atts['product']]) || ! mmn_lms_ready()) {
        return current_user_can('manage_options') ? '<p><em>[mmn_buy]: finish Settings > LMS link (the connection, and this product under Courses for sale).</em></p>' : '';
    }
    $style = 'display:inline-block;padding:12px 22px;background:#1d4ed8;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;border:0;cursor:pointer;font-size:16px';

    return '<button type="button" class="mmn-buy" style="' . esc_attr($style) . '" data-product="' . esc_attr($atts['product']) . '">' . esc_html($atts['text']) . '</button>';
}
add_shortcode('mmn_buy', 'mmn_lms_buy_shortcode');

/** The script that makes every ".mmn-buy" / "[data-mmn-register]" / "?mmn_buy=" work. Printed on every public page. */
function mmn_lms_front_script()
{
    if (is_admin() || ! mmn_lms_ready()) {
        return;
    }
    $products = [];
    foreach (mmn_lms_products() as $id => $p) {
        if ($p['price'] == 0 || mmn_lms_razorpay_ready()) {
            $products[$id] = ['title' => $p['title'], 'free' => $p['price'] == 0];
        }
    }
    $cfg = wp_json_encode([
        'ajax'     => admin_url('admin-ajax.php'),
        'nonce'    => is_user_logged_in() ? wp_create_nonce('mmn_rzp') : '',
        'loggedIn' => is_user_logged_in(),
        'products' => (object) $products,
        'loginUrl' => wp_login_url(),
        'site'     => get_bloginfo('name'),
    ]);
    ?>
    <style>
    .mmn-ov{position:fixed;inset:0;background:rgba(17,24,39,.7);display:flex;align-items:center;justify-content:center;z-index:999999;padding:16px}
    .mmn-box{background:#fff;width:100%;max-width:440px;border-radius:18px;padding:28px 24px;position:relative;box-shadow:0 20px 25px -5px rgba(0,0,0,.2);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;max-height:94vh;overflow:auto}
    .mmn-box h3{font-size:20px;font-weight:800;color:#111827;margin:0 0 6px}.mmn-box p{font-size:13px;color:#6b7280;margin:0 0 16px}
    .mmn-box label{display:block;font-size:12px;font-weight:600;color:#374151;margin:12px 0 4px}
    .mmn-box input{width:100%;padding:11px 13px;border:1px solid #d1d5db;border-radius:8px;font-size:15px;box-sizing:border-box}
    .mmn-box .mmn-go{width:100%;margin-top:16px;padding:12px;background:#1d4ed8;color:#fff;border:0;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer}.mmn-box .mmn-go:disabled{opacity:.6;cursor:wait}
    .mmn-box .mmn-x{position:absolute;top:14px;right:16px;background:none;border:0;font-size:24px;line-height:1;color:#9ca3af;cursor:pointer}
    .mmn-box .mmn-err{background:#fef2f2;color:#b91c1c;padding:9px 12px;border-radius:8px;margin-top:12px;font-size:13px;display:none}
    .mmn-box .mmn-lnk{background:none;border:0;color:#1d4ed8;text-decoration:underline;cursor:pointer;font-size:13px;padding:0;margin-top:12px}
    .mmn-box .mmn-hp{position:absolute;left:-9999px}.mmn-box input.mmn-code{letter-spacing:.5em;text-align:center;font-size:22px}
    .mmn-toast{position:fixed;top:16px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:10px 18px;border-radius:999px;font-size:14px;z-index:999999;display:none}
    </style>
    <script>
    (function () {
        var cfg = <?php echo $cfg; // phpcs:ignore WordPress.Security.EscapeOutput ?>;

        function post(data) {
            var f = new FormData();
            Object.keys(data).forEach(function (k) { f.append(k, data[k]); });
            return fetch(cfg.ajax, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        }
        function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
        var razorpay = null;
        function loadRazorpay() {
            if (window.Razorpay) { return Promise.resolve(); }
            if (!razorpay) {
                razorpay = new Promise(function (ok, no) {
                    var s = document.createElement('script');
                    s.src = 'https://checkout.razorpay.com/v1/checkout.js';
                    s.onload = ok;
                    s.onerror = function () { razorpay = null; no(new Error('Could not load the payment window. Please check your connection and try again.')); };
                    document.head.appendChild(s);
                });
            }
            return razorpay;
        }

        // ---- a small message at the top while the page works (for flows that do not start from a button)
        var toastEl = null;
        function toastUI() {
            return {
                say: function (t) {
                    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'mmn-toast'; document.body.appendChild(toastEl); }
                    toastEl.textContent = t; toastEl.style.display = 'block';
                },
                end: function () { if (toastEl) { toastEl.style.display = 'none'; } },
                opened: function () { if (toastEl) { toastEl.style.display = 'none'; } }
            };
        }
        function buttonUI(b) {
            var label = b.textContent;
            b.dataset.busy = '1';
            return {
                say: function (t) { b.textContent = t; },
                end: function () { b.dataset.busy = ''; b.textContent = label; },
                opened: function () {}
            };
        }

        // ---- the purchase: free sign-up, or Razorpay payment, then into the LMS
        function purchase(product, ui) {
            var info = cfg.products[product];
            function fail(err) { alert(err.message || 'Something went wrong. Please try again.'); ui.end(); }
            if (!info) { fail(new Error('This course cannot be bought right now.')); return; }
            ui.say('Please wait…');
            if (info.free) {
                post({ action: 'mmn_free_enroll', nonce: cfg.nonce, product: product }).then(function (d) {
                    if (!d.success) { throw new Error(d.data || 'Could not sign you up.'); }
                    window.location.href = d.data.redirect;
                }).catch(fail);
                return;
            }
            loadRazorpay().then(function () {
                return post({ action: 'mmn_rzp_create', nonce: cfg.nonce, product: product });
            }).then(function (d) {
                if (!d.success) { throw new Error(d.data || 'Could not start the payment.'); }
                if (d.data && d.data.already_purchased) {
                    ui.say(d.data.message || 'Already enrolled! Taking you to LMS…');
                    window.location.href = d.data.redirect;
                    return;
                }
                var o = d.data;
                new Razorpay({
                    key: o.key, amount: o.amount, currency: 'INR', name: o.site, description: o.title, order_id: o.order_id,
                    prefill: o.prefill, theme: { color: '#1d4ed8' },
                    modal: { ondismiss: ui.end },
                    handler: function (r) {
                        ui.say('Confirming your payment…');
                        post({ action: 'mmn_rzp_verify', nonce: cfg.nonce, razorpay_order_id: r.razorpay_order_id, razorpay_payment_id: r.razorpay_payment_id, razorpay_signature: r.razorpay_signature })
                            .then(function (v) {
                                if (v.success) { window.location.href = v.data.redirect; return; }
                                alert(v.data || 'Your payment was received, but we could not confirm it yet. Please contact support; do not pay again.');
                                ui.end();
                            });
                    }
                }).open();
                ui.opened();
            }).catch(fail);
        }

        // ---- the registration popup (details, then the emailed code)
        var ov = null;
        function closePopup() { if (ov) { ov.remove(); ov = null; } }
        function openRegistration(opts) {
            closePopup();
            ov = document.createElement('div');
            ov.className = 'mmn-ov';
            var title = opts.product && cfg.products[opts.product] ? cfg.products[opts.product].title : '';
            ov.innerHTML =
                '<div class="mmn-box"><button type="button" class="mmn-x" aria-label="Close">&times;</button>' +
                '<div class="mmn-s1"><h3>Student registration</h3><p>' + (title ? 'Create your account for <strong>' + esc(title) + '</strong>, then proceed to payment.' : 'Create your student account.') + ' All fields are compulsory.</p>' +
                '<div class="mmn-hp" style="display:none !important;"><input type="text" class="mmn-hp-field" tabindex="-1" autocomplete="off"></div>' +
                '<label>Full name <span style="color:#ef4444">*</span></label><input type="text" class="mmn-name" autocomplete="name" placeholder="Enter your full name" required>' +
                '<label>Email address <span style="color:#ef4444">*</span></label><input type="email" class="mmn-email" autocomplete="email" placeholder="student@example.com" required>' +
                '<label>Mobile number <span style="color:#ef4444">*</span></label>' +
                '<div style="position:relative;display:flex;align-items:center;">' +
                '<span style="position:absolute;left:12px;font-weight:700;color:#374151;font-size:14px;pointer-events:none;">+91</span>' +
                '<input type="tel" class="mmn-phone" autocomplete="tel" placeholder="9876543210" maxlength="10" inputmode="numeric" style="padding-left:46px;letter-spacing:0.05em;" required>' +
                '</div>' +
                '<label>Create a password (8+ characters) <span style="color:#ef4444">*</span></label><input type="password" class="mmn-pass" autocomplete="new-password" placeholder="At least 8 characters" minlength="8" required>' +
                '<div class="mmn-err"></div><button type="button" class="mmn-go mmn-send">Send me a verification code</button></div>' +
                '<div class="mmn-s2" style="display:none"><h3>Check your email</h3><p class="mmn-sent"></p>' +
                '<label>Verification code</label><input type="text" class="mmn-code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="••••••">' +
                '<div class="mmn-err"></div><button type="button" class="mmn-go mmn-check">' + (opts.product ? 'Verify and continue to payment' : 'Verify and create my account') + '</button>' +
                '<button type="button" class="mmn-lnk mmn-resend">Send the code again</button></div></div>';
            document.body.appendChild(ov);
            var q = function (s) { return ov.querySelector(s); };
            var s1 = q('.mmn-s1'), s2 = q('.mmn-s2'), pid = '';
            function showErr(step, msg) { var e = step.querySelector('.mmn-err'); e.textContent = msg; e.style.display = msg ? 'block' : 'none'; }
            function busy(btn, on, text) {
                btn.dataset.t = btn.dataset.t || btn.textContent;
                btn.disabled = on;
                btn.textContent = on ? text : btn.dataset.t;
            }

            // Restrict phone input to numbers only
            var phoneInput = q('.mmn-phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').slice(0, 10);
                });
            }

            q('.mmn-x').onclick = closePopup;
            ov.addEventListener('mousedown', function (e) { if (e.target === ov) { closePopup(); } });

            q('.mmn-send').onclick = function () {
                var btn = this;
                showErr(s1, '');
                var name = q('.mmn-name').value.trim();
                var email = q('.mmn-email').value.trim();
                var phone = q('.mmn-phone').value.trim();
                var pass = q('.mmn-pass').value;

                if (!name || !email || !phone || !pass) {
                    showErr(s1, 'All fields are compulsory. Please fill in your name, email, mobile number, and password.');
                    return;
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showErr(s1, 'Please enter a valid email address.');
                    return;
                }
                if (!/^[6-9]\d{9}$/.test(phone)) {
                    showErr(s1, 'Please enter a valid 10-digit Indian mobile number (starting with 6, 7, 8, or 9).');
                    return;
                }
                if (pass.length < 8) {
                    showErr(s1, 'Your password must be at least 8 characters long.');
                    return;
                }

                busy(btn, true, 'Verifying…');
                post({
                    action: 'mmn_reg_start',
                    name: name,
                    email: email,
                    phone: phone,
                    password: pass,
                    product: opts.product || '',
                    mmn_hp: q('.mmn-hp-field') ? q('.mmn-hp-field').value : ''
                }).then(function (d) {
                    busy(btn, false);
                    if (!d.success) {
                        showErr(s1, (d.data && d.data.message) || 'Something went wrong. Please try again.');
                        return;
                    }
                    if (d.data && d.data.already_purchased) {
                        closePopup();
                        alert(d.data.message || 'You already own this course! Taking you to your LMS courses...');
                        window.location.href = d.data.redirect || 'https://lms.mymerchantnavy.com/my-courses';
                        return;
                    }
                    if (d.data && (d.data.account_exists || d.data.logged_in)) {
                        // User account already exists on LMS or WordPress. Let them pay immediately!
                        showErr(s1, '');
                        var noticeBox = s1.querySelector('.mmn-exists-box');
                        if (!noticeBox) {
                            noticeBox = document.createElement('div');
                            noticeBox.className = 'mmn-exists-box';
                            noticeBox.style.cssText = 'background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;padding:12px 14px;border-radius:10px;margin-top:14px;font-size:13px;line-height:1.45;';
                            s1.appendChild(noticeBox);
                        }
                        noticeBox.innerHTML = '<div style="font-weight:700;margin-bottom:4px;">Account already exists</div>' +
                            '<div>An account is already registered with <strong>' + esc(email) + '</strong>. Click below to proceed directly to secure payment.</div>' +
                            '<button type="button" class="mmn-go mmn-direct-pay" style="margin-top:10px;background:#15803d;">Proceed to Payment &rarr;</button>';
                        btn.style.display = 'none';
                        noticeBox.querySelector('.mmn-direct-pay').onclick = function () {
                            var payBtn = this;
                            busy(payBtn, true, 'Opening payment…');
                            post({ action: 'mmn_nonce' }).then(function (n) {
                                cfg.nonce = n.data;
                                cfg.loggedIn = true;
                                closePopup();
                                opts.done();
                            }).catch(function () {
                                busy(payBtn, false);
                                alert('Could not initiate payment. Please try again.');
                            });
                        };
                        return;
                    }
                    pid = d.data.pid;
                    q('.mmn-sent').innerHTML = 'We emailed a 6-digit code to <strong>' + esc(d.data.email) + '</strong>.';
                    s1.style.display = 'none';
                    s2.style.display = 'block';
                    q('.mmn-code').focus();
                }).catch(function () {
                    busy(btn, false);
                    showErr(s1, 'Could not reach the server. Please check your connection and try again.');
                });
            };
            q('.mmn-check').onclick = function () {
                var btn = this; showErr(s2, ''); busy(btn, true, 'Checking…');
                post({ action: 'mmn_reg_verify', pid: pid, code: q('.mmn-code').value }).then(function (d) {
                    if (!d.success) { busy(btn, false); showErr(s2, (d.data && d.data.message) || 'That code is not right.'); return; }
                    // logged in now: ask for a fresh security token, then carry on
                    return post({ action: 'mmn_nonce' }).then(function (n) {
                        cfg.nonce = n.data; cfg.loggedIn = true;
                        closePopup();
                        opts.done();
                    });
                }).catch(function () { busy(btn, false); showErr(s2, 'Could not reach the server. Please try again.'); });
            };
            q('.mmn-resend').onclick = function () {
                showErr(s2, '');
                post({ action: 'mmn_reg_resend', pid: pid }).then(function (d) {
                    showErr(s2, d.success ? d.data.message : ((d.data && d.data.message) || ''));
                });
            };
        }

        // ---- start a purchase / a registration from anywhere
        function startBuy(product, ui) {
            if (cfg.loggedIn) { purchase(product, ui); return; }
            openRegistration({
                product: product,
                done: function () { purchase(product, toastUI()); }
            });
        }
        document.addEventListener('click', function (e) {
            var t = e.target.closest ? e.target.closest('.mmn-buy, [data-mmn-buy], [data-mmn-register]') : null;
            if (!t) { return; }
            e.preventDefault();
            var product = t.dataset.product || t.dataset.mmnBuy || '';
            if (product) {
                if (t.dataset.busy) { return; }
                startBuy(product, cfg.loggedIn ? buttonUI(t) : toastUI());
            } else if (!cfg.loggedIn) {
                openRegistration({ done: function () { window.location.href = t.dataset.next || window.location.href; } });
            }
        });
        // a plain link "?mmn_buy=product-id" starts the same thing when the page opens
        var m = /[?&]mmn_buy=([^&#]+)/.exec(window.location.search);
        if (m) {
            var wanted = decodeURIComponent(m[1]);
            try { window.history.replaceState(null, '', window.location.pathname + window.location.search.replace(/([?&])mmn_buy=[^&]*&?/, '$1').replace(/[?&]$/, '') + window.location.hash); } catch (e) {}
            if (cfg.products[wanted]) { window.addEventListener('load', function () { startBuy(wanted, toastUI()); }); }
        }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'mmn_lms_front_script', 20);

// ---------------------------------------------------------------------------------------------------- server side of the button

add_action('wp_ajax_mmn_rzp_create', function () {
    check_ajax_referer('mmn_rzp', 'nonce');
    $products = mmn_lms_products();
    $product  = sanitize_text_field(wp_unslash(isset($_POST['product']) ? $_POST['product'] : ''));
    if (! isset($products[$product]) || ! mmn_lms_razorpay_ready()) {
        wp_send_json_error('This course cannot be bought right now.');
    }
    $user   = wp_get_current_user();

    // Check if user has already purchased and paid for this course
    $existing = get_posts([
        'post_type'   => 'mmn_order',
        'post_status' => 'private',
        'author'      => $user->ID,
        'numberposts' => 1,
        'meta_query'  => [
            ['key' => 'product', 'value' => $product],
            ['key' => 'status', 'value' => 'paid'],
        ],
    ]);
    if (! empty($existing)) {
        wp_send_json_success([
            'already_purchased' => true,
            'redirect'          => mmn_lms_after_payment_url($user, $existing[0]->ID),
            'message'           => 'You already have access to this course! Taking you to LMS...',
        ]);
        return;
    }

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
    wp_send_json_success(['redirect' => mmn_lms_after_payment_url(wp_get_current_user(), $order->ID)]);
});

/** A free sign-up (price 0 in the settings): the course is given at once, nothing to pay. */
add_action('wp_ajax_mmn_free_enroll', function () {
    check_ajax_referer('mmn_rzp', 'nonce');
    $products = mmn_lms_products();
    $product  = sanitize_text_field(wp_unslash(isset($_POST['product']) ? $_POST['product'] : ''));
    if (! isset($products[$product]) || $products[$product]['price'] != 0) {
        wp_send_json_error('This sign-up is not available.');
    }
    $user    = wp_get_current_user();
    $post_id = wp_insert_post([
        'post_type'   => 'mmn_order',
        'post_status' => 'private',
        'post_title'  => $products[$product]['title'] . ' (free) - ' . $user->user_email,
        'post_author' => $user->ID,
        'meta_input'  => ['product' => $product, 'amount' => 0, 'status' => 'created'],
    ]);
    mmn_lms_order_paid($post_id, 'free');
    wp_send_json_success(['redirect' => mmn_lms_after_payment_url($user, $post_id)]);
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
