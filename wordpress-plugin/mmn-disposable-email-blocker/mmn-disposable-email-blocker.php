<?php
/**
 * Plugin Name: MMN Disposable & Temporary Email Blocker
 * Plugin URI:  https://mymerchantnavy.com
 * Description: High-reliability email firewall protecting WordPress registration, WooCommerce checkout, Contact Form 7, WPForms, and Elementor from disposable/temporary emails, throwaway inboxes, and anonymous email services (including ProtonMail).
 * Version:     1.0.0
 * Author:      My Merchant Navy
 * Author URI:  https://mymerchantnavy.com
 * License:     GPL-2.0+
 * Text Domain: mmn-disposable-email-blocker
 */

if (! defined('ABSPATH')) {
    exit;
}

define('MMN_EMAIL_BLOCKER_VERSION', '1.0.0');
define('MMN_EMAIL_BLOCKER_DIR', plugin_dir_path(__FILE__));
define('MMN_EMAIL_BLOCKER_URL', plugin_dir_url(__FILE__));
define('MMN_EMAIL_BLOCKER_FILE', __FILE__);

require_once MMN_EMAIL_BLOCKER_DIR . 'includes/class-detector.php';
require_once MMN_EMAIL_BLOCKER_DIR . 'includes/class-integrations.php';
require_once MMN_EMAIL_BLOCKER_DIR . 'includes/class-admin.php';

// Activation defaults
register_activation_hook(__FILE__, function () {
    if (get_option('mmn_block_temp_mail_enabled') === false) {
        add_option('mmn_block_temp_mail_enabled', 'yes');
    }
    if (get_option('mmn_block_protonmail') === false) {
        add_option('mmn_block_protonmail', 'yes');
    }
    if (get_option('mmn_check_mx_records') === false) {
        add_option('mmn_check_mx_records', 'yes');
    }
});

// Initialize plugin integrations
add_action('plugins_loaded', function () {
    MMN_Email_Integrations::init();
    if (is_admin()) {
        MMN_Email_Admin::init();
    }
});

// Settings link on Plugins list page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=mmn-email-blocker')) . '">' . __('Settings', 'mmn-disposable-email-blocker') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

