<?php

namespace App\Http\Requests;

use App\Models\Court;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $court = $this->route('court');

        return $court instanceof Court && $court->isPubliclyDiscoverable();
    }

    public function rules(): array
    {
        return [
            'court_unit_id' => ['required', 'integer', 'exists:court_units,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:+60 days'],
            'start_time' => ['required', 'date_format:H:i'],
            'player_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
