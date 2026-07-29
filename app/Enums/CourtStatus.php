<?php

namespace App\Enums;

enum CourtStatus: string
{
    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case Published = 'published';
    case Archived = 'archived';
}
