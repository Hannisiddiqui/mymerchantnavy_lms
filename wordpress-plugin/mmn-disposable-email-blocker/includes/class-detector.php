<?php

if (! defined('ABSPATH')) {
    exit;
}

class MMN_Email_Detector
{
    /** Anonymous, privacy-shielded services (specifically including ProtonMail as requested). */
    public const ANONYMOUS_DOMAINS = [
        'protonmail.com',
        'proton.me',
        'pm.me',
        'protonmail.ch',
        'proton.com',
        'tutanota.com',
        'tuta.com',
        'tuta.io',
        'tutanota.de',
        'tutamail.com',
        'keemail.me',
        'skiff.com',
        'mailfence.com',
    ];

    /** Curated list of known temporary and disposable domains. */
    public const DISPOSABLE_DOMAINS = [
        'bankwen.com',
        'mailinator.com',
        'temp-mail.org',
        'tempmail.com',
        'tempmailo.com',
        '10minutemail.com',
        '10minutemail.net',
        '10minutemail.org',
        'guerrillamail.com',
        'guerrillamail.net',
        'guerrillamail.org',
        'guerrillamail.biz',
        'guerrillamailblock.com',
        'grr.la',
        'sharklasers.com',
        'spam4.me',
        'yopmail.com',
        'yopmail.fr',
        'yopmail.net',
        'trashmail.com',
        'trashmail.net',
        'trashmail.me',
        'dispostable.com',
        'throwawaymail.com',
        'crazymailing.com',
        'mohmal.com',
        'fakemailgenerator.com',
        'burnermail.io',
        'mytemp.email',
        'inboxkitten.com',
        'generator.email',
        'emailondeck.com',
        'tempail.com',
        'tmail.com',
        'internxt.com',
        'dropmail.me',
        '1secmail.com',
        '1secmail.org',
        '1secmail.net',
        'wwjmp.com',
        'esiix.com',
        'xmailer.org',
        'brefmail.com',
        'chacuo.net',
        '0-mail.com',
        'nada.ltd',
        'nada.email',
        'getnada.com',
        'inboxbear.com',
        'getairmail.com',
        'mintemail.com',
        'meltmail.com',
        'maildrop.cc',
        'fakeinbox.com',
        'mytempemail.com',
        'spambog.com',
        'pokemail.net',
        'trashymail.com',
        'temporary-mail.net',
        'tempinbox.com',
        'disposablemail.com',
        'boximail.com',
        'temp-mail.io',
        'harakirimail.com',
        'mailcatch.com',
        'mailnesia.com',
        'discard.email',
        'discardmail.com',
        'spamevader.com',
        'armyspy.com',
        'cuvox.de',
        'dayrep.com',
        'fleckens.hu',
        'gustr.com',
        'jourrapide.com',
        'rhyta.com',
        'superrito.com',
        'teleworm.us',
        'einrot.com',
        'zoemail.org',
        'crazymailing.net',
        'mohmal.in',
        'mohmal.im',
    ];

    /** Substrings that indicate throwaway services. */
    public const DISPOSABLE_PATTERNS = [
        'tempmail',
        'temp-mail',
        'disposable',
        'throwaway',
        '10minute',
        'fakeinbox',
        'burnermail',
        'mailinator',
        'guerrillamail',
        'yopmail',
        'dropmail',
        '1secmail',
        'trashmail',
    ];

    /**
     * Inspects an email and determines if it is disposable or blocked.
     *
     * @param string $email
     * @return array{blocked: bool, reason: string, domain: string}
     */
    public static function inspect($email)
    {
        $email = strtolower(trim((string) $email));
        $parts = explode('@', $email);

        if (count($parts) !== 2 || empty($parts[1])) {
            return [
                'blocked' => false,
                'reason'  => 'Invalid email format.',
                'domain'  => '',
            ];
        }

        $domain = trim($parts[1]);

        // Check Whitelist first
        $whitelist = self::get_whitelist();
        if (in_array($domain, $whitelist, true)) {
            return [
                'blocked' => false,
                'reason'  => 'Domain is explicitly whitelisted.',
                'domain'  => $domain,
            ];
        }

        // Check Custom Blacklist
        $blacklist = self::get_blacklist();
        if (in_array($domain, $blacklist, true)) {
            return [
                'blocked' => true,
                'reason'  => 'Domain is in your custom blacklist.',
                'domain'  => $domain,
            ];
        }

        // Check ProtonMail / Anonymous Services (if setting enabled)
        $block_anonymous = get_option('mmn_block_protonmail', 'yes') === 'yes';
        if ($block_anonymous && in_array($domain, self::ANONYMOUS_DOMAINS, true)) {
            return [
                'blocked' => true,
                'reason'  => 'Anonymous and encrypted email services (including ProtonMail) are not allowed. Please use your personal Gmail, Yahoo, Outlook, or institutional email address.',
                'domain'  => $domain,
            ];
        }

        // Check Built-in Disposable Domains
        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return [
                'blocked' => true,
                'reason'  => 'Temporary and disposable email addresses are not allowed. Please use your genuine personal email address.',
                'domain'  => $domain,
            ];
        }

        // Check Patterns
        foreach (self::DISPOSABLE_PATTERNS as $pattern) {
            if (strpos($domain, $pattern) !== false) {
                return [
                    'blocked' => true,
                    'reason'  => 'Disposable and temporary email services are not permitted. Please use your genuine personal email.',
                    'domain'  => $domain,
                ];
            }
        }

        // MX Record DNS Check (catches unlisted subdomains / white-label temp mail)
        if (get_option('mmn_check_mx_records', 'yes') === 'yes' && function_exists('getmxrr')) {
            $mx_hosts = [];
            if (@getmxrr($domain, $mx_hosts) && ! empty($mx_hosts)) {
                foreach ($mx_hosts as $host) {
                    $host = strtolower($host);
                    if ($block_anonymous && (strpos($host, 'protonmail') !== false || strpos($host, 'proton.ch') !== false)) {
                        return [
                            'blocked' => true,
                            'reason'  => 'ProtonMail and anonymous mail services are not permitted. Please use your personal Gmail, Yahoo, or Outlook address.',
                            'domain'  => $domain,
                        ];
                    }
                    if (strpos($host, 'mailinator') !== false || strpos($host, 'yopmail') !== false || strpos($host, 'trashmail') !== false) {
                        return [
                            'blocked' => true,
                            'reason'  => 'Temporary and disposable email services are not permitted.',
                            'domain'  => $domain,
                        ];
                    }
                }
            }
        }

        return [
            'blocked' => false,
            'reason'  => 'Valid personal or organization email.',
            'domain'  => $domain,
        ];
    }

    /** Helper checking boolean status. */
    public static function is_blocked($email)
    {
        return self::inspect($email)['blocked'];
    }

    public static function get_blacklist()
    {
        $raw = (string) get_option('mmn_custom_blocked_domains', '');
        return self::parse_domains($raw);
    }

    public static function get_whitelist()
    {
        $raw = (string) get_option('mmn_whitelisted_domains', '');
        return self::parse_domains($raw);
    }

    public static function parse_domains($text)
    {
        $lines = preg_split('/[\r\n,]+/', $text);
        $clean = [];
        foreach ($lines as $line) {
            $d = strtolower(trim($line));
            if ($d !== '' && strpos($d, '.') !== false) {
                $clean[] = $d;
            }
        }
        return array_unique($clean);
    }

    /** Log blocked attempts for audit in WP Admin. */
    public static function log_attempt($email, $reason, $source = 'Registration')
    {
        $logs = get_option('mmn_blocked_email_logs', []);
        if (! is_array($logs)) {
            $logs = [];
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        array_unshift($logs, [
            'time'   => current_time('mysql'),
            'email'  => sanitize_email($email),
            'ip'     => $ip,
            'source' => sanitize_text_field($source),
            'reason' => sanitize_text_field($reason),
        ]);

        // Keep last 100 entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, 0, 100);
        }

        update_option('mmn_blocked_email_logs', $logs, false);
    }
}
