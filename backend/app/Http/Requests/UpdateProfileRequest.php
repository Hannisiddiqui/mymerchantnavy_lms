<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $common = [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'nickname' => ['required', 'string', 'max:50'],
            'targetExam' => ['required', 'string', 'max:100'],
            'attemptYear' => ['required', 'integer', 'between:'.now()->year.','.(now()->year + 5)],
        ];

        // Email login: the email is the verified login and cannot be changed here (any value sent is ignored);
        // the mobile number is compulsory. Phone login: the other way round.
        return $common + (config('lms.login_method') === 'phone'
            ? ['email' => ['required', 'email', 'max:150', new \App\Rules\NotDisposableEmail()]]
            : ['phone' => ['required', 'regex:/^[6-9]\d{9}$/', Rule::unique('users', 'phone')->ignore($this->user()->id)]]);
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Enter your 10-digit mobile number.',
            'phone.regex' => 'Enter a valid 10-digit mobile number.',
            'phone.unique' => 'This mobile number is already used by another account.',
        ];
    }
}
