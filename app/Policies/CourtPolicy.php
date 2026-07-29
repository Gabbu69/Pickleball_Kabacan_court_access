<?php

namespace App\Policies;

use App\Models\Court;
use App\Models\User;

class CourtPolicy
{
    public function view(?User $user, Court $court): bool
    {
        return $court->isPubliclyDiscoverable() || ($user && $court->isManagedBy($user));
    }

    public function manage(User $user, Court $court): bool
    {
        return $court->isManagedBy($user);
    }

    public function publish(User $user): bool
    {
        return $user->isAdmin();
    }
}
