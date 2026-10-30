<?php
/**
 * Plugin Name: Universal CRM Connector for Contact Form 7
 * Plugin URI:  https://mymerchantnavy.com
 * Description: Connect Contact Form 7 forms to Odoo CRM, HubSpot, Zoho, and custom REST APIs / Webhooks with visual field mapping, automatic retries, SSRF protection, and audit logs without interfering with Google Sheets or transactional emails.
 * Version:     1.0.0
 * Author:      My Merchant Navy Engineering Team
 * Author URI:  https://mymerchantnavy.com
 * License:     GPL-2.0+
 * Text Domain: universal-crm-connector-cf7
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package UniversalCRMConnector
 */

if (! defined('ABSPATH')) {
    exit;
}

define('UCC_VERSION', '1.0.0');
define('UCC_PLUGIN_FILE', __FILE__);
define('UCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UCC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Core Architecture Classes
require_once UCC_PLUGIN_DIR . 'includes/class-ucc-database.php';
require_once UCC_PLUGIN_DIR . 'includes/class-ucc-cf7.php';
require_once UCC_PLUGIN_DIR . 'includes/class-ucc-mapper.php';
require_once UCC_PLUGIN_DIR . 'includes/class-ucc-dispatcher.php';
require_once UCC_PLUGIN_DIR . 'includes/class-ucc-logger.php';
require_once UCC_PLUGIN_DIR . 'includes/class-ucc-odoo.php';
require_once UCC_PLUGIN_DIR . 'includes/class-ucc-admin.php';

/**
 * Main Plugin Orchestrator
 */
class UCC_Plugin
{
    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Initialize CF7 Submission Listener
        UCC_CF7::instance();

        // Initialize Admin UI if in WP Admin
        if (is_admin()) {
            UCC_Admin::instance();
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);
            add_action('admin_notices', [$this, 'check_dependencies_notice']);
        }

        // Daily cleanup cron
        add_action('ucc_daily_cleanup_event', ['UCC_Logger', 'cleanup_old_logs']);
    }

    /**
     * Plugin Activation: creates DB tables & seeds presets
     */
    public static function activate()
    {
        UCC_Database::create_tables();

        if (! wp_next_scheduled('ucc_daily_cleanup_event')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'ucc_daily_cleanup_event');
        }
    }

    /**
     * Plugin Deactivation: removes cron event; preserves settings and tables by default
     */
    public static function deactivate()
    {
        $timestamp = wp_next_scheduled('ucc_daily_cleanup_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ucc_daily_cleanup_event');
        }
    }

    /**
     * Action links on Plugins admin screen
     */
    public function add_action_links($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=universal-crm-connector')),
            __('Settings & Mappings', 'universal-crm-connector-cf7')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Friendly admin notice if Contact Form 7 is not detected
     */
    public function check_dependencies_notice()
    {
        if (! UCC_CF7::is_cf7_active()) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('Universal CRM Connector:', 'universal-crm-connector-cf7'); ?></strong>
                    <?php esc_html_e('Contact Form 7 does not appear to be active. Please activate Contact Form 7 to enable automated form submission delivery to your CRM.', 'universal-crm-connector-cf7'); ?>
                </p>
            </div>
            <?php
        }
    }
}

// Bootstrap plugin lifecycle
register_activation_hook(__FILE__, ['UCC_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['UCC_Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    UCC_Plugin::instance();
});
