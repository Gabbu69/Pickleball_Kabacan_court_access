<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking && $booking->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'court_payment_method_id' => ['nullable', 'integer', 'exists:court_payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reference' => ['nullable', 'string', 'max:120', 'required_without:proof'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096', 'required_without:reference'],
        ];
    }
}
