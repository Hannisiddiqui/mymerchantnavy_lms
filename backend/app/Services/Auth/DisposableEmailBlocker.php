<?php

namespace App\Services\Auth;

class DisposableEmailBlocker
{
    /** Known anonymous and privacy-focused email providers explicitly requested for blocking. */
    private const ANONYMOUS_DOMAINS = [
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

    /** Comprehensive curated list of disposable and temporary email domains. */
    private const DISPOSABLE_DOMAINS = [
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
        'guerrillamail.de',
        'pokemail.net',
        'zippymail.info',
        'trashymail.com',
        'incognitomail.org',
        'temporary-mail.net',
        'tempinbox.com',
        'disposablemail.com',
        'crazymail.com',
        'boximail.com',
        'mytempmail.com',
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

    /** Substring patterns that flag a domain as disposable. */
    private const DISPOSABLE_PATTERNS = [
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
     * Checks if an email address is from a temporary, disposable, or anonymous service.
     *
     * @return array{blocked: bool, reason: ?string}
     */
    public static function check(string $email): array
    {
        $email = mb_strtolower(trim($email));
        $parts = explode('@', $email);
        if (count($parts) !== 2 || empty($parts[1])) {
            return ['blocked' => false, 'reason' => null];
        }

        $domain = trim($parts[1]);

        // 1. Direct Anonymous / Proton check
        if (in_array($domain, self::ANONYMOUS_DOMAINS, true)) {
            return [
                'blocked' => true,
                'reason' => 'Anonymous and encrypted email services (including ProtonMail) are not allowed. Please use your personal Gmail, Yahoo, Outlook, or institutional email address.',
            ];
        }

        // 2. Direct Disposable Domain check
        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return [
                'blocked' => true,
                'reason' => 'Temporary and disposable email addresses are not allowed. Please use your genuine personal email address (e.g. Gmail, Yahoo, Outlook).',
            ];
        }

        // 3. Keyword Pattern Matching
        foreach (self::DISPOSABLE_PATTERNS as $pattern) {
            if (str_contains($domain, $pattern)) {
                return [
                    'blocked' => true,
                    'reason' => 'Temporary and disposable email services are not allowed. Please use your genuine personal email address.',
                ];
            }
        }

        // 4. MX Record inspection (if enabled and function exists)
        if (function_exists('getmxrr')) {
            $mxHosts = [];
            if (@getmxrr($domain, $mxHosts) && ! empty($mxHosts)) {
                foreach ($mxHosts as $host) {
                    $hostLower = mb_strtolower($host);
                    if (str_contains($hostLower, 'protonmail') || str_contains($hostLower, 'proton.ch')) {
                        return [
                            'blocked' => true,
                            'reason' => 'ProtonMail and anonymous email services are not allowed. Please use your personal Gmail, Yahoo, or Outlook address.',
                        ];
                    }
                    if (str_contains($hostLower, 'mailinator') || str_contains($hostLower, 'yopmail') || str_contains($hostLower, 'trashmail')) {
                        return [
                            'blocked' => true,
                            'reason' => 'Disposable email services are not allowed. Please use your genuine personal email address.',
                        ];
                    }
                }
            }
        }

        return ['blocked' => false, 'reason' => null];
    }

    public static function isBlocked(string $email): bool
    {
        return self::check($email)['blocked'];
    }
}
