<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ScanAttendanceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('token') && $this->filled('payload')) {
            $this->merge(['token' => $this->input('payload')]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:2000']];
    }

    protected function failedValidation(Validator $validator): void
    {
        if (! $this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'qr_invalid',
                'message' => $validator->errors()->first(),
                'retryable' => false,
                'request_id' => (string) Str::ulid(),
            ],
        ], 400));
    }
}
