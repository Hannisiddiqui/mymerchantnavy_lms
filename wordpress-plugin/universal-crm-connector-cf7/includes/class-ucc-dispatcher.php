<?php
/**
 * HTTP Dispatcher with SSRF protection, authentication, and retry handling.
 *
 * @package UniversalCRMConnector
 */

if (! defined('ABSPATH')) {
    exit;
}

class UCC_Dispatcher
{
    /**
     * Dispatch submission data to CRM destination.
     *
     * @param object $connection Connection DB object
     * @param array  $payload    Transformed payload
     * @param object $mapping    Form mapping DB object
     * @param array  $meta       Submission metadata
     * @param int    $retry_count Current retry count
     * @return array Result array [success, http_code, body, error, latency_ms]
     */
    public static function dispatch($connection, array $payload, $mapping, array $meta = [], $retry_count = 0)
    {
        $start_time = microtime(true);
        $endpoint   = trim($connection->endpoint_url);

        // 1. SSRF and URL validation
        $ssrf_check = self::validate_endpoint_url($endpoint);
        if (is_wp_error($ssrf_check)) {
            $latency = (int) round((microtime(true) - $start_time) * 1000);
            $err_msg = 'SSRF Block: ' . $ssrf_check->get_error_message();

            UCC_Logger::log_delivery([
                'submission_id'     => $meta['submission_id'] ?? wp_generate_uuid4(),
                'cf7_form_id'       => $mapping->cf7_form_id ?? 0,
                'cf7_form_title'    => $meta['cf7_form_title'] ?? 'Direct Dispatch',
                'connection_name'   => $connection->name,
                'source_label'      => $mapping->source_label ?? '',
                'endpoint_url'      => $endpoint,
                'http_method'       => $connection->http_method ?: 'POST',
                'http_code'         => 0,
                'status'            => 'failed',
                'execution_time_ms' => $latency,
                'request_payload'   => self::sanitize_payload_for_logging($payload),
                'response_body'     => '',
                'error_message'     => $err_msg,
                'retry_count'       => $retry_count,
            ]);

            return [
                'success'    => false,
                'http_code'  => 0,
                'body'       => '',
                'error'      => $err_msg,
                'latency_ms' => $latency,
            ];
        }

        // 2. Query parameters
        $query_params = ! empty($connection->query_params) ? json_decode($connection->query_params, true) : [];
        if (! empty($query_params) && is_array($query_params)) {
            $endpoint = add_query_arg($query_params, $endpoint);
        }

        // 3. Prepare headers
        $headers = [
            'User-Agent' => 'Universal-CRM-Connector-CF7/' . (defined('UCC_VERSION') ? UCC_VERSION : '1.0') . ' (+WordPress/' . get_bloginfo('version') . ')',
            'Accept'     => 'application/json, text/plain, */*',
        ];

        // Idempotency key for preventing duplicate leads
        $idempotency_key = ! empty($meta['submission_id']) ? $meta['submission_id'] : wp_generate_uuid4();
        $headers['Idempotency-Key']   = $idempotency_key;
        $headers['X-Idempotency-Key'] = $idempotency_key;

        // Content-Type & formatted body
        $method = strtoupper($connection->http_method ?: 'POST');
        $content_type = $connection->content_type ?: 'application/json';
        $body = '';

        if ($content_type === 'application/x-www-form-urlencoded') {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
            $body = http_build_query($payload);
        } else {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
            $body = wp_json_encode($payload);
        }

        // Custom headers configured by admin
        $custom_headers = ! empty($connection->custom_headers) ? json_decode($connection->custom_headers, true) : [];
        if (is_array($custom_headers)) {
            foreach ($custom_headers as $h) {
                if (! empty($h['key']) && isset($h['value'])) {
                    $headers[trim($h['key'])] = trim($h['value']);
                }
            }
        }

        // Authentication
        $auth_headers = self::build_auth_headers($connection);
        foreach ($auth_headers as $ak => $av) {
            $headers[$ak] = $av;
        }

        // 4. Request arguments
        $timeout = (int) ($connection->timeout ?: 10);
        if ($timeout < 3) $timeout = 3;
        if ($timeout > 30) $timeout = 30;

        $args = [
            'method'      => $method,
            'timeout'     => $timeout,
            'redirection' => 2,
            'httpversion' => '1.1',
            'sslverify'   => true, // Strictly verified TLS certificates
            'headers'     => $headers,
            'body'        => in_array($method, ['POST', 'PUT', 'PATCH']) ? $body : null,
        ];

        // 5. Execute HTTP Request via WordPress HTTP API
        $response = wp_remote_request($endpoint, $args);
        $latency  = (int) round((microtime(true) - $start_time) * 1000);

        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_msg  = $response->get_error_message();

            $is_retriable = self::is_retriable_error(0, $error_msg);
            $status = ($is_retriable && ! empty($connection->retry_enabled) && $retry_count < (int) ($connection->max_retries ?: 3))
                ? 'retrying'
                : 'failed';

            UCC_Logger::log_delivery([
                'submission_id'     => $idempotency_key,
                'cf7_form_id'       => $mapping->cf7_form_id ?? 0,
                'cf7_form_title'    => $meta['cf7_form_title'] ?? 'Direct Dispatch',
                'connection_name'   => $connection->name,
                'source_label'      => $mapping->source_label ?? '',
                'endpoint_url'      => self::redact_url_credentials($endpoint),
                'http_method'       => $method,
                'http_code'         => 0,
                'status'            => $status,
                'execution_time_ms' => $latency,
                'request_payload'   => self::sanitize_payload_for_logging($payload),
                'response_body'     => '',
                'error_message'     => $error_code . ': ' . $error_msg,
                'retry_count'       => $retry_count,
            ]);

            return [
                'success'    => false,
                'http_code'  => 0,
                'body'       => '',
                'error'      => $error_msg,
                'latency_ms' => $latency,
            ];
        }

        $http_code     = (int) wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $is_success    = ($http_code >= 200 && $http_code < 300);

        $status = $is_success ? 'success' : 'failed';
        $err_note = '';

        if (! $is_success) {
            $is_retriable = self::is_retriable_error($http_code);
            if ($is_retriable && ! empty($connection->retry_enabled) && $retry_count < (int) ($connection->max_retries ?: 3)) {
                $status = 'retrying';
            }
            $err_note = self::get_http_error_summary($http_code, $response_body);
        }

        // Log result
        UCC_Logger::log_delivery([
            'submission_id'     => $idempotency_key,
            'cf7_form_id'       => $mapping->cf7_form_id ?? 0,
            'cf7_form_title'    => $meta['cf7_form_title'] ?? 'Direct Dispatch',
            'connection_name'   => $connection->name,
            'source_label'      => $mapping->source_label ?? '',
            'endpoint_url'      => self::redact_url_credentials($endpoint),
            'http_method'       => $method,
            'http_code'         => $http_code,
            'status'            => $status,
            'execution_time_ms' => $latency,
            'request_payload'   => self::sanitize_payload_for_logging($payload),
            'response_body'     => self::sanitize_response_for_logging($response_body),
            'error_message'     => $err_note,
            'retry_count'       => $retry_count,
        ]);

        return [
            'success'    => $is_success,
            'http_code'  => $http_code,
            'body'       => $response_body,
            'error'      => $err_note,
            'latency_ms' => $latency,
        ];
    }

    /**
     * Send test request with sample data.
     *
     * @param object $connection Connection DB object
     * @param array  $test_payload Custom or fake payload
     * @return array [success, http_code, body, error, latency_ms]
     */
    public static function test_connection($connection, array $test_payload = [])
    {
        $meta = [
            'cf7_form_id'    => 0,
            'cf7_form_title' => 'Test Connection Console',
            'submission_id'  => 'test-' . wp_generate_uuid4(),
            'source_label'   => 'Test Ping',
            'submitted_at'   => current_time('mysql'),
        ];

        $mapping = (object) [
            'cf7_form_id'   => 0,
            'source_label'  => 'Test Connection',
        ];

        return self::dispatch($connection, $test_payload, $mapping, $meta, 0);
    }

    /**
     * Build authentication headers based on connection settings.
     */
    private static function build_auth_headers($connection)
    {
        $headers = [];
        $auth_type = $connection->auth_type ?: 'none';
        if ($auth_type === 'none') {
            return $headers;
        }

        $creds = UCC_Database::get_decrypted_credentials($connection);
        if (empty($creds)) {
            return $headers;
        }

        switch ($auth_type) {
            case 'bearer':
                $token = is_array($creds) ? ($creds['token'] ?? '') : (string) $creds;
                if (! empty($token)) {
                    $headers['Authorization'] = 'Bearer ' . trim($token);
                }
                break;

            case 'basic':
                $user = is_array($creds) ? ($creds['username'] ?? '') : '';
                $pass = is_array($creds) ? ($creds['password'] ?? '') : '';
                if ($user !== '' || $pass !== '') {
                    $headers['Authorization'] = 'Basic ' . base64_encode($user . ':' . $pass);
                }
                break;

            case 'custom_header':
                $hname = is_array($creds) ? ($creds['header_name'] ?? '') : '';
                $hval  = is_array($creds) ? ($creds['header_value'] ?? '') : '';
                if (! empty($hname)) {
                    $headers[trim($hname)] = trim($hval);
                }
                break;
        }

        return $headers;
    }

    /**
     * SSRF and Destination URL Validator.
     * Blocks private, loopback, and reserved IP ranges to protect local network.
     */
    public static function validate_endpoint_url($url)
    {
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('The destination URL is missing or malformed.', 'universal-crm-connector-cf7'));
        }

        $parts = wp_parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return new WP_Error('invalid_scheme', __('Only HTTP and HTTPS destination endpoints are permitted.', 'universal-crm-connector-cf7'));
        }

        $host = strtolower($parts['host'] ?? '');
        if (empty($host)) {
            return new WP_Error('missing_host', __('Destination URL host is missing.', 'universal-crm-connector-cf7'));
        }

        // Allow localhost in local dev environment if explicitly opted in
        if (defined('UCC_ALLOW_LOCAL_REQUESTS') && UCC_ALLOW_LOCAL_REQUESTS) {
            return true;
        }

        // Check if host is an IP address or resolves to private IP
        $ip = gethostbyname($host);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            if (
                ! filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                )
            ) {
                // Deny loopback/private range
                return new WP_Error('ssrf_blocked', sprintf(__('Destination IP %s is inside a private or loopback network range. External CRM webhook required.', 'universal-crm-connector-cf7'), esc_html($ip)));
            }
        }

        return true;
    }

    /**
     * Determine if an HTTP response code or network error is retriable.
     * 4xx client errors (400, 401, 403, 404, 422) are PERMANENT configuration issues and should NOT be retried endlessly.
     * 5xx server errors, 429 rate limits, and network timeouts ARE retriable.
     */
    public static function is_retriable_error($http_code, $error_msg = '')
    {
        if ($http_code === 0) {
            // Network timeout / DNS resolution error
            return true;
        }
        if ($http_code === 429 || ($http_code >= 500 && $http_code <= 599)) {
            return true;
        }
        return false;
    }

    /**
     * Provide an actionable diagnostic summary based on HTTP status code.
     */
    public static function get_http_error_summary($http_code, $body = '')
    {
        switch ($http_code) {
            case 400:
                return 'HTTP 400 Bad Request: The CRM rejected the payload structure or required fields. Check Field Mapping & Odoo Target Record.';
            case 401:
                return 'HTTP 401 Unauthorized: Invalid API token, username/password, or expired Bearer secret.';
            case 403:
                return 'HTTP 403 Forbidden: Endpoint exists but the authenticated user lacks permissions in CRM.';
            case 404:
                return 'HTTP 404 Not Found: The CRM webhook URL is incorrect or endpoint route was removed.';
            case 422:
                return 'HTTP 422 Unprocessable Entity: Validation error on CRM side. Check data types and required keys.';
            case 429:
                return 'HTTP 429 Rate Limit Exceeded: CRM received too many requests. Retry scheduled.';
            case 500:
                if (stripos($body, '_model') !== false || stripos($body, '_id') !== false) {
                    return 'HTTP 500 Odoo Webhook Error: Target Record expected _model and _id. See Troubleshooting tab for Odoo fix.';
                }
                return 'HTTP 500 Internal Server Error: The CRM server encountered an error processing the submission.';
            case 502:
            case 503:
            case 504:
                return 'HTTP ' . $http_code . ' Gateway Error: CRM is temporarily down or overloaded. Retry recommended.';
            default:
                return 'HTTP ' . $http_code . ' response received.';
        }
    }

    /**
     * Sanitizes request payload for logging to protect PII.
     * Privacy by default: If full payload logging is disabled, only field keys are stored.
     */
    public static function sanitize_payload_for_logging(array $payload)
    {
        $log_full = get_option('ucc_log_full_payloads', 'no') === 'yes';
        if (! $log_full) {
            // Mask values, retain only field names and value lengths for auditing
            $summary = [];
            foreach ($payload as $k => $v) {
                if (is_array($v)) {
                    $summary[$k] = '[Array of ' . count($v) . ' items]';
                } elseif (is_bool($v)) {
                    $summary[$k] = $v ? 'true' : 'false';
                } elseif ($v === null || $v === '') {
                    $summary[$k] = '[empty]';
                } else {
                    $summary[$k] = '[masked, length ' . strlen((string)$v) . ']';
                }
            }
            return wp_json_encode($summary, JSON_PRETTY_PRINT);
        }

        // If full logging enabled, still redact credit cards, passwords, or explicit secrets
        $redacted = $payload;
        foreach ($redacted as $k => $v) {
            $lower = strtolower($k);
            if (strpos($lower, 'pass') !== false || strpos($lower, 'token') !== false || strpos($lower, 'secret') !== false || strpos($lower, 'card') !== false) {
                $redacted[$k] = '••••••••';
            }
        }
        return wp_json_encode($redacted, JSON_PRETTY_PRINT);
    }

    /**
     * Sanitizes response body for logging, truncating large responses and hiding secrets.
     */
    public static function sanitize_response_for_logging($body)
    {
        if (empty($body)) {
            return '';
        }

        // Limit logged response to 2048 characters to prevent database bloat
        if (strlen($body) > 2048) {
            $body = substr($body, 0, 2048) . "\n... [truncated response]";
        }

        return $body;
    }

    /**
     * Redact any user/password embedded in URL string.
     */
    public static function redact_url_credentials($url)
    {
        return preg_replace('/:\/\/([^:]+):([^@]+)@/', '://$1:••••••••@', $url);
    }
}

