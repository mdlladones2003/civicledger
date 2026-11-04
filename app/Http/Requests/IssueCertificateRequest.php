<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
        ];
    }
}
