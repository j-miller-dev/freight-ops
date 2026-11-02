<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\HandlingUnit;
use App\Models\User;

class HandlingUnitPolicy
{
    /** Releasing held freight early is the scaler's/supervisor's call, same as closing a manifest. */
    public function release(User $user, HandlingUnit $handlingUnit): bool
    {
        return in_array($user->role, UserRole::trailerAlertRoles(), true);
    }
}
