<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Manifest;
use App\Models\User;

class ManifestPolicy
{
    public function load(User $user, Manifest $manifest): bool
    {
        return $user->exists;
    }

    /** Closing/dispatching is the scaler's call per docs/domain-decisions.md. */
    public function close(User $user, Manifest $manifest): bool
    {
        return in_array($user->role, UserRole::trailerAlertRoles(), true);
    }
}
