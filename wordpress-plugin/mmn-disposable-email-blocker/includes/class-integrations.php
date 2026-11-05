<?php

if (! defined('ABSPATH')) {
    exit;
}

class MMN_Email_Integrations
{
    public static function init()
    {
        // 1. WordPress Core User Registration
        add_filter('registration_errors', [__CLASS__, 'filter_wp_registration'], 10, 3);

        // 2. Profile Email Updates in WP Admin / Frontend
        add_action('user_profile_update_errors', [__CLASS__, 'filter_profile_update'], 10, 3);

        // 3. WooCommerce Registration & Checkout
        add_filter('woocommerce_registration_errors', [__CLASS__, 'filter_woo_registration'], 10, 3);
        add_action('woocommerce_after_checkout_validation', [__CLASS__, 'filter_woo_checkout'], 10, 2);

        // 4. Contact Form 7 Validation
        add_filter('wpcf7_validate_email', [__CLASS__, 'filter_cf7_email'], 20, 2);
        add_filter('wpcf7_validate_email*', [__CLASS__, 'filter_cf7_email'], 20, 2);

        // 5. WPForms Validation
        add_action('wpforms_process_validate_email', [__CLASS__, 'filter_wpforms_email'], 10, 4);

        // 6. Elementor Forms Validation
        add_action('elementor_pro/forms/validation', [__CLASS__, 'filter_elementor_forms'], 10, 2);
    }

    /** 1. WordPress standard registration. */
    public static function filter_wp_registration(WP_Error $errors, $sanitized_user_login, $user_email)
    {
        if (get_option('mmn_block_temp_mail_enabled', 'yes') !== 'yes') {
            return $errors;
        }

        $check = MMN_Email_Detector::inspect($user_email);
        if ($check['blocked']) {
            MMN_Email_Detector::log_attempt($user_email, $check['reason'], 'WordPress Registration');
            $errors->add('disposable_email_blocked', '<strong>Registration Blocked:</strong> ' . esc_html($check['reason']));
        }

        return $errors;
    }

    /** 2. User profile email update. */
    public static function filter_profile_update(WP_Error $errors, $update, $user)
    {
        if (! $update || get_option('mmn_block_temp_mail_enabled', 'yes') !== 'yes') {
            return;
        }

        if (isset($_POST['email'])) {
            $email = sanitize_email(wp_unslash($_POST['email']));
            $check = MMN_Email_Detector::inspect($email);
            if ($check['blocked']) {
                MMN_Email_Detector::log_attempt($email, $check['reason'], 'Profile Update');
                $errors->add('disposable_email_blocked', '<strong>Update Blocked:</strong> ' . esc_html($check['reason']));
            }
        }
    }

    /** 3. WooCommerce registration. */
    public static function filter_woo_registration(WP_Error $errors, $username, $email)
    {
        if (get_option('mmn_block_temp_mail_enabled', 'yes') !== 'yes') {
            return $errors;
        }

        $check = MMN_Email_Detector::inspect($email);
        if ($check['blocked']) {
            MMN_Email_Detector::log_attempt($email, $check['reason'], 'WooCommerce Register');
            $errors->add('disposable_email_blocked', esc_html($check['reason']));
        }

        return $errors;
    }

    /** 3b. WooCommerce Checkout. */
    public static function filter_woo_checkout($data, $errors)
    {
        if (get_option('mmn_block_temp_mail_enabled', 'yes') !== 'yes') {
            return;
        }

        $email = isset($data['billing_email']) ? sanitize_email($data['billing_email']) : '';
        if ($email !== '') {
            $check = MMN_Email_Detector::inspect($email);
            if ($check['blocked']) {
                MMN_Email_Detector::log_attempt($email, $check['reason'], 'WooCommerce Checkout');
                $errors->add('validation', esc_html($check['reason']));
            }
        }
    }

    /** 4. Contact Form 7. */
    public static function filter_cf7_email($result, $tag)
    {
        if (get_option('mmn_block_temp_mail_enabled', 'yes') !== 'yes') {
            return $result;
        }

        $tag_name = is_array($tag) ? $tag['name'] : (is_object($tag) ? $tag->name : '');
        $value    = isset($_POST[$tag_name]) ? trim(wp_unslash($_POST[$tag_name])) : '';

        if ($value !== '' && is_email($value)) {
            $check = MMN_Email_Detector::inspect($value);
            if ($check['blocked']) {
                MMN_Email_Detector::log_attempt($value, $check['reason'], 'Contact Form 7');
                $result->invalidate($tag, $check['reason']);
            }
        }

        return $result;
    }

    /** 5. WPForms. */
    public static function filter_wpforms_email($field_id, $field_submit, $form_data, $fields)
    {
        if (get_option('mmn_block_temp_mail_enabled', 'yes') !== 'yes') {
            return;
        }

        if (is_string($field_submit) && is_email($field_submit)) {
            $check = MMN_Email_Detector::inspect($field_submit);
            if ($check['blocked']) {
                MMN_Email_Detector::log_attempt($field_submit, $check['reason'], 'WPForms');
                wpforms()->process->errors[$form_data['id']][$field_id] = $check['reason'];
            }
        }
    }

    /** 6. Elementor Pro Forms. */
    public static function filter_elementor_forms($record, $ajax_handler)
    {
        if (get_option('mmn_block_temp_mail_enabled', 'yes') !== 'yes') {
            return;
        }

        $fields = $record->get('fields');
        if (is_array($fields)) {
            foreach ($fields as $id => $field) {
                if (isset($field['type']) && $field['type'] === 'email' && ! empty($field['value'])) {
                    $check = MMN_Email_Detector::inspect($field['value']);
                    if ($check['blocked']) {
                        MMN_Email_Detector::log_attempt($field['value'], $check['reason'], 'Elementor Form');
                        $ajax_handler->add_error($id, $check['reason']);
                    }
                }
            }
        }
    }
}
