<?php

namespace App\Http\Requests;

use App\Models\Court;
use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $court = $this->route('court');

        return $court instanceof Court && $court->isManagedBy($this->user());
    }

    public function rules(): array
    {
        return [
            'court_unit_id' => ['required', 'integer', 'exists:court_units,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'slot_minutes' => ['required', 'integer', 'in:30,60,90,120'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}
