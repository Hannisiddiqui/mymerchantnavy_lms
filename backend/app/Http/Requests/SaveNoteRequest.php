<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            // The page of a PDF the note belongs to. Left out (or null) for a note on the whole item.
            'page' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    public function messages(): array
    {
        return ['body.required' => 'Write something in the note first.', 'body.max' => 'A note can be at most 5000 characters.'];
    }
}
