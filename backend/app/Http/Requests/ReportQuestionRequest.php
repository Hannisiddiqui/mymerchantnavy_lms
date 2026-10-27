<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportQuestionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            // "Something else" needs a few words, otherwise the admin can't tell what to look at.
            'details' => ['nullable', 'string', 'max:1000', Rule::requiredIf($this->input('reason') === ReportReason::Other->value)],
        ];
    }

    public function messages(): array
    {
        return ['details.required' => 'Please tell us what is wrong.'];
    }
}
