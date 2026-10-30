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

        // Route to Odoo direct JSON-RPC API handler if configured
        if (($connection->crm_type ?? '') === 'odoo' || ($connection->auth_type ?? '') === 'odoo_api' || strpos($endpoint, '/jsonrpc') !== false) {
            return self::dispatch_odoo_api($connection, $payload, $mapping, $meta, $retry_count);
        }

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

    /**
     * Dispatch submission directly to Odoo CRM via authenticated JSON-RPC API.
     *
     * @param object $connection Connection DB object
     * @param array  $payload    Field values
     * @param object $mapping    Form mapping
     * @param array  $meta       Metadata
     * @param int    $retry_count Retries
     * @return array [success, http_code, body, error, latency_ms]
     */
    public static function dispatch_odoo_api($connection, array $payload, $mapping, array $meta = [], $retry_count = 0)
    {
        $start_time = microtime(true);
        $endpoint   = trim($connection->endpoint_url);
        if (! str_ends_with($endpoint, '/jsonrpc')) {
            $endpoint = rtrim($endpoint, '/') . '/jsonrpc';
        }

        $idempotency_key = ! empty($meta['submission_id']) ? $meta['submission_id'] : wp_generate_uuid4();
        $source_label    = ! empty($mapping->source_label) ? $mapping->source_label : 'CF7 Submission';
        $form_title      = ! empty($meta['cf7_form_title']) ? $meta['cf7_form_title'] : 'Contact Form';

        // 1. Credentials
        $creds    = UCC_Database::get_decrypted_credentials($connection);
        $database = ! empty($creds['database']) ? trim($creds['database']) : 'mmnin';
        $username = ! empty($creds['username']) ? trim($creds['username']) : 'support.mymerchantnavy@gmail.com';
        $api_key  = ! empty($creds['api_key']) ? trim($creds['api_key']) : (! empty($creds['token']) ? trim($creds['token']) : 'aac08ac4a2b071a7fd2613b2d3cd08909eb07f5e_oak');

        // 2. Authenticate with Odoo via JSON-RPC
        $auth_body = wp_json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => [
                'service' => 'common',
                'method'  => 'authenticate',
                'args'    => [$database, $username, $api_key, new stdClass()],
            ],
            'id'      => 1,
        ]);

        $timeout = max(5, min(30, (int) ($connection->timeout ?: 15)));
        $auth_res = wp_remote_post($endpoint, [
            'timeout'     => $timeout,
            'sslverify'   => true,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'        => $auth_body,
        ]);

        if (is_wp_error($auth_res)) {
            $latency = (int) round((microtime(true) - $start_time) * 1000);
            $err_msg = 'Odoo Auth Network Error: ' . $auth_res->get_error_message();
            self::log_odoo_result($idempotency_key, $mapping, $meta, $connection, $endpoint, 0, 'failed', $latency, $payload, '', $err_msg, $retry_count);
            return ['success' => false, 'http_code' => 0, 'body' => '', 'error' => $err_msg, 'latency_ms' => $latency];
        }

        $auth_json = json_decode(wp_remote_retrieve_body($auth_res), true);
        $uid = $auth_json['result'] ?? null;
        if (! $uid) {
            $latency = (int) round((microtime(true) - $start_time) * 1000);
            $err_msg = 'Odoo Authentication Failed: Invalid database, username, or API key.';
            if (! empty($auth_json['error']['message'])) {
                $err_msg .= ' (' . $auth_json['error']['message'] . ')';
            }
            self::log_odoo_result($idempotency_key, $mapping, $meta, $connection, $endpoint, 401, 'failed', $latency, $payload, wp_remote_retrieve_body($auth_res), $err_msg, $retry_count);
            return ['success' => false, 'http_code' => 401, 'body' => wp_remote_retrieve_body($auth_res), 'error' => $err_msg, 'latency_ms' => $latency];
        }

        // 3. Prepare Lead Fields for crm.lead
        $raw_name     = ! empty($payload['name']) ? $payload['name'] : (! empty($payload['full-name']) ? $payload['full-name'] : 'Lead from Contact Form 7');
        $contact_name = ! empty($payload['contact_name']) ? $payload['contact_name'] : (! empty($payload['full-name']) ? $payload['full-name'] : $raw_name);
        $email        = ! empty($payload['email_from']) ? $payload['email_from'] : (! empty($payload['email']) ? $payload['email'] : '');
        $phone_raw    = ! empty($payload['phone']) ? $payload['phone'] : (! empty($payload['whatsapp']) ? $payload['whatsapp'] : '');
        $whatsapp_raw = ! empty($payload['whatsapp']) ? $payload['whatsapp'] : $phone_raw;
        $city         = ! empty($payload['city']) ? $payload['city'] : '';
        $source       = ! empty($payload['source']) ? $payload['source'] : (! empty($payload['form-source']) ? $payload['form-source'] : $source_label);
        $eligibility  = ! empty($payload['eligibility']) ? $payload['eligibility'] : (! empty($payload['qualfied']) ? $payload['qualfied'] : (! empty($payload['qualified']) ? $payload['qualified'] : ''));
        $pcm          = ! empty($payload['pcm%']) ? $payload['pcm%'] : (! empty($payload['pcm']) ? $payload['pcm'] : (! empty($payload['pcm_percentage']) ? $payload['pcm_percentage'] : ''));
        $course       = ! empty($payload['course']) ? $payload['course'] : (! empty($payload['target_course']) ? $payload['target_course'] : '');

        // If course is empty but payment link or form title has a course name, extract it nicely
        if (empty($course) && ! empty($payload['payment-link'])) {
            if (stripos($payload['payment-link'], 'gp-rating') !== false || stripos($payload['payment-link'], 'gp_rating') !== false) {
                $course = 'GP Rating Pass';
            } elseif (stripos($payload['payment-link'], 'dns') !== false || stripos($payload['payment-link'], 'bsc') !== false) {
                $course = 'DNS & B.Sc Nautical Science Pass';
            } elseif (stripos($payload['payment-link'], 'gme') !== false || stripos($payload['payment-link'], 'btech') !== false) {
                $course = 'GME & B.Tech Pass';
            }
        }

        // Clean phone and whatsapp numbers
        $phone_clean    = preg_replace('/[^0-9+]/', '', (string) $phone_raw);
        $whatsapp_clean = preg_replace('/[^0-9+]/', '', (string) $whatsapp_raw);

        // Build structured submission notes for description
        $desc_lines = [];
        $desc_lines[] = "=== CONTACT FORM 7 SUBMISSION ===";
        $desc_lines[] = "Form: " . $form_title;
        $desc_lines[] = "Source Label: " . $source_label;
        $desc_lines[] = "Submission ID: " . $idempotency_key;
        $desc_lines[] = "Timestamp: " . current_time('mysql');
        $desc_lines[] = "";
        $desc_lines[] = "=== DEDICATED FIELDS ===";
        $desc_lines[] = "Full Name: " . $contact_name;
        $desc_lines[] = "City: " . ($city ?: '—');
        $desc_lines[] = "Eligibility: " . ($eligibility ?: '—');
        $desc_lines[] = "Lead Source: " . $source;
        $desc_lines[] = "Whatsapp no: " . ($whatsapp_clean ?: '—');
        $desc_lines[] = "Phone No.: " . ($phone_clean ?: '—');
        $desc_lines[] = "Pcm%: " . ($pcm ?: '—');
        $desc_lines[] = "Course: " . ($course ?: '—');
        $desc_lines[] = "";
        $desc_lines[] = "=== ALL FORM FIELD VALUES ===";
        foreach ($payload as $k => $v) {
            if ($k === '_model' || $k === '_id') continue;
            if (is_array($v)) $v = implode(', ', $v);
            $desc_lines[] = ucfirst(str_replace(['-', '_'], ' ', $k)) . ': ' . $v;
        }
        $description = implode("\n", $desc_lines);

        $lead_values = [
            'name'             => $raw_name . ' (' . $source_label . ')',
            'contact_name'     => $contact_name,
            'email_from'       => $email,
            'phone'            => $phone_clean,
            'type'             => 'opportunity', // 'opportunity' ensures it appears immediately on the main Odoo CRM Pipeline kanban board
            'stage_id'         => 1,             // Places into the initial 'New' pipeline stage
            'user_id'          => (int) $uid,    // Assigns to the authenticated user so it shows in 'My Pipeline' filter
            'city'             => $city,
            'x_WhatsApp'       => $whatsapp_clean,
            'x_form_source'    => $source,
            'x_eligibility'    => $eligibility,
            'x_Lead_Qualified' => $eligibility,
            'x_pcm_percentage' => $pcm,
            'x_course'         => $course,
            'description'      => $description,
        ];
        if (! empty($payload['created-date'])) {
            $lead_values['x_creation_date'] = (string) $payload['created-date'];
        }

        // 4. Create Lead in crm.lead via object.execute_kw
        $create_body = wp_json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => [
                'service' => 'object',
                'method'  => 'execute_kw',
                'args'    => [
                    $database,
                    (int) $uid,
                    $api_key,
                    'crm.lead',
                    'create',
                    [$lead_values],
                ],
            ],
            'id'      => 2,
        ]);

        $create_res = wp_remote_post($endpoint, [
            'timeout'     => $timeout,
            'sslverify'   => true,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'        => $create_body,
        ]);

        $latency = (int) round((microtime(true) - $start_time) * 1000);

        if (is_wp_error($create_res)) {
            $err_msg = 'Odoo API Network Error: ' . $create_res->get_error_message();
            self::log_odoo_result($idempotency_key, $mapping, $meta, $connection, $endpoint, 0, 'failed', $latency, $payload, '', $err_msg, $retry_count);
            return ['success' => false, 'http_code' => 0, 'body' => '', 'error' => $err_msg, 'latency_ms' => $latency];
        }

        $create_json = json_decode(wp_remote_retrieve_body($create_res), true);
        if (! empty($create_json['result'])) {
            $lead_id = (int) $create_json['result'];
            $odoo_url = preg_replace('#/jsonrpc/?$#', '', $endpoint) . '/web#id=' . $lead_id . '&model=crm.lead&view_type=form';
            $success_payload = [
                'success'      => true,
                'status'       => 'success',
                'lead_id'      => $lead_id,
                'contact_name' => $contact_name,
                'email'        => $email,
                'phone'        => $phone_clean,
                'source'       => $source_label,
                'message'      => "Lead #{$lead_id} successfully created in Odoo CRM Pipeline!",
                'odoo_url'     => $odoo_url,
            ];
            $body_str = wp_json_encode($success_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            self::log_odoo_result($idempotency_key, $mapping, $meta, $connection, $endpoint, 200, 'success', $latency, $payload, $body_str, '', $retry_count);
            return ['success' => true, 'http_code' => 200, 'body' => $body_str, 'error' => '', 'latency_ms' => $latency];
        }

        // Odoo server error
        $err_msg = 'Odoo CRM Error: ';
        if (! empty($create_json['error']['data']['message'])) {
            $err_msg .= $create_json['error']['data']['message'];
        } elseif (! empty($create_json['error']['message'])) {
            $err_msg .= $create_json['error']['message'];
        } else {
            $err_msg .= wp_remote_retrieve_body($create_res);
        }

        self::log_odoo_result($idempotency_key, $mapping, $meta, $connection, $endpoint, 500, 'failed', $latency, $payload, wp_remote_retrieve_body($create_res), $err_msg, $retry_count);
        return ['success' => false, 'http_code' => 500, 'body' => wp_remote_retrieve_body($create_res), 'error' => $err_msg, 'latency_ms' => $latency];
    }

    /**
     * Helper to log Odoo dispatch results.
     */
    private static function log_odoo_result($idempotency_key, $mapping, $meta, $connection, $endpoint, $http_code, $status, $latency, $payload, $response_body, $err_msg, $retry_count)
    {
        UCC_Logger::log_delivery([
            'submission_id'     => $idempotency_key,
            'cf7_form_id'       => $mapping->cf7_form_id ?? 0,
            'cf7_form_title'    => $meta['cf7_form_title'] ?? 'Direct Odoo Dispatch',
            'connection_name'   => $connection->name,
            'source_label'      => $mapping->source_label ?? '',
            'endpoint_url'      => self::redact_url_credentials($endpoint),
            'http_method'       => 'POST (JSON-RPC)',
            'http_code'         => $http_code,
            'status'            => $status,
            'execution_time_ms' => $latency,
            'request_payload'   => self::sanitize_payload_for_logging($payload),
            'response_body'     => self::sanitize_response_for_logging($response_body),
            'error_message'     => $err_msg,
            'retry_count'       => $retry_count,
        ]);
    }
}

