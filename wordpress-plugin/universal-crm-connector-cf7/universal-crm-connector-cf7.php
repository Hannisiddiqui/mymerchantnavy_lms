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

        // Auto-sync direct Odoo connection and form mappings on site load
        add_action('init', function () {
            if (get_option('ucc_live_odoo_direct_api_v5') !== 'yes') {
                UCC_Database::sync_live_odoo_and_forms();
                update_option('ucc_live_odoo_direct_api_v5', 'yes');
            }

            // Ensure draft courses stay in draft as requested
            if (get_option('mmn_keep_draft_courses_hidden_v1') !== 'yes') {
                global $wpdb;
                $wpdb->update($wpdb->posts, ['post_status' => 'draft'], ['ID' => 9495]);
                $wpdb->update($wpdb->posts, ['post_status' => 'draft'], ['ID' => 9456]);
                update_option('mmn_keep_draft_courses_hidden_v1', 'yes');
            }
        });

        // Ensure draft courses remain hidden from public catalog queries
        add_action('pre_get_posts', function ($query) {
            if (!is_admin() && $query->is_main_query() && ($query->get('post_type') === 'courses' || is_post_type_archive('courses'))) {
                $query->set('post_status', 'publish');
            }
        });

        // Inject layout CSS for header alignment and modern course buttons
        add_action('wp_head', function () {
            ?>
            <style id="mmn-theme-header-course-fixes">
                /* Ensure desktop Elementor header remains in 1 clean horizontal row */
                @media (min-width: 1025px) {
                    header[data-elementor-type="header"] .elementor-element-4fb0f1a,
                    header.elementor-6507 .elementor-element-4fb0f1a,
                    .elementor-element-4fb0f1a {
                        display: flex !important;
                        flex-direction: row !important;
                        align-items: center !important;
                        justify-content: space-between !important;
                        flex-wrap: nowrap !important;
                        width: 100% !important;
                    }
                    header[data-elementor-type="header"] .elementor-element-d9b473f,
                    header.elementor-6507 .elementor-element-d9b473f {
                        flex: 0 0 auto !important;
                        width: auto !important;
                        max-width: 240px !important;
                    }
                    header[data-elementor-type="header"] .elementor-element-b12b705,
                    header.elementor-6507 .elementor-element-b12b705 {
                        flex: 1 1 auto !important;
                        width: auto !important;
                        display: flex !important;
                        justify-content: center !important;
                    }
                    header[data-elementor-type="header"] .elementor-element-1b662fd,
                    header.elementor-6507 .elementor-element-1b662fd {
                        flex: 0 0 auto !important;
                        width: auto !important;
                        display: flex !important;
                        flex-direction: row !important;
                        align-items: center !important;
                        justify-content: flex-end !important;
                        gap: 12px !important;
                    }
                }

                /* Mobile & Tablet header alignment */
                @media (max-width: 1024px) {
                    header[data-elementor-type="header"] .elementor-element-4fb0f1a,
                    header.elementor-6507 .elementor-element-4fb0f1a,
                    .elementor-element-4fb0f1a {
                        display: flex !important;
                        flex-direction: row !important;
                        align-items: center !important;
                        justify-content: space-between !important;
                        flex-wrap: nowrap !important;
                        width: 100% !important;
                    }
                    header[data-elementor-type="header"] .elementor-element-d9b473f,
                    header.elementor-6507 .elementor-element-d9b473f {
                        flex: 0 0 auto !important;
                        max-width: 180px !important;
                    }
                    header[data-elementor-type="header"] .elementor-element-67b1950,
                    header.elementor-6507 .elementor-element-67b1950 {
                        flex: 0 0 auto !important;
                        margin-left: auto !important;
                    }
                }

                /* Header buttons styling */
                header[data-elementor-type="header"] .elementor-element-18dacdd .elementor-button {
                    background-color: #0B1F4A !important;
                    color: #ffffff !important;
                    border: 1px solid #0B1F4A !important;
                    border-radius: 6px !important;
                    font-weight: 600 !important;
                    padding: 10px 22px !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    transition: all 0.2s ease !important;
                }
                header[data-elementor-type="header"] .elementor-element-18dacdd .elementor-button:hover {
                    background-color: #17326c !important;
                    border-color: #17326c !important;
                }
                header[data-elementor-type="header"] .elementor-element-fb4f449 .elementor-button {
                    background-color: transparent !important;
                    color: #0B1F4A !important;
                    border: 1.5px solid #0B1F4A !important;
                    border-radius: 6px !important;
                    font-weight: 600 !important;
                    padding: 9px 20px !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    transition: all 0.2s ease !important;
                }
                header[data-elementor-type="header"] .elementor-element-fb4f449 .elementor-button:hover {
                    background-color: #0B1F4A !important;
                    color: #ffffff !important;
                }

                /* Tutor LMS Course Cards & Buttons */
                .tutor-course-card, .tutor-card {
                    border-radius: 12px !important;
                    border: 1px solid #e2e8f0 !important;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 2px 4px -2px rgba(0, 0, 0, 0.04) !important;
                    overflow: hidden !important;
                    transition: transform 0.2s ease, box-shadow 0.2s ease !important;
                }
                .tutor-course-card:hover, .tutor-card:hover {
                    transform: translateY(-3px) !important;
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.04) !important;
                }
                .tutor-card-footer {
                    padding: 16px 20px !important;
                    border-top: 1px solid #f1f5f9 !important;
                    background: #ffffff !important;
                }
                .tutor-card-footer .tutor-d-flex {
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    flex-wrap: nowrap !important;
                    gap: 12px !important;
                    width: 100% !important;
                }
                .tutor-card-footer .list-item-price,
                .tutor-card-footer .tutor-item-price {
                    display: flex !important;
                    align-items: baseline !important;
                    gap: 6px !important;
                    font-weight: 700 !important;
                    font-size: 17px !important;
                    color: #0f172a !important;
                    flex: 1 1 auto !important;
                    margin-bottom: 0 !important;
                }
                .tutor-card-footer .tutor-item-price del {
                    font-size: 13px !important;
                    color: #94a3b8 !important;
                    font-weight: 500 !important;
                }
                .tutor-card-footer .list-item-button {
                    flex: 0 0 auto !important;
                    margin-left: auto !important;
                }
                .tutor-card-footer .list-item-button .tutor-btn,
                .tutor-card-footer .tutor-course-list-btn,
                .tutor-card-footer .tutor-btn-outline-primary,
                .tutor-card-footer .tutor-btn {
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    padding: 10px 22px !important;
                    font-size: 14px !important;
                    font-weight: 600 !important;
                    border-radius: 8px !important;
                    color: #ffffff !important;
                    background: #0B1F4A !important;
                    border: 1px solid #0B1F4A !important;
                    text-decoration: none !important;
                    transition: all 0.2s ease !important;
                    white-space: nowrap !important;
                }
                .tutor-card-footer .list-item-button .tutor-btn:hover,
                .tutor-card-footer .tutor-course-list-btn:hover,
                .tutor-card-footer .tutor-btn-outline-primary:hover,
                .tutor-card-footer .tutor-btn:hover {
                    background: #17326c !important;
                    border-color: #17326c !important;
                    color: #ffffff !important;
                    transform: translateY(-1px) !important;
                }
                .tutor-card-footer > .tutor-course-list-btn,
                .tutor-card-footer > .tutor-btn-block {
                    width: 100% !important;
                    text-align: center !important;
                }
            </style>
            <?php
        }, 99);

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

