<?php

namespace App\Policies;

use App\Models\Manifest;
use App\Models\User;

class ManifestPolicy
{
    public function load(User $user, Manifest $manifest): bool
    {
        return $user->exists;
    }
}
