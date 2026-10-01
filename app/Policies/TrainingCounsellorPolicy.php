<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TrainingCounsellor;

class TrainingCounsellorPolicy
{
    /**
     * Determine if the user can view any training counsellors.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Determine if the user can view the training counsellor.
     */
    public function view(User $user, TrainingCounsellor $tc): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Determine if the user can create training counsellors.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Determine if the user can update the training counsellor.
     */
    public function update(User $user, TrainingCounsellor $tc): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Determine if the user can delete the training counsellor.
     */
    public function delete(User $user, TrainingCounsellor $tc): bool
    {
        return $user->role === 'admin';
    }
}

