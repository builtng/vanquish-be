<?php

namespace App\Policies;

use App\Models\User;

class ServicePolicy
{
    /**
     * Determine if the user can update service settings.
     */
    public function update(User $user): bool
    {
        return $user->role === 'admin';
    }
}

