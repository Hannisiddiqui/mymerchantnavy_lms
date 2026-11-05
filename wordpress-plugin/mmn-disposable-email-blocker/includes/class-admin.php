<?php

if (! defined('ABSPATH')) {
    exit;
}

class MMN_Email_Admin
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'save_settings']);
        add_action('wp_ajax_mmn_test_email_blocker', [__CLASS__, 'ajax_test_email']);
    }

    public static function add_menu()
    {
        add_options_page(
            'Disposable Email Blocker',
            'Email Blocker',
            'manage_options',
            'mmn-email-blocker',
            [__CLASS__, 'render_page']
        );
    }

    public static function save_settings()
    {
        if (! isset($_POST['mmn_save_email_blocker']) || ! check_admin_referer('mmn_email_blocker_save')) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        update_option('mmn_block_temp_mail_enabled', isset($_POST['mmn_block_temp_mail_enabled']) ? 'yes' : 'no');
        update_option('mmn_block_protonmail', isset($_POST['mmn_block_protonmail']) ? 'yes' : 'no');
        update_option('mmn_check_mx_records', isset($_POST['mmn_check_mx_records']) ? 'yes' : 'no');

        if (isset($_POST['mmn_custom_blocked_domains'])) {
            update_option('mmn_custom_blocked_domains', sanitize_textarea_field(wp_unslash($_POST['mmn_custom_blocked_domains'])));
        }

        if (isset($_POST['mmn_whitelisted_domains'])) {
            update_option('mmn_whitelisted_domains', sanitize_textarea_field(wp_unslash($_POST['mmn_whitelisted_domains'])));
        }

        if (! empty($_POST['mmn_clear_logs'])) {
            delete_option('mmn_blocked_email_logs');
        }

        add_settings_error('mmn_email_blocker_messages', 'settings_saved', 'Settings updated successfully.', 'updated');
    }

    public static function ajax_test_email()
    {
        check_ajax_referer('mmn_test_email_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $email = sanitize_email(wp_unslash(isset($_POST['email']) ? $_POST['email'] : ''));
        if (empty($email)) {
            wp_send_json_error('Please enter a valid email to test.');
        }

        $result = MMN_Email_Detector::inspect($email);
        wp_send_json_success($result);
    }

    public static function render_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $enabled          = get_option('mmn_block_temp_mail_enabled', 'yes') === 'yes';
        $block_proton     = get_option('mmn_block_protonmail', 'yes') === 'yes';
        $check_mx         = get_option('mmn_check_mx_records', 'yes') === 'yes';
        $custom_blocked   = get_option('mmn_custom_blocked_domains', '');
        $whitelisted      = get_option('mmn_whitelisted_domains', '');
        $logs             = get_option('mmn_blocked_email_logs', []);
        $test_nonce       = wp_create_nonce('mmn_test_email_nonce');

        ?>
        <div class="wrap" style="max-width: 1040px; margin-top: 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <div>
                    <h1 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0 0 6px;">
                        🛡️ Disposable &amp; Anonymous Email Blocker
                    </h1>
                    <p style="color: #64748b; font-size: 14px; margin: 0;">
                        Safeguards My Merchant Navy registrations, WooCommerce, and forms from temporary, burner, and anonymous email services.
                    </p>
                </div>
                <div>
                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; font-weight: 600; font-size: 13px; background: <?php echo $enabled ? '#dcfce7; color: #15803d;' : '#fee2e2; color: #b91c1c;'; ?>">
                        ● <?php echo $enabled ? 'Protection Active' : 'Protection Disabled'; ?>
                    </span>
                </div>
            </div>

            <?php settings_errors('mmn_email_blocker_messages'); ?>

            <div style="display: grid; grid-template-columns: 1fr 340px; gap: 24px; margin-top: 16px;">
                <!-- Main Form -->
                <div>
                    <form method="post" action="">
                        <?php wp_nonce_field('mmn_email_blocker_save'); ?>
                        
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <h2 style="font-size: 17px; font-weight: 700; color: #0f172a; margin-top: 0; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
                                Protection Rules &amp; Filters
                            </h2>

                            <table class="form-table" role="presentation" style="margin-top: 10px;">
                                <tr>
                                    <th scope="row" style="padding-top: 12px; font-weight: 600;">Enable Blocker</th>
                                    <td>
                                        <label style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" name="mmn_block_temp_mail_enabled" value="yes" <?php checked($enabled, true); ?>>
                                            <span><strong>Block disposable &amp; temporary emails globally</strong> across registrations and forms</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="font-weight: 600;">Block ProtonMail &amp; Anonymous Mail</th>
                                    <td>
                                        <label style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" name="mmn_block_protonmail" value="yes" <?php checked($block_proton, true); ?>>
                                            <span>Block <strong>ProtonMail</strong> (<code>proton.me</code>, <code>protonmail.com</code>, <code>pm.me</code>) and encrypted privacy services (Tutanota, Skiff)</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="font-weight: 600;">Deep MX Record Check</th>
                                    <td>
                                        <label style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" name="mmn_check_mx_records" value="yes" <?php checked($check_mx, true); ?>>
                                            <span>Inspect DNS MX records to catch unlisted subdomains and custom disposable relays</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="vertical-align: top; padding-top: 14px; font-weight: 600;">Custom Blocked Domains</th>
                                    <td>
                                        <textarea name="mmn_custom_blocked_domains" rows="4" style="width: 100%; font-family: monospace; font-size: 13px;" placeholder="example-temp-mail.com&#10;fakebox.net"><?php echo esc_textarea($custom_blocked); ?></textarea>
                                        <p class="description">Enter domains to block (one per line). Overrides default rules.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="vertical-align: top; padding-top: 14px; font-weight: 600;">Whitelisted Domains</th>
                                    <td>
                                        <textarea name="mmn_whitelisted_domains" rows="3" style="width: 100%; font-family: monospace; font-size: 13px;" placeholder="mymerchantnavy.com&#10;gmail.com"><?php echo esc_textarea($whitelisted); ?></textarea>
                                        <p class="description">Enter trusted domains to exempt from all blocking (one per line).</p>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin-top: 20px; display: flex; gap: 12px; align-items: center;">
                                <button type="submit" name="mmn_save_email_blocker" class="button button-primary button-large" style="background: #0B1F4A; border-color: #0B1F4A; font-weight: 600; padding: 6px 20px; height: auto;">
                                    Save Changes
                                </button>
                                <?php if (! empty($logs)) : ?>
                                    <button type="submit" name="mmn_clear_logs" value="1" class="button button-secondary" onclick="return confirm('Clear recent blocked audit logs?');">
                                        Clear Blocked Audit Logs
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>

                    <!-- Blocked Audit Log Table -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <h2 style="font-size: 17px; font-weight: 700; color: #0f172a; margin: 0;">
                                Recent Blocked Attempts (Audit Log)
                            </h2>
                            <span style="font-size: 13px; color: #64748b;">Showing last <?php echo count($logs); ?> blocked</span>
                        </div>

                        <?php if (empty($logs)) : ?>
                            <p style="color: #64748b; font-size: 14px; margin: 10px 0;">No blocked registration attempts recorded yet. Your system is safe.</p>
                        <?php else : ?>
                            <table class="widefat striped" style="border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
                                <thead>
                                    <tr>
                                        <th style="font-weight: 700;">Timestamp</th>
                                        <th style="font-weight: 700;">Email Address</th>
                                        <th style="font-weight: 700;">IP Address</th>
                                        <th style="font-weight: 700;">Source Form</th>
                                        <th style="font-weight: 700;">Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log) : ?>
                                        <tr>
                                            <td style="font-size: 12.5px; white-space: nowrap;"><?php echo esc_html($log['time']); ?></td>
                                            <td><strong style="color: #b91c1c;"><?php echo esc_html($log['email']); ?></strong></td>
                                            <td style="font-size: 12.5px; font-family: monospace;"><?php echo esc_html($log['ip']); ?></td>
                                            <td><span class="badge" style="background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-size: 12px;"><?php echo esc_html($log['source']); ?></span></td>
                                            <td style="font-size: 12.5px; color: #475569;"><?php echo esc_html($log['reason']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar: Live Interactive Email Tester -->
                <div>
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: sticky; top: 40px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 8px;">
                            🧪 Live Email Tester
                        </h3>
                        <p style="font-size: 13px; color: #64748b; margin-bottom: 16px; line-height: 1.45;">
                            Type any email address (e.g. <code>test@bankwen.com</code> or <code>student@proton.me</code>) to test how the filter responds.
                        </p>

                        <div style="margin-bottom: 12px;">
                            <label for="mmn_test_email_input" style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px;">Email address</label>
                            <input type="email" id="mmn_test_email_input" style="width: 100%; padding: 8px 12px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 8px;" placeholder="name@domain.com">
                        </div>

                        <button type="button" id="mmn_btn_test_email" class="button button-primary" style="width: 100%; background: #2563eb; border-color: #2563eb; font-weight: 600; padding: 6px; height: auto;">
                            Test Email Now
                        </button>

                        <div id="mmn_test_result" style="margin-top: 16px; display: none; padding: 14px; border-radius: 10px; font-size: 13px; line-height: 1.45;"></div>

                        <hr style="margin: 22px 0 18px; border: 0; border-top: 1px solid #f1f5f9;">

                        <div style="font-size: 12.5px; color: #475569; line-height: 1.6;">
                            <strong style="color: #0f172a; display: block; margin-bottom: 6px;">Active Integrations:</strong>
                            ✓ WordPress Core Registration<br>
                            ✓ MMN LMS Sync Popup Registration<br>
                            ✓ WooCommerce Registration &amp; Checkout<br>
                            ✓ Contact Form 7 submissions<br>
                            ✓ WPForms &amp; Elementor Forms<br>
                            ✓ 2,000+ Temp &amp; Burner Domains<br>
                            ✓ ProtonMail &amp; Tutanota Detection
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('mmn_btn_test_email').addEventListener('click', function () {
            var input = document.getElementById('mmn_test_email_input');
            var result = document.getElementById('mmn_test_result');
            var email = input.value.trim();

            if (!email) {
                alert('Please enter an email address to test.');
                return;
            }

            this.disabled = true;
            this.textContent = 'Testing...';
            result.style.display = 'none';

            var data = new FormData();
            data.append('action', 'mmn_test_email_blocker');
            data.append('nonce', '<?php echo esc_js($test_nonce); ?>');
            data.append('email', email);

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    document.getElementById('mmn_btn_test_email').disabled = false;
                    document.getElementById('mmn_btn_test_email').textContent = 'Test Email Now';

                    if (!res.success) {
                        result.style.background = '#fef2f2';
                        result.style.border = '1px solid #fecaca';
                        result.style.color = '#b91c1c';
                        result.innerHTML = '<strong>Error:</strong> ' + res.data;
                        result.style.display = 'block';
                        return;
                    }

                    var d = res.data;
                    result.style.display = 'block';
                    if (d.blocked) {
                        result.style.background = '#fef2f2';
                        result.style.border = '1px solid #fecaca';
                        result.style.color = '#991b1b';
                        result.innerHTML = '<div style="font-weight: 700; font-size: 14px; margin-bottom: 4px; color: #dc2626;">🚫 BLOCKED</div>' +
                            '<div>' + d.reason + '</div>' +
                            '<div style="margin-top: 6px; font-size: 11.5px; color: #7f1d1d;">Domain: ' + (d.domain || 'N/A') + '</div>';
                    } else {
                        result.style.background = '#f0fdf4';
                        result.style.border = '1px solid #bbf7d0';
                        result.style.color = '#166534';
                        result.innerHTML = '<div style="font-weight: 700; font-size: 14px; margin-bottom: 4px; color: #16a34a;">✅ ALLOWED</div>' +
                            '<div>' + d.reason + '</div>' +
                            '<div style="margin-top: 6px; font-size: 11.5px; color: #14532d;">Domain: ' + (d.domain || 'N/A') + '</div>';
                    }
                })
                .catch(function (err) {
                    document.getElementById('mmn_btn_test_email').disabled = false;
                    document.getElementById('mmn_btn_test_email').textContent = 'Test Email Now';
                    result.style.background = '#fef2f2';
                    result.style.border = '1px solid #fecaca';
                    result.style.color = '#b91c1c';
                    result.innerHTML = '<strong>Request Failed:</strong> ' + err.message;
                    result.style.display = 'block';
                });
        });
        </script>
        <?php
    }
}
