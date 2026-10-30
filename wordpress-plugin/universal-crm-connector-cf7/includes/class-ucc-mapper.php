<?php
/**
 * Field mapping, transformation engine and payload constructor.
 *
 * @package UniversalCRMConnector
 */

if (! defined('ABSPATH')) {
    exit;
}

class UCC_Mapper
{
    /**
     * Build the outgoing CRM payload from raw CF7 submission data.
     *
     * @param array  $posted_data Raw CF7 values
     * @param object $mapping     Form mapping record
     * @param array  $meta        Metadata (form_id, title, submission_id, source_label, etc.)
     * @return array
     */
    public static function build_payload(array $posted_data, $mapping, array $meta = [])
    {
        $payload = [];
        $field_mappings = is_string($mapping->field_mappings) ? json_decode($mapping->field_mappings, true) : $mapping->field_mappings;
        $static_fields  = is_string($mapping->static_fields) ? json_decode($mapping->static_fields, true) : $mapping->static_fields;

        if (! is_array($field_mappings)) {
            $field_mappings = [];
        }
        if (! is_array($static_fields)) {
            $static_fields = [];
        }

        // 1. Process dynamic CF7 field mappings
        foreach ($field_mappings as $row) {
            $cf7_key   = trim($row['cf7_field'] ?? '');
            $crm_key   = trim($row['crm_key'] ?? '');
            $transform = $row['transform'] ?? 'trim';
            $default   = $row['default_value'] ?? '';

            if (empty($crm_key)) {
                continue;
            }

            // Extract value
            $raw_val = '';
            if (isset($posted_data[$cf7_key])) {
                $raw_val = $posted_data[$cf7_key];
                if (is_array($raw_val)) {
                    $raw_val = implode(', ', array_map('trim', $raw_val));
                }
            }

            // Apply fallback if empty
            if ($raw_val === '' || $raw_val === null) {
                $raw_val = $default;
            }

            // Apply transformations
            $transformed = self::transform_value($raw_val, $transform);

            // Assign into payload (supports dot notation like "partner.name")
            self::set_nested_key($payload, $crm_key, $transformed);
        }

        // 2. Add static / constant parameters
        foreach ($static_fields as $row) {
            $key = trim($row['key'] ?? '');
            $val = $row['value'] ?? '';
            if ($key !== '') {
                self::set_nested_key($payload, $key, $val);
            }
        }

        // 3. Inject standard metadata if not already defined
        if (! isset($payload['cf7_form_id']) && ! empty($meta['cf7_form_id'])) {
            $payload['cf7_form_id'] = $meta['cf7_form_id'];
        }
        if (! isset($payload['cf7_form_title']) && ! empty($meta['cf7_form_title'])) {
            $payload['cf7_form_title'] = $meta['cf7_form_title'];
        }
        if (! isset($payload['source']) && ! empty($meta['source_label'])) {
            $payload['source'] = $meta['source_label'];
        }
        if (! isset($payload['submission_id']) && ! empty($meta['submission_id'])) {
            $payload['submission_id'] = $meta['submission_id'];
        }

        return apply_filters('ucc_outgoing_payload', $payload, $posted_data, $mapping, $meta);
    }

    /**
     * Apply specific transformation to a value.
     */
    public static function transform_value($val, $transform)
    {
        if ($val === null || $val === '') {
            return '';
        }

        switch ($transform) {
            case 'trim':
                return is_string($val) ? trim($val) : $val;

            case 'clean_phone_10':
                $digits = preg_replace('/\D/', '', (string) $val);
                if (strlen($digits) === 12 && strpos($digits, '91') === 0) {
                    $digits = substr($digits, 2);
                } elseif (strlen($digits) === 11 && strpos($digits, '0') === 0) {
                    $digits = substr($digits, 1);
                }
                return substr($digits, 0, 10);

            case 'clean_phone_e164':
                $digits = preg_replace('/\D/', '', (string) $val);
                if (strlen($digits) === 10) {
                    return '+91' . $digits;
                }
                return '+' . $digits;

            case 'uppercase':
                return is_string($val) ? strtoupper($val) : $val;

            case 'lowercase':
                return is_string($val) ? strtolower($val) : $val;

            case 'int':
                return (int) $val;

            case 'float':
                return (float) $val;

            case 'date_ymd':
                $ts = strtotime((string) $val);
                return $ts ? date('Y-m-d', $ts) : $val;

            case 'date_iso':
                $ts = strtotime((string) $val);
                return $ts ? gmdate('c', $ts) : $val;

            case 'none':
            default:
                return $val;
        }
    }

    /**
     * Set a value in an array using dot notation (e.g. "lead.contact_name").
     */
    public static function set_nested_key(&$array, $key, $value)
    {
        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return;
        }

        $parts   = explode('.', $key);
        $current = &$array;

        foreach ($parts as $p) {
            if (! isset($current[$p]) || ! is_array($current[$p])) {
                $current[$p] = [];
            }
            $current = &$current[$p];
        }

        $current = $value;
    }

    /**
     * Generate fake/sample data for testing a specific mapping.
     */
    public static function get_fake_data(array $tags = [])
    {
        $fake = [
            'full-name'        => 'Rahul Sharma',
            'email'            => 'rahul.test@example.com',
            'city'             => 'Mumbai',
            'whatsapp'         => '9876543210',
            'form-source'      => 'Website Header',
            'created-date'     => date('Y-m-d H:i:s'),
            'lead-status'      => 'New Inquiry',
            'date'             => date('Y-m-d'),
            'qualfied'         => 'Yes',
            'wp-group-created' => 'Batch 2026',
            'meeting-status'   => 'Scheduled',
            'payment-link'     => 'https://mymerchantnavy.com/pass/?ref=sample',
        ];

        // Fill any other detected tags with reasonable sample values
        foreach ($tags as $t) {
            $name = $t['name'] ?? '';
            if ($name && ! isset($fake[$name])) {
                $type = $t['basetype'] ?? 'text';
                if ($type === 'email') {
                    $fake[$name] = 'test@example.com';
                } elseif ($type === 'tel') {
                    $fake[$name] = '9876543210';
                } elseif ($type === 'number') {
                    $fake[$name] = '100';
                } elseif ($type === 'date') {
                    $fake[$name] = date('Y-m-d');
                } else {
                    $fake[$name] = 'Sample ' . ucfirst($name);
                }
            }
        }

        return $fake;
    }
}
