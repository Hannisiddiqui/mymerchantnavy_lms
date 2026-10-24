<?php

namespace App\Services\WordPress;

use App\Models\Course;
use App\Models\User;
use App\Services\Courses\EnrollmentService;
use Illuminate\Support\Facades\Cache;

/**
 * Everything about the link with the WordPress site: checking its signed messages, finding or creating the student,
 * giving the course that was bought, and the one-time "go to my course" sign-in token.
 */
class WordPressLink
{
    private const CLOCK_SKEW = 300;

    public function __construct(private EnrollmentService $enrollments) {}

    public function enabled(): bool
    {
        return filled(config('lms.wordpress.secret'));
    }

    // ------------------------------------------------------------ signed messages

    /** Header X-MMN-Signature = hex hmac-sha256 of "{X-MMN-Timestamp}.{raw body}". */
    public function validRequest(string $timestamp, string $signature, string $body): bool
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::CLOCK_SKEW) {
            return false;
        }

        return hash_equals($this->mac("{$timestamp}.{$body}"), strtolower($signature));
    }

    private function mac(string $data): string
    {
        return hash_hmac('sha256', $data, (string) config('lms.wordpress.secret'));
    }

    // ------------------------------------------------------------ students and courses

    /** The student with this email; created (without any course) when new. Name and phone only fill empty fields. */
    public function student(string $email, ?string $name = null, ?string $phone = null, ?int $wpUserId = null, bool $verified = false): User
    {
        $email = mb_strtolower(trim($email));
        $user = User::whereRaw('lower(email) = ?', [$email])->first() ?? new User(['email' => $email]);

        $user->name = $user->name ?: ($name ? mb_substr(trim($name), 0, 120) : null);
        $user->phone = $user->phone ?: $this->phone($phone);
        $user->wordpress_user_id = $user->wordpress_user_id ?: $wpUserId;
        // WordPress emailed a code to this address and the student typed it back: the address is proven.
        $user->email_verified_at = $user->email_verified_at ?: ($verified ? now() : null);
        $user->save();

        return $user;
    }

    /** @return array<int,Course> courses sold under this WordPress product id */
    public function coursesFor(string $productId): array
    {
        $productId = trim($productId);

        return Course::whereNotNull('wordpress_product_id')->get()
            ->filter(fn (Course $c) => in_array($productId, array_map('trim', explode(',', $c->wordpress_product_id)), true))
            ->values()->all();
    }

    /** @return array<int,string> titles of the courses newly given */
    public function enroll(User $student, string $productId): array
    {
        $given = [];
        foreach ($this->coursesFor($productId) as $course) {
            if ($this->enrollments->assign($student, $course)) {
                $given[] = $course->title;
            }
        }

        return $given;
    }

    private function phone(?string $phone): ?string
    {
        $digits = substr(preg_replace('/\D+/', '', (string) $phone), -10);

        return strlen($digits) === 10 ? $digits : null;
    }

    // ------------------------------------------------------------ one-time sign-in token

    /** Token as WordPress builds it: base64url(json{e,x,j,v}) . "." . hex hmac. v = WordPress has verified this email. */
    public function makeToken(string $email, ?int $expires = null, bool $verified = false): string
    {
        $json = json_encode(['e' => mb_strtolower($email), 'x' => $expires ?? time() + 120, 'j' => bin2hex(random_bytes(8)), 'v' => $verified]);
        $payload = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return $payload.'.'.$this->mac($payload);
    }

    /** @return array{email: string, verified: bool}|null null when the token is forged, expired or already used */
    public function claimsFromToken(string $token): ?array
    {
        [$payload, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if ($payload === '' || ! hash_equals($this->mac($payload), strtolower($sig))) {
            return null;
        }
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        $latest = time() + config('lms.wordpress.sso_minutes') * 60 + self::CLOCK_SKEW;
        if (! is_array($data) || ! isset($data['e'], $data['x'], $data['j']) || $data['x'] < time() || $data['x'] > $latest) {
            return null;
        }
        // usable once
        if (! Cache::add('wp-sso:'.$data['j'], 1, now()->addMinutes(config('lms.wordpress.sso_minutes') + 10))) {
            return null;
        }

        $email = filter_var($data['e'], FILTER_VALIDATE_EMAIL);

        return $email ? ['email' => $email, 'verified' => ($data['v'] ?? false) === true] : null;
    }
}
