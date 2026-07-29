<?php

namespace App\Http\Requests;

use App\Models\Court;
use App\Models\CourtVerificationClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourtVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $court = $this->route('court');

        return $court instanceof Court && $court->isManagedBy($this->user());
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:official_page,court_owner,google_maps,field_verification'],
            'source_url' => ['nullable', 'url', 'max:2048', 'required_without:evidence'],
            'notes' => ['required', 'string', 'max:3000'],
            'facts' => ['required', 'array', 'min:1'],
            'facts.*' => ['string', Rule::in(array_keys(CourtVerificationClaim::REQUIRED_FIELDS))],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096', 'required_without:source_url'],
        ];
    }
}
