<?php
/**
 * Database management and table migrations for Universal CRM Connector.
 *
 * @package UniversalCRMConnector
 */

if (! defined('ABSPATH')) {
    exit;
}

class UCC_Database
{
    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. CRM Connections table
        $table_connections = $wpdb->prefix . 'ucc_crm_connections';
        $sql_connections = "CREATE TABLE $table_connections (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            crm_type varchar(50) NOT NULL DEFAULT 'generic_webhook',
            endpoint_url text NOT NULL,
            http_method varchar(10) NOT NULL DEFAULT 'POST',
            content_type varchar(50) NOT NULL DEFAULT 'application/json',
            auth_type varchar(30) NOT NULL DEFAULT 'none',
            auth_credentials text,
            custom_headers text,
            query_params text,
            timeout int(11) NOT NULL DEFAULT 10,
            retry_enabled tinyint(1) NOT NULL DEFAULT 1,
            max_retries int(11) NOT NULL DEFAULT 3,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_connections);

        // 2. CF7 Form Mappings table
        $table_mappings = $wpdb->prefix . 'ucc_form_mappings';
        $sql_mappings = "CREATE TABLE $table_mappings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cf7_form_id bigint(20) unsigned NOT NULL,
            connection_id bigint(20) unsigned NOT NULL,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            source_label varchar(100) NOT NULL DEFAULT '',
            field_mappings longtext,
            static_fields text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cf7_form_id (cf7_form_id),
            KEY connection_id (connection_id)
        ) $charset_collate;";
        dbDelta($sql_mappings);

        // 3. CRM Delivery Logs table
        $table_logs = $wpdb->prefix . 'ucc_crm_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id varchar(64) NOT NULL,
            cf7_form_id bigint(20) unsigned NOT NULL,
            cf7_form_title varchar(255) NOT NULL,
            connection_name varchar(100) NOT NULL,
            source_label varchar(100) NOT NULL,
            endpoint_url text NOT NULL,
            http_method varchar(10) NOT NULL,
            http_code int(11) DEFAULT 0,
            status varchar(20) NOT NULL,
            execution_time_ms int(11) DEFAULT 0,
            request_payload longtext,
            response_body longtext,
            error_message text,
            retry_count int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY cf7_form_id (cf7_form_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_logs);

        // Run default seed if this is first installation
        self::seed_defaults();
    }

    /**
     * Seed initial demo connections and presets for Form A ("Data Needed") and Form B ("Sales Funnel")
     */
    public static function seed_defaults()
    {
        global $wpdb;
        $table_connections = $wpdb->prefix . 'ucc_crm_connections';
        $table_mappings    = $wpdb->prefix . 'ucc_form_mappings';

        // Check if connections already exist
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_connections");
        if ($count > 0) {
            return;
        }

        // 1. Create default Odoo CRM connection placeholder
        $wpdb->insert($table_connections, [
            'name'             => 'Odoo CRM Webhook',
            'crm_type'         => 'odoo',
            'endpoint_url'     => 'https://myodoo.example.com/website/lead-webhook',
            'http_method'      => 'POST',
            'content_type'     => 'application/json',
            'auth_type'        => 'none',
            'auth_credentials' => '',
            'custom_headers'   => '[]',
            'query_params'     => '[]',
            'timeout'          => 12,
            'retry_enabled'    => 1,
            'max_retries'      => 3,
            'created_at'       => current_time('mysql'),
        ]);
        $conn_id = $wpdb->insert_id;

        // Try to find CF7 forms to pre-link
        $cf7_forms = get_posts([
            'post_type'      => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        $form_a_id = 0;
        $form_b_id = 0;

        foreach ($cf7_forms as $f) {
            $title = strtolower($f->post_title);
            if (strpos($title, 'data') !== false || strpos($title, 'needed') !== false) {
                $form_a_id = $f->ID;
            } elseif (strpos($title, 'funnel') !== false || strpos($title, 'eligibility') !== false) {
                $form_b_id = $f->ID;
            }
        }

        // Form A mappings ("Data Needed")
        $mappings_a = [
            ['cf7_field' => 'full-name',   'crm_key' => 'name',         'transform' => 'trim',            'default_value' => 'Candidate'],
            ['cf7_field' => 'email',       'crm_key' => 'email_from',   'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'city',        'crm_key' => 'city',         'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'whatsapp',    'crm_key' => 'phone',        'transform' => 'clean_phone_10',  'default_value' => ''],
            ['cf7_field' => 'form-source', 'crm_key' => 'source',       'transform' => 'trim',            'default_value' => 'Data Needed'],
        ];

        // Form B mappings ("Sales Funnel / Eligibility Checker")
        $mappings_b = [
            ['cf7_field' => 'full-name',        'crm_key' => 'name',              'transform' => 'trim',            'default_value' => 'Candidate'],
            ['cf7_field' => 'email',            'crm_key' => 'email_from',        'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'whatsapp',         'crm_key' => 'phone',             'transform' => 'clean_phone_10',  'default_value' => ''],
            ['cf7_field' => 'city',             'crm_key' => 'city',              'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'created-date',     'crm_key' => 'created_date',      'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'lead-status',      'crm_key' => 'lead_status',       'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'date',             'crm_key' => 'date',              'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'form-source',      'crm_key' => 'source',            'transform' => 'trim',            'default_value' => 'Sales Funnel / Eligibility Checker'],
            ['cf7_field' => 'qualfied',         'crm_key' => 'qualfied',          'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'wp-group-created', 'crm_key' => 'wp_group_created',  'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'meeting-status',   'crm_key' => 'meeting_status',    'transform' => 'trim',            'default_value' => ''],
            ['cf7_field' => 'payment-link',     'crm_key' => 'payment_link',      'transform' => 'trim',            'default_value' => ''],
        ];

        $static_a = [
            ['key' => 'source', 'value' => 'Data Needed'],
            ['key' => '_model', 'value' => 'crm.lead'],
        ];
        $static_b = [
            ['key' => 'source', 'value' => 'Sales Funnel / Eligibility Checker'],
            ['key' => '_model', 'value' => 'crm.lead'],
        ];

        if ($form_a_id) {
            $wpdb->insert($table_mappings, [
                'cf7_form_id'    => $form_a_id,
                'connection_id'  => $conn_id,
                'enabled'        => 1,
                'source_label'   => 'Data Needed',
                'field_mappings' => wp_json_encode($mappings_a),
                'static_fields'  => wp_json_encode($static_a),
                'created_at'     => current_time('mysql'),
            ]);
        }

        if ($form_b_id) {
            $wpdb->insert($table_mappings, [
                'cf7_form_id'    => $form_b_id,
                'connection_id'  => $conn_id,
                'enabled'        => 1,
                'source_label'   => 'Sales Funnel / Eligibility Checker',
                'field_mappings' => wp_json_encode($mappings_b),
                'static_fields'  => wp_json_encode($static_b),
                'created_at'     => current_time('mysql'),
            ]);
        }
    }

    // ------------------------------------------------------------------------- CRUD: Connections

    public static function get_connections()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_connections';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY id ASC");
    }

    public static function get_connection($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_connections';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", (int) $id));
    }

    public static function save_connection(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_connections';

        $id = ! empty($data['id']) ? (int) $data['id'] : 0;
        $fields = [
            'name'             => sanitize_text_field($data['name'] ?? 'CRM Connection'),
            'crm_type'         => sanitize_text_field($data['crm_type'] ?? 'generic_webhook'),
            'endpoint_url'     => esc_url_raw(trim($data['endpoint_url'] ?? '')),
            'http_method'      => strtoupper(sanitize_text_field($data['http_method'] ?? 'POST')),
            'content_type'     => sanitize_text_field($data['content_type'] ?? 'application/json'),
            'auth_type'        => sanitize_text_field($data['auth_type'] ?? 'none'),
            'auth_credentials' => ! empty($data['auth_credentials']) ? self::encrypt_secret($data['auth_credentials']) : '',
            'custom_headers'   => is_string($data['custom_headers'] ?? '') ? $data['custom_headers'] : wp_json_encode($data['custom_headers'] ?? []),
            'query_params'     => is_string($data['query_params'] ?? '') ? $data['query_params'] : wp_json_encode($data['query_params'] ?? []),
            'timeout'          => max(3, min(60, (int) ($data['timeout'] ?? 10))),
            'retry_enabled'    => ! empty($data['retry_enabled']) ? 1 : 0,
            'max_retries'      => max(1, min(5, (int) ($data['max_retries'] ?? 3))),
        ];

        // Keep existing secret if input is left blank (when editing)
        if ($id > 0 && empty($data['auth_credentials'])) {
            $existing = self::get_connection($id);
            if ($existing) {
                $fields['auth_credentials'] = $existing->auth_credentials;
            }
        }

        if ($id > 0) {
            $wpdb->update($table, $fields, ['id' => $id]);
            return $id;
        }

        $fields['created_at'] = current_time('mysql');
        $wpdb->insert($table, $fields);
        return $wpdb->insert_id;
    }

    public static function delete_connection($id)
    {
        global $wpdb;
        $table_conn = $wpdb->prefix . 'ucc_crm_connections';
        $table_map  = $wpdb->prefix . 'ucc_form_mappings';
        $wpdb->delete($table_conn, ['id' => (int) $id]);
        $wpdb->delete($table_map, ['connection_id' => (int) $id]);
    }

    // ------------------------------------------------------------------------- CRUD: Form Mappings

    public static function get_form_mappings($cf7_form_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_form_mappings';
        if ($cf7_form_id > 0) {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE cf7_form_id = %d", (int) $cf7_form_id));
        }
        return $wpdb->get_results("SELECT * FROM $table ORDER BY id ASC");
    }

    public static function get_mapping_by_id($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_form_mappings';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", (int) $id));
    }

    public static function get_mapping_for_form($cf7_form_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_form_mappings';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE cf7_form_id = %d AND enabled = 1 LIMIT 1", (int) $cf7_form_id));
    }

    public static function save_form_mapping(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_form_mappings';

        $id = ! empty($data['id']) ? (int) $data['id'] : 0;
        $fields = [
            'cf7_form_id'    => (int) ($data['cf7_form_id'] ?? 0),
            'connection_id'  => (int) ($data['connection_id'] ?? 0),
            'enabled'        => ! empty($data['enabled']) ? 1 : 0,
            'source_label'   => sanitize_text_field($data['source_label'] ?? ''),
            'field_mappings' => is_string($data['field_mappings'] ?? '') ? $data['field_mappings'] : wp_json_encode($data['field_mappings'] ?? []),
            'static_fields'  => is_string($data['static_fields'] ?? '') ? $data['static_fields'] : wp_json_encode($data['static_fields'] ?? []),
        ];

        if ($id > 0) {
            $wpdb->update($table, $fields, ['id' => $id]);
            return $id;
        }

        $fields['created_at'] = current_time('mysql');
        $wpdb->insert($table, $fields);
        return $wpdb->insert_id;
    }

    public static function delete_form_mapping($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_form_mappings';
        return $wpdb->delete($table, ['id' => (int) $id]);
    }

    // ------------------------------------------------------------------------- Helpers: Security & Encryption

    public static function encrypt_secret($plain)
    {
        if (empty($plain)) {
            return '';
        }
        $key = substr(hash('sha256', wp_salt('auth')), 0, 32);
        $iv  = openssl_random_pseudo_bytes(16);
        $enc = openssl_encrypt((string) $plain, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . '::' . $enc);
    }

    public static function decrypt_secret($encrypted)
    {
        if (empty($encrypted)) {
            return '';
        }
        $decoded = base64_decode((string) $encrypted);
        if (! str_contains($decoded, '::')) {
            return $encrypted; // Fallback if plain
        }
        [$iv, $ciphertext] = explode('::', $decoded, 2);
        $key = substr(hash('sha256', wp_salt('auth')), 0, 32);
        return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);
    }
}

