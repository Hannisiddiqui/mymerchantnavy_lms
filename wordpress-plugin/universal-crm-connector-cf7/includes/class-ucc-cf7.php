<?php
/**
 * Contact Form 7 Form Detection and Submission Interceptor.
 *
 * @package UniversalCRMConnector
 */

if (! defined('ABSPATH')) {
    exit;
}

class UCC_CF7
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
        // Hook into CF7 when mail is sent successfully.
        // We use priority 20 so CF7's own mail and Google Sheets connector finish first.
        add_action('wpcf7_mail_sent', [$this, 'handle_cf7_submission'], 20, 1);
    }

    /**
     * Check whether Contact Form 7 is active.
     */
    public static function is_cf7_active()
    {
        return class_exists('WPCF7') || defined('WPCF7_VERSION');
    }

    /**
     * Get all Contact Form 7 forms on the site.
     *
     * @return array Array of objects with id, title, and tags
     */
    public static function get_forms()
    {
        $forms = [];

        if (class_exists('WPCF7_ContactForm')) {
            $cf7_forms = WPCF7_ContactForm::find([
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ]);

            foreach ($cf7_forms as $item) {
                $form_id = $item->id();
                $forms[] = (object) [
                    'id'     => $form_id,
                    'title'  => $item->title(),
                    'tags'   => self::get_form_tags($item),
                ];
            }
        } else {
            // Fallback via post query if WPCF7 class not loaded yet in admin
            $posts = get_posts([
                'post_type'      => 'wpcf7_contact_form',
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ]);
            foreach ($posts as $p) {
                $forms[] = (object) [
                    'id'     => $p->ID,
                    'title'  => $p->post_title,
                    'tags'   => [],
                ];
            }
        }

        return $forms;
    }

    /**
     * Extract all input tag names from a CF7 form.
     */
    public static function get_form_tags($contact_form)
    {
        $tags = [];
        if (! $contact_form instanceof WPCF7_ContactForm) {
            if (is_numeric($contact_form) && class_exists('WPCF7_ContactForm')) {
                $contact_form = WPCF7_ContactForm::get_instance($contact_form);
            } else {
                return $tags;
            }
        }

        if ($contact_form) {
            $manager = $contact_form->scan_form_tags();
            foreach ($manager as $tag) {
                if (! empty($tag->name)) {
                    $tags[] = [
                        'name'     => $tag->name,
                        'type'     => $tag->type,
                        'basetype' => $tag->basetype,
                        'required' => str_ends_with($tag->type, '*'),
                    ];
                }
            }
        }

        return $tags;
    }

    /**
     * Handles CF7 submission after mail has been sent.
     * Guaranteed not to interrupt CF7 mail sending or Google Sheets.
     *
     * @param WPCF7_ContactForm $contact_form
     */
    public function handle_cf7_submission($contact_form)
    {
        try {
            $submission = WPCF7_Submission::get_instance();
            if (! $submission) {
                return;
            }

            $form_id = $contact_form->id();
            $form_title = $contact_form->title();

            // Look up mapping configuration for this form
            $mapping = UCC_Database::get_mapping_for_form($form_id);
            if (! $mapping || ! $mapping->enabled) {
                return;
            }

            $connection = UCC_Database::get_connection($mapping->connection_id);
            if (! $connection) {
                return;
            }

            // Extract posted data safely
            $posted_data = $submission->get_posted_data();

            // Build metadata
            $meta = [
                'cf7_form_id'    => $form_id,
                'cf7_form_title' => $form_title,
                'submission_id'  => wp_generate_uuid4(),
                'source_label'   => $mapping->source_label,
                'submitted_at'   => current_time('mysql'),
                'remote_ip'      => $submission->get_meta('remote_ip') ?: '',
            ];

            // Build payload using mapper
            $payload = UCC_Mapper::build_payload($posted_data, $mapping, $meta);

            // Dispatch HTTP request to CRM
            UCC_Dispatcher::dispatch($connection, $payload, $mapping, $meta);

        } catch (\Throwable $e) {
            // Never crash the submission flow
            error_log('[Universal CRM Connector] Error handling submission: ' . $e->getMessage());
        }
    }
}

