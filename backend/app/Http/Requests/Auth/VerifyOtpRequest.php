<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $identifier = config('lms.login_method') === 'phone'
            ? ['phone' => ['required', 'regex:/^[6-9]\d{9}$/']]
            : ['email' => ['required', 'email:rfc', 'max:190']];

        return $identifier + [
            'code' => ['required', 'digits:'.config('otp.length')],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
