<?php

namespace App\Policies;

use App\Models\Consignment;
use App\Models\User;

class ConsignmentPolicy
{
    public function holdBack(User $user, Consignment $consignment): bool
    {
        return $user->exists;
    }
}
