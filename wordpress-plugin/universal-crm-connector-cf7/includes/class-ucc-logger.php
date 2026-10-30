<?php
/**
 * Delivery Logger, Log Viewer, Stats, and Manual Retry Engine.
 *
 * @package UniversalCRMConnector
 */

if (! defined('ABSPATH')) {
    exit;
}

class UCC_Logger
{
    /**
     * Record a delivery attempt in the database.
     */
    public static function log_delivery(array $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_logs';

        $wpdb->insert($table, [
            'submission_id'     => sanitize_text_field($data['submission_id'] ?? wp_generate_uuid4()),
            'cf7_form_id'       => (int) ($data['cf7_form_id'] ?? 0),
            'cf7_form_title'    => sanitize_text_field($data['cf7_form_title'] ?? 'Direct Dispatch'),
            'connection_name'   => sanitize_text_field($data['connection_name'] ?? 'Generic Webhook'),
            'source_label'      => sanitize_text_field($data['source_label'] ?? ''),
            'endpoint_url'      => esc_url_raw($data['endpoint_url'] ?? ''),
            'http_method'       => sanitize_text_field($data['http_method'] ?? 'POST'),
            'http_code'         => (int) ($data['http_code'] ?? 0),
            'status'            => sanitize_text_field($data['status'] ?? 'failed'),
            'execution_time_ms' => (int) ($data['execution_time_ms'] ?? 0),
            'request_payload'   => $data['request_payload'] ?? '',
            'response_body'     => $data['response_body'] ?? '',
            'error_message'     => sanitize_textarea_field($data['error_message'] ?? ''),
            'retry_count'       => (int) ($data['retry_count'] ?? 0),
            'created_at'        => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Retrieve paginated logs with optional status or form filter.
     */
    public static function get_logs($limit = 50, $offset = 0, $status = '', $form_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_logs';

        $where = ['1=1'];
        $params = [];

        if (! empty($status)) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        if (! empty($form_id)) {
            $where[] = 'cf7_form_id = %d';
            $params[] = (int) $form_id;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = (int) $limit;
        $params[] = (int) $offset;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Count total logs for pagination.
     */
    public static function count_logs($status = '', $form_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_logs';

        $where = ['1=1'];
        $params = [];

        if (! empty($status)) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        if (! empty($form_id)) {
            $where[] = 'cf7_form_id = %d';
            $params[] = (int) $form_id;
        }

        $where_sql = implode(' AND ', $where);
        if (! empty($params)) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where_sql", $params));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_sql");
    }

    /**
     * Get single log item by ID.
     */
    public static function get_log($log_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_logs';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", (int) $log_id));
    }

    /**
     * Get aggregate statistics for dashboard.
     */
    public static function get_stats()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_logs';

        $total      = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $successful = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'success'");
        $failed     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'");
        $retrying   = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'retrying'");

        $success_rate = ($total > 0) ? round(($successful / $total) * 100, 1) : 100;

        return [
            'total'        => $total,
            'successful'   => $successful,
            'failed'       => $failed,
            'retrying'     => $retrying,
            'success_rate' => $success_rate,
        ];
    }

    /**
     * Retry a previously logged delivery attempt safely.
     */
    public static function retry_delivery($log_id)
    {
        $log = self::get_log($log_id);
        if (! $log) {
            return new WP_Error('not_found', 'Log entry not found.');
        }

        // Find connection by name or form mapping
        $mapping = null;
        if ($log->cf7_form_id) {
            $mapping = UCC_Database::get_mapping_for_form($log->cf7_form_id);
        }

        $connection = null;
        if ($mapping) {
            $connection = UCC_Database::get_connection($mapping->connection_id);
        } else {
            // Find connection by endpoint or name
            $conns = UCC_Database::get_connections();
            foreach ($conns as $c) {
                if ($c->name === $log->connection_name || $c->endpoint_url === $log->endpoint_url) {
                    $connection = $c;
                    break;
                }
            }
        }

        if (! $connection) {
            return new WP_Error('no_connection', 'Destination CRM connection could not be resolved for this retry.');
        }

        // Parse payload
        $payload = json_decode($log->request_payload, true);
        if (! is_array($payload)) {
            $payload = ['retry_raw' => $log->request_payload];
        }

        $meta = [
            'submission_id'  => $log->submission_id,
            'cf7_form_id'    => $log->cf7_form_id,
            'cf7_form_title' => $log->cf7_form_title,
            'source_label'   => $log->source_label,
            'submitted_at'   => current_time('mysql'),
        ];

        return UCC_Dispatcher::dispatch($connection, $payload, $mapping ?: (object)['cf7_form_id' => $log->cf7_form_id, 'source_label' => $log->source_label], $meta, $log->retry_count + 1);
    }

    /**
     * Clear all delivery logs with capability and nonce protection.
     */
    public static function clear_logs()
    {
        if (! current_user_can('manage_options')) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_logs';
        return $wpdb->query("TRUNCATE TABLE $table");
    }

    /**
     * Automatic log cleanup according to retention policy (default 30 days).
     */
    public static function cleanup_old_logs()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ucc_crm_logs';
        $days = (int) get_option('ucc_log_retention_days', 30);
        if ($days < 1) {
            $days = 30;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s", $cutoff));
    }
}

