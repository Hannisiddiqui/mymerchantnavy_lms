<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** The address the login code goes to: an email (default) or, with LMS_LOGIN_METHOD=phone, a mobile number. */
class SendOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return config('lms.login_method') === 'phone'
            ? ['phone' => ['required', 'regex:/^[6-9]\d{9}$/']]
            : ['email' => ['required', 'email:rfc', 'max:190']];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 10-digit mobile number.',
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
        ];
    }
}
