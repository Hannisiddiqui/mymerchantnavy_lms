<?php

namespace App\Services\Auth;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Optional two-step verification for students: after the emailed code they also type the 6-digit code of their
 * authenticator app (or one of 8 one-time recovery codes). The student switches it on and off under My Profile;
 * the Super Admin can switch the whole feature off or reset one student (Settings > Security).
 */
class StudentTwoFactor
{
    private const CHALLENGE_MINUTES = 5;

    private const MAX_TRIES = 5;

    /** 'required' (default: every student must use it), 'optional' (students choose) or 'off'. Set by the Super Admin. */
    public static function mode(): string
    {
        $mode = (string) Setting::get('student_2fa_mode', 'required');

        return in_array($mode, ['required', 'optional', 'off'], true) ? $mode : 'required';
    }

    public static function featureOn(): bool
    {
        return self::mode() !== 'off';
    }

    /** Compulsory for this student? (The admins' preview students are exempt, so a course can still be checked.) */
    public static function requiredFor(User $user): bool
    {
        return self::mode() === 'required' && ! $user->is_preview;
    }

    public function enabledFor(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && filled($user->two_factor_secret);
    }

    // ----------------------------------------------------------------- the extra step at login

    /** @return string a one-time challenge id the student app sends back with the code */
    public function startChallenge(User $user, string $deviceName): string
    {
        $id = Str::random(48);
        Cache::put($this->key($id), ['uid' => $user->id, 'tries' => 0, 'device' => mb_substr($deviceName, 0, 200)], now()->addMinutes(self::CHALLENGE_MINUTES));

        return $id;
    }

    /** @return array{user: User, device: string}|null */
    public function finishChallenge(string $id, string $code): ?array
    {
        $data = Cache::get($this->key($id));
        if (! $data) {
            return null;
        }
        if ($data['tries'] >= self::MAX_TRIES) {
            Cache::forget($this->key($id));

            return null;
        }
        $data['tries']++;
        Cache::put($this->key($id), $data, now()->addMinutes(self::CHALLENGE_MINUTES));

        $user = User::find($data['uid']);
        if (! $user || ! $user->is_active || ! $this->enabledFor($user) || ! $this->checkCode($user, $code)) {
            return null;
        }
        Cache::forget($this->key($id));

        return ['user' => $user, 'device' => $data['device']];
    }

    private function key(string $id): string
    {
        return '2fa-challenge:'.hash('sha256', $id);
    }

    // ----------------------------------------------------------------- set up / turn off

    /** @return array{secret: string, uri: string} */
    public function begin(User $user): array
    {
        $secret = Totp::generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->save();

        return ['secret' => $secret, 'uri' => Totp::uri($secret, (string) ($user->email ?: $user->phone))];
    }

    /** @return list<string>|null the recovery codes (shown once), or null when the code was wrong */
    public function confirm(User $user, string $code): ?array
    {
        if (blank($user->two_factor_secret) || $user->two_factor_confirmed_at) {
            return null;
        }
        $step = Totp::verify($user->two_factor_secret, $code);
        if ($step === null) {
            return null;
        }
        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes, 'two_factor_last_step' => $step])->save();
        ActivityLog::record(event: 'student.2fa_enabled', description: "Student {$user->email} turned on two-step verification.", subject: $user, student: $user);

        return $codes;
    }

    public function disable(User $user, string $code): bool
    {
        if (self::requiredFor($user) || ! $this->enabledFor($user) || ! $this->checkCode($user, $code)) {
            return false;
        }
        $this->clear($user);
        ActivityLog::record(event: 'student.2fa_disabled', description: "Student {$user->email} turned off two-step verification.", subject: $user, student: $user);

        return true;
    }

    public function clear(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null])->save();
    }

    // ----------------------------------------------------------------- checking a code

    /** Authenticator code (each works once) or an unused recovery code (works once, then it is removed). */
    private function checkCode(User $user, string $code): bool
    {
        $code = trim($code);
        if (preg_match('/^\d{3}\s?\d{3}$/', $code)) {
            $step = Totp::verify((string) $user->two_factor_secret, $code);
            if ($step === null || ($user->two_factor_last_step !== null && $step <= $user->two_factor_last_step)) {
                return false;
            }
            $user->forceFill(['two_factor_last_step' => $step])->save();

            return true;
        }

        $codes = (array) $user->two_factor_recovery_codes;
        foreach ($codes as $i => $stored) {
            if (hash_equals((string) $stored, Str::lower($code))) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                ActivityLog::record(event: 'student.2fa_recovery_used', description: "Student {$user->email} used a two-step recovery code.", subject: $user, student: $user);

                return true;
            }
        }

        return false;
    }
}
