<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Submitted = 'submitted';
    case PartiallyPaid = 'partially_paid';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Refunded = 'refunded';
}
