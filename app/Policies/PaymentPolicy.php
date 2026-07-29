<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $payment->booking->user_id === $user->id
            || $user->isAdmin()
            || $payment->booking->court->isManagedBy($user);
    }

    public function manage(User $user, Payment $payment): bool
    {
        return $payment->booking->court->isManagedBy($user);
    }

    public function refund(User $user): bool
    {
        return $user->isAdmin();
    }
}
