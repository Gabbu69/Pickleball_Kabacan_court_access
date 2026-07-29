<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentRefundController extends Controller
{
    public function __invoke(Request $request, Payment $payment, PaymentService $payments)
    {
        $this->authorize('refund', $payment);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'reason' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $payments->refund(
            $payment,
            $request->user(),
            (int) round(((float) $data['amount']) * 100),
            $data['reason'],
            $data['reference'] ?? null,
        );

        return back()->with('success', 'Refund recorded and booking balance recalculated.');
    }
}
