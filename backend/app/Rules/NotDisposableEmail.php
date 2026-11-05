<?php

namespace App\Rules;

use App\Services\Auth\DisposableEmailBlocker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotDisposableEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || blank($value)) {
            return;
        }

        $check = DisposableEmailBlocker::check($value);
        if ($check['blocked']) {
            $fail($check['reason'] ?? 'Temporary, disposable, and anonymous email addresses are not permitted.');
        }
    }
}

