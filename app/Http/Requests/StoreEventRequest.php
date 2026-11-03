<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Authorization happens in the controller via policies; scope checks need the persisted user assignments. */
class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue' => ['required', 'string', 'max:200'],
            'component' => ['required', 'in:CWTS,LTS,ROTC'],
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
