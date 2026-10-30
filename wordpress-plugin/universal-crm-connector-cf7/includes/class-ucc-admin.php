<?php
/**
 * Admin Panel & AJAX Handlers for Universal CRM Connector.
 *
 * @package UniversalCRMConnector
 */

if (! defined('ABSPATH')) {
    exit;
}

class UCC_Admin
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
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_ucc_save_connection', [$this, 'ajax_save_connection']);
        add_action('wp_ajax_ucc_delete_connection', [$this, 'ajax_delete_connection']);
        add_action('wp_ajax_ucc_save_mapping', [$this, 'ajax_save_mapping']);
        add_action('wp_ajax_ucc_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_ucc_preview_payload', [$this, 'ajax_preview_payload']);
        add_action('wp_ajax_ucc_retry_delivery', [$this, 'ajax_retry_delivery']);
        add_action('wp_ajax_ucc_clear_logs', [$this, 'ajax_clear_logs']);
    }

    public function register_menu()
    {
        add_menu_page(
            __('Universal CRM Connector', 'universal-crm-connector-cf7'),
            __('Universal CRM', 'universal-crm-connector-cf7'),
            'manage_options',
            'universal-crm-connector',
            [$this, 'render_admin_page'],
            'dashicons-networking',
            57
        );
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'universal-crm-connector') === false) {
            return;
        }

        wp_enqueue_style(
            'ucc-admin-css',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            [],
            defined('UCC_VERSION') ? UCC_VERSION : '1.0'
        );

        wp_enqueue_script(
            'ucc-admin-js',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery'],
            defined('UCC_VERSION') ? UCC_VERSION : '1.0',
            true
        );

        wp_localize_script('ucc-admin-js', 'uccAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ucc_admin_nonce'),
        ]);
    }

    public function render_admin_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'universal-crm-connector-cf7'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        $tabs = [
            'dashboard'       => __('Dashboard', 'universal-crm-connector-cf7'),
            'connections'     => __('CRM Connections', 'universal-crm-connector-cf7'),
            'forms'           => __('CF7 Forms', 'universal-crm-connector-cf7'),
            'mapping'         => __('Field Mapping', 'universal-crm-connector-cf7'),
            'test'            => __('Test Connection', 'universal-crm-connector-cf7'),
            'logs'            => __('Delivery Logs', 'universal-crm-connector-cf7'),
            'troubleshooting' => __('Troubleshooting', 'universal-crm-connector-cf7'),
            'documentation'   => __('Documentation & Odoo Guide', 'universal-crm-connector-cf7'),
        ];

        ?>
        <div class="wrap ucc-wrap">
            <div class="ucc-header">
                <div class="ucc-header-left">
                    <span class="dashicons dashicons-networking ucc-header-icon"></span>
                    <div>
                        <h1 class="ucc-title"><?php esc_html_e('Universal CRM Connector for Contact Form 7', 'universal-crm-connector-cf7'); ?></h1>
                        <p class="ucc-subtitle"><?php esc_html_e('Reliable webhook and REST API gateway connecting Contact Form 7 to Odoo and external CRMs.', 'universal-crm-connector-cf7'); ?></p>
                    </div>
                </div>
                <div class="ucc-header-badge">
                    <span class="ucc-pill-status ucc-pill-live">● <?php esc_html_e('Active & Ready', 'universal-crm-connector-cf7'); ?></span>
                </div>
            </div>

            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper ucc-nav-tabs">
                <?php foreach ($tabs as $key => $label) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=universal-crm-connector&tab=' . $key)); ?>" class="nav-tab <?php echo $active_tab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="ucc-tab-content">
                <?php
                switch ($active_tab) {
                    case 'connections':
                        $this->render_tab_connections();
                        break;
                    case 'forms':
                        $this->render_tab_forms();
                        break;
                    case 'mapping':
                        $this->render_tab_mapping();
                        break;
                    case 'test':
                        $this->render_tab_test();
                        break;
                    case 'logs':
                        $this->render_tab_logs();
                        break;
                    case 'troubleshooting':
                        $this->render_tab_troubleshooting();
                        break;
                    case 'documentation':
                        $this->render_tab_documentation();
                        break;
                    case 'dashboard':
                    default:
                        $this->render_tab_dashboard();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * 1. Dashboard Tab
     */
    private function render_tab_dashboard()
    {
        $stats = UCC_Logger::get_stats();
        $connections = UCC_Database::get_connections();
        $forms = UCC_CF7::get_forms();
        $recent_logs = UCC_Logger::get_logs(8);

        ?>
        <div class="ucc-cards-grid">
            <div class="ucc-card-stat">
                <span class="ucc-stat-label"><?php esc_html_e('Total Submissions Dispatched', 'universal-crm-connector-cf7'); ?></span>
                <span class="ucc-stat-val"><?php echo esc_html(number_format_i18n($stats['total'])); ?></span>
            </div>
            <div class="ucc-card-stat">
                <span class="ucc-stat-label"><?php esc_html_e('Delivered Successfully', 'universal-crm-connector-cf7'); ?></span>
                <span class="ucc-stat-val ucc-text-success"><?php echo esc_html(number_format_i18n($stats['successful'])); ?></span>
            </div>
            <div class="ucc-card-stat">
                <span class="ucc-stat-label"><?php esc_html_e('Failed / Retrying', 'universal-crm-connector-cf7'); ?></span>
                <span class="ucc-stat-val <?php echo $stats['failed'] > 0 ? 'ucc-text-danger' : 'ucc-text-muted'; ?>"><?php echo esc_html(number_format_i18n($stats['failed'] + $stats['retrying'])); ?></span>
            </div>
            <div class="ucc-card-stat">
                <span class="ucc-stat-label"><?php esc_html_e('Delivery Success Rate', 'universal-crm-connector-cf7'); ?></span>
                <span class="ucc-stat-val"><?php echo esc_html($stats['success_rate']); ?>%</span>
            </div>
        </div>

        <div class="ucc-grid-two">
            <div class="ucc-box">
                <div class="ucc-box-head">
                    <h2><?php esc_html_e('Connected Contact Form 7 Forms', 'universal-crm-connector-cf7'); ?></h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=universal-crm-connector&tab=forms')); ?>" class="button button-small"><?php esc_html_e('Manage Forms', 'universal-crm-connector-cf7'); ?></a>
                </div>
                <?php if (empty($forms)) : ?>
                    <p class="ucc-empty-text"><?php esc_html_e('No Contact Form 7 forms detected on this site yet.', 'universal-crm-connector-cf7'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Form Name', 'universal-crm-connector-cf7'); ?></th>
                                <th><?php esc_html_e('Target Destination', 'universal-crm-connector-cf7'); ?></th>
                                <th><?php esc_html_e('Source Label', 'universal-crm-connector-cf7'); ?></th>
                                <th><?php esc_html_e('Status', 'universal-crm-connector-cf7'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forms as $f) :
                                $mapping = UCC_Database::get_mapping_for_form($f->id);
                                $conn = $mapping ? UCC_Database::get_connection($mapping->connection_id) : null;
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($f->title); ?></strong> <span class="ucc-tag">ID: <?php echo esc_html($f->id); ?></span></td>
                                    <td><?php echo $conn ? esc_html($conn->name) : '<span class="ucc-text-muted">' . esc_html__('Unmapped', 'universal-crm-connector-cf7') . '</span>'; ?></td>
                                    <td><code><?php echo $mapping ? esc_html($mapping->source_label) : '—'; ?></code></td>
                                    <td>
                                        <?php if ($mapping && $mapping->enabled) : ?>
                                            <span class="ucc-badge-success"><?php esc_html_e('Enabled', 'universal-crm-connector-cf7'); ?></span>
                                        <?php else : ?>
                                            <span class="ucc-badge-muted"><?php esc_html_e('Disabled', 'universal-crm-connector-cf7'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="ucc-box">
                <div class="ucc-box-head">
                    <h2><?php esc_html_e('Recent CRM Deliveries', 'universal-crm-connector-cf7'); ?></h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=universal-crm-connector&tab=logs')); ?>" class="button button-small"><?php esc_html_e('View All Logs', 'universal-crm-connector-cf7'); ?></a>
                </div>
                <?php if (empty($recent_logs)) : ?>
                    <p class="ucc-empty-text"><?php esc_html_e('No delivery attempts logged yet. Use the Test Connection tab to send a test ping!', 'universal-crm-connector-cf7'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Time', 'universal-crm-connector-cf7'); ?></th>
                                <th><?php esc_html_e('Form / Source', 'universal-crm-connector-cf7'); ?></th>
                                <th><?php esc_html_e('Status', 'universal-crm-connector-cf7'); ?></th>
                                <th><?php esc_html_e('Latency', 'universal-crm-connector-cf7'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $l) : ?>
                                <tr>
                                    <td><small><?php echo esc_html(mysql2date('H:i:s d M', $l->created_at)); ?></small></td>
                                    <td>
                                        <strong><?php echo esc_html($l->cf7_form_title); ?></strong><br>
                                        <small class="ucc-text-muted"><?php echo esc_html($l->source_label ?: $l->connection_name); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($l->status === 'success') : ?>
                                            <span class="ucc-badge-success"><?php echo esc_html($l->http_code ?: 200); ?> OK</span>
                                        <?php elseif ($l->status === 'retrying') : ?>
                                            <span class="ucc-badge-warning"><?php echo esc_html($l->http_code ?: 'Err'); ?> Retrying</span>
                                        <?php else : ?>
                                            <span class="ucc-badge-danger"><?php echo esc_html($l->http_code ?: 'Fail'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo esc_html($l->execution_time_ms); ?>ms</small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="ucc-box ucc-banner-info">
            <span class="dashicons dashicons-shield-alt" style="font-size:24px;width:24px;height:24px;margin-right:12px;color:#2563eb;"></span>
            <div>
                <strong><?php esc_html_e('Zero-Conflict Assurance: Google Sheets and CF7 Emails Protected', 'universal-crm-connector-cf7'); ?></strong>
                <p><?php esc_html_e('This connector operates on wpcf7_mail_sent at priority 20 inside safe try/catch isolation. Your Google Sheets connector and standard Contact Form 7 transactional notification emails will never be delayed, modified, or blocked.', 'universal-crm-connector-cf7'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * 2. CRM Connections Tab
     */
    private function render_tab_connections()
    {
        $connections = UCC_Database::get_connections();
        $edit_id = isset($_GET['edit_conn']) ? (int) $_GET['edit_conn'] : 0;
        $active_conn = $edit_id ? UCC_Database::get_connection($edit_id) : null;

        ?>
        <div class="ucc-grid-two">
            <!-- Left: List Connections -->
            <div class="ucc-box">
                <div class="ucc-box-head">
                    <h2><?php esc_html_e('Configured CRM Endpoints', 'universal-crm-connector-cf7'); ?></h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=universal-crm-connector&tab=connections')); ?>" class="button button-primary button-small">+ <?php esc_html_e('New Connection', 'universal-crm-connector-cf7'); ?></a>
                </div>

                <?php if (empty($connections)) : ?>
                    <p class="ucc-empty-text"><?php esc_html_e('No connections created yet. Create your first CRM connection on the right.', 'universal-crm-connector-cf7'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'universal-crm-connector-cf7'); ?></th>
                                <th><?php esc_html_e('Method / Endpoint', 'universal-crm-connector-cf7'); ?></th>
                                <th><?php esc_html_e('Auth', 'universal-crm-connector-cf7'); ?></th>
                                <th><?php esc_html_e('Actions', 'universal-crm-connector-cf7'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($connections as $conn) : ?>
                                <tr class="<?php echo ($active_conn && $active_conn->id == $conn->id) ? 'ucc-row-selected' : ''; ?>">
                                    <td>
                                        <strong><?php echo esc_html($conn->name); ?></strong><br>
                                        <span class="ucc-tag"><?php echo esc_html($conn->crm_type); ?></span>
                                    </td>
                                    <td>
                                        <code><?php echo esc_html($conn->http_method); ?></code> <span title="<?php echo esc_attr($conn->endpoint_url); ?>"><?php echo esc_html(wp_trim_words($conn->endpoint_url, 4, '...')); ?></span>
                                    </td>
                                    <td><small><?php echo esc_html(strtoupper($conn->auth_type)); ?></small></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=universal-crm-connector&tab=connections&edit_conn=' . $conn->id)); ?>" class="button button-small"><?php esc_html_e('Edit', 'universal-crm-connector-cf7'); ?></a>
                                        <button type="button" class="button button-small button-link-delete ucc-btn-delete-conn" data-id="<?php echo esc_attr($conn->id); ?>"><?php esc_html_e('Delete', 'universal-crm-connector-cf7'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Right: Edit / Create Connection Form -->
            <div class="ucc-box">
                <div class="ucc-box-head">
                    <h2><?php echo $active_conn ? esc_html(sprintf(__('Edit Connection: %s', 'universal-crm-connector-cf7'), $active_conn->name)) : esc_html__('Add New CRM Connection', 'universal-crm-connector-cf7'); ?></h2>
                </div>

                <form id="ucc-connection-form" method="post" action="#">
                    <input type="hidden" name="id" value="<?php echo $active_conn ? esc_attr($active_conn->id) : 0; ?>">

                    <p>
                        <label for="ucc_conn_name"><strong><?php esc_html_e('Connection Name', 'universal-crm-connector-cf7'); ?></strong> <span class="ucc-req">*</span></label>
                        <input type="text" id="ucc_conn_name" name="name" class="regular-text widefat" value="<?php echo $active_conn ? esc_attr($active_conn->name) : 'Odoo CRM Webhook'; ?>" required placeholder="e.g. Odoo Production Lead Webhook">
                    </p>

                    <p>
                        <label for="ucc_conn_type"><strong><?php esc_html_e('CRM Preset / Type', 'universal-crm-connector-cf7'); ?></strong></label>
                        <select id="ucc_conn_type" name="crm_type" class="widefat">
                            <option value="odoo" <?php selected($active_conn ? $active_conn->crm_type : 'odoo', 'odoo'); ?>>Odoo CRM (Webhook / Server Action)</option>
                            <option value="generic_webhook" <?php selected($active_conn ? $active_conn->crm_type : '', 'generic_webhook'); ?>>Generic Webhook (JSON POST)</option>
                            <option value="hubspot" <?php selected($active_conn ? $active_conn->crm_type : '', 'hubspot'); ?>>HubSpot CRM API</option>
                            <option value="zoho" <?php selected($active_conn ? $active_conn->crm_type : '', 'zoho'); ?>>Zoho CRM Webhook</option>
                            <option value="custom_rest" <?php selected($active_conn ? $active_conn->crm_type : '', 'custom_rest'); ?>>Custom REST API</option>
                        </select>
                    </p>

                    <p>
                        <label for="ucc_conn_url"><strong><?php esc_html_e('Endpoint URL', 'universal-crm-connector-cf7'); ?></strong> <span class="ucc-req">*</span></label>
                        <input type="url" id="ucc_conn_url" name="endpoint_url" class="widefat" value="<?php echo $active_conn ? esc_attr($active_conn->endpoint_url) : ''; ?>" required placeholder="https://your-crm.example.com/webhook/endpoint">
                        <small class="ucc-desc"><?php esc_html_e('Must be an HTTPS or HTTP URL. Private and loopback IP addresses are blocked by default for SSRF safety.', 'universal-crm-connector-cf7'); ?></small>
                    </p>

                    <div class="ucc-flex-fields">
                        <p style="flex:1;">
                            <label for="ucc_conn_method"><strong><?php esc_html_e('HTTP Method', 'universal-crm-connector-cf7'); ?></strong></label>
                            <select id="ucc_conn_method" name="http_method" class="widefat">
                                <option value="POST" <?php selected($active_conn ? $active_conn->http_method : 'POST', 'POST'); ?>>POST</option>
                                <option value="PUT" <?php selected($active_conn ? $active_conn->http_method : '', 'PUT'); ?>>PUT</option>
                                <option value="PATCH" <?php selected($active_conn ? $active_conn->http_method : '', 'PATCH'); ?>>PATCH</option>
                                <option value="GET" <?php selected($active_conn ? $active_conn->http_method : '', 'GET'); ?>>GET</option>
                            </select>
                        </p>
                        <p style="flex:1;">
                            <label for="ucc_conn_content_type"><strong><?php esc_html_e('Content-Type', 'universal-crm-connector-cf7'); ?></strong></label>
                            <select id="ucc_conn_content_type" name="content_type" class="widefat">
                                <option value="application/json" <?php selected($active_conn ? $active_conn->content_type : 'application/json', 'application/json'); ?>>application/json</option>
                                <option value="application/x-www-form-urlencoded" <?php selected($active_conn ? $active_conn->content_type : '', 'application/x-www-form-urlencoded'); ?>>application/x-www-form-urlencoded</option>
                            </select>
                        </p>
                    </div>

                    <p>
                        <label for="ucc_conn_auth_type"><strong><?php esc_html_e('Authentication Type', 'universal-crm-connector-cf7'); ?></strong></label>
                        <select id="ucc_conn_auth_type" name="auth_type" class="widefat">
                            <option value="none" <?php selected($active_conn ? $active_conn->auth_type : 'none', 'none'); ?>><?php esc_html_e('None (Public Webhook)', 'universal-crm-connector-cf7'); ?></option>
                            <option value="bearer" <?php selected($active_conn ? $active_conn->auth_type : '', 'bearer'); ?>><?php esc_html_e('Bearer Token', 'universal-crm-connector-cf7'); ?></option>
                            <option value="basic" <?php selected($active_conn ? $active_conn->auth_type : '', 'basic'); ?>><?php esc_html_e('Basic Auth (Username / Password)', 'universal-crm-connector-cf7'); ?></option>
                            <option value="custom_header" <?php selected($active_conn ? $active_conn->auth_type : '', 'custom_header'); ?>><?php esc_html_e('Custom Auth Header', 'universal-crm-connector-cf7'); ?></option>
                        </select>
                    </p>

                    <!-- Auth credentials fields (dynamically shown) -->
                    <div id="ucc-auth-creds-wrap" style="background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:14px;">
                        <div class="ucc-auth-field ucc-auth-bearer" style="display:none;">
                            <label for="ucc_auth_token"><strong><?php esc_html_e('Bearer Token / Secret Key', 'universal-crm-connector-cf7'); ?></strong></label>
                            <input type="password" id="ucc_auth_token" name="auth_token" class="widefat" placeholder="<?php echo ($active_conn && $active_conn->auth_type === 'bearer') ? '•••••••• (Leave blank to keep existing)' : 'Paste Bearer token here'; ?>">
                        </div>

                        <div class="ucc-auth-field ucc-auth-basic" style="display:none;">
                            <div class="ucc-flex-fields">
                                <div style="flex:1;">
                                    <label for="ucc_auth_user"><strong><?php esc_html_e('Username', 'universal-crm-connector-cf7'); ?></strong></label>
                                    <input type="text" id="ucc_auth_user" name="auth_user" class="widefat">
                                </div>
                                <div style="flex:1;">
                                    <label for="ucc_auth_pass"><strong><?php esc_html_e('Password / API Key', 'universal-crm-connector-cf7'); ?></strong></label>
                                    <input type="password" id="ucc_auth_pass" name="auth_pass" class="widefat" placeholder="<?php echo ($active_conn && $active_conn->auth_type === 'basic') ? '••••••••' : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="ucc-auth-field ucc-auth-custom" style="display:none;">
                            <div class="ucc-flex-fields">
                                <div style="flex:1;">
                                    <label for="ucc_auth_hname"><strong><?php esc_html_e('Header Name', 'universal-crm-connector-cf7'); ?></strong></label>
                                    <input type="text" id="ucc_auth_hname" name="auth_hname" class="widefat" placeholder="e.g. X-API-Key">
                                </div>
                                <div style="flex:1;">
                                    <label for="ucc_auth_hval"><strong><?php esc_html_e('Header Value / Secret', 'universal-crm-connector-cf7'); ?></strong></label>
                                    <input type="password" id="ucc_auth_hval" name="auth_hval" class="widefat" placeholder="<?php echo ($active_conn && $active_conn->auth_type === 'custom_header') ? '••••••••' : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ucc-flex-fields">
                        <p style="flex:1;">
                            <label for="ucc_conn_timeout"><strong><?php esc_html_e('Timeout (Seconds)', 'universal-crm-connector-cf7'); ?></strong></label>
                            <input type="number" id="ucc_conn_timeout" name="timeout" min="3" max="30" class="widefat" value="<?php echo $active_conn ? (int) $active_conn->timeout : 12; ?>">
                        </p>
                        <p style="flex:1;">
                            <label for="ucc_conn_retry"><strong><?php esc_html_e('Retry Failed Deliveries', 'universal-crm-connector-cf7'); ?></strong></label>
                            <select id="ucc_conn_retry" name="retry_enabled" class="widefat">
                                <option value="1" <?php selected($active_conn ? (int)$active_conn->retry_enabled : 1, 1); ?>><?php esc_html_e('Enabled (up to 3 retries for 5xx/timeouts)', 'universal-crm-connector-cf7'); ?></option>
                                <option value="0" <?php selected($active_conn ? (int)$active_conn->retry_enabled : 1, 0); ?>><?php esc_html_e('Disabled', 'universal-crm-connector-cf7'); ?></option>
                            </select>
                        </p>
                    </div>

                    <div style="margin-top:20px;display:flex;gap:10px;align-items:center;">
                        <button type="submit" class="button button-primary button-large"><?php esc_html_e('Save Connection', 'universal-crm-connector-cf7'); ?></button>
                        <span class="ucc-save-spinner spinner"></span>
                        <span class="ucc-save-msg"></span>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * 3. CF7 Forms Tab
     */
    private function render_tab_forms()
    {
        $forms = UCC_CF7::get_forms();
        $connections = UCC_Database::get_connections();

        ?>
        <div class="ucc-box">
            <div class="ucc-box-head">
                <h2><?php esc_html_e('Detected Contact Form 7 Forms on this Site', 'universal-crm-connector-cf7'); ?></h2>
                <span class="ucc-text-muted"><?php echo count($forms); ?> <?php esc_html_e('forms discovered', 'universal-crm-connector-cf7'); ?></span>
            </div>

            <?php if (empty($forms)) : ?>
                <p class="ucc-empty-text"><?php esc_html_e('No Contact Form 7 forms found. Please ensure Contact Form 7 is active and has at least one contact form created.', 'universal-crm-connector-cf7'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Form Title & ID', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Detected Input Tags', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Target CRM Connection', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Source Label', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Integration Status', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Action', 'universal-crm-connector-cf7'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forms as $f) :
                            $mapping = UCC_Database::get_mapping_for_form($f->id);
                            $tags = UCC_CF7::get_form_tags($f->id);
                            $tag_names = wp_list_pluck($tags, 'name');
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($f->title); ?></strong><br>
                                    <span class="ucc-tag">ID: <?php echo esc_html($f->id); ?></span>
                                    <code>[contact-form-7 id="<?php echo esc_attr($f->id); ?>"]</code>
                                </td>
                                <td>
                                    <div class="ucc-tag-cloud">
                                        <?php foreach ($tag_names as $tname) : ?>
                                            <span class="ucc-tag-item"><?php echo esc_html($tname); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $conn = $mapping ? UCC_Database::get_connection($mapping->connection_id) : null;
                                    echo $conn ? '<strong>' . esc_html($conn->name) . '</strong>' : '<span class="ucc-text-muted">' . esc_html__('None assigned', 'universal-crm-connector-cf7') . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <code><?php echo $mapping ? esc_html($mapping->source_label) : esc_html($f->title); ?></code>
                                </td>
                                <td>
                                    <?php if ($mapping && $mapping->enabled) : ?>
                                        <span class="ucc-badge-success"><?php esc_html_e('Active', 'universal-crm-connector-cf7'); ?></span>
                                    <?php else : ?>
                                        <span class="ucc-badge-muted"><?php esc_html_e('Disabled', 'universal-crm-connector-cf7'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=universal-crm-connector&tab=mapping&form_id=' . $f->id)); ?>" class="button button-primary button-small"><?php esc_html_e('Configure Mapping &rarr;', 'universal-crm-connector-cf7'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * 4. Field Mapping Tab
     */
    private function render_tab_mapping()
    {
        $forms = UCC_CF7::get_forms();
        $connections = UCC_Database::get_connections();

        $selected_form_id = isset($_GET['form_id']) ? (int) $_GET['form_id'] : (! empty($forms) ? $forms[0]->id : 0);
        $mapping = $selected_form_id ? UCC_Database::get_mapping_for_form($selected_form_id) : null;

        $form_tags = $selected_form_id ? UCC_CF7::get_form_tags($selected_form_id) : [];
        $existing_mappings = $mapping && ! empty($mapping->field_mappings) ? json_decode($mapping->field_mappings, true) : [];
        $static_fields = $mapping && ! empty($mapping->static_fields) ? json_decode($mapping->static_fields, true) : [];

        // If no mappings yet, prefill defaults
        if (empty($existing_mappings) && ! empty($form_tags)) {
            foreach ($form_tags as $t) {
                $fname = $t['name'];
                $crm_key = strtolower(str_replace('-', '_', $fname));
                if ($fname === 'whatsapp') $crm_key = 'phone';
                if ($fname === 'full-name') $crm_key = 'name';
                $existing_mappings[] = [
                    'cf7_field'     => $fname,
                    'crm_key'       => $crm_key,
                    'transform'     => ($fname === 'whatsapp') ? 'clean_phone_10' : 'trim',
                    'default_value' => '',
                ];
            }
        }

        ?>
        <div class="ucc-grid-two">
            <!-- Left: Form selector & Mapping Table -->
            <div class="ucc-box">
                <div class="ucc-box-head">
                    <h2><?php esc_html_e('Contact Form 7 Field Mapping Table', 'universal-crm-connector-cf7'); ?></h2>
                </div>

                <div style="background:#f8fafc;padding:14px;border-radius:10px;margin-bottom:16px;border:1px solid #e2e8f0;">
                    <label for="ucc_select_form"><strong><?php esc_html_e('Select Form to Configure:', 'universal-crm-connector-cf7'); ?></strong></label>
                    <select id="ucc_select_form" class="widefat" style="margin-top:6px;" onchange="window.location.href='<?php echo esc_url(admin_url('admin.php?page=universal-crm-connector&tab=mapping&form_id=')); ?>' + this.value;">
                        <?php foreach ($forms as $f) : ?>
                            <option value="<?php echo esc_attr($f->id); ?>" <?php selected($selected_form_id, $f->id); ?>>
                                <?php echo esc_html($f->title); ?> (ID: <?php echo esc_html($f->id); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <form id="ucc-mapping-form" method="post" action="#">
                    <input type="hidden" name="cf7_form_id" value="<?php echo esc_attr($selected_form_id); ?>">

                    <div class="ucc-flex-fields">
                        <p style="flex:1;">
                            <label for="ucc_map_conn"><strong><?php esc_html_e('Destination CRM Connection', 'universal-crm-connector-cf7'); ?></strong></label>
                            <select id="ucc_map_conn" name="connection_id" class="widefat">
                                <?php foreach ($connections as $conn) : ?>
                                    <option value="<?php echo esc_attr($conn->id); ?>" <?php selected($mapping ? $mapping->connection_id : 0, $conn->id); ?>>
                                        <?php echo esc_html($conn->name); ?> (<?php echo esc_html($conn->crm_type); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p style="flex:1;">
                            <label for="ucc_map_source"><strong><?php esc_html_e('Source Label (Category)', 'universal-crm-connector-cf7'); ?></strong></label>
                            <input type="text" id="ucc_map_source" name="source_label" class="widefat" value="<?php echo $mapping ? esc_attr($mapping->source_label) : 'Contact Form 7'; ?>" placeholder="e.g. Data Needed or Sales Funnel">
                        </p>
                    </div>

                    <p>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked($mapping ? $mapping->enabled : 1, 1); ?>>
                            <strong><?php esc_html_e('Enable automated CRM delivery for this form', 'universal-crm-connector-cf7'); ?></strong>
                        </label>
                    </p>

                    <h3 style="margin-top:20px;border-top:1px solid #e2e8f0;padding-top:16px;"><?php esc_html_e('Form Field Mappings', 'universal-crm-connector-cf7'); ?></h3>
                    <p class="ucc-desc"><?php esc_html_e('Match CF7 tags on the left to CRM payload keys on the right. Supports nested paths (e.g. partner.name) and safe data transformations.', 'universal-crm-connector-cf7'); ?></p>

                    <table class="widefat striped" id="ucc-mapping-table">
                        <thead>
                            <tr>
                                <th style="width:25%;"><?php esc_html_e('Contact Form 7 Field', 'universal-crm-connector-cf7'); ?></th>
                                <th style="width:28%;"><?php esc_html_e('CRM Payload Key / Path', 'universal-crm-connector-cf7'); ?></th>
                                <th style="width:25%;"><?php esc_html_e('Transformation', 'universal-crm-connector-cf7'); ?></th>
                                <th style="width:17%;"><?php esc_html_e('Default Value', 'universal-crm-connector-cf7'); ?></th>
                                <th style="width:5%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (! empty($existing_mappings)) :
                                foreach ($existing_mappings as $idx => $m) : ?>
                                    <tr>
                                        <td>
                                            <input type="text" name="mappings[<?php echo $idx; ?>][cf7_field]" value="<?php echo esc_attr($m['cf7_field']); ?>" class="widefat" placeholder="e.g. full-name">
                                        </td>
                                        <td>
                                            <input type="text" name="mappings[<?php echo $idx; ?>][crm_key]" value="<?php echo esc_attr($m['crm_key']); ?>" class="widefat" placeholder="e.g. name or contact.name">
                                        </td>
                                        <td>
                                            <select name="mappings[<?php echo $idx; ?>][transform]" class="widefat">
                                                <option value="trim" <?php selected($m['transform'] ?? 'trim', 'trim'); ?>>Trim whitespace</option>
                                                <option value="clean_phone_10" <?php selected($m['transform'] ?? '', 'clean_phone_10'); ?>>Clean 10-digit Phone</option>
                                                <option value="uppercase" <?php selected($m['transform'] ?? '', 'uppercase'); ?>>Uppercase</option>
                                                <option value="lowercase" <?php selected($m['transform'] ?? '', 'lowercase'); ?>>Lowercase</option>
                                                <option value="date_ymd" <?php selected($m['transform'] ?? '', 'date_ymd'); ?>>Format Date (Y-m-d)</option>
                                                <option value="date_dmy" <?php selected($m['transform'] ?? '', 'date_dmy'); ?>>Format Date (d-m-Y)</option>
                                                <option value="boolean_int" <?php selected($m['transform'] ?? '', 'boolean_int'); ?>>Boolean to 1/0</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="mappings[<?php echo $idx; ?>][default_value]" value="<?php echo esc_attr($m['default_value'] ?? ''); ?>" class="widefat" placeholder="Fallback">
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small button-link-delete ucc-btn-remove-row">&times;</button>
                                        </td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>

                    <div style="margin-top:10px;">
                        <button type="button" class="button button-secondary button-small" id="ucc-btn-add-row">+ <?php esc_html_e('Add Field Row', 'universal-crm-connector-cf7'); ?></button>
                    </div>

                    <h3 style="margin-top:24px;border-top:1px solid #e2e8f0;padding-top:16px;"><?php esc_html_e('Static Constant Fields (Optional)', 'universal-crm-connector-cf7'); ?></h3>
                    <p class="ucc-desc"><?php esc_html_e('Add fixed keys and values included with every dispatch (e.g. _model = crm.lead).', 'universal-crm-connector-cf7'); ?></p>

                    <table class="widefat striped" id="ucc-static-table">
                        <thead>
                            <tr>
                                <th style="width:45%;"><?php esc_html_e('Key Name', 'universal-crm-connector-cf7'); ?></th>
                                <th style="width:45%;"><?php esc_html_e('Static Value', 'universal-crm-connector-cf7'); ?></th>
                                <th style="width:10%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (! empty($static_fields)) :
                                foreach ($static_fields as $sidx => $st) : ?>
                                    <tr>
                                        <td><input type="text" name="static[<?php echo $sidx; ?>][key]" value="<?php echo esc_attr($st['key']); ?>" class="widefat"></td>
                                        <td><input type="text" name="static[<?php echo $sidx; ?>][value]" value="<?php echo esc_attr($st['value']); ?>" class="widefat"></td>
                                        <td><button type="button" class="button button-small button-link-delete ucc-btn-remove-row">&times;</button></td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>

                    <div style="margin-top:10px;">
                        <button type="button" class="button button-secondary button-small" id="ucc-btn-add-static">+ <?php esc_html_e('Add Static Key', 'universal-crm-connector-cf7'); ?></button>
                    </div>

                    <div style="margin-top:24px;display:flex;gap:12px;align-items:center;">
                        <button type="submit" class="button button-primary button-large"><?php esc_html_e('Save Field Mapping', 'universal-crm-connector-cf7'); ?></button>
                        <button type="button" class="button button-secondary button-large" id="ucc-btn-refresh-preview"><?php esc_html_e('Refresh Preview', 'universal-crm-connector-cf7'); ?></button>
                        <span class="ucc-save-spinner spinner"></span>
                        <span class="ucc-save-msg"></span>
                    </div>
                </form>
            </div>

            <!-- Right: Real-time Payload Preview -->
            <div class="ucc-box">
                <div class="ucc-box-head">
                    <h2><?php esc_html_e('Live Outgoing JSON Payload Preview', 'universal-crm-connector-cf7'); ?></h2>
                    <button type="button" class="button button-small" id="ucc-btn-copy-preview"><?php esc_html_e('Copy JSON', 'universal-crm-connector-cf7'); ?></button>
                </div>
                <p class="ucc-desc"><?php esc_html_e('Preview of the JSON data structure generated with sample fake values using the mappings configured on the left.', 'universal-crm-connector-cf7'); ?></p>

                <div class="ucc-code-wrap">
                    <pre id="ucc-json-preview"><code><?php
                        $fake_payload = UCC_Mapper::generate_sample_payload($selected_form_id);
                        echo esc_html(wp_json_encode($fake_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    ?></code></pre>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 5. Test Connection Tab
     */
    private function render_tab_test()
    {
        $connections = UCC_Database::get_connections();
        $forms = UCC_CF7::get_forms();

        ?>
        <div class="ucc-grid-two">
            <div class="ucc-box">
                <div class="ucc-box-head">
                    <h2><?php esc_html_e('Test Connection & Send Test Request', 'universal-crm-connector-cf7'); ?></h2>
                </div>
                <p class="ucc-desc"><?php esc_html_e('Test network reachability, TLS certificates, authentication credentials, and response formatting using fake sample data. No real contact details will be dispatched.', 'universal-crm-connector-cf7'); ?></p>

                <form id="ucc-test-form" method="post" action="#">
                    <p>
                        <label for="ucc_test_conn"><strong><?php esc_html_e('Target CRM Connection', 'universal-crm-connector-cf7'); ?></strong></label>
                        <select id="ucc_test_conn" class="widefat">
                            <?php foreach ($connections as $conn) : ?>
                                <option value="<?php echo esc_attr($conn->id); ?>">
                                    <?php echo esc_html($conn->name); ?> (<?php echo esc_html($conn->endpoint_url); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="ucc_test_preset"><strong><?php esc_html_e('Sample Payload Template', 'universal-crm-connector-cf7'); ?></strong></label>
                        <select id="ucc_test_preset" class="widefat">
                            <option value="form_a"><?php esc_html_e('Form A: Data Needed (Candidate Inquiry)', 'universal-crm-connector-cf7'); ?></option>
                            <option value="form_b"><?php esc_html_e('Form B: Sales Funnel / Eligibility (with qualfied)', 'universal-crm-connector-cf7'); ?></option>
                            <option value="custom"><?php esc_html_e('Custom Test JSON Payload', 'universal-crm-connector-cf7'); ?></option>
                        </select>
                    </p>

                    <p>
                        <label for="ucc_test_payload"><strong><?php esc_html_e('Payload Data (JSON)', 'universal-crm-connector-cf7'); ?></strong></label>
                        <textarea id="ucc_test_payload" class="widefat" rows="11" style="font-family:monospace;font-size:13px;"><?php
                            echo esc_textarea(wp_json_encode([
                                'name'           => 'Rohan Sharma',
                                'email'          => 'rohan.sharma@example.com',
                                'phone'          => '9876543210',
                                'city'           => 'Mumbai',
                                'source'         => 'Data Needed',
                                'cf7_form_id'    => 101,
                                'cf7_form_title' => 'Data Needed',
                                'submission_id'  => wp_generate_uuid4(),
                                '_model'         => 'crm.lead',
                            ], JSON_PRETTY_PRINT));
                        ?></textarea>
                    </p>

                    <div style="margin-top:20px;display:flex;gap:12px;align-items:center;">
                        <button type="submit" class="button button-primary button-large" id="ucc-btn-send-test"><?php esc_html_e('Send Test Request Now', 'universal-crm-connector-cf7'); ?></button>
                        <span class="ucc-test-spinner spinner"></span>
                    </div>
                </form>
            </div>

            <div class="ucc-box">
                <div class="ucc-box-head">
                    <h2><?php esc_html_e('Live Test Response Console', 'universal-crm-connector-cf7'); ?></h2>
                    <span id="ucc-test-badge" class="ucc-badge-muted"><?php esc_html_e('Awaiting Test', 'universal-crm-connector-cf7'); ?></span>
                </div>

                <div id="ucc-test-results-wrap">
                    <div style="background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;font-size:13px;">
                            <span><strong>HTTP Status:</strong> <span id="ucc-res-status">—</span></span>
                            <span><strong>Round-Trip Latency:</strong> <span id="ucc-res-latency">—</span></span>
                        </div>
                    </div>

                    <div class="ucc-code-wrap">
                        <pre id="ucc-res-body"><code><?php esc_html_e('Click "Send Test Request Now" to inspect the CRM response headers and payload body.', 'universal-crm-connector-cf7'); ?></code></pre>
                    </div>

                    <div id="ucc-res-diagnosis" style="display:none;margin-top:14px;padding:12px;border-radius:8px;"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 6. Delivery Logs Tab
     */
    private function render_tab_logs()
    {
        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        $form_filter = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 25;
        $offset = ($page - 1) * $per_page;

        $total_logs = UCC_Logger::count_logs($status_filter, $form_filter);
        $logs = UCC_Logger::get_logs($per_page, $offset, $status_filter, $form_filter);
        $total_pages = ceil($total_logs / $per_page);

        $forms = UCC_CF7::get_forms();

        ?>
        <div class="ucc-box">
            <div class="ucc-box-head">
                <h2><?php esc_html_e('Audit & Delivery Logs', 'universal-crm-connector-cf7'); ?></h2>
                <div>
                    <button type="button" class="button button-secondary" id="ucc-btn-clear-logs"><?php esc_html_e('Clear Logs', 'universal-crm-connector-cf7'); ?></button>
                </div>
            </div>

            <!-- Filters -->
            <div class="ucc-filters-bar">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="universal-crm-connector">
                    <input type="hidden" name="tab" value="logs">

                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'universal-crm-connector-cf7'); ?></option>
                        <option value="success" <?php selected($status_filter, 'success'); ?>><?php esc_html_e('Success', 'universal-crm-connector-cf7'); ?></option>
                        <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php esc_html_e('Failed', 'universal-crm-connector-cf7'); ?></option>
                        <option value="retrying" <?php selected($status_filter, 'retrying'); ?>><?php esc_html_e('Retrying', 'universal-crm-connector-cf7'); ?></option>
                    </select>

                    <select name="form_id">
                        <option value="0"><?php esc_html_e('All Forms', 'universal-crm-connector-cf7'); ?></option>
                        <?php foreach ($forms as $f) : ?>
                            <option value="<?php echo esc_attr($f->id); ?>" <?php selected($form_filter, $f->id); ?>>
                                <?php echo esc_html($f->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="button"><?php esc_html_e('Filter', 'universal-crm-connector-cf7'); ?></button>
                </form>
            </div>

            <?php if (empty($logs)) : ?>
                <p class="ucc-empty-text"><?php esc_html_e('No log entries match your filter criteria.', 'universal-crm-connector-cf7'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date / Time', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Form', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Destination', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Status', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Latency', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Diagnostic Summary', 'universal-crm-connector-cf7'); ?></th>
                            <th><?php esc_html_e('Actions', 'universal-crm-connector-cf7'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $l) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html(mysql2date('d M Y', $l->created_at)); ?></strong><br>
                                    <small class="ucc-text-muted"><?php echo esc_html(mysql2date('H:i:s', $l->created_at)); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($l->cf7_form_title); ?></strong><br>
                                    <code><?php echo esc_html($l->source_label ?: 'CF7'); ?></code>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($l->connection_name); ?></strong><br>
                                    <small class="ucc-text-muted"><?php echo esc_html($l->http_method); ?> <?php echo esc_html(wp_trim_words($l->endpoint_url, 4, '...')); ?></small>
                                </td>
                                <td>
                                    <?php if ($l->status === 'success') : ?>
                                        <span class="ucc-badge-success"><?php echo esc_html($l->http_code ?: 200); ?> OK</span>
                                    <?php elseif ($l->status === 'retrying') : ?>
                                        <span class="ucc-badge-warning"><?php echo esc_html($l->http_code ?: 'Err'); ?> Retry #<?php echo esc_html($l->retry_count); ?></span>
                                    <?php else : ?>
                                        <span class="ucc-badge-danger"><?php echo esc_html($l->http_code ?: 'Failed'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo esc_html($l->execution_time_ms); ?>ms</small></td>
                                <td>
                                    <?php if ($l->error_message) : ?>
                                        <span class="ucc-text-danger" style="font-size:12.5px;"><?php echo esc_html(wp_trim_words($l->error_message, 10, '...')); ?></span>
                                    <?php else : ?>
                                        <span class="ucc-text-muted" style="font-size:12.5px;">Payload delivered</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small ucc-btn-view-log" data-log="<?php echo esc_attr(wp_json_encode($l)); ?>"><?php esc_html_e('Inspect', 'universal-crm-connector-cf7'); ?></button>
                                    <?php if ($l->status !== 'success') : ?>
                                        <button type="button" class="button button-small button-primary ucc-btn-retry-log" data-id="<?php echo esc_attr($l->id); ?>"><?php esc_html_e('Retry', 'universal-crm-connector-cf7'); ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav" style="margin-top:14px;">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php printf(esc_html__('%d items', 'universal-crm-connector-cf7'), $total_logs); ?></span>
                            <?php
                            echo paginate_links([
                                'base'      => add_query_arg('paged', '%#%'),
                                'format'    => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total'     => $total_pages,
                                'current'   => $page,
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Log Details Modal -->
        <div id="ucc-log-modal" class="ucc-modal" style="display:none;">
            <div class="ucc-modal-box">
                <button type="button" class="ucc-modal-close">&times;</button>
                <h3 id="ucc-modal-title"><?php esc_html_e('Submission Delivery Audit', 'universal-crm-connector-cf7'); ?></h3>
                <div id="ucc-modal-meta" style="margin:12px 0 16px;background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0;font-size:13px;"></div>

                <h4><?php esc_html_e('Outbound Request Payload (Sanitized / PII Protected):', 'universal-crm-connector-cf7'); ?></h4>
                <div class="ucc-code-wrap"><pre id="ucc-modal-request"><code></code></pre></div>

                <h4 style="margin-top:16px;"><?php esc_html_e('CRM Response Body:', 'universal-crm-connector-cf7'); ?></h4>
                <div class="ucc-code-wrap"><pre id="ucc-modal-response"><code></code></pre></div>
            </div>
        </div>
        <?php
    }

    /**
     * 7. Troubleshooting Tab
     */
    private function render_tab_troubleshooting()
    {
        $odoo_guide = UCC_Odoo::get_diagnostic_guide();

        ?>
        <div class="ucc-box">
            <h2><?php esc_html_e('Troubleshooting & Diagnostics Guide', 'universal-crm-connector-cf7'); ?></h2>
            <p class="ucc-desc"><?php esc_html_e('Find clear explanations and actionable steps for common integration scenarios.', 'universal-crm-connector-cf7'); ?></p>

            <div class="ucc-accordion">
                <!-- 1. Odoo Target Record Issue -->
                <div class="ucc-accordion-item" style="border-left:4px solid #ef4444;">
                    <div class="ucc-acc-head">
                        <h3>🔴 <?php echo esc_html($odoo_guide['issue_title']); ?></h3>
                    </div>
                    <div class="ucc-acc-body" style="display:block;">
                        <p><strong>Root Cause:</strong> <?php echo esc_html($odoo_guide['root_cause']); ?></p>
                        <p><strong>Actionable Resolution Steps:</strong></p>
                        <ol>
                            <?php foreach ($odoo_guide['solution_steps'] as $step) : ?>
                                <li><?php echo esc_html($step); ?></li>
                            <?php endforeach; ?>
                        </ol>
                        <div class="ucc-banner-warning" style="margin-top:12px;">
                            <strong><?php esc_html_e('Important Verification Note:', 'universal-crm-connector-cf7'); ?></strong>
                            <p><?php echo esc_html($odoo_guide['verification']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- 2. HTTP 200 Received But No Lead in Odoo -->
                <div class="ucc-accordion-item" style="border-left:4px solid #f59e0b;">
                    <div class="ucc-acc-head">
                        <h3>🟡 <?php esc_html_e('HTTP 200 OK Received, but No Lead is Visible in Odoo CRM Pipeline', 'universal-crm-connector-cf7'); ?></h3>
                    </div>
                    <div class="ucc-acc-body">
                        <p><?php esc_html_e('An HTTP 200 OK simply indicates that Odoo\'s web server accepted the incoming HTTP request without a Python crash. It does not automatically guarantee that record creation logic ran or succeeded.', 'universal-crm-connector-cf7'); ?></p>
                        <ol>
                            <li><?php esc_html_e('Verify that your Odoo Automation Rule has "Action To Do" set to "Execute Python Code" and contains the create() snippet.', 'universal-crm-connector-cf7'); ?></li>
                            <li><?php esc_html_e('Check Odoo CRM Filters: In Odoo CRM, remove the default "My Pipeline" filter and select "All Leads" or "Unassigned". Often leads are created without an assigned salesperson.', 'universal-crm-connector-cf7'); ?></li>
                            <li><?php esc_html_e('Check Odoo Leads Feature: In Odoo Settings > CRM, ensure the "Leads" checkbox is enabled. If disabled, Odoo only shows Opportunities.', 'universal-crm-connector-cf7'); ?></li>
                        </ol>
                    </div>
                </div>

                <!-- 3. HTTP 401 / 403 -->
                <div class="ucc-accordion-item">
                    <div class="ucc-acc-head">
                        <h3>🔒 <?php esc_html_e('HTTP 401 Unauthorized or HTTP 403 Forbidden', 'universal-crm-connector-cf7'); ?></h3>
                    </div>
                    <div class="ucc-acc-body">
                        <p><?php esc_html_e('The CRM rejected the authentication credentials provided in the request.', 'universal-crm-connector-cf7'); ?></p>
                        <ul>
                            <li><strong>Bearer Token:</strong> <?php esc_html_e('Verify that your token has not expired and has write/create permissions for CRM leads.', 'universal-crm-connector-cf7'); ?></li>
                            <li><strong>Basic Auth:</strong> <?php esc_html_e('Check if Odoo or your CRM requires an API Key rather than the user account password.', 'universal-crm-connector-cf7'); ?></li>
                            <li><strong>Webhook URL Key:</strong> <?php esc_html_e('Some CRM webhooks embed the secret in the URL (e.g. ?token=xyz). Verify the URL in CRM Connections tab.', 'universal-crm-connector-cf7'); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- 4. HTTP 400 Bad Request / 422 Unprocessable -->
                <div class="ucc-accordion-item">
                    <div class="ucc-acc-head">
                        <h3>⚠️ <?php esc_html_e('HTTP 400 Bad Request or HTTP 422 Unprocessable Entity', 'universal-crm-connector-cf7'); ?></h3>
                    </div>
                    <div class="ucc-acc-body">
                        <p><?php esc_html_e('The CRM server rejected the payload format or a required validation failed on the CRM side.', 'universal-crm-connector-cf7'); ?></p>
                        <ul>
                            <li><?php esc_html_e('Ensure phone numbers are properly formatted. In the Field Mapping tab, set the transformation for whatsapp to "Clean 10-digit Phone".', 'universal-crm-connector-cf7'); ?></li>
                            <li><?php esc_html_e('Verify that no required CRM fields are omitted. Missing hidden fields like qualfied or payment-link will default gracefully if mapped.', 'universal-crm-connector-cf7'); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- 5. Network Timeouts -->
                <div class="ucc-accordion-item">
                    <div class="ucc-acc-head">
                        <h3>⏱️ <?php esc_html_e('cURL error 28: Operation timed out / Network Latency', 'universal-crm-connector-cf7'); ?></h3>
                    </div>
                    <div class="ucc-acc-body">
                        <p><?php esc_html_e('The CRM endpoint did not respond within the configured timeout window.', 'universal-crm-connector-cf7'); ?></p>
                        <ul>
                            <li><?php esc_html_e('In the CRM Connections tab, increase the Timeout setting from 10 to 20 or 25 seconds.', 'universal-crm-connector-cf7'); ?></li>
                            <li><?php esc_html_e('Check if the CRM server is behind Cloudflare or a firewall that is blocking requests originating from Hostinger IP addresses.', 'universal-crm-connector-cf7'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 8. Documentation & Odoo Guide Tab
     */
    private function render_tab_documentation()
    {
        $python_code = UCC_Odoo::get_odoo_server_action_python();

        ?>
        <div class="ucc-box">
            <h2><?php esc_html_e('Odoo CRM Configuration & Webhook Implementation Guide', 'universal-crm-connector-cf7'); ?></h2>
            <p class="ucc-desc"><?php esc_html_e('Follow these click-by-click instructions to configure Odoo to receive Contact Form 7 submissions and automatically create leads.', 'universal-crm-connector-cf7'); ?></p>

            <div class="ucc-setup-steps">
                <h3><?php esc_html_e('Step 1: Create Automation Rule in Odoo', 'universal-crm-connector-cf7'); ?></h3>
                <ol>
                    <li>Log into your <strong>Odoo Admin Console</strong> (Odoo 16, 17, or 18).</li>
                    <li>Ensure Developer Mode is enabled (Settings &gt; Developer Tools &gt; <em>Activate the developer mode</em>).</li>
                    <li>Navigate to <strong>Settings &gt; Technical &gt; Automation &gt; Automation Rules</strong> (or <em>Automated Actions</em> in Odoo 16).</li>
                    <li>Click <strong>New</strong> (or <em>Create</em>).</li>
                    <li>Set <strong>Rule Name:</strong> <code>CF7 Website Inquiries Lead Creator</code>.</li>
                    <li>Set <strong>Model:</strong> <code>Lead/Opportunity (crm.lead)</code>.</li>
                    <li>Set <strong>Trigger:</strong> <code>On Webhook</code>.</li>
                    <li><strong style="color:#dc2626;">CRITICAL STEP:</strong> In the <strong>Target Record</strong> field, delete any default formula! Leave it completely blank/empty.</li>
                    <li>Under <strong>Action To Do</strong>, select <strong>Execute Python Code</strong>.</li>
                </ol>

                <h3 style="margin-top:24px;"><?php esc_html_e('Step 2: Paste the Python Lead Creator Snippet into Odoo', 'universal-crm-connector-cf7'); ?></h3>
                <p><?php esc_html_e('Copy this production-ready Python script and paste it into the Python Code editor in your Odoo Automation Rule:', 'universal-crm-connector-cf7'); ?></p>

                <div class="ucc-code-wrap">
                    <pre><code id="ucc-odoo-python"><?php echo esc_html($python_code); ?></code></pre>
                </div>
                <div style="margin-top:10px;">
                    <button type="button" class="button button-secondary" id="ucc-btn-copy-odoo"><?php esc_html_e('Copy Python Code', 'universal-crm-connector-cf7'); ?></button>
                </div>

                <h3 style="margin-top:24px;"><?php esc_html_e('Step 3: Copy Webhook URL into Universal CRM Connector', 'universal-crm-connector-cf7'); ?></h3>
                <ol>
                    <li>After saving your Automation Rule in Odoo, Odoo will display a unique <strong>Webhook URL</strong>.</li>
                    <li>Copy that Webhook URL from Odoo.</li>
                    <li>Return to WordPress &gt; <strong>Universal CRM &gt; CRM Connections</strong> tab.</li>
                    <li>Paste the Webhook URL into the <strong>Endpoint URL</strong> field and click <strong>Save Connection</strong>.</li>
                    <li>Navigate to the <strong>Test Connection</strong> tab and click <strong>Send Test Request Now</strong>.</li>
                    <li>Open <strong>Odoo CRM &gt; Pipeline</strong> to verify that your test lead has arrived!</li>
                </ol>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save Connection
     */
    public function ajax_save_connection()
    {
        check_ajax_referer('ucc_admin_nonce', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $endpoint = esc_url_raw(wp_unslash($_POST['endpoint_url'] ?? ''));

        if (empty($name) || empty($endpoint)) {
            wp_send_json_error(['message' => 'Connection name and endpoint URL are required.']);
        }

        // Validate endpoint against SSRF
        $ssrf = UCC_Dispatcher::validate_endpoint_url($endpoint);
        if (is_wp_error($ssrf)) {
            wp_send_json_error(['message' => $ssrf->get_error_message()]);
        }

        $data = [
            'name'          => $name,
            'crm_type'      => sanitize_key($_POST['crm_type'] ?? 'generic_webhook'),
            'endpoint_url'  => $endpoint,
            'http_method'   => sanitize_text_field($_POST['http_method'] ?? 'POST'),
            'content_type'  => sanitize_text_field($_POST['content_type'] ?? 'application/json'),
            'auth_type'     => sanitize_key($_POST['auth_type'] ?? 'none'),
            'timeout'       => max(3, min(30, (int) ($_POST['timeout'] ?? 10))),
            'retry_enabled' => ! empty($_POST['retry_enabled']) ? 1 : 0,
            'max_retries'   => 3,
        ];

        // Process auth credentials if supplied
        $auth_type = $data['auth_type'];
        if ($auth_type === 'bearer' && ! empty($_POST['auth_token'])) {
            $data['auth_credentials'] = ['token' => sanitize_text_field(wp_unslash($_POST['auth_token']))];
        } elseif ($auth_type === 'basic' && (! empty($_POST['auth_user']) || ! empty($_POST['auth_pass']))) {
            $data['auth_credentials'] = [
                'username' => sanitize_text_field(wp_unslash($_POST['auth_user'] ?? '')),
                'password' => (string) wp_unslash($_POST['auth_pass'] ?? ''),
            ];
        } elseif ($auth_type === 'custom_header' && ! empty($_POST['auth_hname'])) {
            $data['auth_credentials'] = [
                'header_name'  => sanitize_text_field(wp_unslash($_POST['auth_hname'])),
                'header_value' => sanitize_text_field(wp_unslash($_POST['auth_hval'] ?? '')),
            ];
        }

        $conn_id = UCC_Database::save_connection($data, $id);
        if (! $conn_id) {
            wp_send_json_error(['message' => 'Failed to save connection in database.']);
        }

        wp_send_json_success([
            'id'      => $conn_id,
            'message' => 'CRM Connection saved successfully!',
        ]);
    }

    /**
     * AJAX: Delete Connection
     */
    public function ajax_delete_connection()
    {
        check_ajax_referer('ucc_admin_nonce', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id && UCC_Database::delete_connection($id)) {
            wp_send_json_success(['message' => 'Connection deleted.']);
        }

        wp_send_json_error(['message' => 'Failed to delete connection.']);
    }

    /**
     * AJAX: Save Field Mapping
     */
    public function ajax_save_mapping()
    {
        check_ajax_referer('ucc_admin_nonce', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $form_id = (int) ($_POST['cf7_form_id'] ?? 0);
        $conn_id = (int) ($_POST['connection_id'] ?? 0);
        $source  = sanitize_text_field(wp_unslash($_POST['source_label'] ?? ''));
        $enabled = ! empty($_POST['enabled']) ? 1 : 0;

        if (! $form_id || ! $conn_id) {
            wp_send_json_error(['message' => 'Form ID and Connection ID are required.']);
        }

        $raw_mappings = isset($_POST['mappings']) && is_array($_POST['mappings']) ? $_POST['mappings'] : [];
        $clean_mappings = [];

        foreach ($raw_mappings as $m) {
            if (! empty($m['cf7_field']) && ! empty($m['crm_key'])) {
                $clean_mappings[] = [
                    'cf7_field'     => sanitize_text_field(wp_unslash($m['cf7_field'])),
                    'crm_key'       => sanitize_text_field(wp_unslash($m['crm_key'])),
                    'transform'     => sanitize_key($m['transform'] ?? 'trim'),
                    'default_value' => sanitize_text_field(wp_unslash($m['default_value'] ?? '')),
                ];
            }
        }

        $raw_static = isset($_POST['static']) && is_array($_POST['static']) ? $_POST['static'] : [];
        $clean_static = [];
        foreach ($raw_static as $s) {
            if (! empty($s['key'])) {
                $clean_static[] = [
                    'key'   => sanitize_text_field(wp_unslash($s['key'])),
                    'value' => sanitize_text_field(wp_unslash($s['value'] ?? '')),
                ];
            }
        }

        $mapping_id = UCC_Database::save_mapping([
            'cf7_form_id'    => $form_id,
            'connection_id'  => $conn_id,
            'source_label'   => $source,
            'enabled'        => $enabled,
            'field_mappings' => $clean_mappings,
            'static_fields'  => $clean_static,
        ]);

        if (! $mapping_id) {
            wp_send_json_error(['message' => 'Failed to save mapping.']);
        }

        wp_send_json_success([
            'id'      => $mapping_id,
            'message' => 'Field mappings updated successfully!',
        ]);
    }

    /**
     * AJAX: Test Connection
     */
    public function ajax_test_connection()
    {
        check_ajax_referer('ucc_admin_nonce', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $conn_id = (int) ($_POST['connection_id'] ?? 0);
        $conn = UCC_Database::get_connection($conn_id);
        if (! $conn) {
            wp_send_json_error(['message' => 'Selected connection does not exist.']);
        }

        $raw_payload = wp_unslash($_POST['payload'] ?? '');
        $payload = json_decode($raw_payload, true);
        if (! is_array($payload)) {
            $payload = [
                'name'    => 'Test Candidate',
                'email'   => 'test@example.com',
                'phone'   => '9876543210',
                'source'  => 'Test Ping',
            ];
        }

        $res = UCC_Dispatcher::test_connection($conn, $payload);
        wp_send_json_success($res);
    }

    /**
     * AJAX: Live Preview Payload
     */
    public function ajax_preview_payload()
    {
        check_ajax_referer('ucc_admin_nonce', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $form_id = (int) ($_POST['cf7_form_id'] ?? 0);
        $preview = UCC_Mapper::generate_sample_payload($form_id);

        wp_send_json_success([
            'payload' => $preview,
            'json'    => wp_json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * AJAX: Retry Delivery
     */
    public function ajax_retry_delivery()
    {
        check_ajax_referer('ucc_admin_nonce', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $log_id = (int) ($_POST['log_id'] ?? 0);
        $res = UCC_Logger::retry_delivery($log_id);

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        wp_send_json_success($res);
    }

    /**
     * AJAX: Clear Logs
     */
    public function ajax_clear_logs()
    {
        check_ajax_referer('ucc_admin_nonce', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        UCC_Logger::clear_logs();
        wp_send_json_success(['message' => 'Delivery logs cleared successfully.']);
    }
}

