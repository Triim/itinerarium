<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItineraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'is_published' => ['boolean'],
            'place_ids' => ['required', 'array', 'min:1', 'max:50'],
            // Every stop must exist and belong to the chosen city.
            'place_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('places', 'id')->where('city_id', $this->integer('city_id')),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'city_id' => 'city',
            'place_ids' => 'stops',
            'place_ids.*' => 'stop',
        ];
    }

    public function messages(): array
    {
        return [
            'place_ids.required' => 'Add at least one stop to the route.',
            'place_ids.min' => 'Add at least one stop to the route.',
            'place_ids.*.exists' => 'This stop does not belong to the selected city.',
        ];
    }
}
