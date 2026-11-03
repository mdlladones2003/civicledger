<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Reason is optional on approve, mandatory on reject / request-revision. */
class DecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                Rule::requiredIf(fn () => ! $this->routeIs('hours.approve')),
                'nullable', 'string', 'min:5', 'max:500',
            ],
        ];
    }
}
