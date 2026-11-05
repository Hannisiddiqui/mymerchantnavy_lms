/**
 * Filters out temporary, disposable, and anonymous email services.
 */

const ANONYMOUS_DOMAINS = new Set([
  "protonmail.com",
  "proton.me",
  "pm.me",
  "protonmail.ch",
  "proton.com",
  "tutanota.com",
  "tuta.com",
  "tuta.io",
  "tutanota.de",
  "tutamail.com",
  "keemail.me",
  "skiff.com",
  "mailfence.com",
]);

const DISPOSABLE_DOMAINS = new Set([
  "bankwen.com",
  "mailinator.com",
  "temp-mail.org",
  "tempmail.com",
  "tempmailo.com",
  "10minutemail.com",
  "10minutemail.net",
  "10minutemail.org",
  "guerrillamail.com",
  "guerrillamail.net",
  "guerrillamail.org",
  "guerrillamail.biz",
  "guerrillamailblock.com",
  "grr.la",
  "sharklasers.com",
  "spam4.me",
  "yopmail.com",
  "yopmail.fr",
  "yopmail.net",
  "trashmail.com",
  "trashmail.net",
  "trashmail.me",
  "dispostable.com",
  "throwawaymail.com",
  "crazymailing.com",
  "mohmal.com",
  "fakemailgenerator.com",
  "burnermail.io",
  "mytemp.email",
  "inboxkitten.com",
  "generator.email",
  "emailondeck.com",
  "tempail.com",
  "tmail.com",
  "internxt.com",
  "dropmail.me",
  "1secmail.com",
  "1secmail.org",
  "1secmail.net",
  "wwjmp.com",
  "esiix.com",
  "xmailer.org",
  "brefmail.com",
  "chacuo.net",
  "0-mail.com",
  "nada.ltd",
  "nada.email",
  "getnada.com",
  "inboxbear.com",
  "getairmail.com",
  "mintemail.com",
  "meltmail.com",
  "maildrop.cc",
  "fakeinbox.com",
  "mytempemail.com",
  "spambog.com",
  "guerrillamail.de",
  "pokemail.net",
  "zippymail.info",
  "trashymail.com",
  "incognitomail.org",
  "temporary-mail.net",
  "tempinbox.com",
  "disposablemail.com",
  "crazymail.com",
  "boximail.com",
  "mytempmail.com",
  "temp-mail.io",
  "harakirimail.com",
  "mailcatch.com",
  "mailnesia.com",
  "discard.email",
  "discardmail.com",
  "spamevader.com",
]);

const DISPOSABLE_KEYWORDS = [
  "tempmail",
  "temp-mail",
  "disposable",
  "throwaway",
  "10minute",
  "fakeinbox",
  "burnermail",
  "mailinator",
  "guerrillamail",
  "yopmail",
  "dropmail",
  "1secmail",
  "trashmail",
];

export function checkDisposableEmail(email: string): { blocked: boolean; reason?: string } {
  if (!email || !email.includes("@")) {
    return { blocked: false };
  }

  const parts = email.trim().toLowerCase().split("@");
  if (parts.length !== 2) return { blocked: false };
  const domain = parts[1];

  if (ANONYMOUS_DOMAINS.has(domain)) {
    return {
      blocked: true,
      reason: "Anonymous or encrypted email services (including ProtonMail) are not allowed. Please use your personal Gmail, Yahoo, Outlook, or institutional email.",
    };
  }

  if (DISPOSABLE_DOMAINS.has(domain)) {
    return {
      blocked: true,
      reason: "Temporary or disposable email addresses are not allowed. Please use your personal Gmail, Yahoo, or Outlook address.",
    };
  }

  for (const kw of DISPOSABLE_KEYWORDS) {
    if (domain.includes(kw)) {
      return {
        blocked: true,
        reason: "Temporary and disposable email services are not permitted. Please use your personal email address.",
      };
    }
  }

  return { blocked: false };
}
